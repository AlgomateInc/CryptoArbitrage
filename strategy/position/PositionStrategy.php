<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 12/8/2014
 * Time: 11:54 PM
 */

require_once(__DIR__ . '/../IStrategy.php');
require_once('PositionInstructionLoader.php');

class PositionStrategy implements IStrategy {

    public function run($instructions, $markets, $balances)
    {
        $arbLoader = new PositionInstructionLoader($instructions);
        $inst = $arbLoader->load($instructions);

        if($inst instanceof IStrategyOrder)
            return $inst;

        return null;
    }
}