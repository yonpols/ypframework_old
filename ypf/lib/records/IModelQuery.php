<?php
    interface IModelQuery
    {
        public function fields($fields);
        public function count($sqlConditions = null, $sqlGrouping = null);
        public function first();
        public function last();
        public function all();
        public function select($sqlConditions, $sqlGrouping = array(),
                            $sqlOrdering = array(), $sqlLimit = null);
        public function orderBy($sqlOrdering);
        public function groupBy($sqlGrouping);
        public function limit($limit);
    }
?>
