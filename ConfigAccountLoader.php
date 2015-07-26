<?php

require_once('config.php');
require_once('common.php');
require_once('IAccountLoader.php');

require_once('markets/btce.php');
require_once('markets/bitstamp.php');
require_once('markets/jpmchase.php');
require_once('markets/Cryptsy.php');
require_once('markets/Bitfinex.php');
require_once('markets/BitVC.php');
require_once('markets/TestMarket.php');
require_once('markets/Poloniex.php');

class ConfigAccountLoader implements IAccountLoader{

    protected $accountsConfig;

    public function __construct(){
        global $accountsConfig;

        $this->accountsConfig = $accountsConfig;
    }

    function getAccounts(array $mktFilter = null)
    {
        $accounts = array();

        foreach($this->accountsConfig as $mktName => $mktConfig){

            //filter to specific exchanges, as specified
            if($mktFilter != null)
                if(!in_array($mktName, $mktFilter))
                    continue;

            switch($mktName)
            {
                case Exchange::Bitstamp:
                    $accounts[Exchange::Bitstamp] = new BitstampExchange(
                        $mktConfig['custid'],
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;

                case Exchange::Btce:
                    $accounts[Exchange::Btce] = new BtceExchange(
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;

                case Exchange::Cryptsy:
                    $accounts[Exchange::Cryptsy] = new Cryptsy(
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;

                case Exchange::JPMChase:
                    $accounts[Exchange::JPMChase] = new JPMChase(
                        $mktConfig['name'],
                        $mktConfig['username'],
                        $mktConfig['password']
                    );
                    break;

                case Exchange::Bitfinex:
                    $accounts[Exchange::Bitfinex] = new Bitfinex(
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;

                case Exchange::BitVC:
                    $accounts[Exchange::BitVC] = new BitVC(
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;

                case Exchange::Poloniex:
                    $accounts[Exchange::Poloniex] = new Poloniex(
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;
            }
        }

        return $accounts;
    }
}