<?php
    class Logger extends Base
    {
        private static $framework_log;
        private static $application_log;

        private static $excludes = array(
            'development' => array(),
            'production' => array(
                'SQL', 'DEBUG'
            )
        );

        private static $colors = array('ERROR' => 31, 'INFO' => 32, 'NOTICE' => 33, 'DEBUG' => 36, 'ROUTE' => 34, 'SQL' => 35);

        public static function init()
        {
            self::$framework_log = build_file_path(self::$paths->log, sprintf('ypf-%s-%s.log', self::$mode, date('Y-m')));
            self::$application_log = build_file_path(self::$paths->log, sprintf('app-%s-%s.log', self::$mode, date('Y-m')));
        }

        public static function framework($type, $log)
        {
            if (strpos($type, ':') !== false)
                list($type, $subtype) = explode(':', $type);
            else
                $subtype = 'LOG';

            if (self::isExcluded($type))
                return;

            $fd = fopen(self::$framework_log, "a");
            fwrite($fd, sprintf("[%s] \x1B[1;%d;1m%s:%s\x1B[0;0;0m %s\n", strftime('%F %T'), self::getColor($type), $type, $subtype, $log));
            fclose($fd);
        }

        public static function application($type, $log)
        {
            if (strpos($type, ':') !== false)
                list($type, $subtype) = explode(':', $type);
            else
                $subtype = 'LOG';

            if (self::isExcluded($type))
                return;

            $fd = fopen(self::$application_log, "a");
            fwrite($fd, sprintf("[%s] \x1B[1;%d;1m%s:%s\x1B[0;0;0m %s\n", strftime('%F %T'), self::getColor($type), $type, $subtype, $log));
            fclose($fd);
        }

        private static function getColor($type)
        {
            if (isset(self::$colors[$type]))
                return self::$colors[$type];
            else
                return 0;
        }

        private static function isExcluded($type)
        {
            return (isset(self::$excludes[self::$mode]) && (array_search($type, self::$excludes[self::$mode]) !== false));
        }
    }
?>
