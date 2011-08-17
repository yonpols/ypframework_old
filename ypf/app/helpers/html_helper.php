<?php
    class HtmlHelper extends ViewBase
    {
        public static function __initialize() {
            parent::__initialize();
            if (!isset(self::$routes->ypf_packed_media_css))
            {
                $packed_css = new Route('ypf_packed_media_css', array(
                    'match' => 'css/ypf_packed',
                    'controller' => '_media',
                    'action' => 'css',
                    'method' => 'get'
                ));
                self::$config->registerRoute($packed_css);

                $packed_js = new Route('ypf_packed_media_js', array(
                    'match' => 'js/ypf_packed',
                    'controller' => '_media',
                    'action' => 'js',
                    'method' => 'get'
                ));
                self::$config->registerRoute($packed_js);
            }
        }

        public function htmlRenderStylesheets()
        {
            if (self::$config->application('pack_media'))
                printf('<link rel="stylesheet" type="text/css" href="%s" />', self::$routes->ypf_packed_media_css->path());
            else
            {
                $lista = $this->htmlListPublicFiles('css', '.css');
                foreach ($lista as $file)
                    printf('<link rel="stylesheet" type="text/css" href="%s/%s" />', self::$config->application('url', self::$paths->request_uri), $file);
            }
        }

        public function htmlRenderJavascripts()
        {
            if (self::$config->application('pack_media'))
                printf('<script type="text/javascript" src="%s"></script>', self::$routes->ypf_packed_media_js->path());
            else
            {
                $lista = $this->htmlListPublicFiles('js', '.js');
                foreach ($lista as $file)
                    printf('<script type="text/javascript" src="%s/%s"></script>', self::$config->application('url', self::$paths->request_uri), $file);
            }
        }

        public function htmlListPublicFiles($baseDir, $extension)
        {
            $lista = array();

            if ($this->profile !== null)
            {
                $fileBasePath = self::$paths->www."/$baseDir/_profiles/".$this->profile.'/';
                $urlBasePath = "$baseDir/_profiles/".$this->profile.'/';
            } else
            {
                $fileBasePath = self::$paths->www."/$baseDir/";
                $urlBasePath = "$baseDir/";
            }

            $files = opendir($fileBasePath);
            while ($file = readdir($files))
            {
                $file = realpath($fileBasePath.$file);
                if (is_file($file) && (substr($file, -strlen($extension)) == $extension))
                    $lista[] = $urlBasePath.basename($file);
            }

            usort($lista, function($f1, $f2) { return strcasecmp(basename($f1), basename($f2)); });
            return $lista;
        }
    }

    HtmlHelper::__initialize();

    ViewBase::__include('HtmlHelper');
?>
