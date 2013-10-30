<?php

require_once('IReporter.php');

class MongoReporter implements IReporter
{
    private $mongo;
    private $mdb;
    
    public function __construct(){
        global $mongodb_uri;
        
        $this->mongo = new MongoClient($mongodb_uri);
        $this->mdb = $this->mongo->coindata;
    }
    
    public function balance($exchange_name, $currency, $balance){
        $balances = $this->mdb->balance;
        $balance_entry = array(
            'Exchange'=>"$exchange_name",
            'Currency'=>"$currency",
            'Balance'=>"$balance",
            'Timestamp'=>new MongoDate());
        
        $balance_id = $balances->insert($balance_entry);
    }

    public function market($exchange_name, $currencyPair, $bid, $ask, $last){
        $markets = $this->mdb->market;
        $market_entry = array(
            'Exchange'=>"$exchange_name",
            'CurrencyPair'=>"$currencyPair",
            'Bid'=>"$bid",
            'Ask'=>"$ask",
            'Last'=>"$last",
            'Timestamp'=>new MongoDate());
        
        $me_id = $markets->insert($market_entry);
    }

    public function spread($buy_market_name, $sell_market_name, $difference){
        print "Buy $buy_market_name, Sell $sell_market_name: $difference\n";
    }

}

?>
