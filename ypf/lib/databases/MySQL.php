<?php


    class MySQLDataBase extends DataBase
	{
        public function __destruct()
        {
            mysql_close($this->db);
        }

		/*
		 * 	conectar a la base de datos.
		 *	true 	=> conecta
		 *	false	=> no conecta
		 */
		public function connect($database = null)
		{
            if ($database !== null)
                $this->dbname = $database;

            if (is_resource($this->db))
            {
                mysql_close($this->db);
                $this->db = null;
            }

			if (($this->db = mysql_connect($this->host, $this->user, $this->pass, true)) === false)
			{
				Logger::framework('ERROR:DB', mysql_error());
				return false;
			}

			if (!mysql_select_db($this->dbname, $this->db))
			{
				Logger::framework('ERROR:DB', mysql_error($this->db));
				return false;
			}
			//mysql_query("SET CHARACTER SET 'latin1'", $this->db);
			//mysql_query("SET NAMES latin1", $this->db);

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

			$res = mysql_query($sql);

			if (!$res)
			{
                Logger::framework('ERROR:SQL', sprintf("%s; '%s'", mysql_error($this->db), $sql));
				return false;
			} else
                Logger::framework('SQL', $sql);

            $id = mysql_insert_id($this->db);
			if ((strtoupper(substr($sql, 0, 6)) == "INSERT") && ($id > 0))
                return $id;
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

			if (!$res = mysql_query($sql))
			{
                Logger::framework('ERROR:SQL', sprintf("%s; '%s'", mysql_error($this->db), $sql));
				return false;
			} else
                Logger::framework('SQL', $sql);

			$q = new MySQLQuery($this, $sql, $res);
			return $q;
		}

		/*
		 *	Ejecuta una Consulta SQL. Destinado a SELECT
		 *	false 	=>	si hay error
		 *	valor 	=> 	devuelve el valor solicitado de la consulta
		 */
		public function value($sql, $getRow = false)
		{
			if (!$res = mysql_query($sql))
			{
                Logger::framework('ERROR:SQL', sprintf("%s; '%s'", mysql_error($this->db), $sql));
				return false;
			} else
                Logger::framework('SQL', $sql);

			$row = mysql_fetch_assoc($res);

			if (!$row)
				return false;

            if ($getRow)
                return $row;
            else
                return array_shift($row);
		}

        public function getTableFields($table)
        {
            $fields = array();

            $query = $this->query(sprintf('SHOW COLUMNS FROM %s', $table));

            if ($query)
                while ($row = $query->getNext())
                {
                    $obj = new Object();

                    $obj->Name = $row['Field'];
                    $obj->Type = $row['Type'];

                    if (($pos = strpos($obj->Type, '(')))
                        $obj->Type = substr ($obj->Type, 0, $pos);

                    $obj->Key = ($row['Key'] == 'PRI');
                    $obj->Null = ($row['Null'] == 'YES');
                    $obj->Default = $row['Default'];

                    $fields[$row['Field']] = $obj;
                }

            return $fields;
        }

        public function sqlEscaped($str)
        {
            return mysql_real_escape_string($str, $this->db);
        }
	}

	class MySQLQuery extends Query implements Iterator
	{
        private $_iteratorKey = null;

        public function __construct(DataBase $database, $sql, $res)
        {
            parent::__construct($database, $sql, $res);
            $this->rows = mysql_num_rows($this->resource);
			$this->cols = mysql_num_fields($this->resource);
        }

        public function __destruct()
        {
            mysql_free_result($this->resource);
        }

        protected function loadMetaData()
        {
			$this->fieldsInfo = array();

			for ($i = 0; $i < $this->cols; $i++)
            {
                $obj = new Object();
                $info = mysql_fetch_field($this->resource, $i);

                $obj->Name = $info->name;
                $obj->Type = $info->type;
                $obj->Key = ($info->primary_key == 1);
                $obj->Null = !$obj->Key;
                $obj->Default = null;

				$this->fieldsInfo[$obj->Name] = $obj;
            }

		}

		public function getNext()
		{
			$this->row = mysql_fetch_assoc($this->resource);
            $this->eof = ($this->row === false);
			return $this->row;
		}

		public function getNextObject()
		{
			$this->row = mysql_fetch_object($this->resource);
            $this->eof = ($this->row === false);
			return $this->row;
		}

        public function current()
        {
            return $this->row;
        }

        public function key()
        {
            return $this->_iteratorKey;
        }

        public function next()
        {
            $this->getNextObject();
            if ($this->_iteratorKey === null)
                $this->_iteratorKey = 0;
            else
                $this->_iteratorKey++;
        }

        public function rewind()
        {
            if ($this->_iteratorKey !== null)
            {
                $this->eof = true;
                return;
            }
            $this->next();
        }

        public function valid()
        {
            return !$this->eof;
        }
	}
?>
