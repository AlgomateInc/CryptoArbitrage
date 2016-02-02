<?php

/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 2/2/2016
 * Time: 5:21 AM
 */
class BitcoinAddress implements IAccount
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

    public function balances()
    {
        $totalBalance = 0;
        foreach($this->address as $addy)
        {
            $addy = trim($addy);
            $raw = curl_query("https://blockchain.info/rawaddr/$addy?limit=0");
            $totalBalance = strval($totalBalance + $raw['final_balance'] / pow(10, 8));
        }

        $balances = array();
        $balances[Currency::BTC] = $totalBalance;
        return $balances;
    }

    public function transactions()
    {
        // TODO: Implement transactions() method.
    }

}