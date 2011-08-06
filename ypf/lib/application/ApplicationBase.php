<?php
    class ApplicationBase extends Object
    {
        public static $app = null;

        public $title;
        public $database = null;
        public $notice = null;
        public $error = null;
        public $lastAction = null;
        public $urlBasePath = null;

        private $params = null;
        private $viewName = null;
        private $data = null;
        private $layout = 'main';

        private $actions = array();
        private $output = null;
        private $format = '.html';

        public static function run($db = null)
        {
            if ($db === NULL)
                $db = new MySQLDataBase(DB_NAME, DB_HOST, DB_USER, DB_PASS);

            self::$app = new Application($db);

            return self::$app->render();
        }

        public static function log($type, $message)
        {
            $fd = fopen(LOG_PATH.'log'.date("Ym").'.txt', "a");
            fwrite($fd, sprintf("[%s] %s\n", $type, $message));
            fclose($fd);
        }

        public function render()
        {
            $this->processUrl();
            $controller = null;

            while ($action = array_shift($this->actions))
            {
                $this->lastAction = $action;

                if ($action['controller'] == null)
                    break;

                if ($action['controller'][0] == '_')
                {
                    $className = fileNameToClass(preg_replace('/[^A-Za-z_]/', 'A', substr($action['controller'], 1))).'Controller';
                    $archivo = YPF_PATH.sprintf('app/controllers/%s.php', classToFileName($className));
                }
                else
                {
                    $className = fileNameToClass(preg_replace('/[^A-Za-z_]/', 'A', $action['controller'])).'Controller';
                    $archivo = APP_PATH.sprintf('controllers/%s.php', classToFileName($className));
                }
                $className = strtoupper($className[0]).substr($className, 1);

                if (!is_file($archivo))
                    throw new YPFrameworkError (sprintf("Coudn't find %s controller: %s", $className, $archivo));

                require_once $archivo;

                $controller = eval(sprintf('return new %s();', $className));

                try
                {
                    $controller->processAction(fileNameToClass($action['action']),
                                                $this->params, $this->data,
                                                $this->viewName, $this->layout);
                } catch (JumpToNextActionException $e) {

                } catch (ErrorMessage $e) {
                    $this->error = $e->getMessage();
                    break;
                } catch (NoticeMessage $e) {
                    $this->notice .= $e->getMessage();
                }
            }

            if ($this->error) $this->data->error = $this->error;
            if ($this->notice) $this->data->notice = $this->notice;

            if (!APP_DEVELOPMENT)
                ob_clean();

            if ($this->format == '.json')
            {
                header('Cache-Control: no-cache, must-revalidate');
                header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
                header('Content-type: application/json');
                echo json_encode ($this->data->__toJSONRepresentable());
            }
            if ($this->format == '.xml')
            {
                header('Cache-Control: no-cache, must-revalidate');
                header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
                header('Content-type: text/xml');
                echo $this->objectToXML($this->data);
            }
            elseif ($this->format == '.js')
            {
                header('Cache-Control: no-cache, must-revalidate');
                header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
                header('Content-type: application/javascript');

                if (is_string($this->data))
                    echo $this->data;
                else
                    foreach (get_object_vars ($this->data) as $value)
                        echo $value;
            }
            elseif ($this->format == '.html')
            {
                header('Content-Type: text/html; charset=utf-8');
                header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

                if ($this->lastAction['controller'])
                {
                    if ($this->viewName == '')
                        $this->viewName = $this->lastAction['controller'].'/'.$this->lastAction['action'];

                    if (strpos($this->viewName, '/') === false)
                        $this->viewName = $this->lastAction['controller'].'/'.$this->viewName;

                    $view = new View($controller);
                    foreach ($this->data as $key=>$value)
                        $view->set($key, $value);
                    $html = $view->render($this->viewName);

                    $view = new ViewBase();
                    $view->set('html', $html);
                    $view->set('app', $this);
                    $view->set('cont', $controller);

                    echo $view->render('layouts/'.$this->layout);
                } else
                {
                    echo file_get_contents(WWW_PATH.'static/'.$this->lastAction['action'].'.html');
                }
            }
        }

        public function redirectTo($action = null, $object = null, $format = null, $params = array())
        {
            $this->redirectToUrl(urlTo($action, $object, $format, $params));
        }

        public function redirectToUrl($url)
        {
            if ($this->error)
                $_SESSION['error'] = $this->error;
            if ($this->notice)
                $_SESSION['notice'] = $this->notice;

            header('Location: '.$url);
            exit;
        }

        public function forwardTo($action, $params = array())
        {
            $this->actions[] = $action;

            foreach ($params as $k=>$v)
                if ($v !== null) $this->params[$k] = $v;

            throw new JumpToNextActionException();
        }

        public function sendEmail($to, $subject, $text, $params = null)
        {
            if (is_array($to))
            {
                $sum = 0;
                foreach ($to as $mail)
                    $sum += ($this->sendEmail ($email, $subject, $text, $params))? 1: 0;
                return $sum;
            }
            else {
                return mail($to, $subject, $text, implode("\r\n", $params));
            }
        }

        private function objectToXML($object, $xmlParent = null)
        {
            if ($xmlParent === null)
                $root = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><root />');
            else
                $root = $xmlParent;

            foreach($object as $key=>$val)
            {
                if (is_scalar($val) or ($val == null))
                    $root->addChild ($key, $val);
                elseif (is_object($val))
                    $this->objectToXML($val, $root->addChild($key));
            }

            if ($xmlParent === null)
                return $root->asXML();
        }

        protected function __construct($database)
        {
            $this->database = $database;

            if (isset($_SESSION['usuario']))
                $this->usuario = $_SESSION['usuario'];
            if (isset($_SESSION['facID']))
                $this->facultad = $_SESSION['facID'];

            $this->data = new Object();
            $this->urlBasePath = APP_URL;
            $this->title = APP_TITLE;

            if (isset($_SESSION['error']))
            {
                $this->error = $_SESSION['error'];
                unset($_SESSION['error']);
            }

            if (isset($_SESSION['notice']))
            {
                $this->notice = $_SESSION['notice'];
                unset($_SESSION['notice']);
            }
        }

        private function processUrl()
        {
            if (APP_ROOT != '')
                $default = explode('.', APP_ROOT);
            else
                $default = array(null, 'index');

            $this->actions[] = array(
                "controller" => get('controller', $default[0]),
                "action"     => get('action', $default[1]));
            $this->format = get('format', '.html');

            unset($_GET['controller']);
            unset($_GET['action']);
            unset($_GET['format']);

            foreach ($_POST as $k=>$v)
                if ($v == '')
                    unset($_POST[$k]);

            $this->params = array_merge($_GET, $_POST);
        }
    }

?>
