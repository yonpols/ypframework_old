<?php
    class ModelBase extends Object implements Iterator
    {
        protected $_tableName = null;
        protected $_keyFields = 'id';
        protected $_database = null;
        protected $_metaData = null;
        protected $_dbCharset = null;

        protected $_data = array();
        protected $_modifiedData = array();
        protected $_modified = false;
        protected $_relations = array();

        protected $_beforeLoad = array();
        protected $_beforeSave = array();
        protected $_beforeDelete = array();

        protected $_afterLoad = array();
        protected $_afterSave = array();
        protected $_afterDelete = array();

        protected $_error = array();

        private static $_instances = array();

        private $_iteratorCurrentKey = null;
        private $_iteratorCurrentIndex = null;
        private $_iteratorKeys = null;

        //Data access
        public function  __construct($id = null)
        {
            $this->_database = Application::$app->database;

            if ($this->_tableName === null)
                $this->_tableName = strtolower(get_class($this));

            if ($this->_keyFields === null)
                $this->_keyFields = 'id';

            if (get_class($this) != 'ModelBase')
            {
                $this->_metaData = $this->_database->getTableFields($this->_tableName);
                foreach ($this->_metaData as $field)
                {
                    $this->_data[$field->Name] = null;
                    $this->_modifiedData[$field->Name] = false;
                }
            }

            if ($id != null)
                $this->find($id);
        }

        public function __get($name)
        {
            if (($name[0] == '_') && (isset($this->{$name})))
                return $this->{$name};
            if (isset($this->_data[$name]))
                return $this->_data[$name];
            elseif (isset($this->_relations[$name]))
                return $this->processRelation($name);
            else
                return null;
        }

        public function __set($name, $value)
        {
            if ($name[0] == '_')
                $this->{$name} = $value;
            elseif (isset($this->_metaData[$name]))
            {
                $this->_data[$name] = $value;
                $this->_modified = true;
                $this->_modifiedData[$name] = true;
            }
            elseif (isset($this->_relations[$name]))
            {
                $this->processRelation($name);
                $this->_relations[$name]['object']->set($value);
            }
        }

        public function __isset($name) {
            return (isset($this->_data[$name]) | isset($this->_relations[$name]));
        }

        public function setAttributes($row, $ignorePrefix = null)
        {
            if ($ignorePrefix == null)
                foreach ($row as $key=>$value)
                    $this->setAttribute($key, $value);
            else
                foreach ($row as $key=>$value)
                {
                    $key = substr($key, strlen($ignorePrefix)+1);
                    $this->setAttribute($key, $value);
                }
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
            $this->loadFromRow($row);
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
            $this->loadFromRow($row);
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
                $obj->loadFromRow($row);
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

        public function loadFromRow($row, $ignorePrefix = null)
        {
            if ($ignorePrefix == null)
                foreach ($row as $key=>$value)
                    $this->setFieldSql($key, $value);
            else
                foreach ($row as $key=>$value)
                {
                    $key = substr($key, strlen($ignorePrefix)+1);
                    $this->setFieldSql($key, $value);
                }

            $this->_modified = false;
            $this->_modifiedData = array_fill_keys(array_keys($this->_metaData), false);
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

        protected function setAttribute($name, $value)
        {
            if (isset($this->_relations[$name]))
            {
                $this->processRelation($name);
                $this->_relations[$name]['object']->set($value);
            } else
            {
                $this->_modified = true;

                if (isset ($this->_data[$name]))
                    $this->_modifiedData[$name] = true;

                if (isset($this->_metaData[$name]) && $this->_dbCharset != "UTF-8")
                    $this->_data[$name] = iconv($this->_dbCharset, 'UTF-8', $value);
                else
                    $this->_data[$name] = $value;
            }
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
                case 'real':
                    return sprintf("%F", $value);

                case 'date':
                    return sprintf("'%s'", $this->_database->sqlEscaped(DataBase::localDateToSqlDate($value)));
                case 'time':
                    return sprintf("'%s'", $this->_database->sqlEscaped(DataBase::localTimeToSqlTime($value)));
                case 'datetime':
                    return sprintf("'%s'", $this->_database->sqlEscaped(DataBase::localDateTimeToSqlDateTime($value)));

                case 'varchar':
                case 'mediumtext':
                case 'text':
                case 'string':
                default:
                    if ($this->_dbCharset != 'UTF-8')
                        return sprintf("'%s'", $this->_database->sqlEscaped(iconv('UTF-8', $this->_dbCharset, $value)));
                    else
                        return sprintf("'%s'", $this->_database->sqlEscaped($value));
            }
        }

        protected function setFieldSql($field, $value)
        {
            if (!isset($this->_metaData[$field]))
                return '';

            if ($value === NULL)
            {
                $this->_data[$field] = $value;
                return;
            }

            switch ($this->_metaData[$field]->Type)
            {
                case 'integer':
                case 'int':
                case 'tinyint':
                    $this->_data[$field] = $value*1;
                    return;

                case 'double':
                case 'float':
                case 'real':
                    $this->_data[$field] = $value*1.0;
                    return;

                case 'date':
                    $this->_data[$field] = DataBase::sqlDateToLocalDate($value);
                    return;

                case 'time':
                    $this->_data[$field] = DataBase::sqlTimeToLocalTime($value);
                    return;

                case 'datetime':
                    $this->_data[$field] = DataBase::sqlDateTimeToLocalDateTime($value);
                    return;

                case 'varchar':
                case 'mediumtext':
                case 'text':
                case 'string':
                default:
                    if ($this->_dbCharset != 'UTF-8')
                        $this->_data[$field] = iconv($this->_dbCharset, 'UTF-8', $value);
                    else
                        $this->_data[$field] = $value;
                    return;
            }
        }

        protected function encodeKey($key)
        {
            $result = "";
            $key = $key.'';

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
                $keyFields = is_array($this->_keyFields)? $this->_keyFields: array($this->_keyFields);

                $result = array();
                for ($i = 0; $i < count($keyFields); $i++)
                    $result[$keyFields[$i]] = urldecode ($key[$i]);

                return $result;
            }

            return null;
        }

        // ----------- Iterator Implementation --------------------------------
        public function current()
        {
            if ($this->_iteratorCurrentIndex == null)
                $this->next();

            return $this->_data[$this->_iteratorCurrentKey];
        }

        public function key()
        {
            if ($this->_iteratorCurrentIndex == null)
                $this->next();

            return $this->_iteratorCurrentKey;
        }

        public function next()
        {
            $this->_iteratorCurrentIndex++;

            if ($this->_iteratorCurrentIndex < count($this->_data))
            {
                if ($this->_iteratorKeys === null)
                    $this->_iteratorKeys = array_keys($this->_data);

                $this->_iteratorCurrentKey = $this->_iteratorKeys[$this->_iteratorCurrentIndex];
            }
        }

        public function rewind()
        {
            $this->_iteratorCurrentIndex = null;
        }

        public function valid()
        {
            return ($this->_iteratorCurrentIndex < count($this->_data));
        }
    }
?>
