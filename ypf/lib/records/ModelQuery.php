<?php
    class ModelQuery extends Object implements Iterator, IModelQuery
    {
        protected $database;
        protected $modelName;
        protected $tableReference;
        protected $tableName;
        protected $aliasName;
        protected $aliaseQuery;

        protected $sqlFields;
        protected $sqlConditions;
        protected $sqlGrouping;
        protected $sqlOrdering;
        protected $sqlLimit;

        protected $customQueries;

        private $_count = null;
        private $_query = null;

        public function __construct($modelName, $tableName, $aliasName = null, $sqlFields = null,
                                    $sqlConditions = array(), $sqlGrouping = array(),
                                    $sqlOrdering = array(), $sqlLimit = null,
                                    $customQueries = array(), $aliaseQuery = true)
        {
            $this->database = Application::get()->database;
            $this->modelName = $modelName;
            $this->tableName = $tableName;
            $this->aliasName = $aliasName;
            $this->aliaseQuery = $aliaseQuery;
            $this->sqlFields = ($sqlFields === null)? array(): $sqlFields;
            $this->sqlConditions = $sqlConditions;
            $this->sqlGrouping = $sqlGrouping;
            $this->sqlOrdering = $sqlOrdering;
            $this->sqlLimit = $sqlLimit;
            $this->customQueries = $customQueries;
            $this->tableReference = $tableName;
        }

        //Devuelven valores simples o una instancia de modelos
        public function fields($fields)
        {
            if (is_string($fields))
                $fields = array($fields);

            return new ModelQuery($this->modelName, $this->tableName, $this->aliasName, $fields,
                                  $this->sqlConditions, $this->sqlGrouping,
                                  $this->sqlOrdering, $this->sqlLimit, $this->customQueries,
                                  $this->aliaseQuery);
        }

        public function count($sqlConditions = null, $sqlGrouping = null)
        {
            if (($this->_count === null) || ($sqlConditions !== null) || ($sqlGrouping !== null))
            {
                if ($sqlConditions !== null)
                    $sqlConditions = array_merge($this->sqlConditions, $sqlConditions);
                else
                    $sqlConditions = $this->sqlConditions;

                if ($sqlGrouping !== null)
                    $sqlGrouping = array_merge($this->sqlGrouping, $sqlGrouping);
                else
                    $sqlGrouping = $this->sqlGrouping;

                $sql = sprintf('SELECT COUNT(*) FROM %s%s', $this->tableReference,
                    $this->getSQLSuffix($sqlConditions, $sqlGrouping));

                $this->_count = $this->database->value($sql);
            }

            return $this->_count;
        }

        public function first()
        {
            $aliasPrefix = ($this->aliasName == null)? '': $this->aliasName.'.';
            $fields = (count($this->sqlFields) == 0)? array($aliasPrefix.'*'): $this->sqlFields;

            $sql = sprintf('SELECT %s FROM %s%s',
                            implode(', ', $fields),
                            $this->tableReference,
                            $this->getSQLSuffix($this->sqlConditions, $this->sqlGrouping,
                                $this->sqlOrdering, '0,1'));

            $query = $this->database->query($sql);
            $row = $query->getNext();
            return $this->getModelInstance($row, $query);
        }

        public function last()
        {
            $aliasPrefix = ($this->aliasName == null)? '': $this->aliasName.'.';
            $fields = (count($this->sqlFields) == 0)? array($aliasPrefix.'*'): $this->sqlFields;

            $count = $this->count();

            $sql = sprintf('SELECT %s FROM %s%s',
                            implode(', ', $fields),
                            $this->tableReference,
                            $this->getSQLSuffix($this->sqlConditions, $this->sqlGrouping,
                                $this->sqlOrdering, sprintf('%d,1', $count-1)));

            $query = $this->database->query($sql);
            $row = $query->getNext();
            return $this->getModelInstance($row, $query);
        }

        public function toArray()
        {
            $result = array();
            foreach ($this as $instance)
                $result[] = $instance;

            return $result;
        }

        //Devuelven listado de valores
        public function all()
        {
            $this->_query = null;
            return $this;
        }

        public function select($sqlConditions, $sqlGrouping = array(),
                                    $sqlOrdering = array(), $sqlLimit = null)
        {
            if (is_string($sqlConditions))
                $sqlConditions = array('('.$sqlConditions.')');
            if (is_string($sqlGrouping))
                $sqlGrouping = array($sqlGrouping);
            if (is_string($sqlOrdering))
                $sqlOrdering = array($sqlOrdering);

            return new ModelQuery($this->modelName, $this->tableName, $this->aliasName, $this->sqlFields,
                                        array_merge($this->sqlConditions, $sqlConditions),
                                        array_merge($this->sqlGrouping, $sqlGrouping),
                                        array_merge($this->sqlOrdering, $sqlOrdering),
                                        ($sqlLimit !== null)? $sqlLimit: $this->sqlLimit,
                                        $this->customQueries, $this->aliaseQuery);
        }

        public function orderBy($sqlOrdering)
        {
            if (is_string($sqlOrdering))
                $sqlOrdering = array($sqlOrdering);

            return new ModelQuery($this->modelName, $this->tableName, $this->aliasName, $this->sqlFields,
                                        $this->sqlConditions, $this->sqlGrouping,
                                        array_merge($this->sqlOrdering, $sqlOrdering),
                                        $this->sqlLimit, $this->customQueries, $this->aliaseQuery);
        }

        public function groupBy($sqlGrouping)
        {
            if (is_string($sqlGrouping))
                $sqlGrouping = array($sqlGrouping);

            return new ModelQuery($this->modelName, $this->tableName, $this->aliasName, $this->sqlFields,
                                        $this->sqlConditions,
                                        array_merge($this->sqlGrouping, $sqlGrouping),
                                        $this->sqlOrdering, $this->sqlLimit,
                                        $this->customQueries, $this->aliaseQuery);
        }

        public function limit($sqlLimit)
        {
            return new ModelQuery($this->modelName, $this->tableName, $this->aliasName, $this->sqlFields,
                                        $this->sqlConditions,
                                        $this->sqlGrouping,
                                        $this->sqlOrdering, $sqlLimit, $this->customQueries,
                                        $this->aliaseQuery);

        }

        public function __get($name)
        {
            if (isset($this->customQueries[$name]))
                return $this->processCustomQuery($name);
        }

        public function getSqlQuery()
        {
            $aliasPrefix = ($this->aliasName == null)? '': $this->aliasName.'.';
            $fields = (count($this->sqlFields) == 0)? array($aliasPrefix.'*'): $this->sqlFields;

            return sprintf('SELECT %s FROM %s%s', implode(', ', $fields), $this->tableReference,
                        $this->getSQLSuffix($this->sqlConditions, $this->sqlGrouping,
                                            $this->sqlOrdering, $this->sqlLimit));
        }

        protected function getModelInstance($row, $query)
        {
            if ($row == false)
                return null;

            if (count($this->sqlFields) == 1)
                return array_shift($row);

            $instance = eval(sprintf('return new %s();', $this->modelName));
            $instance->loadFromRecord($row, $query);
            return $instance;
        }

        private function processCustomQuery($name)
        {
            $query = $this->customQueries[$name];
            $others = array_diff($this->customQueries, array($name=>$query));

            $sqlConditions = $this->sqlConditions;
            if (isset($query['sqlConditions']))
            {
                if (is_string($query['sqlConditions']))
                    $sqlConditions[] = $query['sqlConditions'];
                else
                    $sqlConditions = array_merge ($sqlConditions, $query['sqlConditions']);
            }

            $sqlGrouping = $this->sqlGrouping;
            if (isset($query['sqlGrouping']))
            {
                if (is_string($query['sqlGrouping']))
                    $sqlGrouping[] = $query['sqlGrouping'];
                else
                    $sqlGrouping = array_merge ($sqlGrouping, $query['sqlGrouping']);
            }

            $sqlOrdering = $this->sqlOrdering;
            if (isset($query['sqlOrdering']))
            {
                if (is_string($query['sqlOrdering']))
                    $sqlOrdering[] = $query['sqlOrdering'];
                else
                    $sqlOrdering = array_merge ($sqlOrdering, $query['sqlOrdering']);
            }

            if (!isset($query['sqlLimit']))
                $sqlLimit = $this->sqlLimit;
            else
                $sqlLimit = $query['sqlLimit'];

            if (!isset($query['sqlFields']))
                $sqlFields = $this->sqlFields;
            elseif (is_string($query['sqlFields']))
                $sqlFields = array($query['sqlFields']);
            else
                $sqlFields = $query['sqlFields'];

            if (!isset($query['tableReference']))
                $tableReference = $this->tableReference;
            else
                $tableReference = $query['tableReference'];

            if (!isset($query['aliasName']))
                $aliasName = $this->aliasName;
            else
                $aliasName = $query['aliasName'];

            $modelQuery = new ModelQuery($this->modelName, $tableReference, $aliasName,
                                        $sqlFields, $sqlConditions, $sqlGrouping,
                                        $sqlOrdering, $sqlLimit, $others, $this->aliaseQuery);
            if (isset($query['action']))
            {
                switch($query['action'])
                {
                    case 'first':
                        return $modelQuery->first();
                    case 'last':
                        return $modelQuery->last();
                    case 'count':
                        return $modelQuery->count();
                }
            } else
                return $modelQuery;
        }

        private function getSQLSuffix($sqlConditions = array(), $sqlGrouping = array(),
                                    $sqlOrdering = array(), $sqlLimit = null)
        {
            $where = (count($sqlConditions) > 0)? ' WHERE '.implode(' AND ', $sqlConditions): '';
            $group = (count($sqlGrouping) > 0)? ' GROUP BY '.implode(', ', $sqlGrouping): '';
            $order = (count($sqlOrdering) > 0)? ' ORDER BY '.implode(', ', $sqlOrdering): '';
            $limit = ($sqlLimit !== null)? ' LIMIT '.(is_array($sqlLimit)? implode(',', array_slice($sqlLimit, 0, 2)): $sqlLimit): '';

            return $where.$group.$order.$limit;
        }

        // ----------- Iterator Implementation --------------------------------
        private $_iteratorCurrentInstance = null;
        private $_iteratorCurrentIndex = null;
        private $_iteratorKeys = null;

        public function current()
        {
            if ($this->_query == null)
                $this->next();

            return $this->_iteratorCurrentInstance;
        }

        public function key()
        {
            if ($this->_query == null)
                $this->next();

            return $this->_iteratorCurrentIndex;
        }

        public function next()
        {
            if ($this->_query === null)
            {
                $this->_query = $this->database->query($this->getSqlQuery());
                $this->_iteratorCurrentIndex = -1;
                if ($this->_query === false)
                    throw new YPFrameworkError ('You have an error in your query: '.$this->getSqlQuery());
            }

            if (($row = $this->_query->getNext()))
            {
                $this->_iteratorCurrentIndex++;
                $this->_iteratorCurrentInstance = $this->getModelInstance($row, $this->_query);
            } else
                $this->_iteratorCurrentIndex = -1;
        }

        public function rewind()
        {
            $this->_query = null;
            $this->_iteratorCurrentIndex = null;
        }

        public function valid()
        {
            if ($this->_iteratorCurrentIndex === null)
                $this->next ();

            return ($this->_iteratorCurrentIndex !== -1);
        }
    }
?>
