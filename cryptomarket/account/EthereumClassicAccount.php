<?php

require_once ('MultiSourcedAccount.php');

/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 12/28/2016
 * Time: 11:47 PM
 */
class EthereumClassicAccount extends MultiSourcedAccount
{
    private $address;

    public function __construct($address)
    {
        $this->address = explode(',', $address);
    }

    public function Name()
    {
        return Exchange::EthereumClassic;
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
                $raw = curl_query('https://etcchain.com/api/v1/getAddressBalance?address=' . trim($addr));
                return $raw['balance'];
            },
            function ($addr)
            {
                $raw = curl_query('https://api.gastracker.io/addr/' . trim($addr));
                return $raw['balance']['ether'];
            }
        );
    }

    protected function getCurrencyName()
    {
        return Currency::ETC;
    }
}