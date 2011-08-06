<?php
    function get($index, $value = NULL)
	{
		if (isset($_GET[$index]))
			return $_GET[$index];
		else
			return $value;
	}

	function post($index, $value = NULL)
	{
		if (isset($_POST[$index]))
			return $_POST[$index];
		else
			return $value;
	}

	function session($index, $value = NULL)
	{
		if (isset($_SESSION[$index]))
			return $_SESSION[$index];
		else
			return $value;
	}

    function classToFileName($class)
    {
        if (is_object($class))
            $class = get_class ($class);

        $result = '';

        for ($i = 0; $i < strlen($class); $i++)
        {
            if ((strtoupper($class[$i]) == $class[$i]) && ($i > 0) && (strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ', $class[$i]) !== false))
                $result .= '_';

            $result .= strtolower($class[$i]);
        }

        return $result;
    }

    function fileNameToClass($fileName)
    {
        $result = '';

        for ($i = 0; $i < strlen($fileName); $i++)
        {
            if ($fileName[$i] == '_')
            {
                $i++;
                $result .= strtoupper($fileName[$i]);
            } else
                $result .= $fileName[$i];
        }

        return $result;
    }

    function to_hash()
    {
        $result = array();
        for ($i = 0; $i < func_num_args(); $i+=2)
            $result[func_get_arg ($i)] = func_get_arg ($i+1);

        return $result;
    }

    function arraize($value)
    {
        if ($value === null)
            return array();
        elseif(is_array($value))
            return $value;
        else
            return array($value);
    }
?>
