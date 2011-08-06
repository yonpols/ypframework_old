<?php
    abstract class DataBase extends Object
	{
		protected $host = '';
		protected $dbname = '';
		protected $user = '';
		protected $pass = '';
		protected $connected = false;

		protected $db = NULL;

        public function  __construct($dbname, $host = NULL, $user = NULL, $pass = NULL, $connect = true)
		{
			$this->host = $host;
			$this->dbname = $dbname;
			$this->user = $user;
			$this->pass = $pass;

			if ($connect)
            {
                if (!$this->connect())
                    throw new Exception ("Coudn't connect to DB");
            }
		}

		/*
		 * 	conectar a la base de datos.
		 *	true 	=> conecta
		 *	false	=> no conecta
		 */
		public abstract function connect();

		/*
		 *	Ejecuta una consulta SQL. Orientado a consultas DELETE, UPDATE  e INSERT
		 *	false	=> error
		 *	id		=> si es INSERT devuelve el valor de ID
		 *	true 	=> si no se generó id pero se realizo con exito
		 */
		public abstract function command($sql);

		/*
		 *	Ejecuta una consulta SQL. Destinado a SELECT
		 *	Devuelve un Objeto YPFWQuery que representa la consulta.
		 *	NULL 	=> si se produjo un error
		 */
		public abstract function query($sql, $limit = NULL);

		/*
		 *	Ejecuta una Consulta SQL. Destinado a SELECT
		 *	false 	=>	si hay error
		 *	valor 	=> 	devuelve el valor solicitado de la consulta
		 */
		public abstract function value($sql, $getRow = false);

        public abstract function getTableFields($table);

        public abstract function sqlEscaped($str);

        public function localDateToUTC($date)
        {
            list($dia, $mes, $anio) = sscanf($date, "%d/%d/%d");
            return mktime(12, 0, 0, $mes, $dia, $anio);
        }

        public function localTimeToUTC($date)
        {
            list($hora, $minuto, $segundo) = sscanf($date, "%d:%d:%d");
            return mktime($hora, $minuto, $segundo, 1, 1, 2000);
        }

        public function localDateTimeToUTC($date)
        {
            list($dia, $mes, $anio, $hora, $minuto, $segundo) = sscanf($date, "%d/%d/%d %d:%d:%d");
            return mktime($hora, $minuto, $segundo, $mes, $dia, $anio);
        }

        public function sqlDateToUTC($date)
        {
            list($anio, $mes, $dia) = sscanf($date, "%d-%d-%d");
            return mktime(12, 0, 0, $mes, $dia, $anio);
        }

        public function sqlTimeToUTC($date)
        {
            list($hora, $minuto, $segundo) = sscanf($date, "%d:%d:%d");
            return mktime($hora, $minuto, $segundo, 1, 1, 2000);
        }

        public function sqlDateTimeToUTC($date)
        {
            list($anio, $mes, $dia, $hora, $minuto, $segundo) = sscanf($date, "%d-%d-%d %d:%d:%d");
            return mktime($hora, $minuto, $segundo, $mes, $dia, $anio);
        }

        public function utcDateToLocalDate($date)
        {
            return strftime("%d/%m/%Y", $date);
        }

        public function utcTimeToLocalTime($date)
        {
            return strftime("%H:%M:%S", $date);
        }

        public function utcDateTimeToLocalDateTime($date)
        {
            return strftime("%d/%m/%Y %H:%M:%S", $date);
        }

        public function utcDateToSqlDate($date)
        {
            return strftime("%Y-%m-%d", $date);
        }

        public function utcTimeToSqlTime($date)
        {
            return strftime("%H:%M:%S", $date);
        }

        public function utcDateTimeToSqlDateTime($date)
        {
            return strftime("%Y-%m-%d %H:%M:%S", $date);
        }

        public function localDateToSqlDate($date)
        {
            return self::utcDateToSqlDate(self::localDateToUTC($date));
        }

        public function localTimeToSqlTime($date)
        {
            return self::utcTimeToSqlTime(self::localTimeToUTC($date));
        }

        public function localDateTimeToSqlDateTime($date)
        {
            return self::utcDateTimeToSqlDateTime(self::localDateTimeToUTC($date));
        }

        public function sqlDateToLocalDate($date)
        {
            return self::utcDateToLocalDate(self::sqlDateToUTC($date));
        }

        public function sqlTimeToLocalTime($date)
        {
            return self::utcTimeToLocalTime(self::sqlTimeToUTC($date));
        }

        public function sqlDateTimeToLocalDateTime($date)
        {
            return self::utcDateTimeToLocalDateTime(self::sqlDateTimeToUTC($date));
        }
	}

	abstract class Query extends Object
	{
		protected $sqlQuery = '';
		protected $rows = 0;
		protected $cols = 0;
		protected $fieldsInfo = NULL;
		protected $dataBase = NULL;
		protected $resource = NULL;
        protected $row;
        protected $eof = NULL;

		public function __construct(DataBase $database, $sql, $res)
		{
			$this->dataBase = $database;
            $this->sql = $sql;
            $this->resource = $res;
            $this->eof = false;
            $this->row = new stdClass();

            $this->loadMetaData();
		}

		public abstract function getNext();

        protected function prepareRow()
        {
            foreach($this->fieldsInfo as $field)
            {
                switch ($field->type)
                {
                    case 'int':
                    case 'integer':
						if (is_array($this->row) && isset($this->row[$field->name]))
                            $this->row[$field->name] = ($this->row[$field->name])*1; else
                        if (is_object($this->row) && isset($this->row->{$field->name}))
                            $this->row->{$field->name} = ($this->row->{$field->name})*1;
                        break;
                    case 'tinyint':
						if (is_array($this->row) && isset($this->row[$field->name]))
                            $this->row[$field->name] = (bool)$this->row[$field->name]; else
                        if (is_object($this->row) && isset($this->row->{$field->name}))
                            $this->row->{$field->name} = (bool)$this->row->{$field->name};
                        break;
                    case 'real':
                    case 'float':
                    case 'double':
						if (is_array($this->row) && isset($this->row[$field->name]))
                            $this->row[$field->name] = (real)$this->row[$field->name]; else
                        if (is_object($this->row) && isset($this->row->{$field->name}))
                            $this->row->{$field->name} = (real)$this->row->{$field->name};
                        break;
                }
            }
        }

        public function  __get($name)
        {
            if (!$this->eof)
			{
				if (is_array($this->row) && isset($this->row[$name]))
	                return $this->row[$name];
    	        else
        	    if (is_object($this->row) && isset($this->row->{$name}))
            	    return $this->row->{$name};
            }
			else
                return NULL;
        }

        public function  __set($name, $value)
        {
			if (is_array($this->row))
	            $this->row[$name] = $value;
			else {
                if (!is_object($this->row))
                    $this->row = new stdClass();

                $this->row->{$name} = $value;
            }
        }

        public function getRows()
        {
            return $this->rows;
        }

        public function getCols()
        {
            return $this->cols;
        }

        public function isEOF()
        {
            return $this->eof;
        }

        public function getFieldInfo($index)
        {
            if (isset($this->fieldsInfo[$index]))
                return $this->fieldsInfo[$index];
            else
                return false;
        }

        public function getDataBase()
        {
            return $this->database;
        }

        public function getSql()
        {
            return $this->sqlQuery;
        }

        protected abstract function loadMetaData();
	}

?>