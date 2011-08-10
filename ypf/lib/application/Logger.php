<?php
    class Logger extends Object
    {
        private static $configuration;
        private static $framework_log;
        private static $application_log;

        private static $excludes = array(
            'development' => array(),
            'production' => array(
                'SQL'
            )
        );

        private static $colors = array('ERROR' => 31, 'INFO' => 32, 'NOTICE' => 33, 'ROUTE' => 34, 'SQL' => 35);

        public static function init()
        {
            self::$configuration = Configuration::get();

            self::$framework_log = sprintf('%s/ypf-%s-%s.log', self::$configuration->paths->log, self::$configuration->mode, date('Y-m'));
            self::$application_log = sprintf('%s/app-%s-%s.log', self::$configuration->paths->log, self::$configuration->mode, date('Y-m'));
        }

        public static function framework($type, $log)
        {
            if (strpos($type, ':') !== false)
                list($type, $subtype) = explode(':', $type);
            else
                $subtype = 'LOG';

            if (isset(self::$excludes[self::$configuration->mode]) && (array_search($type, self::$excludes[self::$configuration->mode]) !== false))
                return;

            $fd = fopen(self::$framework_log, "a");
            fwrite($fd, sprintf("  \x1B[1;%d;1m[%s:%s]\x1B[0;0;0m %s\n", self::$colors[$type], $type, $subtype, $log));
            fclose($fd);
        }

        public static function application($type, $log)
        {
            if (strpos($type, ':') !== false)
                list($type, $subtype) = explode(':', $type);
            else
                $subtype = 'LOG';

            if (isset(self::$excludes[self::$configuration->mode]) && (array_search($type, self::$excludes[self::$configuration->mode]) !== false))
                return;

            $fd = fopen(self::$application_log, "a");
            fwrite($fd, sprintf("  \x1B[1;%d;1m[%s:%s]\x1B[0;0;0m %s\n", self::$colors[$type], $type, $subtype, $log));
            fclose($fd);
        }
    }
?>
