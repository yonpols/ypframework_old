<?php
    set_include_path(get_include_path().PATH_SEPARATOR.build_file_path(self::$paths->library, 'min/lib'));

    class MediaController extends ControllerBase
    {
        public function css()
        {
            $compiledFile = build_file_path(self::$paths->temp, 'media', $this->output->profile, 'css');
            if (!file_exists($compiledFile))
            {
                if (!is_dir(dirname($compiledFile)))
                    mkdir(dirname($compiledFile), 0777, true);

                $files = $this->htmlListPublicFiles('css', '.css');
                $css = '';
                foreach ($files as $file)
                    $css .= file_get_contents(build_file_path(self::$paths->www, $file));

                require_once 'Minify/CSS.php';
                $css = Minify_CSS::minify($css);
                file_put_contents($compiledFile, $css);
            }

            ob_end_clean();
            if ($this->config->mode == 'development')
                header('Expires: '.date("r", time()-24*3600));

            header('Content-type: text/css');

            if (extension_loaded('zlib'))
               ob_start('ob_gzhandler');
           readfile($compiledFile);
           ob_end_flush();
           exit;
        }

        public function js()
        {
            $compiledFile = build_file_path(self::$paths->temp, 'media', $this->output->profile, 'js');
            if (!file_exists($compiledFile))
            {
                if (!is_dir(dirname($compiledFile)))
                    mkdir(dirname($compiledFile), 0777, true);

                $files = $this->htmlListPublicFiles('js', '.js');
                $script = '';
                foreach ($files as $file)
                    $script .= file_get_contents(build_file_path(self::$paths->www, $file));

                require_once 'JSMin.php';
                file_put_contents($compiledFile, JSMin::minify($script));
            }

            ob_end_clean();
            if (self::$development)
                header('Expires: '.date("r", time()-24*3600));

            header('Content-type: application/javascript');

            if (extension_loaded('zlib'))
               ob_start('ob_gzhandler');
            readfile($compiledFile);
            ob_end_flush();
            exit;
        }
    }

    MediaController::__include('HtmlHelper');

?>
