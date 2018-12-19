<?php

namespace ApolloPY\SimpleES;

use Illuminate\Database\Eloquent\Collection as BaseCollection;

/**
 * Collection class.
 *
 * @author ApolloPY <ApolloPY@Gmail.com>
 */
class Collection extends BaseCollection
{
    /**
     * The total number.
     *
     * @var int
     */
    protected $total;

    /**
     * Create a new collection.
     *
     * @param array $items
     * @param integer $total
     */
    public function __construct($items = [], $total = 0)
    {
        parent::__construct($items);
        $this->total = $total;
    }

    /**
     * Get the total number.
     *
     * @return int
     */
    public function total()
    {
        return $this->total;
    }
}
