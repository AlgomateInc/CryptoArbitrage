<?php

require_once('IReporter.php');

class MongoReporter implements IReporter
{
    private $mongo;
    private $mdb;
    
    public function __construct(){
        $this->mongo = new MongoClient();
        $this->mdb = $this->mongo->coindata;
    }
    
    public function balance($exchange_name, $currency, $balance){
        $balances = $this->mdb->balance;
        $balance_entry = array(
            'exchange'=>"$exchange_name",
            'currency'=>"$currency",
            'balance'=>"$balance",
            'ts'=>new MongoDate());
        
        $balance_id = $balances->insert($balance_entry);
    }

    public function market($exchange_name, $currencyPair, $bid, $ask, $last){
        $markets = $this->mdb->market;
        $market_entry = array(
            'exchange'=>"$exchange_name",
            'currency'=>"$currencyPair",
            'bid'=>"$bid",
            'ask'=>"$ask",
            'last'=>"$last",
            'ts'=>new MongoDate());
        
        $me_id = $markets->insert($market_entry);
    }

    public function spread($buy_market_name, $sell_market_name, $difference){
        print "Buy $buy_market_name, Sell $sell_market_name: $difference\n";
    }

}

?>
