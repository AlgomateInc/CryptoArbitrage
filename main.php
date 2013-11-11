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

class ArbitrageOrder{
    public $buyExchange;
    public $buyLimit = 0;
    public $sellExchange;
    public $sellLimit = INF;
    public $quantity = 0;
}

//////////////////////////////////////////////////////////

$reporter = new ConsoleReporter();
$monitor = false;
$liveTrade = false;
$fork = false;

$shortopts = "";
$longopts = array(
    "mongodb",
    "monitor",
    "fork",
    "live"
);

$options = getopt($shortopts, $longopts);

if(array_key_exists("mongodb", $options))
    $reporter = new MongoReporter();
if(array_key_exists("monitor", $options))
    $monitor = true;
if(array_key_exists("fork", $options))
    $fork = true;
if(array_key_exists("live", $options))
    $liveTrade = true;

//////////////////////////////////////////////////////////
// Initialize active exchange interfaces
$exchanges = array();
$exchanges[Exchange::Btce] = new BtceExchange();
$exchanges[Exchange::Bitstamp] = new BitstampExchange();

//////////////////////////////////////////////////////////
function computeDepthStats($depth){

    $qtySum = 0; //running depth quantity
    $pxqtySum = 0; //price times quantity, running total
    $wavgDiffSum = 0; //wavg px minus px, running total

    for($i = 0; $i < count($depth); $i++){
        $px = $depth[$i][0];
        $qty = $depth[$i][1];

        //update our running sums
        $qtySum += $qty;
        $pxqtySum += $px*$qty;

        //get our wavg px and update pxdiff sum
        $wavgPx = $pxqtySum/$qtySum;
        $wavgDiffSum += abs($wavgPx - $px);

        //add depth stats to depth array
        $depth[$i][] = $wavgPx;
        $depth[$i][] = $wavgDiffSum;
    }

    return $depth;
}

function getOptimalOrder($buy_depth, $sell_depth, $target_spread)
{
    $asks = computeDepthStats($buy_depth);
    $bids = computeDepthStats($sell_depth);

    //calculate order size and limits
    $order = new ArbitrageOrder();

    for($i = 0; $i < count($asks); $i++){
        $buyPx = $asks[$i][0];
        $buyQty = $asks[$i][1];

        //check our wavg stat for exit condition
        $buyDiffSum = $asks[$i][3];
        if($buyDiffSum > 1)
            return $order;

        for ($j = 0; $j < count($bids);$j++){
            $sellPx = $bids[$j][0];
            $sellQty = $bids[$j][1];

            //check our wavg stat for exit condition
            $sellDiffSum = $bids[$j][3];
            if($sellDiffSum > 1)
                return $order;

            //execute if we are still within the spread target
            if($buyPx + $target_spread < $sellPx){
                $execSize = min($buyQty, $sellQty);

                //update leftover order sizes
                $asks[$i][1] -= $execSize;
                $buyQty = $asks[$i][1];
                $bids[$j][1] -= $execSize;
                $sellQty = $bids[$j][1];

                //update order limits and size
                $order->buyLimit = $buyPx;
                $order->sellLimit = $sellPx;
                $order->quantity += $execSize;
            }

            //if we ran out of buy quantity, exit the sell-side loop
            if($buyQty <= 0)
                break;
        }
    }

    return $order;
}

function execute_trades(ArbitrageOrder $order)
{
    //abort if this is test only
    global $liveTrade;
    if($liveTrade != true)
        return;

    //submit orders to the exchanges
    global $exchanges;
    $buyMarket = $exchanges[$order->buyExchange];
    $sellMarket = $exchanges[$order->sellExchange];

    $buy_res = $buyMarket->buy($order->quantity, $order->buyLimit);
    $sell_res = $sellMarket->sell($order->quantity, $order->sellLimit);

    //TODO:verify that both orders were accepted
    //if not, cancel the active one and assess the damage

    //TODO: verify that accepted orders were fully executed
    //wait for execution if needed

    $buyMarket->processTradeResponse($buy_res);
    $sellMarket->processTradeResponse($sell_res);
}

function fetchMarketData()
{
    global $reporter;

    try{
        //////////////////////////////////////////
        // Fetch the account balances
        //////////////////////////////////////////
        static $balances = array();
        if(count($balances) == 0){
            $balances[Exchange::Btce] = array();
            $balances[Exchange::Bitstamp] = array();
        }

        $btce_info = btce_query("getInfo");
        if($btce_info['success'] == 1){
            if(!isset($balances[Exchange::Btce][Currency::USD]) ||
                $balances[Exchange::Btce][Currency::USD] != $btce_info['return']['funds']['usd'])
                $reporter->balance(Exchange::Btce, Currency::USD, $btce_info['return']['funds']['usd']);

            if(!isset($balances[Exchange::Btce][Currency::BTC]) ||
                $balances[Exchange::Btce][Currency::BTC] != $btce_info['return']['funds']['btc'])
                $reporter->balance(Exchange::Btce, Currency::BTC, $btce_info['return']['funds']['btc']);

            $balances[Exchange::Btce][Currency::USD] = $btce_info['return']['funds']['usd'];
            $balances[Exchange::Btce][Currency::BTC] = $btce_info['return']['funds']['btc'];
        } else {
            syslog(LOG_ERR, $btce_info['error']);
        }

        $bstamp_info = bitstamp_query('balance');
        if(!isset($bstamp_info['error'])){
            if(!isset($balances[Exchange::Bitstamp][Currency::USD]) ||
                $balances[Exchange::Bitstamp][Currency::USD] != $bstamp_info['usd_balance'])
                $reporter->balance(Exchange::Bitstamp, Currency::USD, $bstamp_info['usd_balance']);

            if(!isset($balances[Exchange::Bitstamp][Currency::BTC]) ||
                $balances[Exchange::Bitstamp][Currency::BTC] != $bstamp_info['btc_balance'])
                $reporter->balance(Exchange::Bitstamp, Currency::BTC, $bstamp_info['btc_balance']);

            $balances[Exchange::Bitstamp][Currency::USD] = $bstamp_info['usd_balance'];
            $balances[Exchange::Bitstamp][Currency::BTC] = $bstamp_info['btc_balance'];
        } else {
            syslog(LOG_ERR, $bstamp_info['error']);
        }

        //////////////////////////////////////////
        // Get the current market data
        //////////////////////////////////////////
        $btce = btce_ticker();        
        $reporter->market(Exchange::Btce, CurrencyPair::BTCUSD, $btce['ticker']['sell'], $btce['ticker']['buy'], $btce['ticker']['last']);
        
        $btce_depth = btce_depth();
        $reporter->depth(Exchange::Btce, CurrencyPair::BTCUSD, $btce_depth);
        
        $bstamp = bitstamp_ticker();
        $reporter->market(Exchange::Bitstamp, CurrencyPair::BTCUSD, $bstamp['bid'], $bstamp['ask'], $bstamp['last']);
        
        $bstamp_depth = bitstamp_depth();
        $bstamp_depth['bids'] = array_slice($bstamp_depth['bids'],0,150);
        $bstamp_depth['asks'] = array_slice($bstamp_depth['asks'],0,150);
        $reporter->depth(Exchange::Bitstamp, CurrencyPair::BTCUSD, $bstamp_depth);

        //////////////////////////////////////////
        // Calculate an optimal order and execute
        //////////////////////////////////////////
        $btce_buy_stamp_sell = getOptimalOrder($btce_depth['asks'], $bstamp_depth['bids'], 6.2);
        $btce_buy_stamp_sell->buyExchange = Exchange::Btce;
        $btce_buy_stamp_sell->sellExchange = Exchange::Bitstamp;

        $stamp_buy_btce_sell = getOptimalOrder($bstamp_depth['asks'], $btce_depth['bids'], 1.5);
        $stamp_buy_btce_sell->buyExchange = Exchange::Bitstamp;
        $stamp_buy_btce_sell->sellExchange = Exchange::Btce;

        $ior = ($btce_buy_stamp_sell->quantity > $stamp_buy_btce_sell->quantity)? $btce_buy_stamp_sell : $stamp_buy_btce_sell;
        if($ior->quantity > 0){
            $reporter->arborder($ior->quantity,$ior->buyExchange,$ior->buyLimit,$ior->sellExchange, $ior->sellLimit);

            //adjust order size based on current limits
            global $max_order_usd_size;
            if($ior->quantity * $ior->buyLimit > $max_order_usd_size)
                $ior->quantity = round($max_order_usd_size/$ior->buyLimit, 8, PHP_ROUND_HALF_DOWN);

            //adjust order size based on available balance
            if($balances[$ior->sellExchange][Currency::BTC] < $ior->quantity)
                $ior->quantity = $balances[$ior->sellExchange][Currency::BTC];
            if($balances[$ior->buyExchange][Currency::USD] < $ior->quantity * $ior->buyLimit)
                $ior->quantity = round($balances[$ior->buyExchange][Currency::USD]/$ior->buyLimit,8,PHP_ROUND_HALF_DOWN);

            //check for active orders with the exchanges
            //if any, we need to abort
            global $exchanges;
            if($exchanges[$ior->buyExchange]->hasActiveOrders() ||
                $exchanges[$ior->sellExchange]->hasActiveOrders())
            {
                print "abort";
                return;
            };

            //execute the order on the market if it meets minimum size
            //TODO: remove hardcoding of minimum size
            if($ior->quantity > 0.01)
                execute_trades($ior);
        }

    }catch(Exception $e){
        syslog(LOG_ERR, $e);
    }
};

////////////////////////////////////////////////////////
// Execute process according to setup
// if not monitoring, run once and exit
if($monitor == false){
    fetchMarketData();
    exit;
}

//if we are here, we are monitoring
//fork the process depending on setup and loop
if($fork){
    $pid = pcntl_fork();

    if($pid == -1){
        die('Could not fork process for monitoring!');
    }else if ($pid){
        //parent process can now exit
        exit;
    }
}

//perform the monitoring loop
do {
    fetchMarketData();
    sleep(15);
}while($monitor);

?>
