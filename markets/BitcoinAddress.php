<?php

require_once ('MultiSourcedAccount.php');
/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 2/2/2016
 * Time: 5:21 AM
 */
class BitcoinAddress extends MultiSourcedAccount
{
    private $address;

    /**
     * BitcoinAddress constructor.
     */
    public function __construct($address)
    {
        $this->address = explode(',', $address);
    }

    public function Name()
    {
        return Exchange::Bitcoin;
    }

    public function transactions()
    {
        // TODO: Implement transactions() method.
    }

    protected function getAddressList()
    {
        return $this->address;
    }

    protected function getBalanceFunctions()
    {
        return array(
            function ($addr)
            {
                $raw = curl_query("https://blockchain.info/rawaddr/$addr?limit=0");
                return $raw['final_balance'] / pow(10, 8);
            },
            function ($addr)
            {
                $raw = curl_query("https://blockexplorer.com/api/addr/$addr?noTxList=1");
                return $raw['balance'];
            }
        );
    }

    protected function getCurrencyName()
    {
        return Currency::BTC;
    }
}