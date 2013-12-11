<?php

require('common.php');
require('markets/btce.php');
require('markets/bitstamp.php');
require('markets/jpmchase.php');
require('markets/Cryptsy.php');

require('reporting/ConsoleReporter.php');
require('reporting/MongoReporter.php');

require('arbinstructions/ConfigArbInstructionLoader.php');
require('arbinstructions/MongoArbInstructionLoader.php');

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
$exchanges[Exchange::Btce] = new BtceExchange($btce_key, $btce_secret);
$exchanges[Exchange::Bitstamp] = new BitstampExchange($bitstamp_custid, $bitstamp_key, $bitstamp_secret);
$exchanges[Exchange::JPMChase] = new JPMChase($mailbox_name, $mailbox_username, $mailbox_password);
$exchanges[Exchange::Cryptsy] = new Cryptsy($cryptsy_key, $cryptsy_secret);


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

function execute_trades(ArbitrageOrder $arb)
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

    if(!$buyMarket instanceof IExchange)
        throw new Exception('Cannot trade on non-market!');
    if(!$sellMarket instanceof IExchange)
        throw new Exception('Cannot trade on non-market!');

    $buy_res = $buyMarket->buy($arb->currencyPair, $arb->executionQuantity, $arb->buyLimit);
    $sell_res = $sellMarket->sell($arb->currencyPair, $arb->executionQuantity, $arb->sellLimit);

    if(!$reporter instanceof IReporter)
        throw new Exception('Invalid report was passed!');

    $arbid = $reporter->arbitrage($arb->quantity,$arb->buyExchange,$arb->buyLimit,$arb->sellExchange, $arb->sellLimit);
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

        //TODO:if just one of two orders failed, we need to correct our position
        //TODO:right now, stop trading
        syslog(LOG_CRIT, 'Position imbalance! Pair order entry failed.');
        $liveTrade = false;
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

        if(!$market instanceof IExchange)
            continue;

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
        // Fetch the account balances and transaction history
        //////////////////////////////////////////
        static $balances = array();
        $depth = array();

        foreach($exchanges as $mkt)
        {
            if($mkt instanceof IAccount)
            {
                //initialize local data structures
                if(!array_key_exists($mkt->Name(), $balances))
                    $balances[$mkt->Name()] = array();
                if(!array_key_exists($mkt->Name(), $depth))
                    $depth[$mkt->Name()] = array();

                //get balances
                $balList = $mkt->balances();
                foreach($balList as $cur => $bal){
                    //report balance only on balance change (or first run)
                    if(!isset($balances[$mkt->Name()][$cur]) || $balances[$mkt->Name()][$cur] != $bal)
                        $reporter->balance($mkt->Name(), $cur, $bal);

                    $balances[$mkt->Name()][$cur] = $bal;
                }
            }
        }

        //////////////////////////////////////////
        // Check and process any active orders
        //////////////////////////////////////////

        processActiveOrders();

        //abort further processing if any active orders exist
        global $activeOrders;
        if(count($activeOrders) > 0)
            return;

        //////////////////////////////////////////
        // Run through all arbitrage instructions and execute as necessary
        //////////////////////////////////////////
        global $arbInstructionLoader;
        $instructions = $arbInstructionLoader->load();

        foreach($instructions as $inst)
        {
            if(!$inst instanceof ArbInstructions)
                continue;

            //////////////////////////////////////////
            // Check the necessary markets exist and support trading for pair
            //////////////////////////////////////////
            if(!array_key_exists($inst->buyExchange, $exchanges) || !array_key_exists($inst->sellExchange, $exchanges)){
                syslog(LOG_WARNING, 'Markets in arbitrage instructions not supported');
                continue;
            }

            $buyMarket = $exchanges[$inst->buyExchange];
            $sellMarket = $exchanges[$inst->sellExchange];
            if(!($buyMarket instanceof IExchange && $sellMarket instanceof IExchange))
                continue;

            if(!($buyMarket->supports($inst->currencyPair) && $sellMarket->supports($inst->currencyPair))){
                syslog(LOG_WARNING, 'Markets in arbitrage instructions do not support pair: ' . $inst->currencyPair);
                continue;
            }

            /////////////////////////////////////////////////////////
            // Get the depth for the necessary markets
            // check if we fetched it already. if not, get it
            /////////////////////////////////////////////////////////
            if(!array_key_exists($inst->currencyPair, $depth[$inst->buyExchange])){
                $depth[$inst->buyExchange][$inst->currencyPair] = $buyMarket->depth($inst->currencyPair);
            }

            if(!array_key_exists($inst->currencyPair, $depth[$inst->sellExchange])){
                $depth[$inst->sellExchange][$inst->currencyPair] = $sellMarket->depth($inst->currencyPair);;
            }

            $buyDepth = $depth[$inst->buyExchange][$inst->currencyPair];
            $sellDepth = $depth[$inst->sellExchange][$inst->currencyPair];

            ////////////////////////////////////////////////////////////////
            // Run through all factors on arbitrage instructions and find execution candidates
            ////////////////////////////////////////////////////////////////
            $arbOrderList = array();
            foreach($inst->arbExecutionFactorList as $fctr)
            {
                //calculate optimal order
                $arbOrder = getOptimalOrder($buyDepth['asks'], $sellDepth['bids'], $fctr->targetSpreadPct);

                //once we find an order that can be placed, we queue it up
                if($arbOrder->quantity > 0){
                    $arbOrder->currencyPair = $inst->currencyPair;
                    $arbOrder->buyExchange = $inst->buyExchange;
                    $arbOrder->sellExchange = $inst->sellExchange;
                    $arbOrder->executionQuantity = $arbOrder->quantity;

                    //reduce the quantity to be executed by our factors
                    $arbOrder->executionQuantity = floorp($arbOrder->executionQuantity * $fctr->orderSizeScaling,8);

                    //adjust order size based on current dollar limits
                    if($arbOrder->executionQuantity * $arbOrder->buyLimit > $fctr->maxUsdOrderSize)
                        $arbOrder->executionQuantity = floorp($fctr->maxUsdOrderSize/$arbOrder->buyLimit, 8);

                    //adjust order size based on available balances
                    $arbOrder->executionQuantity = min(
                        $arbOrder->executionQuantity,
                        $balances[$arbOrder->sellExchange][CurrencyPair::Base($arbOrder->currencyPair)]);

                    $quoteBalance = $balances[$arbOrder->buyExchange][CurrencyPair::Quote($arbOrder->currencyPair)];
                    if($quoteBalance < $arbOrder->executionQuantity * $arbOrder->buyLimit)
                        $arbOrder->executionQuantity = floorp($quoteBalance / $arbOrder->buyLimit, 8);

                    /////////////////////////
                    $arbOrderList[] = $arbOrder;
                }
            }

            //select the target order by picking the one with the largest executable quantity
            $ior = null;
            foreach($arbOrderList as $ao){
                if($ior == null || $ao->executionQuantity > $ior->executionQuantity)
                    $ior = $ao;
            }

            //////////////////////////////////////////
            // Execute the order
            //////////////////////////////////////////
            if($ior instanceof ArbitrageOrder && $ior->executionQuantity > 0){
                //execute the order on the market if it meets minimum size
                //TODO: remove hardcoding of minimum size
                if($ior->executionQuantity > 0.01)
                    execute_trades($ior);
                else{
                    //no execution, but report the arbitrage with the original, desired, quantity for records
                    $reporter->arbitrage($ior->quantity,$ior->buyExchange,$ior->buyLimit,$ior->sellExchange, $ior->sellLimit);
                }
            }
        }

        //report the market depth
        foreach($depth as $mktName => $currencyPairDepth){
            foreach($currencyPairDepth as $pairName => $pairDepth){
                $reporter->depth($mktName, $pairName, $pairDepth);
            }
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
