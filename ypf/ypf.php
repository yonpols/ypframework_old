<?php
    //Initial paths definitions.
    define('BASE_PATH', dirname(dirname(__FILE__)).'/');
    if (!defined('YPF_PATH')) define('YPF_PATH', realpath(BASE_PATH.'/ypf'));
    if (!defined('LIB_PATH')) define('LIB_PATH', realpath(BASE_PATH.'/lib'));
    if (!defined('WWW_PATH')) define('WWW_PATH', realpath(BASE_PATH.'/www'));
    if (!defined('APP_PATH')) define('APP_PATH', realpath(BASE_PATH.'/app'));
    define('LOG_PATH', realpath(APP_PATH.'/logs'));
    define('TMP_PATH', realpath(APP_PATH.'/tmp'));

    define('YPF_MODEL_CACHE_MAX', 20);

    //Check for PHP version
    if ((!defined('PHP_VERSION_ID')) || PHP_VERSION_ID < 50300)
        include(YPF_PATH.'/app/errors/php_version.php');

    ob_start();

    //Load YPF clases
    require_once YPF_PATH.'/lib/basic/Object.php';
    require_once YPF_PATH.'/lib/basic/Base.php';
    require_once YPF_PATH.'/lib/basic/Exceptions.php';
    require_once YPF_PATH.'/lib/basic/Logger.php';
    require_once YPF_PATH.'/lib/basic/Configuration.php';

    require_once YPF_PATH.'/lib/databases/DataBase.php';

    require_once YPF_PATH.'/lib/application/ApplicationBase.php';
    require_once YPF_PATH.'/lib/application/ControllerBase.php';
    require_once YPF_PATH.'/lib/application/Route.php';
    require_once YPF_PATH.'/lib/application/Cache.php';
    require_once YPF_PATH.'/lib/application/Filter.php';

    require_once YPF_PATH.'/lib/templates/ViewBase.php';

    require_once YPF_PATH.'/lib/records/IModelQuery.php';
    require_once YPF_PATH.'/lib/records/ModelQuery.php';
    require_once YPF_PATH.'/lib/records/ModelBaseRelation.php';
    require_once YPF_PATH.'/lib/records/ModelBase.php';

    //Load helpers
    $helpers = opendir(YPF_PATH.'/app/helpers/');
    while ($helper = readdir($helpers))
        if (is_file(YPF_PATH.'/app/helpers/'.$helper) && substr($helper, -4) == '.php')
            require_once YPF_PATH.'/app/helpers/'.$helper;

    //Load base clases
    require_once APP_PATH.'/base/Application.php';
    require_once APP_PATH.'/base/Controller.php';
    require_once APP_PATH.'/base/Model.php';
    require_once APP_PATH.'/base/View.php';

    //Load app helpers
    $helpers = opendir(APP_PATH.'/helpers/');
    while ($helper = readdir($helpers))
        if (is_file(APP_PATH.'/helpers/'.$helper) && substr($helper, -4) == '.php')
            require_once APP_PATH.'/helpers/'.$helper;

    Base::__initialize();
    Cache::__initialize();
?>