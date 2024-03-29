<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 3/18/2015
 * Time: 11:46 PM
 */

use CryptoMarket\Exchange\IExchange;

require_once('IStrategy.php');

abstract class BaseStrategy implements IStrategy {

    protected $strategyId;
    public function getStrategyId()
    {
        return $this->strategyId;
    }

    public function setStrategyId($id)
    {
        $this->strategyId = $id;
    }

    /**
     * Checks if the required market exists and supports trading for pair
     * @param $marketName
     * @param $markets
     * @param $pair
     * @return null
     */
    protected function findMarket($markets, $marketName, $pair)
    {
        $logger = Logger::getLogger(get_class($this));

        if(!array_key_exists($marketName, $markets)){
            $logger->warn('Market in strategy instructions not supported');
            return null;
        }

        $market = $markets[$marketName];
        if(!($market instanceof IExchange))
            return null;

        if(!($market->supports($pair))){
            $logger->warn('Market in strategy instructions does not support pair: ' . $pair);
            return null;
        }

        return $market;
    }

}
