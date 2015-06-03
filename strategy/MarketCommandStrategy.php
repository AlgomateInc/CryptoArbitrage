<?php
/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 6/3/2015
 * Time: 12:26 AM
 */

require_once('BaseStrategy.php');
require_once('IStrategyInstructions.php');
require_once('IStrategyOrder.php');
require_once('position/LimitOrderInstructions.php');

class MarketCommandStrategy extends BaseStrategy{

    public function run($instructions, $markets, $balances)
    {
        $this->setStrategyId($instructions['StrategyId']);

        $l = new LimitOrderInstructions();
        $l->load($instructions);
        return $l;
    }

    public function update(ActiveOrder $activeOrder)
    {
        // TODO: Implement update() method.
    }

}