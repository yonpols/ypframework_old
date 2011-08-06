<?php

    class ModelRelation extends ModelBase
    {
        protected $_relationName;
        protected $_relationType;
        protected $_relationForeignKeys;
        protected $_relationJoinTable;
        protected $_relationJoinKeys;
        protected $_relationModel;
        protected $_relationBaseModel;
        protected $_relationConditions;
        protected $_relationCanBeNull;

        protected $_selectClause = null;

        public function __construct($name, $baseModel, $params)
        {
            $this->_database = Application::$app->database;

            $this->_relationBaseModel = $baseModel;
            $this->_relationName = $name;
            $this->_tableName = $name;

            if (isset($params['where']))
                $this->_relationConditions = $params['where'];

            if (isset($params['has_one']))
            {
                $this->_relationType = 'has_one';
                $this->_relationModel = eval(sprintf("return new %s();", $params['has_one']));
                $this->_relationForeignKeys = $params['keys'];

                $sql = sprintf("FROM %s AS %s JOIN %s ON ",
                            $this->_relationModel->_tableName,
                            $this->_relationName,
                            $this->_relationBaseModel->_tableName);

                $join = array();
                if (is_array($this->_relationForeignKeys))
                {
                    foreach ($this->_relationForeignKeys as $i=>$key)
                        $join[] = sprintf ('(%s.%s = %s.%s)',
                            $this->_relationName, $key,
                            $this->_relationBaseModel->_tableName, $this->_relationBaseModel->_keyFields[$i]);
                } else {
                    $join[] = sprintf ('(%s.%s = %s.%s)',
                        $this->_relationName, $this->_relationForeignKeys,
                        $this->_relationBaseModel->_tableName, $this->_relationBaseModel->_keyFields);
                }

                $sql .= implode(' AND ', $join);
            } elseif (isset($params['has_many']))
            {
                $this->_relationType = 'has_many';
                $this->_relationModel = eval(sprintf("return new %s();", $params['has_many']));

                if (isset($params['through']))
                {
                    $this->_relationJoinTable = $params['through'];
                    $this->_relationJoinKeys = $params['join'];

                    $join = array();
                    //contract has_many acls acl-> contract_id
                    $sql = sprintf("FROM %s AS %s JOIN %s ON ",
                                $this->_relationModel->_tableName,
                                $this->_relationName,
                                $this->_relationJoinTable);

                    if (is_array($this->_relationModel->_keyFields))
                    {
                        foreach ($this->_relationModel->_keyFields as $key)
                            $join[] = sprintf ('(%s.%s = %s.%s)',
                                $this->_relationName, $key,
                                $this->_relationJoinTable, $this->_relationJoinKeys[$key]);
                    } else {
                        $join[] = sprintf ('(%s.%s = %s.%s)',
                            $this->_relationName, $this->_relationModel->_keyFields,
                            $this->_relationJoinTable, $this->_relationJoinKeys[$this->_relationModel->_keyFields]);
                    }

                    $sql .= implode(' AND ', $join).sprintf(" JOIN %s ON ",
                                $this->_relationBaseModel->_tableName);

                    $join = array();
                    if (is_array($this->_relationBaseModel->_keyFields))
                    {
                        foreach ($this->_relationBaseModel->_keyFields as $key)
                            $join[] = sprintf ('(%s.%s = %s.%s)',
                                $this->_relationBaseModel->_tableName, $key,
                                $this->_relationJoinTable, $this->_relationJoinKeys[$key]);
                    } else {
                        $join[] = sprintf ('(%s.%s = %s.%s)',
                            $this->_relationBaseModel->_tableName, $this->_relationBaseModel->_keyFields,
                            $this->_relationJoinTable, $this->_relationJoinKeys[$this->_relationBaseModel->_keyFields]);
                    }

                    $sql .= implode(' AND ', $join);
                } else
                {
                    $this->_relationForeignKeys = $params['keys'];
                    $join = array();

                    //contract has_many acls acl-> contract_id
                    $sql = sprintf("FROM %s AS %s JOIN %s ON ",
                                $this->_relationModel->_tableName,
                                $this->_relationName,
                                $this->_relationBaseModel->_tableName);

                    if (is_array($this->_relationForeignKeys))
                    {
                        foreach ($this->_relationForeignKeys as $i=>$key)
                            $join[] = sprintf ('(%s.%s = %s.%s)',
                                $this->_relationName, $key,
                                $this->_relationBaseModel->_tableName, $this->_relationBaseModel->_keyFields[$i]);
                    } else {
                        $join[] = sprintf ('(%s.%s = %s.%s)',
                            $this->_relationName, $this->_relationForeignKeys,
                            $this->_relationBaseModel->_tableName, $this->_relationBaseModel->_keyFields);
                    }
                    $sql .= implode(' AND ', $join);
                }
            } elseif (isset($params['belongs_to']))
            {
                $this->_relationType = 'belongs_to';
                $this->_relationModel = eval(sprintf("return new %s();", $params['belongs_to']));
                $this->_relationForeignKeys = $params['keys'];

                $sql = sprintf("FROM %s AS %s JOIN %s ON ",
                            $this->_relationModel->_tableName,
                            $this->_relationName,
                            $this->_relationBaseModel->_tableName);

                $join = array();
                if (is_array($this->_relationForeignKeys))
                {
                    foreach ($this->_relationForeignKeys as $i=>$key)
                        $join[] = sprintf ('(%s.%s = %s.%s)',
                            $this->_relationName, $this->_relationModel->_keyFields[$i],
                            $this->_relationBaseModel->_tableName, $key);
                } else {
                    $join[] = sprintf ('(%s.%s = %s.%s)',
                        $this->_relationName, $this->_relationModel->_keyFields,
                        $this->_relationBaseModel->_tableName, $this->_relationForeignKeys);
                }

                $sql .= implode(' AND ', $join);
            }

            $this->_selectClause = $sql;
        }

        public function find($id)
        {
            return new ErrorException(sprintf("Can't use find in relation %s.%s", $this->_relationBaseModel->_tableName, $this->_relationName));
        }

        public function findWhere($where)
        {
            return new ErrorException(sprintf("Can't use find in relation %s.%s", $this->_relationBaseModel->_tableName, $this->_relationName));
        }

        public function select($where = '', $limit = null)
        {
            $result = array();
            $query = $this->_database->query(sprintf('SELECT %s.* %s', $this->_relationName,
                $this->getSelectClause($where)), $limit);

            while ($row = $query->getNext())
            {
                $obj = clone $this->_relationModel;
                $obj->loadFromRow($row, $this->_relationName);
                $result[] = $obj;
            }

            return $result;
        }

        public function set($value)
        {
            if ($this->_relationType == 'has_one')
            {
                if (is_string($value))
                {
                    $id = $value;
                    $value = clone $this->_relationModel;
                    if (!$value->find($id))
                        throw new ErrorException('has_one: needs an object assignment');
                }

                if (!is_object($value))
                    throw new ErrorException('has_one: needs an object assignment');
                if (get_class($value) != get_class($this->_relationModel))
                    throw new ErrorException('has_one: needs an object of class: '.
                        get_class($this->_relationModel).'. Not: '.get_class($value));

                if (is_array($this->_relationForeignKeys))
                    foreach ($this->_relationForeignKeys as $i => $k)
                        $value->{$k} = $this->_relationBaseModel->{$this->_keyFields[$i]};
                else
                    $value->{$this->_relationForeignKeys} = $this->_relationBaseModel->{$this->_keyFields};
            } elseif ($this->_relationType == 'belongs_to')
            {
                if (is_string($value))
                {
                    $id = $value;
                    $value = clone $this->_relationModel;

                    if (!$value->find($id))
                        throw new ErrorException('belongs_to: needs an object assignment');
                }

                if (!is_object($value))
                    throw new ErrorException('belongs_to: needs an object assignment');
                if (get_class($value) != get_class($this->_relationModel))
                    throw new ErrorException('belongs_to: needs an object of class: '.
                        get_class($this->_relationModel).'. Not: '.get_class($value));

                if (is_array($this->_relationForeignKeys))
                    foreach ($this->_relationForeignKeys as $i => $k)
                        $this->_relationBaseModel->{$k} = $value->{$value->_keyFields[$i]};
                else
                    $this->_relationBaseModel->{$this->_relationForeignKeys} = $value->{$value->_keyFields};
            } elseif ($this->_relationType == 'has_many')
            {
                if (!is_array($value))
                    throw new ErrorException('has_many: needs an array assignment');

                if (isset($this->_relationJoinTable))
                {
                    $this->_database->command("TRUNCATE TABLE ".$this->_relationJoinTable);

                    foreach ($value as $obj)
                    {
                        if (get_class($obj) != get_class($this->_relationModel))
                            throw new ErrorException('has_many: needs an object of class: '.
                                get_class($this->_relationModel).'. Not: '.get_class($obj));

                        $sql = sprintf("INSERT INTO %s (%s) VALUES(",
                            $this->_relationJoinTable, implode(', ', $this->_relationJoinKeys));

                        foreach ($this->_relationJoinKeys as $k => $fk)
                            if (array_search($k, $this->_relationBaseModel->_keyFields) !== false)
                                $sql .= sprintf ('%s, ', $this->_relationBaseModel->getFieldSql($k));
                            else
                                $sql .= sprintf ('%s, ', $obj->getFieldSql($k));

                        $this->_database->command(substr($sql, 0, -2).')');
                    }
                } else
                {
                    if (is_array($this->_relationForeignKeys))
                    {
                        foreach ($value as $obj)
                        {
                            if (get_class($obj) != get_class($this->_relationModel))
                                throw new ErrorException('has_many: needs an object of class: '.
                                    get_class($this->_relationModel).'. Not: '.get_class($obj));

                            foreach ($this->_relationForeignKeys as $i => $k)
                                $obj->{$k} = $this->{$this->_keyFields[$i]};
                        }
                    } else {
                        foreach ($value as $obj)
                        {
                            if (get_class($obj) != get_class($this->_relationModel))
                                throw new ErrorException('has_many: needs an object of class: '.
                                    get_class($this->_relationModel).'. Not: '.get_class($obj));

                            $obj->{$this->_relationForeignKeys} = $this->{$this->_keyFields};
                        }
                    }
                }
            }
        }

        public function get()
        {
            if ($this->isOne())
            {
                $obj = clone $this->_relationModel;
                $query = $this->_database->query(sprintf("SELECT %s.* %s", $this->_relationName, $this->getSelectClause()));

                if (is_object($query))
                    $row = $query->getNext();
                else
                    return NULL;

                if (!$row)
                    return NULL;

                $obj->loadFromRow($row, $this->_relationName);
                return $obj;
            } else {
                return new ErrorException(sprintf("Can't use get in relation %s.%s", $this->_relationBaseModel->_tableName, $this->_relationName));
            }
        }

        public function isOne()
        {
            switch ($this->_relationType)
            {
                case 'has_one':
                case 'belongs_to':
                    return true;
                default:
                    return false;
            }
        }

        public function add($values = array())
        {
            if ($this->_relationType == 'has_many')
            {
                $instance = clone $this->_relationModel;

                foreach ($values as $k=>$v)
                    $instance->{$k} = $v;

                if ($this->_relationJoinTable)
                {
                    if ($instance->save())
                    {
                        $sql = sprintf("INSERT INTO %s (%s) VALUES(",
                            $this->_relationJoinTable, implode(', ', $this->_relationJoinKeys));

                        foreach ($this->_relationJoinKeys as $k => $fk)
                            if (array_search($k, $this->_relationBaseModel->_keyFields) !== false)
                                $sql .= sprintf ('%s, ', $this->_relationBaseModel->getFieldSql($k));
                            else
                                $sql .= sprintf ('%s, ', $instance->getFieldSql($k));

                        $this->_database->command(substr($sql, 0, -2).')');
                    } else
                        return null;
                } else {
                    if (is_array($this->_relationForeignKeys))
                        foreach ($this->_relationForeignKeys as $i=>$k)
                            $instance->{$k} = $this->_relationBaseModel->{$this->_relationBaseModel->_keyFields[$i]};
                    else
                        $instance->{$this->_relationForeignKeys} = $this->_relationBaseModel->{$this->_relationBaseModel->_keyFields};
                }
            }
        }

        public function getRelationModel()
        {
            return $this->_relationModel;
        }

        protected function getSelectClause($where='')
        {
            $sql = $this->_selectClause." WHERE ".$this->_relationBaseModel->getWhereCond();

            if ($this->_relationConditions != '')
                $sql .= " AND ".$this->_relationConditions;

            if ($where != '')
                $sql .= " AND ".$where;

            return $sql;
        }
    }

?>
