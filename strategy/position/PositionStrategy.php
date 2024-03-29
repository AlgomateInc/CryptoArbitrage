<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 12/8/2014
 * Time: 11:54 PM
 */

use CryptoMarket\Record\ActiveOrder;

require_once(__DIR__ . '/../IStrategy.php');
require_once('PositionInstructionLoader.php');

class PositionStrategy extends BaseStrategy {

    public function run($instructions, $markets, $balances)
    {
        $arbLoader = new PositionInstructionLoader($instructions);
        $inst = $arbLoader->load($instructions);

        if($inst instanceof IStrategyOrder)
            return $inst;

        return null;
    }

    public function update(ActiveOrder $activeOrder)
    {
        // TODO: Implement update() method.
    }
}
