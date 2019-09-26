<?php

namespace ApolloPY\SimpleES;

use Closure;
use \Illuminate\Pagination\LengthAwarePaginator as Paginator;

/**
 * Builder class.
 *
 * @author ApolloPY <ApolloPY@Gmail.com>
 */
class Builder
{
    /**
     * @var \Elastica\Client
     */
    protected $client;

    /**
     * The Eloquent Model Name
     *
     * @var string
     */
    protected $eloquent_name;

    /**
     * The Eloquent ID resolver for \Elastica\Result
     *
     * @var Closure
     */
    protected $eloquent_id_resolver;

    /**
     * The index name
     *
     * @var string
     */
    protected $index;

    /**
     * The type name
     *
     * @var null | string
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
     * The script for the query.
     *
     * @var \Elastica\Script\Script
     */
    protected $script;

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
    protected $operators = [
        '=', '<', '>', '<=', '>=',
        'text', 'range', '<>', '!=',
    ];

    /**
     * Create a new query builder instance.
     *
     * @param \Elastica\Client $client
     * @param                  $index
     * @param null $type
     * @param null $model_name
     */
    public function __construct(\Elastica\Client $client, $index, $type = null, $model_name = null)
    {
        $this->client = $client;
        $this->index = $index;
        $this->type = $type;
        $this->eloquent_name = $model_name;
    }

    /**
     * Set eloquent name
     *
     * @param null $model_name
     * @return \ApolloPY\SimpleES\Builder
     */
    public function setEloquentName($model_name = null)
    {
        $this->eloquent_name = $model_name;

        return $this;
    }

    /**
     * Set eloquent id resolver
     *
     * @param Closure $resolver
     * @return \ApolloPY\SimpleES\Builder
     */
    public function setEloquentIdResolver(Closure $resolver)
    {
        $this->eloquent_id_resolver = $resolver;

        return $this;
    }

    /**
     * Get a new instance of the query builder.
     *
     * @return \ApolloPY\SimpleES\Builder
     */
    public function newSearch()
    {
        return new Builder($this->client, $this->index, $this->type);
    }

    /**
     * Get Connection
     *
     * @return \Elastica\Index|\Elastica\Type
     */
    public function getConnection()
    {
        $connection = $this->client->getIndex($this->index);
        if ($this->type) {
            $connection = $connection->getType($this->type);
        }

        return $connection;
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param  string $column
     * @param  string $operator
     * @param  mixed $value
     * @param  string $boolean
     * @return \ApolloPY\SimpleES\Builder
     */
    public function where($column, $operator = null, $value = null, $boolean = 'must')
    {
        // Here we will make some assumptions about the operator. If only 2 values are
        // passed to the method, we will assume that the operator is an equals sign
        // and keep going. Otherwise, we'll require the operator to be passed in.
        if (func_num_args() == 2) {
            list($value, $operator) = [$operator, '='];
        } elseif ($this->invalidOperatorAndValue($operator, $value)) {
            throw new \InvalidArgumentException("Value must be provided.");
        }

        // If the columns is actually a Closure instance, we will assume the developer
        // wants to begin a nested where statement which is wrapped in parenthesis.
        // We'll add that Closure to the query then return back out immediately.
        if ($column instanceof Closure) {
            return $this->whereNested($column, $boolean);
        }

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if (! in_array(strtolower($operator), $this->operators, true)) {
            list($value, $operator) = [$operator, '='];
        }

        if ($operator == '=') {
            $operator = 'term';
        } elseif ($operator == '!=' || $operator == '<>') {
            $operator = 'term';
            $boolean = 'must_not';
        } else {
            $conversion = [
                '<'  => 'lt',
                '<=' => 'lte',
                '>'  => 'gt',
                '>=' => 'gte',
            ];
            if (isset($conversion[$operator])) {
                $value = [$conversion[$operator] => $value];
                $operator = 'range';
            }
        }

        $this->wheres[] = compact('operator', 'column', 'value', 'boolean');

        return $this;
    }

    /**
     * Add an "or where" clause to the query.
     *
     * @param  string $column
     * @param  string $operator
     * @param  mixed $value
     * @return \ApolloPY\SimpleES\Builder
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        return $this->where($column, $operator, $value, 'should');
    }

    /**
     * Determine if the given operator and value combination is legal.
     *
     * @param  string $operator
     * @param  mixed $value
     * @return bool
     */
    protected function invalidOperatorAndValue($operator, $value)
    {
        $isOperator = in_array($operator, $this->operators);

        return ($isOperator && $operator != '=' && is_null($value));
    }

    /**
     * Add a nested where statement to the query.
     *
     * @param  \Closure $callback
     * @param  string $boolean
     * @return \ApolloPY\SimpleES\Builder
     */
    public function whereNested(Closure $callback, $boolean = 'must')
    {
        // To handle nested queries we'll actually create a brand new query instance
        // and pass it off to the Closure that we have. The Closure can simply do
        // do whatever it wants to a query then we will store it for compiling.
        $search = $this->newSearch();

        call_user_func($callback, $search);

        if (count($search->wheres)) {
            $operator = 'nested';

            $this->wheres[] = compact('operator', 'search', 'boolean');
        }

        return $this;
    }

    /**
     * Add a raw where clause to the query.
     *
     * @param \Elastica\Query\AbstractQuery $query
     * @param string $boolean
     * @return \ApolloPY\SimpleES\Builder
     */
    public function whereRaw(\Elastica\Query\AbstractQuery $query, $boolean = 'must')
    {
        $operator = 'raw';
        $this->wheres[] = compact('operator', 'query', 'boolean');

        return $this;
    }

    /**
     * Add a raw or where clause to the query.
     *
     * @param \Elastica\Query\AbstractQuery $query
     * @return \ApolloPY\SimpleES\Builder
     */
    public function orWhereRaw(\Elastica\Query\AbstractQuery $query)
    {
        return $this->whereRaw($query, 'should');
    }

    /**
     * Add a test where clause to the query.
     *
     * @param        $column
     * @param        $value
     * @param string $boolean
     * @return \ApolloPY\SimpleES\Builder
     */
    public function whereText($column, $value, $boolean = 'must')
    {
        return $this->where($column, 'text', $value, $boolean);
    }

    /**
     * Add a test or where clause to the query.
     *
     * @param $column
     * @param $value
     * @return \ApolloPY\SimpleES\Builder
     */
    public function orWhereText($column, $value)
    {
        return $this->whereText($column, $value, 'should');
    }

    /**
     * Add a where between statement to the query.
     *
     * @param  string $column
     * @param  array $values
     * @param  string $boolean
     * @return \ApolloPY\SimpleES\Builder
     */
    public function whereBetween($column, array $values, $boolean = 'must')
    {
        $operator = 'range';

        list($gte, $lte) = $values;
        $value = compact('gte', 'lte');

        $this->wheres[] = compact('operator', 'column', 'value', 'boolean');

        return $this;
    }

    /**
     * Add an or where between statement to the query.
     *
     * @param       $column
     * @param array $values
     * @return \ApolloPY\SimpleES\Builder
     */
    public function orWhereBetween($column, array $values)
    {
        return $this->whereBetween($column, $values, 'should');
    }

    /**
     * Add a "where not null" clause to the query.
     *
     * @param $column
     * @param  string $boolean
     * @return \ApolloPY\SimpleES\Builder
     */
    public function whereNotNull($column, $boolean = 'must')
    {
        $operator = 'exists';

        $this->wheres[] = compact('operator', 'column', 'boolean');

        return $this;
    }

    /**
     * Add a "where null" clause to the query.
     *
     * @param $column
     * @return \ApolloPY\SimpleES\Builder
     */
    public function whereNull($column)
    {
        return $this->whereNotNull($column, 'must_not');
    }

    /**
     * Set the "offset" value of the query.
     *
     * @param  int $value
     * @return \ApolloPY\SimpleES\Builder
     */
    public function offset($value)
    {
        $this->offset = max(0, (int) $value);

        return $this;
    }

    /**
     * Alias to set the "offset" value of the query.
     *
     * @param  int $value
     * @return \ApolloPY\SimpleES\Builder
     */
    public function skip($value)
    {
        return $this->offset($value);
    }

    /**
     * Set the "limit" value of the query.
     *
     * @param  int $value
     * @return \ApolloPY\SimpleES\Builder
     */
    public function limit($value)
    {
        if ($value > 0) $this->limit = (int) $value;

        return $this;
    }

    /**
     * Alias to set the "limit" value of the query.
     *
     * @param  int $value
     * @return \ApolloPY\SimpleES\Builder
     */
    public function take($value)
    {
        return $this->limit($value);
    }

    /**
     * Set the limit and offset for a given page.
     *
     * @param  int $page
     * @param  int $perPage
     * @return \ApolloPY\SimpleES\Builder
     */
    public function forPage($page, $perPage = 15)
    {
        return $this->skip(($page - 1) * $perPage)->take($perPage);
    }

    /**
     * Add an "order by" clause to the query.
     *
     * @param  string $column
     * @param  string $direction
     * @return \ApolloPY\SimpleES\Builder
     */
    public function orderBy($column, $direction = 'asc')
    {
        $direction = strtolower($direction) == 'asc' ? 'asc' : 'desc';
        $this->orders[] = [$column => $direction];

        return $this;
    }

    /**
     * @param string $script
     * @return \ApolloPY\SimpleES\Builder
     * @deprecated use setScriptScore instead
     */
    public function orderByScriptScore($script)
    {
        $score_script = new \Elastica\Script\Script('script_score');
        $score_script->setScript($script);
        $score_script->setLang('expression');

        $this->script = $score_script;

        return $this;
    }

    /**
     * set a custom script to score documents
     *
     * @param \Elastica\Script\Script $script
     * @return Builder
     */
    public function setScriptScore(\Elastica\Script\Script $script)
    {
        $this->script = $script;

        return $this;
    }

    /**
     * Execute the query
     *
     * @param array $columns
     * @return \Elastica\ResultSet
     */
    protected function _get($columns = ['*'])
    {
        $query = new \Elastica\Query();

        if ($this->script) {
            $function_score_query = new \Elastica\Query\FunctionScore();
            $function_score_query->addScriptScoreFunction($this->script);
            $function_score_query->setBoostMode(\Elastica\Query\FunctionScore::BOOST_MODE_REPLACE);
            if ($this->hasWhere()) {
                $function_score_query->setQuery($this->compileWhere($this->wheres));
                $query->setQuery($function_score_query);
            }
        } else {
            if ($this->hasWhere()) {
                $query->setQuery($this->compileWhere($this->wheres));
            }
            if ($this->orders) {
                $query->setSort($this->orders);
            }
        }

        if ($this->offset) {
            $query->setFrom($this->offset);
        }

        if ($this->limit) {
            $query->setSize($this->limit);
        }

        if ($this->eloquent_name) {
            $query->setSource(false);
        } else {
            $query->setSource($columns);
        }

        return $this->getConnection()->search($query);
    }

    /**
     * @param \Elastica\Result $result
     * @return mixed
     */
    protected function resolveEloquentId(\Elastica\Result $result)
    {
        if (isset($this->eloquent_id_resolver)) {
            return call_user_func($this->eloquent_id_resolver, $result);
        }

        return $result->getId();
    }

    /**
     * @return int
     */
    public function count()
    {
        $query = new \Elastica\Query();

        if ($this->hasWhere()) {
            $query->setQuery($this->compileWhere($this->wheres));
        }

        return $this->getConnection()->count($query);
    }

    /**
     * Execute the query and get the first result.
     *
     * @param array $columns
     * @return \Elastica\Result | \Illuminate\Database\Eloquent\Model | null
     */
    public function first($columns = ['*'])
    {
        $results = $this->take(1)->_get($columns);
        if (count($results->getResults()) <= 0) {
            return null;
        }

        $result = $results->getResults()[0];
        if (! is_null($this->eloquent_name) && class_exists($this->eloquent_name)) {
            $model = new $this->eloquent_name();
            if (method_exists($model, 'findFromCache')) {
                return $model->findFromCache($this->resolveEloquentId($result));
            } else {
                return $model->find($this->resolveEloquentId($result), $columns);
            }
        }

        return $result;
    }

    /**
     * Execute the query
     *
     * @param array $columns
     * @return \Elastica\ResultSet | Collection
     */
    public function get($columns = ['*'])
    {
        $results = $this->_get($columns);

        if (! is_null($this->eloquent_name) && class_exists($this->eloquent_name)) {
            $model = new $this->eloquent_name();
            $ids = [];
            foreach ($results->getResults() as $val) {
                /* @var $val \Elastica\Result */
                $ids[] = $this->resolveEloquentId($val);
            }

            if (! $ids) {
                return new Collection([], $results->getTotalHits());
            }

            if (method_exists($model, 'findFromCache')) {
                $items = $model->findFromCache($ids);
            } else {
                $items = $model->whereIn($model->getKeyName(), $ids)
                    ->get($columns)
                    ->sort($this->build_callback_for_collection_sort($ids));
            }

            $items = $items->values()->all();

            return new Collection($items, $results->getTotalHits());
        }

        return $results;
    }

    /**
     * @param array $keys
     * @param string $key_name
     * @return callable
     */
    protected function build_callback_for_collection_sort(array $keys, $key_name = 'id')
    {
        $keys = array_flip($keys);

        return function ($a, $b) use ($keys, $key_name) {
            return $keys[$a->$key_name] - $keys[$b->$key_name];
        };
    }

    /**
     * Get a paginator for the "select" statement.
     *
     * @param int $perPage
     * @param array $columns
     * @param string $pageName
     * @param null $page
     * @return Paginator
     */
    public function paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null)
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);
        $results = $this->forPage($page, $perPage)->_get($columns);

        if (! $results->count()) {
            return new Paginator(new Collection(), $results->getTotalHits(), $perPage, $page, [
                'path' => Paginator::resolveCurrentPath(),
            ]);
        }

        if (! is_null($this->eloquent_name) && class_exists($this->eloquent_name)) {
            $model = new $this->eloquent_name();
            $ids = [];
            foreach ($results->getResults() as $val) {
                /* @var $val \Elastica\Result */
                $ids[] = $this->resolveEloquentId($val);
            }

            if (method_exists($model, 'findFromCache')) {
                $_results = $model->findFromCache($ids);
            } else {
                $_results = $model->whereIn($model->getKeyName(), $ids)
                    ->get($columns)
                    ->sort($this->build_callback_for_collection_sort($ids));
            }
            $_results = $_results->values();

            return new Paginator($_results, $results->getTotalHits(), $perPage, $page, [
                'path'     => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]);
        }

        return new Paginator($results->getResults(), $results->getTotalHits(), $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
        ]);
    }

    /**
     * Compile Where
     *
     * @param array $wheres
     * - column 字段
     * - value 值
     * - operator 判断模式
     * - boolean 与其它条件的关系
     * - query 原生条件
     * - search 递归的 Builder 类
     * @return \Elastica\Query\AbstractQuery
     */
    protected function compileWhere($wheres)
    {
        $queries = [];
        foreach ($wheres as $val) {
            switch ($val['operator']) {
                case 'nested':
                    $_query = $this->compileWhere($val['search']->wheres);
                    break;
                case 'term':
                    $_query = new \Elastica\Query\Term();
                    $_query->setTerm($val['column'], $val['value']);
                    break;
                case 'text':
                    $_query = new \Elastica\Query\Match();
                    $_query->setFieldQuery($val['column'], $val['value']);
                    $_query->setFieldType($val['column'], 'phrase');
                    break;
                case 'range':
                    $_query = new \Elastica\Query\Range();
                    $_query->addField($val['column'], $val['value']);
                    break;
                case 'raw':
                    $_query = $val['query'];
                    break;
                case 'exists':
                    $_query = new \Elastica\Query\Exists($val['column']);
                    break;
                default:
                    throw new \InvalidArgumentException(sprintf('$operator: %s unsupported', $val['operator']));
                    break;
            }

            $queries[] = ['query' => $_query, 'boolean' => $val['boolean']];
        }

        if (1 == count($queries) && $queries[0]['boolean'] != 'must_not') {
            return $queries[0]['query'];
        }

        $query = new \Elastica\Query\BoolQuery();

        // 暂时只兼容 ->where()->orWhere() 这一种情况
        if (count($queries) == 2 && $queries[0]['boolean'] == 'must' && $queries[1]['boolean'] == 'should') {
            $queries[0]['boolean'] = 'should';
        }

        foreach ($queries as $i => $val) {
            // should | must | must_not
            $function_name = 'add'.studly_case($val['boolean']);
            $query->$function_name($val['query']);
        }

        return $query;
    }

    /**
     * @return bool
     */
    protected function hasWhere()
    {
        return (bool) count($this->wheres);
    }

    /**
     * Call the given model scope on the underlying model.
     *
     * @param  string $scope
     * @param  array $parameters
     * @return \ApolloPY\SimpleES\Builder
     */
    protected function callScope($scope, $parameters)
    {
        array_unshift($parameters, $this);

        return call_user_func_array([new $this->eloquent_name, $scope], $parameters) ?: $this;
    }

    /**
     * Dynamically handle calls into the query instance.
     *
     * @param  string $method
     * @param  array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (! is_null($this->eloquent_name) && class_exists($this->eloquent_name)) {
            $scope = 'searchScope'.ucfirst($method);
            if (method_exists($this->eloquent_name, $scope)) {
                return $this->callScope($scope, $parameters);
            }
        }

        throw new \BadMethodCallException('Call to undefined method '.self::class.'::'.$method.'()');
    }
}
