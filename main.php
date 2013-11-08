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
    public $buyLimit = 0;
    public $sellLimit = INF;
    public $quantity = 0;
}

//////////////////////////////////////////////////////////

$reporter = new ConsoleReporter();
$monitor = false;
$liveTrade = false;

$shortopts = "";
$longopts = array(
    "mongodb",
    "monitor",
    "live"
);

$options = getopt($shortopts, $longopts);

if(array_key_exists("mongodb", $options))
    $reporter = new MongoReporter();
if(array_key_exists("monitor", $options))
    $monitor = true;
if(array_key_exists("live", $options))
    $liveTrade = true;

//////////////////////////////////////////////////////////

function fetchBalances() 
{
    global $reporter;

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
};

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

function getOptimalOrder($btce_depth, $bitstamp_depth, $target_spread)
{
    $btce_asks = computeDepthStats($btce_depth['asks']);
    $bstamp_bids = computeDepthStats($bitstamp_depth['bids']);

    //calculate order size and limits
    $order = new ArbitrageOrder();

    for($i = 0; $i < count($btce_asks); $i++){
        $buyPx = $btce_asks[$i][0];
        $buyQty = $btce_asks[$i][1];

        //check our wavg stat for exit condition
        $buyDiffSum = $btce_asks[$i][3];
        if($buyDiffSum > 1)
            return $order;

        for ($j = 0; $j < count($bstamp_bids);$j++){
            $sellPx = $bstamp_bids[$j][0];
            $sellQty = $bstamp_bids[$j][1];

            //check our wavg stat for exit condition
            $sellDiffSum = $bstamp_bids[$j][3];
            if($sellDiffSum > 1)
                return $order;

            //execute if we are still within the spread target
            if($buyPx + $target_spread < $sellPx){
                $execSize = min($buyQty, $sellQty);

                //update leftover order sizes
                $btce_asks[$i][1] -= $execSize;
                $buyQty = $btce_asks[$i][1];
                $bstamp_bids[$j][1] -= $execSize;
                $sellQty = $bstamp_bids[$j][1];

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

    $btce_result = btce_buy($order->quantity, $order->buyLimit);
    $bstamp_result = bitstamp_sell($order->quantity, $order->sellLimit);
    var_dump($btce_result);
    var_dump($bstamp_result);

    if($btce_result['success'] == 1){
    }
}

function fetchMarketData()
{
    global $reporter;
    global $max_order_usd_size;
    
    try{

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
        $ior = getOptimalOrder($btce_depth, $bstamp_depth, 6.2);
        $reporter->arborder($ior->quantity,Exchange::Btce,$ior->buyLimit,Exchange::Bitstamp, $ior->sellLimit);

        //adjust order size based on current limits
        if($ior->quantity * $ior->buyLimit > $max_order_usd_size)
            $ior->quantity = round($max_order_usd_size/$ior->buyLimit, 8, PHP_ROUND_HALF_DOWN);

        //execute the order on the market
        execute_trades($ior);

    }catch(Exception $e){
        syslog(LOG_ERR, $e->getMessage());
    }
};

////////////////////////////////////////////////////////
if($monitor){
    $pid = pcntl_fork();
    if($pid == -1){
        die('Could not fork process for monitoring!');
    }else if ($pid){
        //pcntl_wait($status);
    }else{
        do {
            fetchMarketData();
            sleep(15);
        }while($monitor);
    }
}else{
    fetchMarketData();
}

?>
