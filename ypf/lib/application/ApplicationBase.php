<?php
    class ApplicationBase extends Object
    {
        private static $app = null;

        private $title;
        private $config;
        private $database = null;

        private $notice = null;
        private $error = null;
        private $lastAction = null;

        private $params = null;
        private $viewName = null;
        private $data = null;
        private $layout = 'main';
        private $profile = null;

        private $actions = array();
        private $format = 'html';

        public static function run($db = null)
        {
            if ($db === NULL)
                $db = self::loadDB();

            self::$app = new Application($db);

            return self::$app->render();
        }

        public static function get()
        {
            if (self::$app == null)
                Application::run ();

            return self::$app;
        }

        protected static function loadDB()
        {
            $config = Configuration::get();
            $dbtype = $config->database('type');

            if ($dbtype === null)
                return null;

            $dbdriver = $config->paths->ypf.'/lib/databases/'.$dbtype.'.php';
            if (file_exists($dbdriver))
            {
                require_once $dbdriver;
                $str = sprintf('return new %sDataBase($config->database());', $dbtype);
                return eval($str);
            } else
                throw new YPFrameworkError ('Specified database driver doesn\'t exist: '.$dbtype);
        }

        public function __get($name)
        {
            $allowed = array('config', 'title', 'database', 'notice', 'error', 'lastAction', 'urlBasePath', 'profile', 'layout');
            if (array_search($name, $allowed) !== false)
                return $this->{$name};
        }

        public function __set($name, $value)
        {
            $allowed = array('title', 'notice', 'error');
            if (array_search($name, $allowed) !== false)
                $this->{$name} = $value;
        }

        public function render()
        {
            $this->processUrl();
            $this->processProfile();
            $controller = null;

            while ($action = array_shift($this->actions))
            {
                $this->lastAction = $action;

                if ($action['controller'] == null)
                    break;

                if ($action['controller'][0] == '_')
                {
                    $className = fileNameToClass(preg_replace('/[^A-Za-z_]/', 'A', substr($action['controller'], 1))).'Controller';
                    $archivo = $this->config->paths->ypf.sprintf('/app/controllers/%s.php', classToFileName($className));
                }
                else
                {
                    $className = fileNameToClass(preg_replace('/[^A-Za-z_]/', 'A', $action['controller'])).'Controller';
                    $archivo = $this->config->paths->application.sprintf('/controllers/%s.php', classToFileName($className));
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

            if ($this->config->mode != 'development')
                ob_clean();

            if ($this->format == 'json')
            {
                header('Cache-Control: no-cache, must-revalidate');
                header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
                header('Content-type: application/json');
                echo json_encode ($this->data->__toJSONRepresentable());
            }
            if ($this->format == 'xml')
            {
                header('Cache-Control: no-cache, must-revalidate');
                header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
                header('Content-type: text/xml');
                echo $this->objectToXML($this->data);
            }
            elseif ($this->format == 'js')
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
            elseif (($this->format == 'html') || ($this->format == 'clean'))
            {
                header('Content-Type: text/html; charset=utf-8');
                header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

                if ($this->lastAction['controller'])
                {
                    if ($this->viewName == '')
                        $this->viewName = $this->lastAction['controller'].'/'.$this->lastAction['action'];

                    if (strpos($this->viewName, '/') === false)
                        $this->viewName = $this->lastAction['controller'].'/'.$this->viewName;

                    $view = new View($controller, $this->profile);
                    foreach ($this->data as $key=>$value)
                        $view->set($key, $value);
                    $html = $view->render($this->viewName);

                    if ($this->format == 'html')
                    {
                        $view = new ViewBase(null, $this->profile);
                        $view->set('html', $html);
                        $view->set('app', $this);
                        $view->set('cont', $controller);

                        echo $view->render('_layouts/'.$this->layout);
                    } else {
                        echo $html;
                    }
                } else
                {
                    echo file_get_contents($this->config->paths->www.'/static/'.$this->lastAction['action'].'.html');
                }
            } elseif ($this->format == 'raw')
                echo $this->data;
        }

        public function redirectTo($url=null)
        {
            if ($this->error)
                $_SESSION['error'] = $this->error;
            if ($this->notice)
                $_SESSION['notice'] = $this->notice;

            if ($url === null)
                $url = $this->config->application('url');

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
            $this->config = Configuration::get();
            $this->database = $database;
            $this->data = new Object();
            $this->title = $this->config->application('title');

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
            if (isset($_GET['::action']))
                $action = $_GET['::action'];
            else
                $action = $this->config->application('root', '/home/index');
            if ($action[0] == '/')
                $action = substr($action, 1);

            $match = $this->config->matchingRoute($action);
            if ($match !== false)
            {
                $this->actions = array(array(
                    'controller' => $match['controller'],
                    'action' => $match['action']
                ));

                $this->format = get('format', $match['format']);
                unset($match['controller']);
                unset($match['action']);
                unset($match['format']);

                foreach ($_POST as $k=>$v)
                    if ($v == '')
                        unset($_POST[$k]);

                $this->params = array_merge($_GET, $_POST, $match);
            } else
                throw new YPFrameworkError("No route for action: $action");
        }

        private function processProfile()
        {
            $profile = $this->config->application('profile', true);

            if ($profile === false)
                $this->profile = null;
            elseif ($profile === true)
            {
                $this->profile = (isMobileBrowser()? 'mobile': 'desktop');
                $path = $this->config->paths->application.'/views/_profiles/'.$this->profile.'/_layouts';
                if (!file_exists($path))
                    $this->profile = null;
            }
            else
                $this->profile = $profile;
        }
    }

?>
