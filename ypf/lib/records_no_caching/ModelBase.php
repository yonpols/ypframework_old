<?php
    class ModelBase extends Object implements Iterator, IModelQuery
    {
        //Variables de ModelBase, no pueden redefinirse.
        protected static $__cache = null;
        protected static $__database = null;
        protected static $__modelParams = null;
        //----------------------------------------------------------------------

        //Variables de Clase<ModeloBase
        /**
         * Nombre de la tabla o construcción con la que se realiza SELECT.
         * Si se ingresa una construcción, debe ser de manera que genere una tabla en SQL
         * como lo sería "tabla t1 JOIN tabla2 t2 ON (t1.id = t2.t1_id)". En este caso
         * debe especificarse el parámetro _baseTableName para hacer el modelo escribible.
         * @static string
         */
        //protected static $_tableName = null;

        /**
         * Nombre de la tabla base sobre la que se harán las consultas insert, delete, update.
         * Si no se especifica este parámetro y no puede obtenerse a partir de _tableName
         * el modelo es de sólo lectura.
         * @static string
         */
        //protected static $_baseTableName = null;

        /**
         * Si se especifica una construcción donde hay colisión de nombres,
         * es necesario introducir un alias para referirse a la $_baseTableName.
         * Si no se especifica, se tratará de obtener de $_baseTableName, de lo
         * contrario no se utilizará.
         * @static string
         */
        //protected static $_aliasName = null;

        /**
         * Listado de las claves primarias del modelo. Por defecto es id
         * @static string
         */
        /*
        protected static $_keyFields = array('id');

        protected static $_sqlConditions = array();
        protected static $_sqlGrouping = array();
        protected static $_sqlOrdering = array();
        protected static $_sqlLimit = null;

        protected static $_tableMetaData = null;
        protected static $_tableCharset = 'utf-8';

        protected static $_relations = array();
        protected static $_relationObjects = array();

        protected static $_beforeLoad = array();
        protected static $_beforeSave = array();
        protected static $_beforeDelete = array();

        protected static $_afterLoad = array();
        protected static $_afterSave = array();
        protected static $_afterDelete = array();

        protected static $_modelQuery = null;
        //protected static $_validations = array();
        //----------------------------------------------------------------------
        */

        //Variables de instancia-- debe ser un método
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
            {
                try {
                    eval("new $model();");
                } catch (Exception $e) {

                }
            }

            return (isset(self::$__modelParams->{$model})? self::$__modelParams->{$model}: null);
        }

        //Métodos de Clase<ModelBase
        protected function initializeModel()
        {
            if (self::$__modelParams === null)
                self::$__modelParams = new Object();

            $this->_modelName = get_class($this);
            $model = $this->_modelName;

            if (isset(self::$__modelParams->{$model}))
                return;

            $settings = get_class_vars($model);
            $params = new Object();
            $params->tableName =(isset($settings['_tableName'])? $settings['_tableName']: strtolower($model.'s'));
            $params->keyFields =(isset($settings['_keyFields'])? $settings['_keyFields']: array('id'));
            $params->tableMetaData =(isset($settings['_tableMetaData'])? $settings['_tableMetaData']: null);
            $params->tableCharset =(isset($settings['_tableCharset'])? strtolower($settings['_tableCharset']): 'utf-8');
            $params->baseTableName =(isset($settings['_baseTableName'])? $settings['_baseTableName']: null);
            $params->aliasName =(isset($settings['_aliasName'])? $settings['_aliasName']: null);

            $params->relations =(isset($settings['_relations'])? $settings['_relations']: array());

            $params->sqlConditions =(isset($settings['_sqlConditions'])? $settings['_sqlConditions']: array());
            $params->sqlGrouping =(isset($settings['_sqlGrouping'])? $settings['_sqlGrouping']: array());
            $params->sqlOrdering =(isset($settings['_sqlOrdering'])? $settings['_sqlOrdering']: array());
            $params->sqlLimit =(isset($settings['_sqlLimit'])? $settings['_sqlLimit']: null);

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
            else
                throw new YPFrameworkError(sprintf("Base Table Name for model %s not defined!", $model));

            if (($params->aliasName === null) && ($params->baseTableName !== null))
                $params->aliasName = $params->baseTableName;

            if (self::$__database === null)
                self::$__database = Application::$app->database;

            if ($params->baseTableName !== null)
                $params->tableMetaData = self::$__database->getTableFields($params->baseTableName);
            else
                $params->tableMetaData = false;

            if ($params->modelQuery === null)
                $params->modelQuery = new ModelQuery(
                    $model, $params->tableName,
                    $params->aliasName, array(),
                    $params->sqlConditions,
                    $params->sqlGrouping,
                    $params->sqlOrdering,
                    $params->sqlLimit);
        }

        //Métodos de Instancia
        public function __construct($id=null)
        {
            $this->initializeModel();
            $this->_modelParams = self::$__modelParams->{$this->_modelName};

            if ($id !== null)
            {
                if (!$this->find($id))
                    throw new YPFrameworkError (sprintf('Couldn\'t load %s intance with id "%s"', $this->_modelName, $id));
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
                    $this->_modelParams->relationObjects->{$name} = ModelBaseRelation ($this->_modelName, $name, $this->_modelParams->relations[$name]);

                $this->_modelParams->relationObjects->{$name}->set($this, $value);
            }
        }

        public function getSerializedKey($stringify=true)
        {
            $key = array();

            foreach ($this->_modelParams->keyFields as $k)
                $key[] = $this->encodeKey($this->__get($k));

            return ($stringify)? implode('|', $key): $key;
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



        public function find($id)
        {
            $id = $this->decodeKey($id);
            $alias = ($this->_modelParams->aliasName=='')? $this->_modelParams->baseTableName: $this->_modelParams->aliasName;

            //Preparar condiciones
            $conditions = $this->_modelParams->sqlConditions;
            if (is_array($id))
            {
                foreach ($id as $key=>$value)
                    if (array_search ($key, $this->_modelParams->keyFields) !== false)
                        $conditions[] = sprintf('(%s.%s = %s)', $alias, $key, $this->getFieldSQLRepresentation($key, $value));
            } elseif (count($this->_modelParams->keyFields) == 1)
                $conditions[] = sprintf('(%s.%s = %s)', $alias, $this->_modelParams->keyFields[0], $this->getFieldSQLRepresentation($this->_modelParams->keyFields[0], $id));
            else
                throw new YPFrameworkError(sprintf('%s.find(): invalid number of key values', get_class($this)));
            $where = ' WHERE '.implode(' AND ', $conditions);

            //Preparar agrupación
            $group = (count($this->_modelParams->sqlGrouping) > 0)? ' GROUP BY '.implode(', ', $this->_modelParams->sqlGrouping): '';

            //Preparar ordenación
            $order = (count($this->_modelParams->sqlOrdering) > 0)? ' ORDER BY '.implode(', ', $this->_modelParams->sqlOrdering): '';

            $sql = sprintf('SELECT %s.* FROM (%s) AS %s %s%s%s LIMIT 1',
                                $alias, $this->_modelParams->tableName, $alias,
                                $where, $group, $order);

            $row = self::$__database->value($sql, true);

            if (!$row)
                return false;
            else {
                $this->loadFromRecord($row);
                return true;
            }
        }

        public function save()
        {
            if (!$this->_modelModified)
                return false;

            $result = true;
            foreach($this->_modelParams->beforeSave as $function)
                if (call_user_func(array($this, $function)) === false)
                    $result = false;
            if (!$result)
                return false;

            $fieldNames = array_keys($this->_modelParams->tableMetaData);
            if ($this->isNew())
            {
                $fieldValues = array();

                foreach ($fieldNames as $field)
                    $fieldValues[] = $this->getFieldSQLRepresentation($field);

                $sql = sprintf("INSERT INTO %s (%s) VALUES(%s)", $this->_modelParams->baseTableName,
                    implode(', ', $fieldNames), implode(', ', $fieldValues));

                $result = self::$__database->command($sql);

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
                        $fieldAssigns[] = sprintf("%s = %s", $field, $this->getFieldSQLRepresentation($field));

                $sql = sprintf("UPDATE %s SET %s WHERE %s", $this->_modelParams->baseTableName,
                    implode(', ', $fieldAssigns), implode(', ', $this->getSQlIdConditions(false)));

                $result = self::$__database->command($sql);
            }

            if ($result === true)
            {
                $this->_modelModified = false;
                $this->_modelFieldModified = array_fill_keys($fieldNames, false);

                $result = true;
                foreach($this->_modelParams->afterSave as $function)
                    if (call_user_func(array($this, $function)) === false)
                        $result = false;
                if (!$result)
                    return false;
            }

            return $result;
        }

        public function delete()
        {
            $result = true;
            foreach($this->_modelParams->beforeDelete as $function)
                if (call_user_func(array($this, $function)) === false)
                    $result = false;
            if (!$result)
                return false;

            $alias = ($this->_modelParams->aliasName=='')? '': ' AS '.$this->_modelParams->aliasName;

            $sql = sprintf("DELETE FROM %s%s WHERE %s",
                $this->_modelParams->baseTableName, $alias, implode(' AND ', $this->getSQlIdConditions()));

            $result = self::$__database->command($sql);

            foreach($this->_modelParams->afterDelete as $function)
                if (call_user_func(array($this, $function)) === false)
                    $result = false;

            return $result;
        }

        public function isNew()
        {
            $sql = sprintf("SELECT COUNT(*) FROM %s WHERE %s",
                $this->_modelParams->tableName, implode(' AND ', $this->getSQlIdConditions()));

            return (self::$__database->value($sql) == 0);
        }

        public function loadFromRecord($record)
        {
            $result = true;
            foreach($this->_modelParams->beforeLoad as $function)
                if (call_user_func(array($this, $function)) === false)
                    $result = false;
            if (!$result)
                return false;

            $this->_modelModified = false;
            $this->_modelFieldModified = array_fill_keys(array_keys($this->_modelParams->tableMetaData), false);
            $this->_modelData = array_fill_keys(array_keys($this->_modelParams->tableMetaData), null);

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
                            $this->_modelData[$field] = self::$__database->sqlDateToLocalDate($value);
                            break;

                        case 'time':
                            $this->_modelData[$field] = self::$__database->sqlDateToLocalDate($value);
                            break;

                        case 'datetime':
                            $this->_modelData[$field] = self::$__database->sqlDateToLocalDate($value);
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
                if (call_user_func(array($this, $function)) === false)
                    $result = false;

            return $result;
        }

        public function getSQlIdConditions($withAlias=true)
        {
            $conditions = array();

            if (is_string($withAlias) && (strlen($withAlias) > 0))
                $alias = $withAlias.'.';
            elseif ($withAlias === false)
                $alias = '';
            elseif($this->_modelParams->aliasName != '')
                $alias = $this->_modelParams->aliasName.'.';
            else
                $alias = '';

            foreach($this->_modelParams->keyFields as $field)
                $conditions[] = sprintf('(%s%s = %s)', $alias, $field, $this->getFieldSQLRepresentation($field));

            return $conditions;
        }

        protected function getFieldSQLRepresentation($field, $customValue=null)
        {
            if (!array_key_exists($field, $this->_modelParams->tableMetaData))
                return null;

            $value = ($customValue===null)? $this->_modelData[$field]: $customValue;

            if ($value === null)
                return 'NULL';

            switch ($this->_modelParams->tableMetaData[$field]->Type)
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
                    return sprintf("'%s'", self::$__database->sqlEscaped(self::$__database->localDateToSqlDate($value)));
                case 'time':
                    return sprintf("'%s'", self::$__database->sqlEscaped(self::$__database->localTimeToSqlTime($value)));
                case 'datetime':
                    return sprintf("'%s'", self::$__database->sqlEscaped(self::$__database->localDateTimeToSqlDateTime($value)));

                case 'varchar':
                case 'mediumtext':
                case 'text':
                case 'string':
                default:
                    if ($this->_modelParams->tableCharset != 'utf-8')
                        return sprintf("'%s'", self::$__database->sqlEscaped(iconv('utf-8', $this->_modelParams->tableCharset, $value)));
                    else
                        return sprintf("'%s'", self::$__database->sqlEscaped($value));
            }
        }

        protected function encodeKey($key)
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

        protected function decodeKey($key)
        {
            if (is_array($key))
                return $key;

            $key = explode('|', $key);

            if (count($this->_modelParams->keyFields) <= count($key))
            {
                $result = array();
                for ($i = 0; $i < count($this->_modelParams->keyFields); $i++)
                    $result[$this->_modelParams->keyFields[$i]] = urldecode ($key[$i]);

                return $result;
            }

            return null;
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

            return $this->_modelData[$this->_iteratorCurrentKey];
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

        // ----------- ModelQuery Implementation--------------------------------
        public function all()
        {
            return $this->_modelParams->modelQuery->all();
        }

        public function count($sqlConditions = null, $sqlGrouping = null)
        {
            return $this->_modelParams->modelQuery->count($sqlConditions, $sqlGrouping);
        }

        public function first()
        {
            return $this->_modelParams->modelQuery->first();
        }

        public function groupBy($sqlGrouping)
        {
            return $this->_modelParams->modelQuery->groupBy($sqlGrouping);
        }

        public function last()
        {
            return $this->_modelParams->modelQuery->last();
        }

        public function limit($limit)
        {
            return $this->_modelParams->modelQuery->limit($limit);
        }

        public function orderBy($sqlOrdering)
        {
            return $this->_modelParams->modelQuery->orderBy($sqlOrdering);
        }

        public function select($sqlConditions, $sqlGrouping = array(), $sqlOrdering = array(), $sqlLimit = null)
        {
            return $this->_modelParams->modelQuery->select($sqlConditions, $sqlGrouping, $sqlOrdering, $sqlLimit);
        }
    }
?>
