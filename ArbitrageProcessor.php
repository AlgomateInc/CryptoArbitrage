<?php

require_once('ActionProcess.php');

require_once('arbinstructions/ConfigArbInstructionLoader.php');
require_once('arbinstructions/MongoArbInstructionLoader.php');

class ArbitrageProcessor extends ActionProcess {

    private $liveTrade = false;

    private $activeOrders = array();
    private $arbInstructionLoader;

    public function getProgramOptions()
    {
        return array('live');
    }

    public function processOptions($options)
    {
        if(array_key_exists("live", $options))
            $this->liveTrade = true;

        if(array_key_exists("mongodb", $options))
            $this->arbInstructionLoader = new MongoArbInstructionLoader();
        else{
            global $arbInstructions;
            $this->arbInstructionLoader = new ConfigArbInstructionLoader($arbInstructions);
        }
    }

    public function init()
    {

    }

    public function run()
    {
        //////////////////////////////////////////
        // Fetch the account balances and transaction history
        //////////////////////////////////////////
        static $balances = array();
        $depth = array();

        foreach($this->exchanges as $mkt)
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
                        $this->reporter->balance($mkt->Name(), $cur, $bal);

                    $balances[$mkt->Name()][$cur] = $bal;
                }
            }
        }

        //////////////////////////////////////////
        // Check and process any active orders
        //////////////////////////////////////////

        $origActiveOrderCount = count($this->activeOrders);

        $this->processActiveOrders();

        //abort further processing if any active orders exist
        if(count($this->activeOrders) > 0)
            return;

        //abort processing if active order count has changed
        //this avoids a race condition where the balances fetched
        //would not be correct and subsequent code tries to make orders too large
        if(count($this->activeOrders) != $origActiveOrderCount)
            return;

        //////////////////////////////////////////
        // Run through all arbitrage instructions and execute as necessary
        //////////////////////////////////////////
        $instructions = $this->arbInstructionLoader->load();

        foreach($instructions as $inst)
        {
            if(!$inst instanceof ArbInstructions)
                continue;

            //////////////////////////////////////////
            // Check the necessary markets exist and support trading for pair
            //////////////////////////////////////////
            if(!array_key_exists($inst->buyExchange, $this->exchanges) || !array_key_exists($inst->sellExchange, $this->exchanges)){
                syslog(LOG_WARNING, 'Markets in arbitrage instructions not supported');
                continue;
            }

            $buyMarket = $this->exchanges[$inst->buyExchange];
            $sellMarket = $this->exchanges[$inst->sellExchange];
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
            if(!($buyDepth instanceof OrderBook && $sellDepth instanceof OrderBook)){
                syslog(LOG_WARNING, 'Markets returned depth in wrong format: ' .
                    $inst->buyExchange . ',' . $inst->sellExchange);
                continue;
            }

            ////////////////////////////////////////////////////////////////
            // Run through all factors on arbitrage instructions and find execution candidates
            ////////////////////////////////////////////////////////////////
            $arbOrderList = array();
            foreach($inst->arbExecutionFactorList as $fctr)
            {
                //calculate optimal order
                $arbOrder = $this->getOptimalOrder($buyDepth->asks, $sellDepth->bids, $fctr->targetSpreadPct);

                //once we find an order that can be placed, we queue it up
                if($arbOrder->quantity > 0){
                    $arbOrder->currencyPair = $inst->currencyPair;
                    $arbOrder->buyExchange = $inst->buyExchange;
                    $arbOrder->sellExchange = $inst->sellExchange;
                    $arbOrder->executionQuantity = $arbOrder->quantity;

                    //reduce the quantity to be executed by our factors
                    $arbOrder->executionQuantity = $this->floorp($arbOrder->executionQuantity * $fctr->orderSizeScaling,8);

                    //adjust order size based on current dollar limits
                    if($arbOrder->executionQuantity * $arbOrder->buyLimit > $fctr->maxUsdOrderSize)
                        $arbOrder->executionQuantity = $this->floorp($fctr->maxUsdOrderSize/$arbOrder->buyLimit, 8);

                    //adjust order size based on available balances
                    $arbOrder->executionQuantity = min(
                        $arbOrder->executionQuantity,
                        $balances[$arbOrder->sellExchange][CurrencyPair::Base($arbOrder->currencyPair)]);

                    $quoteBalance = $balances[$arbOrder->buyExchange][CurrencyPair::Quote($arbOrder->currencyPair)];
                    if($quoteBalance < $arbOrder->executionQuantity * $arbOrder->buyLimit)
                        $arbOrder->executionQuantity = $this->floorp($quoteBalance / $arbOrder->buyLimit, 8);

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
                    $this->execute_trades($ior);
                else{
                    //no execution, but report the arbitrage with the original, desired, quantity for records
                    $this->reporter->arbitrage($ior->quantity, $ior->currencyPair, $ior->buyExchange,$ior->buyLimit,$ior->sellExchange, $ior->sellLimit);
                }
            }
        }

    }

    public function shutdown()
    {
        //wait for completion of orders before exit
        $this->processActiveOrders();
        while(count($this->activeOrders) > 0){
            print "Waiting for active orders to complete...\n";
            sleep(5);
            $this->processActiveOrders();
        }
    }


    function processActiveOrders()
    {
        $aoCount = count($this->activeOrders);
        for($i = 0;$i < $aoCount;$i++)
        {
            $market = $this->activeOrders[$i]['exchange'];
            $marketResponse = $this->activeOrders[$i]['response'];
            $arbid = $this->activeOrders[$i]['arbid'];

            if(!$market instanceof IExchange)
                continue;

            //check if the order is done
            if(!$market->isOrderOpen($marketResponse))
            {
                //order complete. remove from list and do post-processing
                unset($this->activeOrders[$i]);

                //get the executions on this order and report them
                $execs = $market->getOrderExecutions($marketResponse);
                foreach($execs as $execItem){
                    $this->reporter->execution(
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
        $this->activeOrders = array_values($this->activeOrders);
    }

    function execute_trades(ArbitrageOrder $arb)
    {
        //abort if this is test only
        if($this->liveTrade != true)
            return;

        //submit orders to the exchanges
        //and report them as we go
        $buyMarket = $this->exchanges[$arb->buyExchange];
        $sellMarket = $this->exchanges[$arb->sellExchange];

        if(!$buyMarket instanceof IExchange)
            throw new Exception('Cannot trade on non-market!');
        if(!$sellMarket instanceof IExchange)
            throw new Exception('Cannot trade on non-market!');

        $buy_res = $buyMarket->buy($arb->currencyPair, $arb->executionQuantity, $arb->buyLimit);
        $sell_res = $sellMarket->sell($arb->currencyPair, $arb->executionQuantity, $arb->sellLimit);

        if(!$this->reporter instanceof IReporter)
            throw new Exception('Invalid report was passed!');

        $arbid = $this->reporter->arbitrage($arb->quantity, $arb->currencyPair,$arb->buyExchange,$arb->buyLimit,$arb->sellExchange, $arb->sellLimit);
        $this->reporter->order($arb->buyExchange, OrderType::BUY, $arb->executionQuantity, $arb->buyLimit, $buy_res, $arbid);
        $this->reporter->order($arb->sellExchange, OrderType::SELL, $arb->executionQuantity, $arb->sellLimit, $sell_res, $arbid);

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
            $this->liveTrade = false;
        }

        //at this point, we are sure both orders were accepted
        //add orders to active list so we can track their progress
        $this->activeOrders[] = array('exchange'=>$buyMarket, 'arbid' => $arbid, 'response'=>$buy_res);
        $this->activeOrders[] = array('exchange'=>$sellMarket, 'arbid' => $arbid, 'response'=>$sell_res);
    }

    function getOptimalOrder($buy_depth, $sell_depth, $target_spread_pct)
    {
        $asks = $this->computeDepthStats($buy_depth);
        $bids = $this->computeDepthStats($sell_depth);

        //calculate order size and limits
        $order = new ArbitrageOrder();

        foreach($asks as $askItem){

            if(!$askItem instanceof DepthItem)
                throw new Exception('Invalid order book depth item type');

            $buyPx = $askItem->price;
            $buyQty = $askItem->quantity;

            //check our wavg stat for exit condition
            $buyDiffSum = $askItem->stats[1];
            if($buyDiffSum > 1)
                return $order;

            foreach($bids as $bidItem){

                if(!$bidItem instanceof DepthItem)
                    throw new Exception('Invalid order book depth item type');

                $sellPx = $bidItem->price;
                $sellQty = $bidItem->quantity;

                //check our wavg stat for exit condition
                $sellDiffSum = $bidItem->stats[1];
                if($sellDiffSum > 1)
                    return $order;

                //execute if we are still within the spread target
                if($buyPx * (1 + $target_spread_pct/100) < $sellPx){
                    $execSize = min($buyQty, $sellQty);

                    //update leftover order sizes
                    $askItem->quantity -= $execSize;
                    $buyQty = $askItem->quantity;
                    $bidItem->quantity -= $execSize;

                    //update order limits and size
                    $order->buyLimit = $buyPx;
                    $order->sellLimit = $sellPx;
                    $order->quantity += $execSize;
                    $order->quantity = $this->floorp($order->quantity, 8);
                }

                //if we ran out of buy quantity, exit the sell-side loop
                if($buyQty <= 0)
                    break;
            }
        }

        return $order;
    }

    function computeDepthStats($depth){

        $qtySum = 0; //running depth quantity
        $pxqtySum = 0; //price times quantity, running total
        $wavgDiffSum = 0; //wavg px minus px, running total

        foreach($depth as $item){
            if(!$item instanceof DepthItem)
                throw new Exception('Order book depth not the right type');

            $px = $item->price;
            $qty = $item->quantity;

            //update our running sums
            $qtySum += $qty;
            $pxqtySum += $px*$qty;

            //get our wavg px and update pxdiff sum
            $wavgPx = $pxqtySum/$qtySum;
            $wavgDiffSum += abs($wavgPx - $px);

            //add depth stats to depth array
            $item->stats[] = $wavgPx;
            $item->stats[] = $wavgDiffSum;
        }

        return $depth;
    }

    function floorp($val, $precision)
    {
        $mult = pow(10, $precision);
        return floor($val * $mult) / $mult;
    }

}

$arbPrc = new ArbitrageProcessor();
$arbPrc->start();