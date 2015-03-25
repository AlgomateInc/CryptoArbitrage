<?php

require_once('ActionProcess.php');

require_once('strategy/ConfigStrategyLoader.php');
require_once('strategy/MongoStrategyLoader.php');

require_once('strategy/arbitrage/ArbitrageStrategy.php');
require_once('strategy/position/PositionStrategy.php');
require_once('strategy/position/MakerEstablishPositionStrategy.php');

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
        $this->loadActiveOrders();
    }

    public function run()
    {
        //////////////////////////////////////////
        // Fetch the account balances and transaction history
        //////////////////////////////////////////
        static $balances = array();
        static $positions = array();
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
                $balList = array();
                try{
                    $balList = $mkt->balances();
                }catch(Exception $e){
                    syslog(LOG_WARNING, $e->getTraceAsString());
                    unset($balances[$mkt->Name()]);
                }

                //update our running list of balances
                foreach($balList as $cur => $bal){
                    //report balance only on balance change (or first run)
                    if(!isset($balances[$mkt->Name()][$cur]) || $balances[$mkt->Name()][$cur] != $bal)
                        $this->reporter->balance($mkt->Name(), $cur, $bal);

                    $balances[$mkt->Name()][$cur] = $bal;
                }

                if($mkt instanceof IMarginExchange){
                    $posList = $mkt->positions();
                    foreach($posList as $pos){
                        if($pos instanceof Trade && !in_array($pos, $positions)){
                            $positions[] = $pos;
                            $this->reporter->position(
                                $pos->exchange,
                                $pos->currencyPair,
                                $pos->orderType,
                                $pos->price,
                                $pos->quantity,
                                $pos->timestamp);
                        }
                    }
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
            $s->setStrategyId($inst->strategyId);

            $iso = $s->run($inst->data, $this->exchanges, $balances);

            //////////////////////////////////////////
            // Execute the order(s) returned
            //////////////////////////////////////////
            if($iso instanceof IStrategyOrder){
                $this->executeStrategy($s, $iso);
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

    private function saveActiveOrders()
    {
        file_put_contents('activeOrders.json', serialize($this->activeOrders));
    }

    private function loadActiveOrders()
    {
        $fileName = 'activeOrders.json';
        if(!file_exists($fileName))
            return;

        $aoFileStr = file_get_contents($fileName);
        if($aoFileStr === false)
            return;

        $aoJson = unserialize($aoFileStr);
        if($aoJson === null)
            return;

        //we have our active list. update it and fix our market references
        $aoCount = count($aoJson);
        for($i = 0;$i < $aoCount;$i++) {
            $ao = $aoJson[$i];
            if ($ao instanceof ActiveOrder && $ao->marketObj instanceof IExchange)
                $ao->marketObj = $this->exchanges[$ao->marketObj->Name()];
            else
                unset($aoJson[$i]);
        }

        $this->activeOrders = array_values($aoJson);
    }

    function processActiveOrders()
    {
        $aoCount = count($this->activeOrders);
        for($i = 0;$i < $aoCount;$i++)
        {
            $ao = $this->activeOrders[$i];
            if(!$ao instanceof ActiveOrder){
                unset($this->activeOrders[$i]);
                continue;
            }

            $market = $ao->marketObj;
            $marketResponse = $ao->marketResponse;
            $strategyId = $ao->strategyId;

            if(!$market instanceof IExchange)
                continue;

            //check if the order is done
            if($market->isOrderOpen($marketResponse))
            {
                //order is still active. our strategy may want to adjust it
                if($ao->strategyObj instanceof IStrategy)
                    $ao->strategyObj->update($ao);
            }
            else
            {
                //order complete. remove from list and do post-processing
                unset($this->activeOrders[$i]);

                //get the executions on this order and report them
                $execs = $market->getOrderExecutions($marketResponse);
                $oid = $market->getOrderID($marketResponse);
                foreach($execs as $execItem){
                    $this->reporter->execution(
                        $strategyId,
                        $oid,
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
        $this->saveActiveOrders();
    }

    function execute(Order $o, IStrategy $strategy, $strategyOrderId)
    {
        $market = $this->exchanges[$o->exchange];

        if(!$market instanceof IExchange)
            throw new Exception("Cannot trade on non-market: $o->exchange");

        //abort if this is test only
        if($this->liveTrade != true)
            return true;

        //submit the order to the market
        $marketResponse = null;
        if($o->orderType == OrderType::BUY)
            $marketResponse = $market->buy($o->currencyPair, $o->quantity, $o->limit);
        elseif($o->orderType == OrderType::SELL)
            $marketResponse = $market->sell($o->currencyPair, $o->quantity, $o->limit);
        else
            throw new Exception("Unable to execute order type: $o->orderType");

        //get the order id and add to active list if the order was accepted by the market
        $oid = null;
        $orderAccepted = $market->isOrderAccepted($marketResponse);
        if($orderAccepted){
            $oid = $market->getOrderID($marketResponse);

            $ao = new ActiveOrder();
            $ao->marketObj = $market;
            $ao->marketResponse = $marketResponse;
            $ao->order = $o;
            $ao->strategyId = $strategyOrderId;
            $ao->strategyObj = $strategy;

            $this->activeOrders[] = $ao;
            $this->saveActiveOrders();
        }

        //record the order and market response
        $this->reporter->order($o->exchange, $o->orderType, $o->quantity, $o->limit, $oid, $marketResponse, $strategyOrderId);

        return $orderAccepted;
    }

    function executeStrategy(IStrategy $strategy, IStrategyOrder $iso)
    {
        if(!$this->reporter instanceof IReporter)
            throw new Exception('Invalid reporter was passed!');

        $strategyOrderId = null;

        //TODO: total hack on strategy-specific reporting. needs revisiting
        if($iso instanceof ArbitrageOrder)
            $strategyOrderId = $this->reporter->arbitrage($iso->quantity, $iso->currencyPair,$iso->buyExchange,
                $iso->buyLimit,$iso->sellExchange, $iso->sellLimit);
        else
            $strategyOrderId = $this->reporter->strategyOrder($strategy->getStrategyId(),$iso);

        //submit orders to the exchanges
        //and report them as we go
        $orders = $iso->getOrders();
        $orderAcceptState = array();
        foreach($orders as $o)
        {
            if(!$o instanceof Order)
                throw new Exception('Strategy returned invalid order');

            $orderAcceptState[] = $this->execute($o, $strategy, $strategyOrderId);
        }

        //if orders failed, we need to take evasive action
        $allOrdersFailed = true;
        $someOrdersFailed = false;
        foreach($orderAcceptState as $orderAccepted){
            $allOrdersFailed = $allOrdersFailed && !$orderAccepted;
            $someOrdersFailed = $someOrdersFailed || !$orderAccepted;
        }

        //if all orders failed, simply throw an exception
        //no damage was done as we are still position-neutral
        if($allOrdersFailed)
            throw new Exception("Order entry failed for strategy: $strategyOrderId");

        //TODO:if just some of the orders failed, we need to correct our position
        //TODO:right now, stop trading
        if($someOrdersFailed){
            syslog(LOG_CRIT, 'Position imbalance! Strategy order entry failed.');
            $this->liveTrade = false;
        }
    }
}

$strPrc = new StrategyProcessor();
$strPrc->start();