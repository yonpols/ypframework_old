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

        private $_count = null;
        private $_query = null;

        public function __construct($modelName, $tableName, $aliasName = null, $sqlFields = array(),
                                    $sqlConditions = array(), $sqlGrouping = array(),
                                    $sqlOrdering = array(), $sqlLimit = null, $aliaseQuery = true, $count = false)
        {
            $this->database = Application::$app->database;
            $this->modelName = $modelName;
            $this->tableName = $tableName;
            $this->aliasName = $aliasName;
            $this->aliaseQuery = $aliaseQuery;
            $this->sqlFields = $sqlFields;
            $this->sqlConditions = $sqlConditions;
            $this->sqlGrouping = $sqlGrouping;
            $this->sqlOrdering = $sqlOrdering;
            $this->sqlLimit = $sqlLimit;

            if ($aliaseQuery)
                $this->tableReference = "($tableName) AS $aliasName";
            else
                $this->tableReference = $tableName;

            if ($count)
                $this->count();
        }

        //Devuelven valores simples o una instancia de modelos
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
            if (count($this->sqlFields) == 0)
                $fields = array($this->aliasName.'.*');
            else
            {
                $fields = array();
                foreach ($this->sqlFields as $field)
                    $fields[] = sprintf('%s.%s', $this->aliasName, $field);
            }

            $sql = sprintf('SELECT %s FROM %s%s',
                            implode(', ', $fields),
                            $this->tableReference,
                            $this->getSQLSuffix($this->sqlConditions, $this->sqlGrouping,
                                $this->sqlOrdering, '0,1'));

            $row = $this->database->value($sql, true);
            return $this->getModelInstance($row);
        }

        public function last()
        {
            if (count($this->sqlFields) == 0)
                $fields = array($this->aliasName.'.*');
            else
            {
                $fields = array();
                foreach ($this->sqlFields as $field)
                    $fields[] = sprintf('%s.%s', $this->aliasName, $field);
            }

            $count = $this->count();

            $sql = sprintf('SELECT %s FROM %s%s',
                            implode(', ', $fields),
                            $this->tableReference,
                            $this->getSQLSuffix($this->sqlConditions, $this->sqlGrouping,
                                $this->sqlOrdering, sprintf('%d,1', $count-1)));

            $row = $this->database->value($sql, true);
            return $this->getModelInstance($row);
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

            return new ModelQuery($this->modelName, $this->tableName, $this->aliasName, $this->sqlFields,
                                        array_merge($this->sqlConditions, $sqlConditions),
                                        array_merge($this->sqlGrouping, $sqlGrouping),
                                        array_merge($this->sqlOrdering, $sqlOrdering),
                                        ($sqlLimit !== null)? $sqlLimit: $this->sqlLimit, $this->aliaseQuery);
        }

        public function orderBy($sqlOrdering)
        {
            if (is_string($sqlOrdering))
                $sqlOrdering = array($sqlOrdering);

            return new ModelQuery($this->modelName, $this->tableName, $this->aliasName, $this->sqlFields,
                                        $this->sqlConditions, $this->sqlGrouping,
                                        array_merge($this->sqlOrdering, $sqlOrdering),
                                        $this->sqlLimit, $this->aliaseQuery);
        }

        public function groupBy($sqlGrouping)
        {
            if (is_string($sqlGrouping))
                $sqlGrouping = array($sqlGrouping);

            return new ModelQuery($this->modelName, $this->tableName, $this->aliasName, $this->sqlFields,
                                        $this->sqlConditions,
                                        array_merge($this->sqlGrouping, $sqlGrouping),
                                        $this->sqlOrdering, $this->sqlLimit, $this->aliaseQuery);
        }

        public function limit($sqlLimit)
        {
            return new ModelQuery($this->modelName, $this->tableName, $this->aliasName, $this->sqlFields,
                                        $this->sqlConditions,
                                        $this->sqlGrouping,
                                        $this->sqlOrdering, $sqlLimit, $this->aliaseQuery);

        }

        protected function getModelInstance($row)
        {
            $instance = eval(sprintf('return new %s();', $this->modelName));
            $instance->loadFromRecord($row);
            return $instance;
        }

        private function getSQLSuffix($sqlConditions = array(), $sqlGrouping = array(),
                                    $sqlOrdering = array(), $sqlLimit = null)
        {
            $where = (count($sqlConditions) > 0)? ' WHERE '.implode(', ', $sqlConditions): '';
            $group = (count($sqlGrouping) > 0)? ' GROUP BY '.implode(', ', $sqlGrouping): '';
            $order = (count($sqlOrdering) > 0)? ' ORDER BY '.implode(', ', $sqlOrdering): '';
            $limit = ($sqlLimit !== null)? ' LIMIT '.$sqlLimit: '';

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
                if (count($this->sqlFields) == 0)
                    $fields = array($this->aliasName.'.*');
                else
                {
                    $fields = array();
                    foreach ($this->sqlFields as $field)
                        $fields[] = sprintf('%s.%s', $this->aliasName, $field);
                }

                $sql = sprintf('SELECT %s FROM %s%s',
                                implode(', ', $fields),
                                $this->tableReference,
                                $this->getSQLSuffix($this->sqlConditions, $this->sqlGrouping,
                                    $this->sqlOrdering, $this->sqlLimit));
                $this->_query = $this->database->query($sql);
                $this->_iteratorCurrentIndex = -1;
            }

            if (($row = $this->_query->getNext()))
            {
                $this->_iteratorCurrentIndex++;
                $this->_iteratorCurrentInstance = $this->getModelInstance($row);
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
            return ($this->_iteratorCurrentIndex !== -1);
        }
    }
?>
