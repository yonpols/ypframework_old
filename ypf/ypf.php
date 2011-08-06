<?php
    //set_error_handler('__ypfErrorHandler');
    ob_start();

    define('BASE_PATH', dirname(dirname(__FILE__)).'/');
    if (!defined('YPF_PATH')) define('YPF_PATH', realpath(BASE_PATH.'/ypf').'/');
    if (!defined('LIB_PATH')) define('LIB_PATH', realpath(BASE_PATH.'/lib').'/');
    if (!defined('WWW_PATH')) define('WWW_PATH', realpath(BASE_PATH.'/www').'/');
    if (!defined('APP_PATH')) define('APP_PATH', realpath(BASE_PATH.'/app').'/');
    define('LOG_PATH', realpath(APP_PATH.'/logs').'/');
    define('TMP_PATH', realpath(APP_PATH.'/tmp').'/');

    //Load YPF clases
    require_once YPF_PATH.'lib/Object.php';
    require_once YPF_PATH.'lib/databases/DataBase.php';
    require_once YPF_PATH.'lib/databases/MySQL.php';
    require_once YPF_PATH.'lib/application/Exceptions.php';
    require_once YPF_PATH.'lib/application/ApplicationBase.php';
    require_once YPF_PATH.'lib/application/ControllerBase.php';
    require_once YPF_PATH.'lib/templates/ViewBase.php';
    require_once YPF_PATH.'lib/records/IModelQuery.php';
    require_once YPF_PATH.'lib/records/ModelQuery.php';
    require_once YPF_PATH.'lib/records/ModelBaseRelation.php';
    require_once YPF_PATH.'lib/records/ModelBase.php';

    //Load helpers
    $helpers = opendir(YPF_PATH.'app/helpers/');
    while ($helper = readdir($helpers))
        if (is_file(YPF_PATH.'app/helpers/'.$helper) && substr($helper, -4) == '.php')
            require_once YPF_PATH.'app/helpers/'.$helper;

    //Load base clases
    require_once APP_PATH.'/base/Application.php';
    require_once APP_PATH.'/base/Controller.php';
    require_once APP_PATH.'/base/Model.php';
    require_once APP_PATH.'/base/View.php';

    //Load configuration
    require_once APP_PATH.'config.php';

    $helpers = opendir(APP_PATH.'/helpers/');
    while ($helper = readdir($helpers))
        if (is_file(APP_PATH.'helpers/'.$helper) && substr($helper, -4) == '.php')
            require_once APP_PATH.'helpers/'.$helper;

    function __autoload($className)
    {
        if (!function_exists('classToFileName'))
            throw new Exception('Coudn\'t find class: '.$className);

        $classFile = APP_PATH.'/models/'.classToFileName($className).'.php';

        if (is_file($classFile))
            require($classFile);
        else
            throw new Exception('Coudn\'t find model: '.$className);
    }

    function __ypfErrorHandler($errno, $errstr, $errfile, $errline)
    {
        $logFile = LOG_PATH.'log'.date("Ym").'.txt';

        @$fd = fopen($logFile, "a");

        if ($fd) {
            fwrite($fd, sprintf("[PHP_ERROR] %d: %s\n\t%s: line %d", $errno, $errstr, $errfile, $errline));
            fclose($fd);
        }

        if ($errno & E_NOTICE)
            return true;

        ob_clean();

        if (file_exists(WWW_PATH.'static/error.html'))
            echo @file_get_contents(WWW_PATH.'static/error.html');
        else {
            echo '<h1>Se ha producido un error inesperado</h1>';
            echo 'Por favor comun&iacute;quese con el administrador del sistema<br/>';

            printf("[PHP_ERROR] %d: %s\n\t%s: line %d", $errno, $errstr, $errfile, $errline);
        }

        exit;
    }

    if (APP_DEVELOPMENT)
        removeTemps('');
?>
