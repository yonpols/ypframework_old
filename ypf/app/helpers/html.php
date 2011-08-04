<?php
    function css_fila($i)
    {
        return $i % 2;
    }

    function renderTag($type = 'js')
    {
        $config = Configuration::get();

        if ($type == 'js')
        {
            if ($config->application('pack_media'))
                printf('<script type="text/javascript" src="%s"></script>', $config->routes->packed_media_js->path());
            else
            {
                $lista = listJSFiles();
                foreach ($lista as $js)
                    printf('<script type="text/javascript" src="%s/js/%s"></script>', $config->application('url', $config->paths->request_uri), $js);
            }
        } elseif ($type == 'css')
        {
            if ($config->application('pack_media'))
                printf('<link rel="stylesheet" type="text/css" href="%s" />', $config->routes->packed_media_css->path());
            else
            {
                $lista = listCSSFiles();
                foreach ($lista as $css)
                    printf('<link rel="stylesheet" type="text/css" href="%s/css/%s" />', $config->application('url', $config->paths->request_uri), $css);
            }
        }
    }

    function to_html($text, $br=true)
    {
        if ($br)
            return str_replace("\n", "<br />", htmlentities($text, ENT_COMPAT, 'utf-8'));
        else
            return htmlentities($text);
    }
?>
