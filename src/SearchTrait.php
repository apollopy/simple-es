<?php namespace Shafa\SimpleES;

trait SearchTrait {

    /**
     * Get search index name
     *
     * @return string
     */
    abstract public function getSearchIndexName();

    /**
     * Get search type name
     *
     * @return string
     */
    abstract public function getSearchTypeName();

    /**
     * Get a new instance of the search builder.
     *
     * @return \Shafa\SimpleES\Builder
     */
    protected function newSearch()
    {
        return new Builder($this->getSearchIndexName(), $this->getSearchTypeName());
    }

    /**
     * @return \Shafa\SimpleES\Builder
     */
    public static function searchWhere()
    {
        $instance = new static;
        $builder = $instance->newSearch();
        return call_user_func_array(array($builder, 'where'), func_get_args());
    }

}
