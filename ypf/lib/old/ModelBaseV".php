<?php
    class ModelBase
    {
        //Model Engine Data
        private static $_instances = array();

        //Specific model data
        public static $_tableName = null;
        public static $_keyFields = 'id';
        public static $_database = null;
        public static $_metaData = null;
        public static $_dbCharset = null;
        public static $_relations = array();
        public static $_validations = array();
        
        public static $_beforeLoad = array();
        public static $_beforeSave = array();
        public static $_beforeDelete = array();
        
        public static $_afterLoad = array();
        public static $_afterSave = array();
        public static $_afterDelete = array();
        
        //Specific instance data
        protected $_data = array();
        protected $_modifiedData = array();
        protected $_modified = false;
        protected $_error = array();

        //Data access
        protected function  __construct() 
        {
            if (get_called_class() != 'ModelBase')
            {
                foreach (self::$_metaData as $field)
                {
                    $this->_data[$field->Name] = $field->Default;
                    $this->_modifiedData[$field->Name] = false;
                }
            }
        }
        
        public function __get($name) 
        {
            if (($name[0] == '_') && (isset($this->{$name})))
                return $this->{$name};
            if (isset($this->_data[$name]))
                return $this->_data[$name];
            elseif (isset(self::$_relations[$name]))
                return self::processRelation($name);
            else
                return null;
        }
        
        public function __set($name, $value)
        {
            if ($name[0] == '_')
                $this->{$name} = $value;
            elseif (isset(self::$_metaData[$name]))
            {
                $this->_data[$name] = $value;
                $this->_modified = true;
                $this->_modifiedData[$name] = true;
            } 
            elseif (isset(self::$_relations[$name]))
            {
                self::processRelation($name);
                self::$_relations[$name]['relation']->set($value);
            }
        }
        
        public function __isset($name) {
            return (isset($this->_data[$name]) | isset($this->_relations[$name]));
        }
        
        public function __toString() 
        {
            $str = sprintf('<%s ', get_class($this));
            foreach ($this->_data as $k=>$v)
                $str .= sprintf('%s: %s', $k, var_export($v, true)); 
            
            return $str;
        }
        
        public function setAttributes($row)
        {
            foreach ($row as $key=>$value)
                $this->setAttribute($key, $value);
        }
        
        public function getSerializedKey($stringify=true)
        {
            $key = array();
            if (is_array($this->_keyFields))
                foreach ($this->_keyFields as $k)
                    $key[] = $this->encodeKey($this->__get($k));
            else 
                $key[] = $this->encodeKey($this->__get($this->_keyFields));
            
            return ($stringify)? implode('|', $key): $key;
        }
        
        public function getRelation($name)
        {
            if (isset($this->_relations[$name]))
                return $this->processRelation($name, false);
        }

        public function getWhereCond($values = null)
        {
            $sql = "";

            if (is_array($this->_keyFields))
            {
                foreach ($this->_keyFields as $field)
                    $sql .= sprintf("%s.%s = %s and ", $this->_tableName, $field, $this->getFieldSql($field, $values));
                $sql = substr($sql, 0, -4);
            } else
                $sql = sprintf("%s.%s = %s", $this->_tableName, $this->_keyFields, $this->getFieldSql($this->_keyFields, $values));
            
            return $sql;
        }
        
        public function getError($field=null)
        {
            if ($field === null)
                return $this->_error;
            elseif (isset($this->_error[$field]))
                return $this->_error[$field];
            else
                return null;
        }
        
        public function isNew()
        {
            return ($this->_database->value(sprintf("SELECT COUNT(*) %s", $this->getSelectClause('WHERE '.$this->getWhereCond()))) == 0);
        }

        //Data looking methods
        public function find($id)
        {
            $id = $this->decodeKey($id);
            
            $sql = sprintf("SELECT %s.* %s LIMIT 0,1", $this->_tableName, $this->getSelectClause(" WHERE ".$this->getWhereCond($id)));
            $query = $this->_database->query($sql);

            if (!$query)
                return false;

            $row = $query->getNext();

            if (!$row)
                return false;

            foreach($this->_beforeLoad as $action)
                call_user_func (array($this, $action), $row);
            $this->setAttributes($row);
            foreach($this->_afterLoad as $action)
                call_user_func (array($this, $action));

            return true;
        }
        
        public function findWhere($where)
        {
            $sql = sprintf("SELECT %s.* %s LIMIT 0,1", $this->_tableName, $this->getSelectClause($where));
            $query = $this->_database->query($sql);

            if (!$query)
                return false;

            $row = $query->getNext();

            if (!$row)
                return false;

            foreach($this->_beforeLoad as $action)
                call_user_func (array($this, $action), $row);
            $this->setAttributes($row);
            foreach($this->_afterLoad as $action)
                call_user_func (array($this, $action));

            return true;
        }
        
        public function select($where = '', $limit = null)
        {
            $sql = sprintf("SELECT %s.* %s", $this->_tableName, $this->getSelectClause($where));
            $query = $this->_database->query($sql, $limit);

            if (!$query)
                return false;

            $result = array();

            while ($row = $query->getNext())
            {
                $obj = clone $this;
                foreach($this->_beforeLoad as $action)
                    call_user_func (array($obj, $action), $row);
                $obj->setAttributes($row);
                foreach($this->_afterLoad as $action)
                    call_user_func (array($obj, $action));
                $result[] = $obj;
            }

            return $result;
        }
        
        public function all()
        {
            return $this->select();
        }
        
        public function count($where = '')
        {
            $sql = sprintf("SELECT SUM(count) FROM (SELECT COUNT(*) AS count %s) tmpcount", $this->getSelectClause($where));
            return $this->_database->value($sql);
        }

        public function save()
        {
            if (!$this->_modified)
                return true;
            
            foreach($this->_beforeSave as $action)
                if (call_user_func(array($this, $action)) === false)
                    return false;
            
            if ($this->isNew())
            {
                $values = array();
                foreach ($this->_metaData as $field)
                    $values[] = $this->getFieldSql($field->Name);

                $sql = sprintf("INSERT INTO %s (%s) VALUES (%s)", 
                            $this->_tableName, 
                            implode(', ', array_keys($this->_metaData)),
                            implode(', ', $values));
            } else
            {
                $sql = sprintf("UPDATE %s SET ", $this->_tableName);
                foreach ($this->_metaData as $field)
                    if (isset($this->_modifiedData[$field->Name]))
                        $sql .= sprintf("%s = %s, ", $field->Name, $this->getFieldSql($field->Name));

                $sql = substr($sql, 0, -2);
                $sql .= ' WHERE '.$this->getWhereCond();
            }
            $result = $this->_database->command($sql);
            
            if (is_int($result) )
                $this->{$this->_keyFields} = $result;

            foreach($this->_afterSave as $action)
                call_user_func(array($this, $action), $result);
            
            $this->_modified = false;
            $this->_modifiedData = array_combine(array_keys($this->_metaData), array_fill(0, count($this->_metaData), false));
            
            return is_int($result)? true: $result;
        }

        public function delete()
        {
            foreach($this->_beforeDelete as $action)
                call_user_func(array($this, $action));
            
            $sql = sprintf("DELETE %s", $this->getSelectClause('WHERE '.$this->getWhereCond()));
            $result = $this->_database->command($sql);

            foreach($this->_afterDelete as $action)
                call_user_func(array($this, $action), $result);
            
            return $result;
        }
        
        protected function getSelectClause($where = '')
        {
            return sprintf(" FROM %s %s", $this->_tableName, $where);
        }

        protected function processRelation($name, $get=true)
        {
            if (!isset($this->_relations[$name]['object']) || !is_object($this->_relations[$name]['object']))
                $this->_relations[$name]['object'] = new ModelRelation($name, $this, $this->_relations[$name]);
            
            if (!$get)
                return $this->_relations[$name]['object'];
                
            if ($this->_relations[$name]['object']->isOne())
                return $this->_relations[$name]['object']->get();
            else
                return $this->_relations[$name]['object'];
        }

        protected function setAttribute($nombre, $valor)
        {
            $this->_modified = true;
            if (isset ($this->_data[$nombre]))
                $this->_modifiedData[$nombre] = true;

            if (isset($this->_metaData[$nombre]) && $this->_dbCharset != "UTF-8")
                $this->_data[$nombre] = iconv($this->_dbCharset, 'UTF-8', $valor);
            else
                $this->_data[$nombre] = $valor;
        }

        protected function getFieldSql($field, $values = null)
        {
            if (!isset($this->_metaData[$field]))
                return '';

            if ($values === null)
                $value = $this->{$field};
            elseif (is_array($values))
                $value = $values[$field];
            else
                $value = $values;
            
            if ($value === NULL)
                return 'NULL';
            
            switch ($this->_metaData[$field]->Type)
            {
                case 'integer':
                case 'int':
                case 'tinyint':
                    return sprintf("%d", $value);

                case 'double':
                case 'float':
                    return sprintf("%F", $value);

                case 'varchar':
                case 'mediumtext':
                case 'text':
                case 'date':
                case 'datetime':
                case 'string':
                default:
                    if ($this->_dbCharset != 'UTF-8')
                        return sprintf("'%s'", $this->_database->sqlEscaped(iconv('UTF-8', $this->_dbCharset, $value)));
                    else
                        return sprintf("'%s'", $this->_database->sqlEscaped($value));
            }
        }
        
        protected function encodeKey($key)
        {
            $result = "";
            for($i = 0; $i < strlen($key); $i++)
            {
                if (preg_match('/[a-zA-Z_0-9\\-]/', $key[$i]) == 0)
                {
                    $result.='%'.sprintf("%02x", ord($key[$i]));
                } else 
                    $result.=$key[$i];
            }
            
            return $result; 
        }
        
        protected function decodeKey($key)
        {
            if (is_array($key))
                return $key;
            
            $key = explode('|', $key);
            
            if (count($key) == 1)
                return urldecode ($key[0]);
            elseif (count($this->_keyFields) <= count($key))
            {
                $result = array();
                for ($i = 0; $i < count($this->_keyFields); $i++)
                    $result[$this->_keyFields[$i]] = urldecode ($key[$i]);
            
                return $result;
            } 
            
            return null;                
        }
        
        private static function loadDataStructure () 
        {
            if (self::$_database !== null)
                return;
            
            self::$_database = Application::$app->database;
            
            if (self::$_tableName === null)
                self::$_tableName = strtolower(get_called_class());
            
            if (self::$_keyFields === null)
                self::$_keyFields = 'id';

            self::$_metaData = self::$_database->getTableFields(self::$_tableName);
        }
    }
?>
