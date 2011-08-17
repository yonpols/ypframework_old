<?php
    class Base extends Object
    {
        protected static $config;
        protected static $paths;
        protected static $routes;
        protected static $mode;
        protected static $production = false;
        protected static $development = true;

        protected static $database;
        protected static $app;

        public static function __initialize()
        {
            if (self::$database !== null)
                return;

            self::$config = Configuration::get();
            self::$routes = self::$config->routes;
            self::$paths = self::$config->paths;
            self::$mode = self::$config->mode;

            self::$production = (stripos(self::$mode, 'production') === 0);
            self::$development = (stripos(self::$mode, 'development') === 0);

            Logger::init();

            self::$database = self::getDatabase(self::$config->database());
        }

        public static function __finalize()
        {

        }

        protected static function getDatabase($config)
        {
            if (!isset($config->type))
                return null;

            $driverFileName = build_file_path(self::$paths->ypf, 'lib/databases', $config->type.'.php');

            if (file_exists($driverFileName))
            {
                require_once $driverFileName;
                $className = $config->type."DataBase";
                return new $className($config);
            } else
                throw new ErrorComponentNotFound ('DB:DRIVER', $config->type);
        }

        public static function isDevelopment()
        {
            return self::$development;
        }

        public static function isProduction()
        {
            return self::$production;
        }
    }
?>
