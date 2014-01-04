<?php

require_once('config.php');
require_once('common.php');
require_once('IAccountLoader.php');

require_once('markets/btce.php');
require_once('markets/bitstamp.php');
require_once('markets/jpmchase.php');
require_once('markets/Cryptsy.php');


class ConfigAccountLoader implements IAccountLoader{

    function getAccounts()
    {
        global $accountsConfig;

        $accounts = array();

        foreach($accountsConfig as $mktName => $mktConfig){
            switch($mktName)
            {
                case Exchange::Bitstamp:
                    $accounts[] = new BitstampExchange(
                        $mktConfig['custid'],
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;

                case Exchange::Btce:
                    $accounts[] = new BtceExchange(
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;

                case Exchange::Cryptsy:
                    $accounts[] = new Cryptsy(
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;

                case Exchange::JPMChase:
                    $accounts[] = new JPMChase(
                        $mktConfig['name'],
                        $mktConfig['username'],
                        $mktConfig['password']
                    );
                    break;
            }
        }

        return $accounts;
    }
}