<?php
    class ViewBase extends Base
    {
        protected $templateFileName;
        protected $compiledFileName;

        protected $data;
        protected $output;
        protected $prefix;
        protected $outputType;
        protected $profile;

        protected static $_beforeFilter = array();
        protected static $_afterFilter = array();

        public function __construct($viewData = null, $viewProfile = null)
        {
            parent::__construct();

            $this->clear($viewData);
            $this->profile = $viewProfile;

            if ($viewProfile !== null)
                $this->prefix = build_file_path('_profiles', $viewProfile);

            if (self::$config->application('minify_output'))
                self::$_afterFilter[] = 'minify';
        }

        public function set($key, $value = true)
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

        public function clear($viewData = null)
        {
            if ($viewData === null)
                $this->data = new Object;
            else
                $this->data = $viewData;

            $this->data->view = $this;
            $this->data->app = self::$app;
            $this->data->config = self::$config;
            $this->data->routes = self::$routes;
            $this->data->paths = self::$paths;
            $this->data->mode = self::$mode;
            $this->data->production = self::$production;
            $this->data->development = self::$development;
        }

        public function render($viewName)
        {
            $time_start = microtime(true);

            $viewName = build_file_path($this->prefix, class_to_file_name($viewName));
            $basePath = build_file_path(self::$paths->application, 'views');

            $templateFileBase = build_file_path($basePath, $viewName);
            $templateFiles = glob("$templateFileBase.*");

            if (count($templateFiles) == 0)
                throw new ErrorComponentNotFound ('TEMPLATE', $viewName);
            elseif (count($templateFiles) > 1)
                throw new ErrorMultipleViewsFound ($viewName);

            $this->templateFileName = $templateFiles[0];
            $templatePath = substr($this->templateFileName, strlen($basePath));

            //PHP Template?
            if (substr($this->templateFileName, -4) == '.php')
            {
                $this->compiledFileName = substr(self::$paths->temp.$templatePath, 0, -4);

                if (!file_exists($this->compiledFileName) || filemtime($this->compiledFileName) <= filemtime($this->templateFileName))
                    copy($this->templateFileName, $this->compiledFileName);
            } else {
                $this->compiledFileName = self::$paths->temp.$templatePath;

                if (!file_exists($this->compiledFileName) || filemtime($this->compiledFileName) <= filemtime($this->templateFileName))
                    $this->compile($this->templateFileName, $this->compiledFileName);
            }

            if (function_exists('finfo_open'))
            {
                $fid = finfo_open(FILEINFO_MIME_TYPE);
                $this->outputType = finfo_file($fid, $this->compiledFileName);
            } else {
                $this->outputType = mime_content_type($this->compiledFileName);
            }

            $className = get_class($this);
            foreach($className::$_beforeFilter as $proc)
                if (is_callable (array($this, $proc)))
                    call_user_func(array($this, $proc));
                else
                    throw new ErrorNoCallback(get_class($this), $proc);

            ob_start();
            include($this->compiledFileName);
            $this->output = ob_get_clean();

            foreach($className::$_afterFilter as $proc)
                if (is_callable (array($this, $proc)))
                    call_user_func(array($this, $proc));
                else
                    throw new ErrorNoCallback(get_class($this), $proc);

            $time_end = microtime(true);
            Logger::framework('DEBUG:VIEW_RENDER', sprintf('%s rendered (%.2F secs)',
                $templatePath, ($time_end-$time_start)));

            return $this->output;
        }

        public function getOutputType() {
            return $this->outputType;
        }

        protected function minify()
        {
            $time_start = microtime(true);
            $length_start = strlen($this->output);

            switch ($this->outputType)
            {
                case 'text/css':
                    require_once self::$paths->library.'/min/lib/Minify/CSS.php';
                    $this->output = Minify_CSS::minify($this->output);
                    break;

                case 'text/javascript':
                case 'application/x-javascript':
                    require_once self::$paths->library.'/min/lib/JSMin.php';
                    $this->output = JSMin::minify($this->output);
                    break;

                case 'text/html':
                case 'text/xhtml':
                    require_once self::$paths->library.'/min/lib/Minify/HTML.php';
                    require_once self::$paths->library.'/min/lib/Minify/CSS.php';
                    require_once self::$paths->library.'/min/lib/JSMin.php';
                    $this->output = Minify_HTML::minify($this->output, array(
                        'cssMinifier' => array('Minify_CSS', 'minify'),
                        'jsMinifier' => array('JSMin', 'minify')
                    ));
                    break;
            }

            $time_end = microtime(true);
            $length_end = strlen($this->output);
            Logger::framework('DEBUG:MINIFY', sprintf('%s content minified %.2F%%. (%.2F secs)',
                $this->outputType, (100-($length_end/$length_start)), ($time_end-$time_start)));
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