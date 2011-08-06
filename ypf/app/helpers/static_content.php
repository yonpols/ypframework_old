<?php

    function removeTemps($parent = '')
    {
        $dir = TMP_PATH.$parent;
        $dd = opendir($dir);
        
        while ($file = readdir($dd))
        {
            if ($file == '.' || $file == '..')
                continue;
            
            if (is_dir($dir.'/'.$file))
                removeTemps ($parent.'/'.$file);
            else
                unlink ($dir.'/'.$file);
        }
        
        if ($parent != '')
            rmdir ($dir);
    }
    
    function listCSSFiles()
    {
        $lista = array();
        $files = opendir(WWW_PATH.'/css');
        while ($css = readdir($files)) 
        {
            $archivo = realpath(WWW_PATH.'/css/'.$css);
            if (is_file($archivo) && (substr($css, -4) == '.css'))
                $lista[] = $css;
        }
        sort($lista);        
        return $lista;
    }
    
    function listJSFiles()
    {
        $lista = array();
        $files = opendir(WWW_PATH.'/js');
        while ($js = readdir($files)) 
        {
            $archivo = realpath(WWW_PATH.'/js/'.$js);
            if (is_file($archivo) && (substr($js, -3) == '.js'))
                $lista[] = $js;
        }

        sort($lista);        
        return $lista;
    }

?>
