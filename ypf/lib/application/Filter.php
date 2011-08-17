<?php
    class Filter extends Base
    {
        protected $output;
        protected $data;
        protected $controller;
        protected $action;

        protected $content;
        protected $contentType;

        public function __construct($controller, $action, $output, $data)
        {
            parent::__construct();

            $this->output = $output;
            $this->data = $data;
            $this->controller = $controller;
            $this->action = $action;
        }

        public function output()
        {
            if (self::$production)
                $previousContent = '';
            else
                $previousContent = ob_get_clean();

            $this->processOutput();

            if ($previousContent != '')
            {
                if (self::$production)
                    Logger::framework('ERROR', sprintf("Out of flow content: %s", $previousContent));
                else
                    throw new ErrorOutOfFlow($previousContent);
            }

            ob_end_clean();
            ob_start('ob_gzhandler');

            if ($this->contentType)
                header("Content-Type: $this->contentType");

            echo $this->content;
            ob_flush();
        }

        protected function processOutput()
        {
            $this->contentType = null;
            $this->content = is_string($this->data)? $this->data: $this->data->__toString();
        }
    }
?>
