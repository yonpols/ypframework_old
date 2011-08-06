<?php
    class ModelBaseRelation implements IModelQuery, Iterator
    {
        protected static $_temporalAlias = 0;

        protected $relationName;
        protected $relationType;
        protected $relationSingle;
        protected $relationParams;

        protected $relatorModelName;
        protected $relatedModelName;
        protected $relatorModelParams;
        protected $relatedModelParams;

        protected $tempAliasRelator;
        protected $tempAliasRelated;

        protected $modelQuery;
        protected $relatorInstance;
        protected $iterationQuery = null;

        public function __construct($relatorModelName, $relationName, $relationParams)
        {
            $this->relatorModelName = $relatorModelName;
            $this->relationName = $relationName;
            $this->relationParams = $relationParams;

            $sqlConditions = isset($relationInfo['sqlConditions'])? $relationInfo['sqlConditions']: array();
            $sqlGrouping = isset($relationInfo['sqlGrouping'])? $relationInfo['sqlGrouping']: array();
            $sqlOrdering = isset($relationInfo['sqlOrdering'])? $relationInfo['sqlOrdering']: array();

            if (isset($this->relationParams['belongs_to']))
            {
                $this->relationType = 'belongs_to';
                $this->relationSingle = true;
                $this->relatedModelName = $this->relationParams['belongs_to'];
                $this->relatorModelParams = ModelBase::getModelParams($relatorModelName);
                $this->relatedModelParams = ModelBase::getModelParams($this->relatedModelName);

                if ($this->relatedModelParams === null)
                    throw new YPFrameworkError ('Model not defined: '.$relatedModel);

                $joinConditions = array();
                $this->tempAliasRelator = 'table'.(self::$_temporalAlias++);
                $this->tempAliasRelated = $this->relationName;

                foreach($this->relationParams['keys'] as $index=>$key)
                    $joinConditions[] = sprintf('(%s.%s = %s.%s)',
                        $this->tempAliasRelated, $this->relatedModelParams->keyFields[$index],
                        $this->tempAliasRelator, $key);
            } else
            {
                if (isset($this->relationParams['has_one']))
                {
                    $this->relationType = 'has_one';
                    $this->relatedModelName = $this->relationParams['has_one'];
                    $this->relationSingle = true;
                } else {
                    $this->relationType = 'has_many';
                    $this->relatedModelName = $this->relationParams['has_many'];
                    $this->relationSingle = false;
                }
                $this->relatorModelParams = ModelBase::getModelParams($relatorModelName);
                $this->relatedModelParams = ModelBase::getModelParams($this->relatedModelName);

                if ($this->relatedModelParams === null)
                    throw new YPFrameworkError ('Model not defined: '.$relatedModel);

                $this->tempAliasRelator = 'table'.(self::$_temporalAlias++);
                $this->tempAliasRelated = $this->relationName;

                if (isset($this->relationParams['through']))
                {
                    $tempAliasJoiner = 'table'.(self::$_temporalAlias++);
                    $this->relationType .= '_through';

                    $joinConditionsRor = array();
                    $joinConditionsRed = array();

                    foreach($this->relationParams['relatorKeys'] as $index=>$key)
                        $joinConditionsRor[] = sprintf('(%s.%s = %s.%s)',
                            $this->tempAliasRelator, $index,
                            $tempAliasJoiner, $key);

                    foreach($this->relationParams['relatedKeys'] as $index=>$key)
                        $joinConditionsRor[] = sprintf('(%s.%s = %s.%s)',
                            $this->tempAliasRelated, $index,
                            $tempAliasJoiner, $key);

                    $tableName = sprintf("(%s) AS %s JOIN (%s) AS %s JOIN (%s) AS %s ON %s AND %s",
                            $this->relatedModelParams->tableName,
                            $this->tempAliasRelated,
                            $this->relationParams['through'],
                            $tempAliasJoiner,
                            $this->relatorModelParams->tableName,
                            $this->tempAliasRelator,
                            implode(' AND ', $joinConditionsRed),
                            implode(' AND ', $joinConditionsRor));
                } else
                {
                    $joinConditions = array();
                    foreach($this->relationParams['keys'] as $index=>$key)
                        $joinConditions[] = sprintf('(%s.%s = %s.%s)',
                            $this->tempAliasRelated, $key,
                            $this->tempAliasRelator, $this->relatorModelParams->keyFields[$index]);
                }
            }

            if (!isset($tableName))
                $tableName = sprintf("(%s) AS %s JOIN (%s) AS %s ON %s",
                            $this->relatedModelParams->tableName,
                            $this->tempAliasRelated,
                            $this->relatorModelParams->tableName,
                            $this->tempAliasRelator,
                            implode(' AND ', $joinConditions));

            $this->modelQuery = new ModelQuery($this->relatedModelName, $tableName, $this->relationName, array(),
                $this->relatedModelParams->sqlConditions,
                $this->relatedModelParams->sqlGrouping,
                $this->relatedModelParams->sqlOrdering, null, false);
        }

        public function get($relatorModel)
        {
            if ($this->relationSingle)
                return $this->modelQuery->select($relatorModel->getSQlIdConditions($this->tempAliasRelator))->first();
            else {
                $relation = clone $this;
                $relation->tieToRelator($relatorModel);
                return $relation;
            }
        }

        public function set($relatorModel, $value)
        {
            if ($this->relationSingle)
            {
                if ($this->relationType == 'belongs_to')
                    foreach($this->relationParams['keys'] as $index=>$key)
                        $relatorModel->{$key} = $value->{$this->relatedModelParams->keyFileds[$index]};
                elseif ($this->relationType == 'has_one')
                    foreach($this->relationParams['keys'] as $index=>$key)
                        $value->{$key} = $relatorModel->{$this->relatorModelParams->keyFileds[$index]};
            } else
                throw new YPFrameworkError(sprintf('Can\'t assign values to %s relation: %s', $this->relationType, $this->relationName));
        }

        public function create($values = array())
        {
            if ($this->relationType != 'has_many')
                throw new YPFrameworkError(sprintf('Can\'t create new instance in %s relation: %s', $this->relationType, $this->relationName));

            $newInstanace = eval(sprintf('return new %s()', $this->relatedModelName));

            foreach ($values as $key=>$value)
                $newInstanace->{$key} = $value;

            foreach($this->relationParams['keys'] as $index=>$key)
                $newInstanace->{$key} = $this->relatorInstance->{$this->relatorModelParams->keyFileds[$index]};
        }

        public function add($newInstance)
        {
            if ($this->relationType != 'has_many')
                throw new YPFrameworkError(sprintf('Can\'t create new instance in %s relation: %s', $this->relationType, $this->relationName));

            foreach($this->relationParams['keys'] as $index=>$key)
                $newInstanace->{$key} = $this->relatorInstance->{$this->relatorModelParams->keyFileds[$index]};
        }

        public function tieToRelator($relatorModel)
        {
            $this->relatorInstance = $relatorModel;
        }

        public function toArray()
        {
            return $this->modelQuery->select($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator))->toArray();
        }

        // ----------- ModelQuery Implementation--------------------------------
        public function all()
        {
            return $this->modelQuery->select($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator));
        }

        public function count($sqlConditions = null, $sqlGrouping = null)
        {
            if ($sqlConditions == null)
                $sqlConditions = $this->relatorInstance->getSQlIdConditions($this->tempAliasRelator);
            else
                $sqlConditions = array_merge($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator), $sqlConditions);

            return $this->modelQuery->count($sqlConditions, $sqlGrouping);
        }

        public function first()
        {
            return $this->modelQuery->select($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator))->first();
        }

        public function groupBy($sqlGrouping)
        {
            $sqlConditions = $this->relatorInstance->getSQlIdConditions($this->tempAliasRelator);
            return $this->modelQuery->select($sqlConditions, $sqlGrouping);
        }

        public function last()
        {
            return $this->modelQuery->select($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator))->last();
        }

        public function limit($limit)
        {
            $sqlConditions = $this->relatorInstance->getSQlIdConditions($this->tempAliasRelator);
            return $this->modelQuery->select($sqlConditions, array(), array(), $limit);
        }

        public function orderBy($sqlOrdering)
        {
            $sqlConditions = $this->relatorInstance->getSQlIdConditions($this->tempAliasRelator);
            return $this->modelQuery->select($sqlConditions, array(), $sqlOrdering);
        }

        public function select($sqlConditions, $sqlGrouping = array(), $sqlOrdering = array(), $sqlLimit = null)
        {
            $sqlConditions = array_merge($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator), $sqlConditions);
            return $this->modelQuery->select($sqlConditions, $sqlGrouping, $sqlOrdering, $sqlLimit);
        }

        // ----------- Iterator Implementation --------------------------------
        public function current()
        {
            return $this->iterationQuery->current();
        }

        public function key()
        {
            return $this->iterationQuery->key();
        }

        public function next()
        {
            $this->iterationQuery->next();
        }

        public function rewind()
        {
            $this->iterationQuery = $this->all();
        }

        public function valid()
        {
            return $this->iterationQuery->valid();
        }
    }
?>
