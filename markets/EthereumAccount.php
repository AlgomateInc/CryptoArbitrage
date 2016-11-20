<?php

require_once ('MultiSourcedAccount.php');
/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 2/1/2016
 * Time: 3:29 AM
 */
class EthereumAccount extends MultiSourcedAccount
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
                $raw = curl_query('http://api.etherscan.io/api?module=account&action=balance&address=' . trim($addr));
                return $raw['result'] / pow(10, 18);
            },
            function ($addr)
            {
                $raw = curl_query('https://etherchain.org/api/account/' . trim($addr));
                return $raw['data'][0]['balance'] / pow(10, 18);
            }
        );
    }

    protected function getCurrencyName()
    {
        return Currency::ETH;
    }
}