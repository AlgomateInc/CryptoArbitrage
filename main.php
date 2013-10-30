<?php

require('btce.php');
require('bitstamp.php');

require('reporting/ConsoleReporter.php');
require('reporting/MongoReporter.php');

//////////////////////////////////////////////////////////

class Exchange{
    const Btce = "Btce";
    const Bitstamp = "Bitstamp";
}

class Currency{
    const USD = "USD";
    const BTC = "BTC";
}

class CurrencyPair{
    const BTCUSD = "BTCUSD";
}

//////////////////////////////////////////////////////////

$reporter = new ConsoleReporter();
$monitor = false;

$shortopts = "";
$longopts = array(
  "mongodb",
  "monitor"
);

$options = getopt($shortopts, $longopts);

if(array_key_exists("mongodb", $options))
    $reporter = new MongoReporter();
if(array_key_exists("monitor", $options))
    $monitor = true;

//////////////////////////////////////////////////////////

function fetchBalances() 
{
    global $reporter;

    print "Fetching Balances...\n";

    $btce_info = btce_query("getInfo");
    if($btce_info['success'] == 1){
        $reporter->balance(Exchange::Btce, Currency::USD, $btce_info['return']['funds']['usd']);
        $reporter->balance(Exchange::Btce, Currency::BTC, $btce_info['return']['funds']['btc']);
    }

    $bstamp_info = bitstamp_query('balance');
    if(!isset($bstamp_info['error'])){
        $reporter->balance(Exchange::Bitstamp, Currency::USD, $bstamp_info['usd_balance']);
        $reporter->balance(Exchange::Bitstamp, Currency::BTC, $bstamp_info['btc_balance']);
    }                                                                    

    print "\n";
};

function fetchMarketData()
{
    global $reporter;
    
    try{
        $btce = btce_ticker();    
        
        $reporter->market(Exchange::Btce, CurrencyPair::BTCUSD, $btce['ticker']['sell'], $btce['ticker']['buy'], $btce['ticker']['last']);
    }catch(Exception $e){
        
    }
    
    try{
        $bstamp = bitstamp_ticker();
        
        $reporter->market(Exchange::Bitstamp, CurrencyPair::BTCUSD, $bstamp['bid'], $bstamp['ask'], $bstamp['last']);
    }catch(Exception $e){
        
    }
};

do {
    fetchMarketData();
    sleep(15);
}while(monitor);

?>
