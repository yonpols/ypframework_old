<?php
    define('YPF_PATH', realpath(dirname(__FILE__).'/../ypf'));
    require realpath(YPF_PATH.'/ypf.php');

    try
    {
        Application::run();
    }
    catch (Exception $e)
    {
        Application::log('ERROR', $e->getMessage()."\n\t".$e->getTraceAsString());

        if (Configuration::get()->mode != 'development')
            ob_clean();
        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
        echo to_html($e->getMessage(), true);
    }
?>