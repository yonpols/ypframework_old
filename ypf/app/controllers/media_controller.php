<?php
    class MediaController extends ControllerBase
    {
        public function css()
        {
            if (!file_exists($this->config->paths->temp.'/media/css'))
            {
                if (!is_dir($this->config->paths->temp.'/media'))
                    mkdir($this->config->paths->temp.'/media', 0777, true);

                $files = listCSSFiles();
                require $this->config->paths->library.'/css_compressor/Css.php';
                $css = new Css($files, $this->config->paths->www.'/css');
                $css->output($this->config->paths->temp.'/media/css');
            }

            ob_end_clean();
            if ($this->config->mode == 'development')
                header('Expires: '.date("r", time()-24*3600));

            header('Content-type: text/css');

            if (extension_loaded('zlib'))
               ob_start('ob_gzhandler');
           readfile($this->config->paths->temp.'/media/css');
           ob_end_flush();
           exit;
        }

        public function js()
        {
            if (!file_exists($this->config->paths->temp.'/media/js'))
            {
                $files = listJSFiles();
                require $this->config->paths->library.'/jsmin.php';

                if (!is_dir($this->config->paths->temp.'/media'))
                    mkdir($this->config->paths->temp.'/media', 0777, true);

                $script = '';
                foreach ($files as $file)
                    $script .= file_get_contents($this->config->paths->www.'/js/'.$file);

                file_put_contents($this->config->paths->temp.'/media/js', JSMin::minify($script));
            }

            ob_end_clean();
            if ($this->config->mode == 'development')
                header('Expires: '.date("r", time()-24*3600));

            header('Content-type: application/javascript');

            if (extension_loaded('zlib'))
               ob_start('ob_gzhandler');
            readfile($this->config->paths->temp.'/media/js');
            ob_end_flush();
            exit;
        }
    }

?>
