<?php

require_once('ConfigAccountLoader.php');
/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 2/1/2016
 * Time: 4:10 AM
 */
class EthereumAccountTest extends PHPUnit_Framework_TestCase
{
    public function testBalances()
    {
        $ea = new EthereumAccount('0xf978b025b64233555cc3c19ada7f4199c9348bf7');
        $bal = $ea->balances();

        $this->assertNotNull($bal);
    }
}
