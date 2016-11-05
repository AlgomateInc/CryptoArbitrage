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

            $bal = $this->getBalance($addy);
            $totalBalance = strval($totalBalance + $bal);
        }

        $balances = array();
        $balances[Currency::BTC] = $totalBalance;
        return $balances;
    }

    function getBalance($addr)
    {
        $functions = array(
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
        static $marketIndex = 0;

        $val = null;
        for($i = 0;$i < count($functions);$i++)
            try{
                echo "Market Index: $marketIndex";
                $val = $functions[($marketIndex + $i) % count($functions)]($addr);
                $marketIndex = ($marketIndex + 1) % count($functions);
                break;
            }catch(Exception $e){}

        if($val == null)
            throw new Exception('Could not get bitcoin address balance');

        return $val;
    }
    
    public function transactions()
    {
        // TODO: Implement transactions() method.
    }

}