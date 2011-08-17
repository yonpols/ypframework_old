<?php
    class ModelBase extends Base implements Iterator
    {
        //Variables de ModelBase, no pueden redefinirse.
        protected static $__cache = null;
        protected static $__modelParams = null;
        //----------------------------------------------------------------------

        //protected static $_validations = array();
        //----------------------------------------------------------------------

        //Variables de instancia
        protected $_modelData = array();
        protected $_modelFieldModified = array();
        protected $_modelModified = false;
        protected $_modelErrors = array();
        protected $_modelName = null;
        protected $_modelParams = null;
        //----------------------------------------------------------------------

        //Métodos de ModelBase
        public static function getModelParams($model)
        {
            if (!isset(self::$__modelParams->{$model}))
                self::initializeModel ($model);
            return (isset(self::$__modelParams->{$model})? self::$__modelParams->{$model}: null);
        }

        public static function find($id, $instance = null, $rawId = false)
        {
            $modelName = get_called_class();
            $modelParams = self::getModelParams($modelName);

            if (is_array($id))
            {
                $null = true;
                $key = array();

                foreach ($modelParams->keyFields as $k)
                {
                    if (!isset($id[$k]))
                        return null;
                    $key[] = self::encodeKey($id[$k]);
                }

                $str_key = implode('|', $key);
            }
            else
                $str_key = $id;

            if (isset(self::$__cache->{$modelName}) && array_key_exists($str_key, self::$__cache->{$modelName}))
                return self::$__cache->{$modelName}[$str_key];
            else
                self::$__cache->{$modelName} = array();

            $id = self::decodeKey($id, $modelParams);
            $aliasPrefix = ($modelParams->aliasName!='')? $modelParams->aliasName.'.': '';

            //Preparar condiciones
            $conditions = $modelParams->sqlConditions;
            if (is_array($id))
            {
                foreach ($id as $key=>$value)
                    if (array_search ($key, $modelParams->keyFields) !== false)
                        $conditions[] = sprintf('(%s%s = %s)', $aliasPrefix, $key, self::getFieldSQLRepresentation($key, $value, $modelParams, $rawId));
            } elseif (count($modelParams->keyFields) == 1)
                $conditions[] = sprintf('(%s%s = %s)', $aliasPrefix, $modelParams->keyFields[0], self::getFieldSQLRepresentation($modelParams->keyFields[0], $id, $modelParams), $rawId);
            else
                throw new ErrorDataModel ($modelName, 'find(): invalid number of key values');

            $sql = $modelParams->modelQuery->fields($modelParams->aliasName.'.*')->select($conditions)->limit(1)->getSqlQuery();
            $query = self::$database->query($sql);
            $row = $query->getNext();

            if (!$row)
                return false;
            else
            {
                if ($instance === null) $instance = eval(sprintf('return new %s();', $modelName));
                $instance->loadFromRecord($row, $query);
                self::$__cache->{$modelName}[$str_key] = $instance;

                if (count(self::$__cache->{$modelName}) > YPF_MODEL_CACHE_MAX)
                    array_splice (self::$__cache->{$modelName}, 0, count(self::$__cache->{$modelName})-YPF_MODEL_CACHE_MAX);

                return $instance;
            }
        }

        // ----------- ModelQuery Implementation--------------------------------
        public static function fields($fields)
        {
            $modelParams = self::getModelParams(get_called_class());
            return $modelParams->modelQuery->fields($fields);
        }

        public static function all()
        {
            $modelParams = self::getModelParams(get_called_class());
            return $modelParams->modelQuery->all();
        }

        public static function count($sqlConditions = null, $sqlGrouping = null)
        {
            $modelParams = self::getModelParams(get_called_class());
            return $modelParams->modelQuery->count($sqlConditions, $sqlGrouping);
        }

        public static function first()
        {
            $modelParams = self::getModelParams(get_called_class());
            return $modelParams->modelQuery->first();
        }

        public static function groupBy($sqlGrouping)
        {
            $modelParams = self::getModelParams(get_called_class());
            return $modelParams->modelQuery->groupBy($sqlGrouping);
        }

        public static function last()
        {
            $modelParams = self::getModelParams(get_called_class());
            return $modelParams->modelQuery->last();
        }

        public static function limit($limit)
        {
            $modelParams = self::getModelParams(get_called_class());
            return $modelParams->modelQuery->limit($limit);
        }

        public static function orderBy($sqlOrdering)
        {
            $modelParams = self::getModelParams(get_called_class());
            return $modelParams->modelQuery->orderBy($sqlOrdering);
        }

        public static function select($sqlConditions, $sqlGrouping = array(), $sqlOrdering = array(), $sqlLimit = null)
        {
            $modelParams = self::getModelParams(get_called_class());
            return $modelParams->modelQuery->select($sqlConditions, $sqlGrouping, $sqlOrdering, $sqlLimit);
        }

        //Métodos de Instancia
        public function __construct($id=null)
        {
            $this->_modelName = get_class($this);

            self::initializeModel($this->_modelName);
            $this->_modelParams = self::$__modelParams->{$this->_modelName};

            if ($id !== null)
            {
                throw new ErrorDataModel($this->_modelName, sprintf('Couldn\'t load intance with id "%s"', $id));
            } else {
                $this->_modelModified = false;
                $this->_modelFieldModified = array_fill_keys(array_keys($this->_modelParams->tableMetaData), false);
                $this->_modelData = array();

                foreach($this->_modelParams->tableMetaData as $field=>$metadata)
                $this->_modelData[$field] = $metadata->Default;
                foreach($this->_modelParams->transientFields as $field=>$default)
                $this->_modelData[$field] = $default;
            }
        }

        public function __get($name)
        {
            if (isset($this->_modelParams->tableMetaData[$name]))
                return $this->_modelData[$name];
            elseif (isset($this->_modelParams->relations[$name]))
            {
                if (!isset($this->_modelParams->relationObjects->{$name}))
                    $this->_modelParams->relationObjects->{$name} = new ModelBaseRelation($this->_modelName, $name, $this->_modelParams->relations[$name]);

                return $this->_modelParams->relationObjects->{$name}->get($this);
            }
            elseif (array_key_exists($name, $this->_modelParams->transientFields))
                return $this->_modelData[$name];
            elseif (isset($this->_modelParams->customQueries[$name]))
                return $this->_modelParams->modelQuery->{$name};
            else
                return null;
        }

        public function __set($name, $value)
        {
            if (isset($this->_modelParams->tableMetaData[$name]))
            {
                $this->_modelData[$name] = $value;
                $this->_modelModified = true;
                $this->_modelFieldModified[$name] = true;
            }
            elseif (isset($this->_modelParams->relations[$name]))
            {
                if (!isset($this->_modelParams->relationObjects->{$name}))
                    $this->_modelParams->relationObjects->{$name} = new ModelBaseRelation($this->_modelName, $name, $this->_modelParams->relations[$name]);

                $this->_modelParams->relationObjects->{$name}->set($this, $value);
            }
            elseif (array_key_exists($name, $this->_modelParams->transientFields))
                $this->_modelData[$name] = $value;
        }

        public function __isset($name)
        {
            return (isset($this->_modelParams->tableMetaData[$name]) |
                    isset($this->_modelParams->relations[$name]) |
                    array_key_exists($name, $this->_modelParams->transientFields));
        }

        public function __unset($name)
        {
            if (isset($this->_modelParams->tableMetaData[$name]) | array_key_exists($name, $this->_modelParams->transientFields))
            {
                $this->_modelData[$name] = $this->_modelParams->tableMetaData[$name]->Default;
                $this->_modelModified = true;
                $this->_modelFieldModified[$name] = true;
            }
        }

        public function getSerializedKey($stringify=true)
        {
            $null = true;
            $key = array();

            foreach ($this->_modelParams->keyFields as $k)
            {
                $v = $this->__get($k);
                $null = $null && ($v === null);
                $key[] = self::encodeKey($v);
            }

            return $null? null: (($stringify)? implode('|', $key): $key);
        }

        public function getAttributes()
        {
            return $this->_modelData;
        }

        public function setAttributes($attributes)
        {
            foreach ($attributes as $key=>$value)
                $this->__set ($key, $value);
        }

        public function getError($field=null)
        {
            if ($field === null)
                return $this->_modelErrors;
            elseif (isset($this->_modelErrors[$field]))
                return $this->_modelErrors[$field];
            else
                return null;
        }

        public function save()
        {
            if (!$this->_modelModified)
                return false;

            $result = true;
            foreach($this->_modelParams->beforeSave as $function)
                if (is_callable (array($this, $function)))
                {
                    if (call_user_func(array($this, $function)) === false)
                        $result = false;
                }
                else
                    throw new ErrorNoCallback(get_class($this), $function);
            if (!$result)
                return false;

            $fieldNames = array_keys($this->_modelParams->tableMetaData);
            if ($this->isNew())
            {
                $fieldValues = array();

                foreach ($fieldNames as $field)
                    $fieldValues[] = self::getFieldSQLRepresentation($field, $this->__get($field), $this->_modelParams);

                $sql = sprintf("INSERT INTO %s (%s) VALUES(%s)", $this->_modelParams->baseTableName,
                    implode(', ', $fieldNames), implode(', ', $fieldValues));

                $result = self::$database->command($sql);

                if (is_int($result) && (count($this->_modelParams->keyFields)==1))
                {
                    $this->_modelData[$this->_modelParams->keyFields[0]] = $result;
                    $result = true;
                }
            } else
            {
                $fieldAssigns = array();

                foreach ($fieldNames as $field)
                    if ($this->_modelFieldModified[$field])
                        $fieldAssigns[] = sprintf("%s = %s", $field, self::getFieldSQLRepresentation($field, $this->__get($field), $this->_modelParams));

                $sql = sprintf("UPDATE %s SET %s WHERE %s", $this->_modelParams->baseTableName,
                    implode(', ', $fieldAssigns), implode(' AND ', $this->getSQlIdConditions(false)));

                $result = self::$database->command($sql);
            }

            if ($result === true)
            {
                $this->_modelModified = false;
                $this->_modelFieldModified = array_fill_keys($fieldNames, false);

                $result = true;
                foreach($this->_modelParams->afterSave as $function)
                    if (is_callable (array($this, $function)))
                    {
                        if (call_user_func(array($this, $function)) === false)
                            $result = false;
                    }
                    else
                        throw new ErrorNoCallback(get_class($this), $function);


                if (!$result)
                    return false;
            }

            return $result;
        }

        public function delete()
        {
            $result = true;
            foreach($this->_modelParams->beforeDelete as $function)
                if (is_callable (array($this, $function)))
                {
                    if (call_user_func(array($this, $function)) === false)
                        $result = false;
                }
                else
                    throw new ErrorNoCallback(get_class($this), $function);

            if (!$result)
                return false;

            $sql = sprintf("DELETE FROM %s WHERE %s",
                $this->_modelParams->baseTableName, implode(' AND ', $this->getSQlIdConditions(false)));

            $result = self::$database->command($sql);

            foreach($this->_modelParams->afterDelete as $function)
                if (is_callable (array($this, $function)))
                {
                    if (call_user_func(array($this, $function)) === false)
                        $result = false;
                }
                else
                    throw new ErrorNoCallback(get_class($this), $function);

            return $result;
        }

        public function isNew()
        {
            $sql = sprintf("SELECT COUNT(*) FROM %s WHERE %s",
                $this->_modelParams->tableName, implode(' AND ', $this->getSQlIdConditions()));

            return (self::$database->value($sql) == 0);
        }

        public function loadFromRecord($record, $query)
        {
            $result = true;
            foreach($this->_modelParams->beforeLoad as $function)
                if (is_callable (array($this, $function)))
                {
                    if (call_user_func(array($this, $function)) === false)
                        $result = false;
                }
                else
                    throw new ErrorNoCallback(get_class($this), $function);
            if (!$result)
                return false;

            $this->_modelModified = false;

            if ($this->_modelParams->tableMetaData == null)
                $this->_modelParams->tableMetaData = $query->getFieldsInfo();

            $this->_modelFieldModified = array_fill_keys(array_keys($this->_modelParams->tableMetaData), false);
            $this->_modelData = array_fill_keys(array_keys($this->_modelParams->tableMetaData), null);
            foreach ($this->_modelParams->transientFields as $field=>$default)
                $this->_modelData[$field] = $default;

            foreach($record as $field => $value)
            {
                //Eliminar prefijo
                $pos = strrpos($field, '.');
                if ($pos !== false)
                    $field = substr($field, $pos+1);

                if (!array_key_exists($field, $this->_modelData))
                    continue;

                if ($value === NULL)
                    $this->_modelData[$field] = $value;
                else
                    switch ($this->_modelParams->tableMetaData[$field]->Type)
                    {
                        case 'integer':
                        case 'int':
                        case 'tinyint':
                            $this->_modelData[$field] = $value*1;
                            break;

                        case 'double':
                        case 'float':
                        case 'real':
                            $this->_modelData[$field] = $value*1.0;
                            break;

                        case 'date':
                            $this->_modelData[$field] = self::$database->sqlDateToLocalDate($value);
                            break;

                        case 'time':
                            $this->_modelData[$field] = self::$database->sqlDateToLocalDate($value);
                            break;

                        case 'datetime':
                            $this->_modelData[$field] = self::$database->sqlDateTimeToLocalDateTime($value);
                            break;

                        case 'varchar':
                        case 'mediumtext':
                        case 'text':
                        case 'string':
                        default:
                            if ($this->_modelParams->tableCharset != 'utf-8')
                                $this->_modelData[$field] = iconv($this->_modelParams->tableCharset, 'utf-8', $value);
                            else
                                $this->_modelData[$field] = $value;
                            break;
                    }
            }

            $result = true;
            foreach($this->_modelParams->afterLoad as $function)
                if (is_callable (array($this, $function)))
                {
                    if (call_user_func(array($this, $function)) === false)
                        $result = false;
                }
                else
                    throw new ErrorNoCallback(get_class($this), $function);

            return $result;
        }

        public function getSQlIdConditions($withAlias=true)
        {
            $conditions = array();

            if (is_string($withAlias) && (strlen($withAlias) > 0))
                $aliasPrefix = $withAlias.'.';
            elseif ($withAlias === false)
                $aliasPrefix = '';
            elseif($this->_modelParams->aliasName != '')
                $aliasPrefix = $this->_modelParams->aliasName.'.';
            else
                $aliasPrefix = '';

            foreach($this->_modelParams->keyFields as $field)
            {
                $conditions[] = sprintf('(%s%s = %s)', $aliasPrefix, $field,
                    self::getFieldSQLRepresentation ($field, $this->__get($field), $this->_modelParams));
            }

            return $conditions;
        }

        public function getRelationObject($name)
        {
            if (isset($this->_modelParams->relations[$name]))
            {
                if (!isset($this->_modelParams->relationObjects->{$name}))
                    $this->_modelParams->relationObjects->{$name} = new ModelBaseRelation($this->_modelName, $name, $this->_modelParams->relations[$name]);

                return $this->_modelParams->relationObjects->{$name}->getTiedToRelator($this);
            }

            return null;
        }

        protected static function getFieldSQLRepresentation($field, $value, $modelParams, $rawData = false)
        {
            if (!array_key_exists($field, $modelParams->tableMetaData))
                $type = 'string';
            else
                $type = $modelParams->tableMetaData[$field]->Type;

            if ($value === null)
                return 'NULL';

            switch ($type)
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
                    if ($rawData) return sprintf("'%s'", self::$database->sqlEscaped($value));
                    return sprintf("'%s'", self::$database->sqlEscaped(self::$database->localDateToSqlDate($value)));
                case 'time':
                    if ($rawData) return sprintf("'%s'", self::$database->sqlEscaped($value));
                    return sprintf("'%s'", self::$database->sqlEscaped(self::$database->localTimeToSqlTime($value)));
                case 'datetime':
                    if ($rawData) return sprintf("'%s'", self::$database->sqlEscaped($value));
                    return sprintf("'%s'", self::$database->sqlEscaped(self::$database->localDateTimeToSqlDateTime($value)));

                case 'varchar':
                case 'mediumtext':
                case 'text':
                case 'string':
                default:
                    if ($modelParams->tableCharset != 'utf-8' && !$rawData)
                        return sprintf("'%s'", self::$database->sqlEscaped(iconv('utf-8', $modelParams->tableCharset, $value)));
                    else
                        return sprintf("'%s'", self::$database->sqlEscaped($value));
            }
        }

        protected static function encodeKey($key)
        {
            $result = '';
            $key = $key.'';

            for($i = 0; $i < strlen($key); $i++)
            {
                if (preg_match('/[a-zA-Z_0-9\\-]/', $key[$i]) == 0)
                {
                    $result.='%'.sprintf('%02x', ord($key[$i]));
                } else
                    $result.=$key[$i];
            }

            return $result;
        }

        protected static function decodeKey($key, $modelParams)
        {
            if (is_array($key))
                return $key;

            $key = explode('|', $key);

            if (count($modelParams->keyFields) <= count($key))
            {
                $result = array();
                for ($i = 0; $i < count($modelParams->keyFields); $i++)
                    $result[$modelParams->keyFields[$i]] = urldecode ($key[$i]);

                return $result;
            }

            return null;
        }

        protected static function initializeModel($model)
        {
            if (self::$__modelParams === null)
                self::$__modelParams = new Object();
            if (self::$__cache === null)
                self::$__cache = new Object();

            if (isset(self::$__modelParams->{$model}))
                return;

            $settings = get_class_vars($model);
            $params = new Object();
            $params->transientFields =(isset($settings['_transientFields'])? $settings['_transientFields']: array());
            $params->tableName =(isset($settings['_tableName'])? $settings['_tableName']: strtolower($model.'s'));
            $params->keyFields =(isset($settings['_keyFields'])? arraize($settings['_keyFields']): array('id'));
            $params->tableMetaData =(isset($settings['_tableMetaData'])? $settings['_tableMetaData']: null);
            $params->tableCharset =(isset($settings['_tableCharset'])? strtolower($settings['_tableCharset']): 'utf-8');
            $params->baseTableName =(isset($settings['_baseTableName'])? $settings['_baseTableName']: null);
            $params->aliasName =(isset($settings['_aliasName'])? $settings['_aliasName']: null);
            $params->customQueries = (isset($settings['_queries'])? $settings['_queries']: array());

            $params->relations =(isset($settings['_relations'])? $settings['_relations']: array());

            $params->sqlConditions =(isset($settings['_sqlConditions'])? $settings['_sqlConditions']: array());
            $params->sqlGrouping =(isset($settings['_sqlGrouping'])? $settings['_sqlGrouping']: array());
            $params->sqlOrdering =(isset($settings['_sqlOrdering'])? $settings['_sqlOrdering']: array());
            $params->sqlLimit =(isset($settings['_sqlLimit'])? $settings['_sqlLimit']: null);
            $params->sqlFields =(isset($settings['_sqlFields'])? $settings['_sqlFields']: null);

            $params->beforeLoad =(isset($settings['_beforeLoad'])? $settings['_beforeLoad']: array());
            $params->beforeDelete =(isset($settings['_beforeDelete'])? $settings['_beforeDelete']: array());
            $params->beforeSave =(isset($settings['_beforeSave'])? $settings['_beforeSave']: array());

            $params->afterLoad =(isset($settings['_afterLoad'])? $settings['_afterLoad']: array());
            $params->afterDelete =(isset($settings['_afterDelete'])? $settings['_afterDelete']: array());
            $params->afterSave =(isset($settings['_afterSave'])? $settings['_afterSave']: array());

            $params->relationObjects = new Object();
            $params->modelQuery =null;
            self::$__modelParams->{$model} = $params;

            if ($params->tableMetaData !== null)
                return false;

            if (($params->baseTableName === null) && (preg_match('/^[a-zA-Z_0-9][a-zA-Z_0-9\\.]*$/', $params->tableName)))
                $params->baseTableName = $params->tableName;

            if (($params->aliasName === null) && ($params->baseTableName !== null))
                $params->aliasName = $params->baseTableName;

            if (self::$database === null)
                self::$database = Application::get()->database;

            if ($params->baseTableName !== null)
                $params->tableMetaData = self::$database->getTableFields($params->baseTableName);
            else
            {
                $params->tableMetaData = array();
                if ($params->sqlFields)
                {
                    foreach($params->sqlFields as $field)
                    {
                        $obj = new Object();
                        $obj->Type = 'string';
                        $obj->Key = (array_search($field, $params->keyFields) !== false);
                        $obj->Null = !$obj->Key;
                        $obj->Default = null;

                        if (($pos = strpos($field, '.')) !== false)
                            $field = substr($field, $pos+1);
                        $obj->Name = $field;
                        $params->tableMetaData[$field] = $obj;
                    }
                }
            }

            if ($params->modelQuery === null)
                $params->modelQuery = new ModelQuery(
                    $model, $params->tableName,
                    $params->aliasName, $params->sqlFields,
                    $params->sqlConditions,
                    $params->sqlGrouping,
                    $params->sqlOrdering,
                    $params->sqlLimit,
                    $params->customQueries);
        }

        //Object redefinition
        public function __toString()
        {
            $values = array();

            foreach($this->_modelData as $key=>$value)
                $values[] = sprintf('%s: %s', $key, $value);

            return sprintf('<#%s %s>', get_class($this), implode(', ', $values));
        }

        public function __toJSONRepresentable()
        {
            return $this->_modelData;
        }

        // ----------- Iterator Implementation --------------------------------
        private $_iteratorCurrentKey = null;
        private $_iteratorCurrentIndex = null;
        private $_iteratorKeys = null;

        public function current()
        {
            if ($this->_iteratorCurrentIndex == null)
                $this->next();

            return $this->__get($this->_iteratorCurrentKey);
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

            if ($this->_iteratorCurrentIndex < count($this->_modelParams->tableMetaData))
            {
                if ($this->_iteratorKeys === null)
                    $this->_iteratorKeys = array_keys($this->_modelParams->tableMetaData);

                $this->_iteratorCurrentKey = $this->_iteratorKeys[$this->_iteratorCurrentIndex];
            }
        }

        public function rewind()
        {
            $this->_iteratorCurrentIndex = null;
        }

        public function valid()
        {
            return ($this->_iteratorCurrentIndex < count($this->_modelParams->tableMetaData));
        }
    }
?>
