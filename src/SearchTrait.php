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
     * @param $func_name
     * @param array $args
     * @return \Shafa\SimpleES\Builder
     */
    private static function call_func($func_name, array $args = []) {
        $instance = new static;
        $builder = $instance->newSearch();

        return call_user_func_array(array($builder, $func_name), $args);
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
        return self::call_func('where', func_get_args());
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
        return self::call_func('whereText', func_get_args());
    }

    /**
     * Add a where between statement to the query.
     *
     * @param  string  $column
     * @param  array   $values
     * @return \Shafa\SimpleES\Builder
     */
    public static function searchWhereBetween($column, array $values)
    {
        return self::call_func('whereBetween', func_get_args());
    }


    /**
     * @param \Elastica\Query\AbstractQuery $query
     * @return \Shafa\SimpleES\Builder
     */
    public static function searchWhereRaw(\Elastica\Query\AbstractQuery $query) {
        return self::call_func('whereRaw', func_get_args());
    }

    /**
     * Set the "offset" value of the query.
     *
     * @param  int $value
     * @return \Shafa\SimpleES\Builder
     */
    public static function searchOffset($value)
    {
        return self::call_func('offset', func_get_args());
    }

    /**
     * Set the "limit" value of the query.
     *
     * @param  int $value
     * @return \Shafa\SimpleES\Builder
     */
    public static function searchLimit($value)
    {
        return self::call_func('limit', func_get_args());
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
        return self::call_func('orderBy', func_get_args());
    }

}
