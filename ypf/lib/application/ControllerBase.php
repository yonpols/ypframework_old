<?php
    class ControllerBase extends Base
    {
        public $controllerName;

        protected $data;
        protected $output;
        protected $params;

        protected static $_beforeAction = array();
        protected static $_afterAction = array();

        public function  __construct()
        {
            parent::__construct();

            $this->controllerName = substr(get_class($this), 0, -10);
        }

        public function processAction($action, &$params, $data, $output)
        {
            $time_start = microtime(true);
            $className = get_class($this);

            $this->data = new Object();
            $this->params = $params;
            $this->output = $output;

            try
            {
                foreach($className::$_beforeAction as $proc)
                    if (is_callable (array($this, $proc)))
                        call_user_func(array($this, $proc), $action);
                    else
                        throw new ErrorNoCallback(get_class($this), $proc);

                if (is_callable (array($this, $action)))
                    call_user_func(array($this, $action));
                else
                    throw new ErrorNoAction($this->controllerName, $action);

                foreach($className::$_afterAction as $proc)
                    if (is_callable (array($this, $proc)))
                        call_user_func(array($this, $proc), $action);
                    else
                        throw new ErrorNoCallback(get_class($this), $proc);

            } catch (StopRenderException $r) { }

            foreach($this->data as $key=>$val)
                $data->{$key} = $val;

            foreach($this->params as $key=>$val)
                $params[$key] = $val;

            $time_end = microtime(true);
            Logger::framework('DEBUG:ACT_RENDER', sprintf('Action rendered (%.2F secs)', ($time_end-$time_start)));
        }

        protected function param($name, $default = null)
        {
            return array_key_exists($name, $this->params)? $this->params[$name]: $default;
        }

        protected function p($name, $default = null)
        {
            return array_key_exists($name, $this->params)? $this->params[$name]: $default;
        }

        protected function render($template, $options = null)
        {
            if (is_array($options))
            {
                if (isset($options['partial']) && $options['partial'])
                {
                    $view = new View(isset($options['data'])? $options['data']: $this->data,
                                    isset($options['profile'])? $options['profile']: $this->output->profile);

                    if (strpos($template, '/') === false)
                        $template = $this->controllerName.'/'.$template;

                    return $view->render($template);
                }

                $this->output($options);
            }

            $this->output->viewName = $template;
            throw new StopRenderException();
        }

        protected function output($options)
        {
            if (array_key_exists('title', $options))
                $this->output->title = $options['title'];
            if (array_key_exists('layout', $options))
                $this->output->layout = $options['layout'];
            if (array_key_exists('profile', $options))
                $this->output->profile = $options['profile'];
            if (array_key_exists('format', $options))
                $this->output->format = $options['format'];
        }

        protected function redirectTo($url=null)
        {
            self::$app->redirectTo($url);
        }

        protected function forwardTo($action, $params = array())
        {
            if (strpos($action, '.') === false)
                $action = $this->controllerName.'.'.$action;

            $data = explode('.', $action);
            self::$app->forwardTo(array('controller' => $data[0], 'action' => $data[1]), $params);
        }

        protected function error($message)
        {
            $this->output->error = $message;
        }

        protected function notice($message)
        {
            $this->output->notice = $message;
        }
    }
?>
