<?php

namespace ApolloPY\SimpleES;

/**
 * Search trait.
 *
 * @author ApolloPY <ApolloPY@Gmail.com>
 */
trait SearchTrait
{

    /**
     * Get search index name
     *
     * @return string
     */
    abstract public function getSearchIndexName();

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
        return new Builder(
            $this->getSearchClient(),
            $this->getSearchIndexName(),
            '_doc',
            get_class($this)
        );
    }

    /**
     * @return \ApolloPY\SimpleES\Builder
     */
    public static function search()
    {
        $instance = new static;

        return $instance->newSearch();
    }

}
