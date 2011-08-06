<?php
    class ViewBase extends Object
    {
        private $data;
        private $controller;

        public function __construct($controller = null)
        {
            $this->clear();
            $this->controller = $controller;
        }

        public function assign($key, $value = true)
        {
            if (is_array($key))
                foreach($key as $n=>$v)
                    $this->data->{$n} = $v;
            elseif(is_object($key))
                foreach(get_object_vars($key) as $n=>$v)
                    $this->data->{$n} = $v;
            else
                $this->data->{$key} = $value;
        }

        public function set($key, $value = true)
        {
            $this->assign($key, $value);
        }

        public function clear()
        {
            $this->data = new Object;
            $this->data->view = $this;
            $this->data->app = Application::$app;
        }

        public function render($viewName)
        {
            $compiledFile = TMP_PATH.classToFileName($viewName).'.php';
            $templateFile = APP_PATH.'views/'.classToFileName($viewName).'.html';

            if (!file_exists($templateFile))
                throw new YPFrameworkError ('Template '.$viewName.':'.$templateFile.' not found');

            if (!file_exists($compiledFile) || filemtime($compiledFile) <= filemtime($templateFile))
                $this->compile ($templateFile, $compiledFile);

            ob_start();
            include($compiledFile);
            $out = ob_get_clean();
            return $out;
        }

        private function compile($templateFile, $compiledFile)
        {
            $compiledDir = dirname($compiledFile);
            if (!file_exists(dirname($compiledFile))) mkdir(dirname($compiledFile), 0744, true);

            $source = file_get_contents($templateFile);

            $num = preg_match_all('/\{%([^}]*)\}/', $source, $matches);
            if($num > 0) {
                for($i = 0; $i < $num; $i++) {
                    $match = $matches[1][$i];
                    $new = $this->transformSyntax($matches[1][$i]);
                    $source = str_replace($matches[0][$i], $new, $source);
                }
            }

            file_put_contents($compiledFile, $source);
        }

        private function transformSyntax($input)
        {
            $parts = explode(':', $input);

            $string = '<?php ';
            switch($parts[0]) { // check for a template statement
                case 'if':
                case 'switch':
                    $string .= $parts[0] . '(' . $this->replaceSyntax($parts[1]) . ') { ' . ($parts[0] == 'switch' ? 'default: ' : '');
                    break;
                case 'elsif':
                    $string .= '} elseif (' . $this->replaceSyntax($parts[1]) . ') { ';
                    break;
                case 'ifnot':
                    $string .= 'if (!(' . $this->replaceSyntax($parts[1]) . ')) { ';
                    break;
                case 'foreach':
                    $pieces = explode(',', $parts[1]);
                    $string .= 'foreach(' . $this->replaceSyntax($pieces[0]) . ' as ';
                    $string .= $this->replaceSyntax($pieces[1]);
                    if(sizeof($pieces) == 3) // prepares the $value portion of foreach($var as $key=>$value)
                        $string .= '=>' . $this->replaceSyntax($pieces[2]);
                    $string .= ') { ';
                    break;
                case 'end':
                case 'endswitch':
                    $string .= '}';
                    break;
                case 'else':
                    $string .= '} else {';
                    break;
                case 'case':
                    $string .= 'break; case ' . $this->replaceSyntax($parts[1]) . ':';
                    break;
                case 'include':
                    if (is_object($this->controller) && (strpos($parts[1], '/') === false))
                        $parts[1] = $this->controller->controllerName.'/'.$parts[1];
                    $string .= 'echo $this->render("' . $parts[1] . '");';
                    break;
                default:
                    $string .= 'echo ' . $this->replaceSyntax($parts[0]) . ';';
                    break;
            }
            $string .= ' ?>';
            return $string;
        }

        private function replaceSyntax($syntax)
        {
            $from = array(
                '/(^|\[|,|\(|\+| )([a-zA-Z_][a-zA-Z0-9_]*)($|\.|\)|,|\[|\]|\+)/',
                '/(^|\[|,|\(|\+| )([a-zA-Z_][a-zA-Z0-9_]*)($|\.|\)|,|\[|\]|\+)/' // again to catch those bypassed by overlapping start/end characters
            );
            $to = array(
                '$1$this->data->$2$3',
                '$1$this->data->$2$3'
            );

            $syntax = preg_replace($from, $to, $syntax);
            $start = 0;

            while (preg_match('/\./', $syntax, $matches, PREG_OFFSET_CAPTURE, $start))
            {
                $pos = $matches[0][1];

                $str_start = 0;
                $in_str = false;

                while (preg_match("/'(\\'|[^'])*'/", $syntax, $matches, PREG_OFFSET_CAPTURE, $str_start))
                {
                    if ($matches[0][1] < $pos && $pos <= ($matches[0][1]+strlen($matches[0][0])))
                    {
                        $in_str = true;
                        break;
                    }
                    $str_start = ($matches[0][1]+strlen($matches[0][0]));
                }

                if (!$in_str)
                    $syntax = substr ($syntax, 0, $pos).'->'.substr ($syntax, $pos+1);

                $start = $pos + 1;
            }

            $syntax = str_replace('$this->data->null', 'null', $syntax);
            $syntax = str_replace('$this->data->true', 'true', $syntax);
            $syntax = str_replace('$this->data->false', 'false', $syntax);

            return  $syntax;
        }
    }
?>
