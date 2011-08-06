<?php

    class SQLite2DataBase extends DataBase
	{
        public $exists;

        public function __construct($dbname, $connect = true)
        {
            parent::__construct($dbname, null, null, null, $connect);
        }

		/*
		 * 	conectar a la base de datos.
		 *	true 	=> conecta
		 *	false	=> no conecta
		 */
		public function connect()
		{
            $this->exists = file_exists($this->dbname);

			if (($this->db = sqlite_open($this->dbname)) === false)
			{
				Application::log('ERROR:DB', 'Coudn\'t open/create database');
				return false;
			}

			$this->connected = true;
			return true;
		}

		/*
		 *	Ejecuta una consulta SQL. Orientado a consultas DELETE, UPDATE  e INSERT
		 *	false	=> error
		 *	id		=> si es INSERT devuelve el valor de ID
		 *	true 	=> si no se generó id pero se realizo con exito
		 */
		public function command($sql)
		{
			$sql = trim($sql);

			$res = sqlite_exec($this->db, $sql, $error);

			if (!$res)
			{
                Application::log('ERROR:DB', sprintf("%s; '%s'", $error, $sql));
				return false;
			} else
                Application::log('SQL:DB', $sql);

			if (strtoupper(substr($sql, 0, 6)) == "INSERT")
				return sqlite_last_insert_rowid($this->db);
			else
				return true;
		}

		/*
		 *	Ejecuta una consulta SQL. Destinado a SELECT
		 *	Devuelve un Objeto YPFWQuery que representa la consulta.
		 *	NULL 	=> si se produjo un error
		 */
		public function query($sql, $limit = NULL)
		{
            if ($limit !== NULL)
            {
                if (is_array($limit))
                    $sql .= sprintf(" LIMIT %d, %d", $limit[0], $limit[1]);
                else
                    $sql .= sprintf(" LIMIT %d", $limit);
            }

			if (!$res = sqlite_query($this->db, $sql, 1, $error))
			{
                if ($error)
                    Application::log('ERROR:DB', sprintf("%s; '%s'", $error, $sql));
                else
    				Application::log('ERROR:DB', sprintf("%s; '%s'", sqlite_error_string(sqlite_last_error ($this->db)), $sql));
				return false;
			} else
                Application::log('SQL:DB', $sql);

			$q = new SQLite2Query($this, $sql, $res);
			return $q;
		}

		/*
		 *	Ejecuta una Consulta SQL. Destinado a SELECT
		 *	false 	=>	si hay error
		 *	valor 	=> 	devuelve el valor solicitado de la consulta
		 */
		public function value($sql, $getRow = false)
		{
			if (!$res = sqlite_query($this->db, $sql, 2, $error))
			{
                if ($error)
                    Application::log('ERROR:DB', sprintf("%s; '%s'", $error, $sql));
                else
    				Application::log('ERROR:DB', sprintf("%s; '%s'", sqlite_error_string(sqlite_last_error ($this->db)), $sql));
				return false;
			} else
                Application::log('SQL:DB', $sql);


            if (sqlite_num_rows($res) > 0)
            {
                if ($getRow)
                    return sqlite_fetch_array($res, 1);
                else
                    return sqlite_fetch_single($res);
            }
            else
                return false;
		}

        public function getTableFields($table)
        {
            $fields = array();

            $query = $this->query(sprintf('PRAGMA table_info(%s)', $table));
            while ($row = $query->getNext())
            {
                $obj = new Object();

                $obj->Name = $row['name'];
                $obj->Type = strtolower($row['type']);

                if (($pos = strpos($obj->Type, '(')))
                    $obj->Type = substr ($obj->Type, 0, $pos);

                $obj->Key = ($row['pk'] == '1');
                $obj->Null = ($row['notnull'] == '1');
                $obj->Default = $row['dflt_value'];

                $fields[$obj->Name] = $obj;
            }

            return $fields;
        }

        public function sqlEscaped($str)
        {
            return sqlite_escape_string($str);
        }
	}

    class SQLite2Query extends Query
	{
        protected function loadMetaData()
        {
            $this->rows = sqlite_num_rows($this->resource);
			$this->cols = sqlite_num_fields($this->resource);
			$this->fieldsInfo = array();

			for ($i = 0; $i < $this->cols; $i++)
            {
                $field = new Object();
                $field->name = sqlite_field_name($this->resource, $i);
                $field->type = 'varchar';
            }
		}

		public function getNext()
		{
			$this->row = sqlite_fetch_array($this->resource, 1);
            $this->eof = ($this->row === false);
			return $this->row;
		}

		public function getNextObject()
		{
			$this->row = sqlite_fetch_object($this->resource);
            $this->eof = ($this->row === false);
			return $this->row;
		}
	}

?>
