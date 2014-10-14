<?php

require_once('ActionProcess.php');

require_once('strategy/ConfigStrategyLoader.php');
require_once('strategy/MongoStrategyLoader.php');

require_once('strategy/ArbitrageStrategy.php');

class StrategyProcessor extends ActionProcess {

    private $liveTrade = false;

    private $activeOrders = array();
    private $instructionLoader;

    public function getProgramOptions()
    {
        return array('live');
    }

    public function processOptions($options)
    {
        if(array_key_exists("live", $options))
            $this->liveTrade = true;

        if(array_key_exists("mongodb", $options))
            $this->instructionLoader = new MongoStrategyLoader();
        else{
            global $strategyInstructions;
            $this->instructionLoader = new ConfigStrategyLoader($strategyInstructions);
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
        $instructions = $this->instructionLoader->load();

        foreach($instructions as $inst)
        {
            if(!$inst instanceof StrategyInstructions)
                continue;

            //////////////////////////////////////////
            // Instantiate named strategy and run
            //////////////////////////////////////////
            $s = new $inst->strategyName;
            if(!$s instanceof IStrategy)
                continue;

            $s->run($inst->data, $this->exchanges, $balances);
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
}

$strPrc = new StrategyProcessor();
$strPrc->start();