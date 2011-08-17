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

    function class_to_file_name($class)
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

    function file_name_to_class($fileName, $firstUp = true)
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

        if ($firstUp)
            return strtoupper($result[0]).substr($result, 1);
        else
            return $result;
    }

    function build_file_path()
    {
        $filePath = '';

        foreach (func_get_args() as $path)
        {
            if ($path == '')
                continue;

            if (substr($path, -1) != '/')
                $filePath .= $path . '/';
            else
                $filePath .= $path;
        }

        if (substr($filePath, -1) == '/')
            return substr($filePath, 0, -1);
        else
            return $filePath;
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
