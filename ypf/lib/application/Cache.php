<?php
    class Cache extends Base
    {
        public static function __initialize()
        {
            if (self::$development)
                self::invalidate();
        }

        public static function invalidate()
        {

        }
    }
?>
