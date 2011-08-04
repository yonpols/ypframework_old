<?php

    class Object
    {
        public function __toString()
        {
            $str = sprintf('<%s ', get_class($this));
            foreach ($this as $k=>$v)
                $str .= sprintf('%s: %s; ', $k, var_export($v, true));

            return substr($str, 0, -2).'>';
        }

        public function __toJSONRepresentable()
        {
            $result = array();

            foreach ($this as $k=>$v)
            {
                if (is_object($v) && ($v instanceof Object))
                    $result[$k] = $v->__toJSONRepresentable();
                else
                    $result[$k] = $v;
            }

            return $result;
        }
    }

?>
