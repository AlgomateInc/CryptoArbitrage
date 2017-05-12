<?php

use CryptoMarket\AccountLoader\IAccountLoader;

require_once('TestMarket.php');

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
    public function __construct($requiresListener)
    {
        $this->accounts = array('TestMarket' => new TestMarket($requiresListener));
    }

    function getConfig()
    {
        return 'TestMarket';
    }

    function getAccounts(array $mktFilter = null)
    {
        return $this->accounts;
    }
}
