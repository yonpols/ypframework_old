<?php
    function css_fila($i)
    {
        return $i % 2;
    }

    function renderTag($type = 'js')
    {
        if ($type == 'js')
        {
            if (APP_PACK_MEDIA)
                printf('<script type="text/javascript" src="%s/_media/js"></script>', APP_URL);
            else
            {
                $lista = listJSFiles();
                foreach ($lista as $js)
                    printf('<script type="text/javascript" src="%s/js/%s"></script>', APP_URL, $js);
            }
        } elseif ($type == 'css')
        {
            if (APP_PACK_MEDIA)
                printf('<link rel="stylesheet" type="text/css" href="%s/_media/css" />', APP_URL);
            else
            {
                $lista = listCSSFiles();
                foreach ($lista as $css)
                    printf('<link rel="stylesheet" type="text/css" href="%s/css/%s" />', APP_URL, $css);
            }
        }
    }

    /**
     * Create an YPF compatible url.
     * @param string $action    Action to call. Can be 'Controller.', 'Action', 'Controller.Action'
     * @param Model $object     Model to pass to action. Serializes ID
     * @param string $format    Desired format. Can be html(default), json, xml
     * @param mixed ...         Hash of params or a list of param, value...
     * @return string           Returns url
     */
    function urlTo($action = null, $object = null, $format = null)
    {
        if (!$action)
        {
            if (APP_ROOT != '')
                $action = APP_ROOT;
            else
                $action = 'index';
        }

        $default = explode('.', $action);

        if (count($default) == 1)
        {
            $action = $default[0];
            $controller = Application::$app->lastAction['controller'];
        } else
        {
            $action = $default[1];
            $controller = $default[0];
        }

        if (APP_PRETTYURL)
        {
            if ($format)
                $format = '.'.$format;

            $url = sprintf('%s/%s/%s', APP_URL, classToFileName($controller), classToFileName($action));

            if (is_object($object) && $object instanceof Model)
                $url .= sprintf("/%s", urlencode ($object->getSerializedKey()));

            $url .= $format;
            $connector = '?';
        } else {
            if ($action == '')
                $action = 'index';

            $url = sprintf('%s/index.php?controller=%s&action=%s', APP_URL, classToFileName($controller), classToFileName($action));

            if (is_object($object) && $object instanceof Model)
                $url .= sprintf("&id=%s", urlencode ($object->getSerializedKey()));

            if ($format)
                $url .= sprintf ("&format=%s", urlencode ($format));

            $connector = '&';
        }

        if (func_num_args() > 3)
        {
            $url .= $connector;

            $arg3 = func_get_arg(3);
            if (is_array($arg3))
            {
                foreach($arg3 as $k=>$v)
                    $url .= sprintf('%s=%s&', urlencode ($k), urldecode ($v));
            } else {
                $conn = "&=";
                for ($i = 3; $i < func_num_args(); $i++)
                    $url .= sprintf("%s%s", urlencode (func_get_arg ($i)), $conn[$i % 2]);
            }
            $url = substr($url, 0, -1);
        }

        return  $url;
    }

    function html($text, $br=true)
    {
        if ($br)
            return str_replace("\n", "<br />", htmlentities($text));
        else
            return htmlentities($text);
    }

    function locl_date($date)
    {
        $date = DataBase::sqlDateToUTC($date);
        return strftime("%x", $date);
    }

    function locl_datetime($date)
    {
        $date = DataBase::sqlDateTimeToUTC($date);
        return strftime("%c", $date);
    }

?>
