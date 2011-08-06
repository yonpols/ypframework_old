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
		public function connect()
		{
			if (($this->db = mysql_connect($this->host, $this->user, $this->pass, true)) === false)
			{
				Application::log('ERROR:DB', mysql_error());
				return false;
			}

			if (!mysql_select_db($this->dbname, $this->db))
			{
				Application::log('ERROR:DB', mysql_error($this->db));
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
                Application::log('ERROR:DB', sprintf("%s; '%s'", mysql_error($this->db), $sql));
				return false;
			}

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
				Application::log('ERROR:DB', sprintf("%s; '%s'", mysql_error($this->db), $sql));
				return false;
			}

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
				Application::log('ERROR:DB', sprintf("%s; '%s'", mysql_error($this->db), $sql));
				return false;
			}

			$row = mysql_fetch_row($res);

			if (!$row)
				return false;

            if ($getRow)
                return $row;
            else
                return $row[0];
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

	class MySQLQuery extends Query
	{
        public function __destruct()
        {
            mysql_free_result($this->resource);
        }

        protected function loadMetaData()
        {
            $this->rows = mysql_num_rows($this->resource);
			$this->cols = mysql_num_fields($this->resource);
			$this->fieldsInfo = array();

			for ($i = 0; $i < $this->cols; $i++)
				$this->fieldsInfo[$i] = mysql_fetch_field($this->resource, $i);
		}

		public function getNext()
		{
			$this->row = mysql_fetch_assoc($this->resource);
            $this->eof = ($this->row === false);
            $this->prepareRow();
			return $this->row;
		}

		public function getNextObject()
		{
			$this->row = mysql_fetch_object($this->resource);
            $this->eof = ($this->row === false);
            $this->prepareRow();
			return $this->row;
		}
	}
?>
