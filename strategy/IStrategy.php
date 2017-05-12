<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 10/4/2014
 * Time: 9:46 AM
 */

use CryptoMarket\Record\ActiveOrder;

interface IStrategy {
    public function getStrategyId();
    public function setStrategyId($id);

    public function run($instructions, $markets, $balances);
    public function update(ActiveOrder $activeOrder);
} 
