<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 10/4/2014
 * Time: 9:46 AM
 */

interface IStrategy {
    public function run($instructions, $markets, $balances);
} 