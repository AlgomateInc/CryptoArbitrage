<?php

/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 2/1/2016
 * Time: 3:29 AM
 */
class EthereumAccount implements IAccount
{
    private $address;

    /**
     * EthereumAccount constructor.
     */
    public function __construct($address)
    {
        $this->address = explode(',', $address);
    }

    public function Name()
    {
        return Exchange::Ethereum;
    }

    public function balances()
    {
        $totalBalance = 0;
        foreach($this->address as $addy)
        {
            $raw = curl_query('http://api.etherscan.io/api?module=account&action=balance&address=' . trim($addy));
            $totalBalance = strval($totalBalance + $raw['result'] / pow(10, 18));
        }

        $balances = array();
        $balances[Currency::ETH] = $totalBalance;
        return $balances;
    }

    public function transactions()
    {
        // TODO: Implement transactions() method.
    }
}