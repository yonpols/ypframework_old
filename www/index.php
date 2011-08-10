<?php
    define('WWW_PATH', realpath(dirname(__FILE__)).'/');

    //Change this settings if your www dir is not in the default location
    define('YPF_PATH', realpath(dirname(__FILE__).'/../ypf').'/');

    //Uncomment this settings if your app dir is not in the default location
    //define('APP_PATH', realpath(dirname(__FILE__).'/../app').'/');
    //define('LIB_PATH', realpath(dirname(__FILE__).'/../lib').'/');

    require realpath(YPF_PATH.'ypf.php');
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