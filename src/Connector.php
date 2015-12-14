<?php namespace ApolloPY\SimpleES;

/**
 * Class Connector
 *
 * @package ApolloPY\SimpleES
 */
class Connector
{
    /**
     * @param array $config
     *
     * @return \Elastica\Client
     */
    public function connect(array $config)
    {
        return new \Elastica\Client($config);
    }

}
