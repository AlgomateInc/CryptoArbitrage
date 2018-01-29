<?php

namespace CryptoArbitrage\Tests;

use CryptoMarket\AccountLoader\IAccountLoader;
use CryptoArbitrage\Tests\TestMarket;

/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 3/7/2016
 * Time: 9:44 PM
 */

class TestAccountLoader implements IAccountLoader
{

    private $accounts;

    /**
     * TestAccountLoader constructor.
     * @param $requiresListener
     */
    public function __construct($clearBook)
    {
        $this->accounts = array('TestMarket' => new TestMarket($clearBook));
    }

    function getConfig($privateKey = null)
    {
        return 'TestMarket';
    }

    function getAccounts(array $mktFilter = null, $privateKey = null)
    {
        return $this->accounts;
    }
}
