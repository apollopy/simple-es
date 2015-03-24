<?php namespace Shafa\SimpleES;
/**
 * Class Builder
 *
 * @see Illuminate\Database\Query\Builder
 * @see Jenssegers\Mongodb\Query\Builder
 * @package Shafa\SimpleES
 */
class Builder
{
    /**
     * @var \Elastica\Client
     */
    protected $client;

    /**
     * The index name
     *
     * @var string
     */
    protected $index;

    /**
     * The type name
     *
     * @var string
     */
    protected $type;

    /**
     * The where constraints for the query.
     *
     * @var array
     */
    protected $wheres;

    /**
     * The orderings for the query.
     *
     * @var array
     */
    protected $orders;

    /**
     * The maximum number of records to return.
     *
     * @var int
     */
    protected $limit;

    /**
     * The number of records to skip.
     *
     * @var int
     */
    protected $offset;

    /**
     * All of the available clause operators.
     *
     * @var array
     */
    protected $operators = array(
        'term', 'text', 'range'
    );

    /**
     * Create a new query builder instance.
     *
     * @param $index
     * @param $type
     * @param \Elastica\Client $client
     */
    public function __construct($index, $type, \Elastica\Client $client = null)
    {
        $this->index = $index;
        $this->type = $type;

        if (is_null($client)) {
            $client = new \Elastica\Client(array(
                'host' => \LConfig::get("es.host"),
                'port' => \LConfig::get("es.port")
            ));
        }
        $this->client = $client;
    }

    /**
     * Get a new instance of the query builder.
     *
     * @return \Shafa\SimpleES\Builder
     */
    public function newSearch()
    {
        return new Builder($this->index, $this->type, $this->client);
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param  string $column
     * @param  string $operator
     * @param  mixed $value
     * @param  string $boolean
     * @return \Shafa\SimpleES\Builder
     */
    public function where($column, $operator = null, $value = null, $boolean = 'must')
    {
        // Here we will make some assumptions about the operator. If only 2 values are
        // passed to the method, we will assume that the operator is an equals sign
        // and keep going. Otherwise, we'll require the operator to be passed in.
        if (func_num_args() == 2) {
            list($value, $operator) = array($operator, 'term');
        }

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the 'term' operators and
        // we will set the operators to 'term' and set the values appropriately.
        if (!in_array(strtolower($operator), $this->operators, true)) {
            list($value, $operator) = array($operator, 'term');
        }

        $this->wheres[] = compact('operator', 'column', 'value', 'boolean');

        return $this;
    }

    /**
     * @param \Elastica\Query\AbstractQuery $query
     * @param string $boolean
     * @return \Shafa\SimpleES\Builder
     */
    public function whereRaw(\Elastica\Query\AbstractQuery $query, $boolean = 'must') {
        $operator = 'raw';
        $this->wheres[] = compact('operator', 'query', 'boolean');

        return $this;
    }

    /**
     * Add an "where text" clause to the query.
     *
     * @param $column
     * @param $value
     * @param string $boolean
     * @return \Shafa\SimpleES\Builder
     */
    public function whereText($column, $value, $boolean = 'must')
    {
        return $this->where($column, 'text', $value, $boolean);
    }

    /**
     * Add a where between statement to the query.
     *
     * @param  string  $column
     * @param  array   $values
     * @param  string  $boolean
     * @return \Shafa\SimpleES\Builder
     */
    public function whereBetween($column, array $values, $boolean = 'must')
    {
        return $this->where($column, 'range', $values, $boolean);
    }

    /**
     * Set the "offset" value of the query.
     *
     * @param  int $value
     * @return \Shafa\SimpleES\Builder
     */
    public function offset($value)
    {
        $this->offset = max(0, $value);

        return $this;
    }

    /**
     * Set the "limit" value of the query.
     *
     * @param  int $value
     * @return \Shafa\SimpleES\Builder
     */
    public function limit($value)
    {
        if ($value > 0) $this->limit = $value;

        return $this;
    }

    /**
     * Add an "order by" clause to the query.
     *
     * @param  string $column
     * @param  string $direction
     * @return \Shafa\SimpleES\Builder
     */
    public function orderBy($column, $direction = 'asc')
    {
        $direction = strtolower($direction) == 'asc' ? 'asc' : 'desc';
        $this->orders[] = [$column => $direction];

        return $this;
    }

    /**
     * Execute the query
     *
     * @return \Elastica\ResultSet
     */
    public function get()
    {
        $query = new \Elastica\Query();

        if ($this->hasWhere()) {
            $query->setQuery($this->compileWhere());
        }

        if ($this->offset) {
            $query->setFrom($this->offset);
        }

        if ($this->limit) {
            $query->setSize($this->limit);
        }

        if ($this->orders) {
            $query->setSort($this->orders);
        }

        return $this->client->getIndex($this->index)->getType($this->type)->search($query);
    }

    /**
     * Compile Where
     *
     * @return \Elastica\Query\AbstractQuery
     */
    protected function compileWhere()
    {
        $queries = [];
        foreach ($this->wheres as $val) {
            switch ($val['operator']) {
                case 'term':
                    $_query = new \Elastica\Query\Term();
                    $_query->setTerm($val['column'], $val['value']);
                    break;
                case 'text':
                    $_query = new \Elastica\Query\Match();
                    $_query->setFieldQuery($val['column'], $val['value']);
                    break;
                case 'range':
                    $_query = new \Elastica\Query\Range();
                    $_query->addField($val['column'], ['from' => $val['value'][0], 'to' => $val['value'][1]]);
                    break;
                case 'raw':
                    $_query = $val['query'];
                    break;
                default:
                    throw new \InvalidArgumentException(sprintf('$operator: %s unsupported', $val['operator']));
                    break;
            }

            $queries[] = ['query' => $_query, 'boolean' => $val['boolean']];
        }

        if (1 == count($queries)) {
            return $queries[0]['query'];
        }

        $query = new \Elastica\Query\Bool();
        foreach ($queries as $val) {
            if ($val['boolean'] == 'must') {
                $query->addMust($val['query']);
            }
//            $function_name = 'add' . studly_case($val['boolean']);
//            $query->$function_name($val['query']);
        }

        return $query;
    }

    /**
     * @return bool
     */
    protected function hasWhere()
    {
        return (bool)count($this->wheres);
    }

}
