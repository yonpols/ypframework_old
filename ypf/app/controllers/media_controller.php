<?php
    class MediaController extends ControllerBase
    {
        public function css()
        {
            if (!file_exists(TMP_PATH.'media/css'))
            {
                if (!is_dir(TMP_PATH.'media'))
                    mkdir(TMP_PATH.'media', 0777, true);
                
                $files = listCSSFiles();
                require LIB_PATH.'css_compressor/Css.php';
                $css = new Css($files, WWW_PATH.'css');
                $css->output(TMP_PATH.'media/css');
            }
            
            ob_end_clean();
            if (APP_DEVELOPMENT)
                header('Expires: '.date("r", time()-24*3600));
            
            header('Content-type: text/css');

            if (extension_loaded('zlib'))
               ob_start('ob_gzhandler');
           readfile(TMP_PATH.'media/css');
           ob_end_flush();
           exit;
        } 
        
        public function js()
        {
            if (!file_exists(TMP_PATH.'media/js'))
            {
                $files = listJSFiles();
                require LIB_PATH.'jsmin.php';
                
                if (!is_dir(TMP_PATH.'media'))
                    mkdir(TMP_PATH.'media', 0777, true);
                
                $script = '';
                foreach ($files as $file)
                    $script .= file_get_contents(WWW_PATH.'js/'.$file);
                
                file_put_contents(TMP_PATH.'media/js', JSMin::minify($script));
            }
            
            ob_end_clean();
            if (APP_DEVELOPMENT)
                header('Expires: '.date("r", time()-24*3600));
            
            header('Content-type: application/javascript');

            if (extension_loaded('zlib'))
               ob_start('ob_gzhandler');
            readfile(TMP_PATH.'media/js');
            ob_end_flush();
            exit;
        }
    }

?>
