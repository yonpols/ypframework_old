<?php
    function removeTemporaryFiles($parent = '')
    {
        $dir = Configuration::get()->paths->temp.'/'.$parent;
        $dd = opendir($dir);

        while ($file = readdir($dd))
        {
            if ($file == '.' || $file == '..')
                continue;

            if (is_dir($dir.'/'.$file))
                removeTemporaryFiles($parent.'/'.$file);
            else
                unlink ($dir.'/'.$file);
        }

        if ($parent != '')
            rmdir ($dir);
    }

    function listCSSFiles($otherDir = null)
    {
        $app = Application::get();
        $lista = array();

        if ($app->profile !== null)
        {
            $path = $app->config->paths->www.'/css/_profiles/'.$app->profile;
            $files = opendir($path);
            while ($css = readdir($files))
            {
                $archivo = realpath($path.'/'.$css);
                if (is_file($archivo) && (substr($css, -4) == '.css'))
                    $lista[] = '_profiles/'.$app->profile.'/'.$css;
            }
        } else
        {
            $path = $app->config->paths->www.'/css';
            $files = opendir($path);
            while ($css = readdir($files))
            {
                $archivo = realpath($path.'/'.$css);
                if (is_file($archivo) && (substr($css, -4) == '.css'))
                    $lista[] = $css;
            }
        }

        usort($lista, '__sortFile');
        return $lista;
    }

    function listJSFiles($otherDir = null)
    {
        $app = Application::get();
        $lista = array();

        if ($app->profile !== null)
        {
            $path = Configuration::get()->paths->www.'/js/_profiles/'.$app->profile;
            $files = opendir($path);
            while ($js = readdir($files))
            {
                $archivo = realpath($path.'/'.$js);
                if (is_file($archivo) && (substr($js, -3) == '.js'))
                    $lista[] = '_profiles/'.$app->profile.'/'.$js;
            }
        } else {
            $path = Configuration::get()->paths->www.'/js';
            $files = opendir($path);
            while ($js = readdir($files))
            {
                $archivo = realpath($path.'/'.$js);
                if (is_file($archivo) && (substr($js, -3) == '.js'))
                    $lista[] = $js;
            }
        }

        usort($lista, '__sortFile');
        return $lista;
    }

    function __sortFile($f1, $f2)
    {
        return strcasecmp(basename($f1), basename($f2));
    }
?>
