<?php
    if (!defined('WWW_PATH'))
        include(YPF_PATH.'/app/errors/no_www_path.php');

    require realpath(YPF_PATH.'/ypf.php');

    try
    {
        Application::run();
    }
    catch (Exception $e)
    {
        if (!($e instanceof BaseError))
            Logger::framework('ERROR', $e->getMessage()."\n\t".$e->getTraceAsString());

        if (!Base::isDevelopment())
            ob_clean();

        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
        echo $e->getMessage();
    }
?>
