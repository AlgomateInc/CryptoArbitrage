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
require_once('markets/Gemini.php');
require_once('markets/Kraken.php');
require_once('markets/EthereumAccount.php');
require_once('markets/BitcoinAddress.php');

class ConfigAccountLoader implements IAccountLoader{

    protected $accountsConfig;

    public function __construct(){
        global $accountsConfig;

        $this->accountsConfig = $accountsConfig;
    }

    function getConfig()
    {
        return $this->accountsConfig;
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

                case Exchange::Gemini:
                    $accounts[Exchange::Gemini] = new Gemini(
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

                case Exchange::Kraken:
                    $accounts[Exchange::Kraken] = new Kraken(
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;

                case Exchange::Ethereum:
                    $accounts[Exchange::Ethereum] = new EthereumAccount(
                        $mktConfig['address']
                    );
                    break;

                case Exchange::Bitcoin:
                    $accounts[Exchange::Bitcoin] = new BitcoinAddress(
                        $mktConfig['address']
                    );
                    break;
            }
        }

        return $accounts;
    }
}