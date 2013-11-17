<?php

require('btce.php');
require('bitstamp.php');

require('reporting/ConsoleReporter.php');
require('reporting/MongoReporter.php');

require('arbinstructions/ConfigArbInstructionLoader.php');
require('arbinstructions/MongoArbInstructionLoader.php');

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

class OrderType{
    const BUY = 'BUY';
    const SELL = 'SELL';
}

class ArbitrageOrder{
    public $buyExchange;
    public $buyLimit = 0;
    public $sellExchange;
    public $sellLimit = INF;
    public $quantity = 0;
    public $executionQuantity = 0;
}

//////////////////////////////////////////////////////////

$reporter = new ConsoleReporter();
$arbInstructionLoader = new ConfigArbInstructionLoader($arbInstructions);
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

if(array_key_exists("mongodb", $options)){
    $reporter = new MongoReporter();
    $arbInstructionLoader = new MongoArbInstructionLoader();
}
if(array_key_exists("monitor", $options))
    $monitor = true;
if(array_key_exists("fork", $options))
    $fork = true;
if(array_key_exists("live", $options))
    $liveTrade = true;

//////////////////////////////////////////////////////////
// Initialize active exchange interfaces and active order list
$exchanges = array();
$exchanges[Exchange::Btce] = new BtceExchange();
$exchanges[Exchange::Bitstamp] = new BitstampExchange();

$activeOrders = array();

//////////////////////////////////////////////////////////
function floorp($val, $precision)
{
    $mult = pow(10, $precision);
    return floor($val * $mult) / $mult;
}

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

function getOptimalOrder($buy_depth, $sell_depth, $target_spread_pct)
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
            if($buyPx * (1 + $target_spread_pct/100) < $sellPx){
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
                $order->quantity = floorp($order->quantity, 8);
            }

            //if we ran out of buy quantity, exit the sell-side loop
            if($buyQty <= 0)
                break;
        }
    }

    return $order;
}

function execute_trades(ArbitrageOrder $arb, $arbid)
{
    //abort if this is test only
    global $liveTrade;
    if($liveTrade != true)
        return;

    //submit orders to the exchanges
    //and report them as we go
    global $exchanges;
    global $reporter;
    $buyMarket = $exchanges[$arb->buyExchange];
    $sellMarket = $exchanges[$arb->sellExchange];

    $buy_res = $buyMarket->buy($arb->executionQuantity, $arb->buyLimit);
    $sell_res = $sellMarket->sell($arb->executionQuantity, $arb->sellLimit);

    $reporter->order($arb->buyExchange, OrderType::BUY, $arb->executionQuantity, $arb->buyLimit, $buy_res, $arbid);
    $reporter->order($arb->sellExchange, OrderType::SELL, $arb->executionQuantity, $arb->sellLimit, $sell_res, $arbid);

    //if orders failed, we need to take evasive action
    $buyFail = !$buyMarket->isOrderAccepted($buy_res);
    $sellFail = !$sellMarket->isOrderAccepted($sell_res);
    if($buyFail || $sellFail)
    {
        //if both orders failed, simply throw an exception
        //no damage was done as we are still position-neutral
        if($buyFail && $sellFail)
            throw new Exception("Order entry failed for arbid: $arbid");

        //TODO:if just one of two orders failed, we need to correct our position, for now just exit with failcode
        //TODO:supervisord knows to ignore error code 2 and let process die
        exit(2);
    }

    //at this point, we are sure both orders were accepted
    //add orders to active list so we can track their progress
    global $activeOrders;
    $activeOrders[] = array('exchange'=>$buyMarket, 'arbid' => $arbid, 'response'=>$buy_res);
    $activeOrders[] = array('exchange'=>$sellMarket, 'arbid' => $arbid, 'response'=>$sell_res);
}

function processActiveOrders()
{
    global $activeOrders;
    $aoCount = count($activeOrders);
    for($i = 0;$i < $aoCount;$i++)
    {
        $market = $activeOrders[$i]['exchange'];
        $marketResponse = $activeOrders[$i]['response'];
        $arbid = $activeOrders[$i]['arbid'];

        //check if the order is done
        if(!$market->isOrderOpen($marketResponse))
        {
            //order complete. remove from list and do post-processing
            unset($activeOrders[$i]);

            //get the executions on this order and report them
            $execs = $market->getOrderExecutions($marketResponse);
            foreach($execs as $execItem){
                global $reporter;
                $reporter->execution(
                    $arbid,
                    $market->Name(),
                    $execItem->txid,
                    $execItem->quantity,
                    $execItem->price,
                    $execItem->timestamp
                );
            }
        }
    }
    //we may have removed some orders with unset(). fix indices
    $activeOrders = array_values($activeOrders);
}

function fetchMarketData()
{
    global $reporter;
    global $exchanges;

    try{
        //////////////////////////////////////////
        // Fetch the account balances
        //////////////////////////////////////////
        static $balances = array();
        if(count($balances) == 0){
            $balances[Exchange::Btce] = array();
            $balances[Exchange::Bitstamp] = array();
        }

        foreach($exchanges as $mkt){
            $balList = $mkt->balances();

            foreach($balList as $cur => $bal){
                //report balance only on balance change (or first run)
                if(!isset($balances[$mkt->Name()][$cur]) || $balances[$mkt->Name()][$cur] != $bal)
                    $reporter->balance($mkt->Name(), $cur, $bal);

                $balances[$mkt->Name()][$cur] = $bal;
            }
        }

        //////////////////////////////////////////
        // Get the current market data
        //////////////////////////////////////////
        $btce = btce_ticker();
        $bstamp = bitstamp_ticker();

        $reporter->market(Exchange::Btce, CurrencyPair::BTCUSD, $btce['ticker']['sell'], $btce['ticker']['buy'], $btce['ticker']['last']);
        $reporter->market(Exchange::Bitstamp, CurrencyPair::BTCUSD, $bstamp['bid'], $bstamp['ask'], $bstamp['last']);

        $btce_depth = btce_depth();
        $bstamp_depth = bitstamp_depth();

        $bstamp_depth['bids'] = array_slice($bstamp_depth['bids'],0,150);
        $bstamp_depth['asks'] = array_slice($bstamp_depth['asks'],0,150);
        $reporter->depth(Exchange::Btce, CurrencyPair::BTCUSD, $btce_depth);
        $reporter->depth(Exchange::Bitstamp, CurrencyPair::BTCUSD, $bstamp_depth);

        $depth = array();
        $depth[Exchange::Btce] = $btce_depth;
        $depth[Exchange::Bitstamp] = $bstamp_depth;

        //////////////////////////////////////////
        // Check and process any active orders
        //////////////////////////////////////////

        processActiveOrders();

        //abort further processing if any active orders exist
        global $activeOrders;
        if(count($activeOrders) > 0)
            return;

        //////////////////////////////////////////
        // Calculate an optimal order from instructions
        //////////////////////////////////////////
        global $arbInstructionLoader;
        $instructions = $arbInstructionLoader->load();

        $arbOrderList = array();
        foreach($instructions as $inst)
        {
            foreach($inst->arbExecutionFactorList as $fctr)
            {
                $arbOrder = getOptimalOrder($depth[$inst->buyExchange]['asks'],
                    $depth[$inst->sellExchange]['bids'], $fctr->targetSpreadPct);

                //once we find an order that can be placed, we queue it up
                //and stop looking at additional factors from this instruction set
                if($arbOrder->quantity > 0){
                    $arbOrder->buyExchange = $inst->buyExchange;
                    $arbOrder->sellExchange = $inst->sellExchange;
                    $arbOrder->executionQuantity = $arbOrder->quantity;

                    //reduce the quantity to be executed by our factors
                    $arbOrder->executionQuantity = floorp($arbOrder->executionQuantity * $fctr->orderSizeScaling,8);

                    //adjust order size based on current dollar limits
                    if($arbOrder->executionQuantity * $arbOrder->buyLimit > $fctr->maxUsdOrderSize)
                        $arbOrder->executionQuantity = floorp($fctr->maxUsdOrderSize/$arbOrder->buyLimit, 8);

                    $arbOrderList[] = $arbOrder;
                    break;
                }
            }
        }

        //select the target order by picking the one with the largest executable quantity
        $ior = null;
        foreach($arbOrderList as $ao)
        {
            if($ior == null || $ao->executionQuantity > $ior->executionQuantity)
                $ior = $ao;
        }

        //////////////////////////////////////////
        // Execute the order
        //////////////////////////////////////////
        if($ior != null && $ior->executionQuantity > 0){
            //report the arbitrage with the original, desired, quantity
            $arbid = $reporter->arbitrage($ior->quantity,$ior->buyExchange,$ior->buyLimit,$ior->sellExchange, $ior->sellLimit);

            //adjust order size based on available balance
            if($balances[$ior->sellExchange][Currency::BTC] < $ior->executionQuantity)
                $ior->executionQuantity = $balances[$ior->sellExchange][Currency::BTC];
            if($balances[$ior->buyExchange][Currency::USD] < $ior->executionQuantity * $ior->buyLimit)
                $ior->executionQuantity = floorp($balances[$ior->buyExchange][Currency::USD]/$ior->buyLimit,8);

            //execute the order on the market if it meets minimum size
            //TODO: remove hardcoding of minimum size
            if($ior->executionQuantity > 0.01)
                execute_trades($ior, $arbid);
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

    //wait for completion of orders before exit
    processActiveOrders();
    while(count($activeOrders) > 0){
        print "Waiting for active orders to complete...\n";
        sleep(5);
        processActiveOrders();
    }
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
    sleep(20);
}while($monitor);

?>
