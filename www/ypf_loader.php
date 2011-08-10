<?php
    if (!defined('WWW_PATH'))
        define('WWW_PATH', realpath(dirname(__FILE__)).'/');
    if (!defined('YPF_PATH'))
        define('YPF_PATH', realpath(dirname(__FILE__).'/../ypf'));

    require realpath(YPF_PATH.'/ypf.php');
    try
    {
        Application::run();
    }
    catch (Exception $e)
    {
        Logger::framework('ERROR', $e->getMessage()."\n\t".$e->getTraceAsString());

        if (!APP_DEVELOPMENT)
            ob_clean();
        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
        echo $e->getMessage();
    }
?>
