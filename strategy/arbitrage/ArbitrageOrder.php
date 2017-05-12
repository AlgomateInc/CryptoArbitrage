<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 10/14/2014
 * Time: 1:24 PM
 */

use CryptoMarket\Record\Order;
use CryptoMarket\Record\OrderType;

require_once(__DIR__ . '/../IStrategyOrder.php');

class ArbitrageOrder implements IStrategyOrder{
    public $currencyPair;
    public $buyExchange;
    public $buyLimit = 0;
    public $sellExchange;
    public $sellLimit = INF;
    public $quantity = 0;
    public $executionQuantity = 0;

    public function getOrders()
    {
        $orders = array();

        if($this->quantity > 0 && $this->executionQuantity > 0)
        {
            $b = new Order();
            $b->currencyPair = $this->currencyPair;
            $b->exchange = $this->buyExchange;
            $b->orderType = OrderType::BUY;
            $b->limit = $this->buyLimit;
            $b->quantity = $this->executionQuantity;

            $s = new Order();
            $s->currencyPair = $this->currencyPair;
            $s->exchange = $this->sellExchange;
            $s->orderType = OrderType::SELL;
            $s->limit = $this->sellLimit;
            $s->quantity = $this->executionQuantity;

            $orders[] = $b;
            $orders[] = $s;
        }

        return $orders;
    }

    public function getCancels()
    {
        return array();
    }
}
