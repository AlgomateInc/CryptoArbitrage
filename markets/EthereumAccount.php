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
    private $tokenContracts = array();

    /**
     * EthereumAccount constructor.
     */
    public function __construct($address)
    {
        $this->address = explode(',', $address);
        $this->tokenContracts['GNT'] = '0xa74476443119A942dE498590Fe1f2454d7D4aC0d';
    }

    public function Name()
    {
        return Exchange::Ethereum;
    }

    public function balances()
    {
        $balances = parent::balances();

        //get token balances
        foreach ($this->tokenContracts as $tokenName => $tokenContract)
        {
            $tokenBalance = 0;

            foreach($this->getAddressList() as $addy)
            {
                $raw = curl_query("https://api.etherscan.io/api?module=account&action=tokenbalance&contractaddress=$tokenContract&address=" . trim($addy));
                $tokenBalance += $raw['result'] / pow(10, 18);
            }

            $balances[$tokenName] = $tokenBalance;
        }

        return $balances;
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
                $raw = curl_query('https://api.etherscan.io/api?module=account&action=balance&address=' . trim($addr));
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