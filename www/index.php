<?php
    define('WWW_PATH', realpath(dirname(__FILE__)).'/');

    //Change this settings if your www dir is not in the default location
    define('YPF_PATH', realpath(dirname(__FILE__).'/../ypf'));

    //Uncomment this settings if your app dir is not in the default location
    //define('APP_PATH', realpath(dirname(__FILE__).'/../app').'/');
    //define('LIB_PATH', realpath(dirname(__FILE__).'/../lib').'/');

    require YPF_PATH.'/loader.php';
?>