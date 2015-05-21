<?php namespace ApolloPY\SimpleES;

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
     * Get search client
     *
     * @return \Elastica\Client
     */
    abstract public function getSearchClient();

    /**
     * Get a new instance of the query builder.
     *
     * @return \ApolloPY\SimpleES\Builder
     */
    protected function newSearch()
    {
        $obj = new Builder($this->getSearchClient(), $this->getSearchIndexName(), $this->getSearchTypeName());
        $obj->setEloquentName(get_class($this));
        return $obj;
    }

    /**
     * @return \ApolloPY\SimpleES\Builder
     */
    public static function search() {
        $instance = new static;
        return $instance->newSearch();
    }

}
