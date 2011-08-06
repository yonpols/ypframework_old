<?php
    class ControllerBase extends Object
    {
        public $controllerName;

        protected $database = null;
        protected $app = null;
        protected $config = null;
        protected $routes = null;
        protected $data;
        protected $layout;
        protected $params;

        protected $beforeAction = array();
        protected $afterAction = array();

        private $viewName = null;

        public function  __construct()
        {
            $this->app = Application::get();
            $this->config = Configuration::get();
            $this->database = $this->app->database;
            $this->routes = $this->config->routes;
            $this->controllerName = substr(get_class($this), 0, -10);
        }

        public function processAction($action, &$params, &$data, &$viewName, &$layout)
        {
            $this->data = new Object();
            $this->params = $params;
            $this->layout = $layout;

            try
            {
                foreach($this->beforeAction as $proc)
                {
                    if (is_callable (array($this, $proc)))
                        call_user_func(array($this, $proc), $action);
                    else
                        throw new YPFrameworkError(sprintf("Undefined method %s in controller %s",
                            $proc, get_class($this)));
                }

                if (is_callable (array($this,$action)))
                    call_user_func(array($this, $action));
                else
                    throw new YPFrameworkError(sprintf("Undefined method %s in controller %s",
                        $action, get_class($this)));

                foreach($this->afterAction as $proc)
                    if (is_callable (array($this, $proc)))
                        call_user_func(array($this, $proc), $action);
                    else
                        throw new YPFrameworkError(sprintf("Undefined method %s in controller %s",
                            $proc, get_class($this)));
            } catch (StopRenderException $r) {
            }

            foreach($this->data as $key=>$val)
                $data->{$key} = $val;

            foreach($this->params as $key=>$val)
                $params[$key] = $val;

            if ($this->layout !== null)
                $layout = $this->layout;

            if ($this->viewName !== null)
                $viewName = $this->viewName;
        }

        protected function renderPartial($template, $data = null)
        {
            if ($data == null)
                $data = $this->data;

            $view = new View($this, $this->app->profile);
            foreach ($data as $key=>$value)
                $view->set($key, $value);

            if (strpos($template, '/') === false)
                $template = $this->controllerName.'/'.$template;

            return $view->render($template);
        }

        protected function render($template)
        {
            $this->viewName = $template;
            throw new StopRenderException();
        }

        protected function redirectTo($url=null)
        {
            $this->app->redirectTo($url);
        }

        protected function forwardTo($action, $params = array())
        {
            if (strpos($action, '.') === false)
                $action = $this->controllerName.'.'.$action;

            $data = explode('.', $action);
            $this->app->forwardTo(array('controller' => $data[0], 'action' => $data[1]), $params);
        }

        protected function error($message)
        {
            $this->app->error .= $message.' ';
        }

        protected function notice($message)
        {
            $this->app->notice .= $message.' ';
        }
    }
?>
