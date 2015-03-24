<?php namespace Shafa\SimpleES;

trait SearchTrait
{

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
     * Get a new instance of the query builder.
     *
     * @return \Shafa\SimpleES\Builder
     */
    protected function newSearch()
    {
        return new Builder($this->getSearchIndexName(), $this->getSearchTypeName());
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param  string $column
     * @param  mixed $value
     * @return \Shafa\SimpleES\Builder
     */
    public static function searchWhere($column, $value)
    {
        $instance = new static;
        $builder = $instance->newSearch();
        return call_user_func_array(array($builder, 'where'), func_get_args());
    }

    /**
     * Add an "where text" clause to the query.
     *
     * @param $column
     * @param $value
     * @return \Shafa\SimpleES\Builder
     */
    public static function searchWhereText($column, $value)
    {
        $instance = new static;
        $builder = $instance->newSearch();
        return call_user_func_array(array($builder, 'whereText'), func_get_args());
    }

    /**
     * Set the "offset" value of the query.
     *
     * @param  int $value
     * @return \Shafa\SimpleES\Builder
     */
    public static function searchOffset($value)
    {
        $instance = new static;
        $builder = $instance->newSearch();
        return call_user_func_array(array($builder, 'offset'), func_get_args());
    }

    /**
     * Set the "limit" value of the query.
     *
     * @param  int $value
     * @return \Shafa\SimpleES\Builder
     */
    public static function searchLimit($value)
    {
        $instance = new static;
        $builder = $instance->newSearch();
        return call_user_func_array(array($builder, 'limit'), func_get_args());
    }

    /**
     * Add an "order by" clause to the query.
     *
     * @param  string $column
     * @param  string $direction
     * @return \Shafa\SimpleES\Builder
     */
    public static function searchOrderBy($column, $direction = 'asc')
    {
        $instance = new static;
        $builder = $instance->newSearch();
        return call_user_func_array(array($builder, 'orderBy'), func_get_args());
    }

}
