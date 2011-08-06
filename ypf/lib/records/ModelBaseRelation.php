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

        protected $customQueries;

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

            $sqlConditions = isset($relationParams['sqlConditions'])? arraize($relationParams['sqlConditions']): array();
            $sqlGrouping = isset($relationParams['sqlGrouping'])? arraize($relationParams['sqlGrouping']): array();
            $sqlOrdering = isset($relationParams['sqlOrdering'])? arraize($relationParams['sqlOrdering']): array();
            $this->customQueries = isset($relationParams['queries'])? arraize($relationParams['queries']): array();

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
                $this->tempAliasRelator = ($this->relatorModelParams->aliasName!=null)? $this->relatorModelParams->aliasName: $this->relationName.'_table'.(self::$_temporalAlias++);
                $this->tempAliasRelated = ($this->relatedModelParams->aliasName!=null)? $this->relatedModelParams->aliasName: $this->relationName;

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

                $this->tempAliasRelator = ($this->relatorModelParams->aliasName!=null)? $this->relatorModelParams->aliasName: $this->relationName.'_table'.(self::$_temporalAlias++);
                $this->tempAliasRelated = ($this->relatedModelParams->aliasName!=null)? $this->relatedModelParams->aliasName: $this->relationName;

                if (isset($this->relationParams['through']))
                {
                    $tempAliasJoiner = $this->relationName.'_table'.(self::$_temporalAlias++);
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


                    $relator = ($this->relatorModelParams->aliasName == $this->tempAliasRelator)? $this->relatorModelParams->tableName: sprintf('%s AS %s', $this->relatorModelParams->tableName, $this->tempAliasRelator);
                    $related = ($this->relatedModelParams->aliasName == $this->tempAliasRelated)? $this->relatedModelParams->tableName: sprintf('%s AS %s', $this->relatedModelParams->tableName, $this->tempAliasRelated);
                    $tableName = sprintf("%s JOIN %s AS %s JOIN %s ON %s",
                            $related,
                            $this->relationParams['through'],
                            $tempAliasJoiner,
                            $relator,
                            implode(' AND ', array_merge($joinConditionsRed, $joinConditionsRor)));
                } else
                {
                    $joinConditions = array();
                    foreach($this->relationParams['keys'] as $index=>$key)
                    {
                        if (is_numeric($index))
                            $joinConditions[] = sprintf('(%s.%s = %s.%s)',
                                $this->tempAliasRelated, $key,
                                $this->tempAliasRelator, $this->relatorModelParams->keyFields[$index]);
                        else
                            $joinConditions[] = sprintf('(%s.%s = %s.%s)',
                                $this->tempAliasRelated, $key,
                                $this->tempAliasRelator, $index);
                    }
                }
            }

            if (!isset($tableName))
            {
                $relator = ($this->relatorModelParams->aliasName == $this->tempAliasRelator)? $this->relatorModelParams->tableName: sprintf('%s AS %s', $this->relatorModelParams->tableName, $this->tempAliasRelator);
                $related = ($this->relatedModelParams->aliasName == $this->tempAliasRelated)? $this->relatedModelParams->tableName: sprintf('%s AS %s', $this->relatedModelParams->tableName, $this->tempAliasRelated);
                $tableName = sprintf("%s JOIN %s ON %s",
                            $related, $relator,
                            implode(' AND ', $joinConditions));
            }

            $this->modelQuery = new ModelQuery($this->relatedModelName, $tableName, $this->tempAliasRelated, array(),
                array_merge($this->relatedModelParams->sqlConditions, $sqlConditions),
                array_merge($this->relatedModelParams->sqlGrouping, $sqlGrouping),
                array_merge($this->relatedModelParams->sqlOrdering, $sqlOrdering), null,
                $this->customQueries, false);
        }

        public function get($relatorModel)
        {
            if ($this->relationSingle)
                return $this->modelQuery->select($relatorModel->getSQlIdConditions($this->tempAliasRelator))->first();
            else
                return $this->getTiedToRelator ($relatorModel);
        }

        public function set($relatorModel, $value)
        {
            if ($this->relationSingle)
            {
                if (is_string($value))
                {
                    $object = eval(sprintf('return new %s();', $this->relatedModelName));
                    if ($object->find($value))
                        $value = $object;
                    else
                        $value = null;
                }

                if ($value === null)
                {
                    if ($this->relationType == 'belongs_to')
                        foreach($this->relationParams['keys'] as $index=>$key)
                            $relatorModel->{$key} = null;
                    elseif ($this->relationType == 'has_one')
                        throw new YPFrameworkError("Unsupported functionality");
                } elseif (!($value instanceof $this->relatedModelName))
                    throw new YPFrameworkError(sprintf('Can\'t assign values to %s relation: %s because object is not instance of %s', $this->relationType, $this->relationName, $this->relatedModelName));

                if ($this->relationType == 'belongs_to')
                    foreach($this->relationParams['keys'] as $index=>$key)
                        $relatorModel->{$key} = $value->{$this->relatedModelParams->keyFields[$index]};
                elseif ($this->relationType == 'has_one')
                    foreach($this->relationParams['keys'] as $index=>$key)
                        $value->{$key} = $relatorModel->{$this->relatorModelParams->keyFields[$index]};
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

        public function getTiedToRelator($relatorModel)
        {
            $relation = clone $this;
            $relation->tieToRelator($relatorModel);
            return $relation;
        }

        public function toArray()
        {
            return $this->modelQuery->select($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator))->toArray();
        }

        public function getRelationName()
        {
            return $this->relationName;
        }

        public function getRelationType()
        {
            return $this->relationType;
        }

        public function getRelatorModelName()
        {
            return $this->relatorModelName;
        }

        public function getRelatedModelName()
        {
            return $this->relatedModelName;
        }

        public function __get($name)
        {
            if (isset($this->customQueries[$name]))
                return $this->modelQuery->select($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator))->{$name};
        }

        // ----------- ModelQuery Implementation--------------------------------
        public function fields($fields)
        {
            return $this->modelQuery->fields($fields);
        }

        public function all()
        {
            return $this->modelQuery->select($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator));
        }

        public function count($sqlConditions = null, $sqlGrouping = null)
        {
            if ($sqlConditions == null)
                $sqlConditions = array();
            elseif (is_string($sqlConditions))
                $sqlConditions = array('('.$sqlConditions.')');

            $sqlConditions = array_merge($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator), $sqlConditions);
            return $this->modelQuery->count($sqlConditions, $sqlGrouping);
        }

        public function first()
        {
            return $this->modelQuery->select($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator))->first();
        }

        public function groupBy($sqlGrouping)
        {
            return $this->modelQuery->select($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator), $sqlGrouping);
        }

        public function last()
        {
            return $this->modelQuery->select($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator))->last();
        }

        public function limit($limit)
        {
            return $this->modelQuery->select($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator),
                array(), array(), $limit);
        }

        public function orderBy($sqlOrdering)
        {
            return $this->modelQuery->select($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator),
                array(), $sqlOrdering);
        }

        public function select($sqlConditions, $sqlGrouping = array(), $sqlOrdering = array(), $sqlLimit = null)
        {
            if (is_string($sqlConditions))
                $sqlConditions = array('('.$sqlConditions.')');
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
