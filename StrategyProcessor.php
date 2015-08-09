<?php

require_once('ActionProcess.php');

require_once('strategy/ConfigStrategyLoader.php');
require_once('strategy/MongoStrategyLoader.php');

require_once('strategy/arbitrage/ArbitrageStrategy.php');
require_once('strategy/position/PositionStrategy.php');
require_once('strategy/position/MakerEstablishPositionStrategy.php');

require_once('trading/ActiveOrderManager.php');
require_once('trading/ExecutionManager.php');

class StrategyProcessor extends ActionProcess {

    private $liveTrade = false;

    private $instructionLoader;

    private $activeOrderManager;
    private $executionManager;

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
        $this->activeOrderManager = new ActiveOrderManager('activeOrders.json', $this->exchanges, $this->reporter);
        $this->executionManager = new ExecutionManager($this->activeOrderManager, $this->exchanges, $this->reporter);

        $this->executionManager->setLiveTrade($this->liveTrade);
    }

    public function run()
    {
        $logger = Logger::getLogger(get_class($this));

        if(!$this->activeOrderManager instanceof ActiveOrderManager)
            throw new Exception();

        if(!$this->instructionLoader instanceof IStrategyLoader)
            throw new Exception();

        if(!$this->executionManager instanceof ExecutionManager)
            throw new Exception();

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
                    $logger->warn('Problem getting balances for market: ' . $mkt->Name(), $e);
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

        $origActiveOrderCount = $this->activeOrderManager->getCount();

        $this->activeOrderManager->processActiveOrders();

        //abort processing if active order count has changed
        //this avoids a race condition where the balances fetched
        //would not be correct and subsequent code tries to make orders too large
        if($this->activeOrderManager->getCount() != $origActiveOrderCount)
            return;

        //////////////////////////////////////////
        // Run through all arbitrage instructions and execute as necessary
        //////////////////////////////////////////
        $instructions = $this->instructionLoader->load();

        foreach($instructions as $inst)
        {
            if(!$inst instanceof StrategyInstructions)
                continue;

            //abort further processing if any active orders exist
            //for this strategy
            if($this->activeOrderManager->isStrategyActive($inst->strategyId))
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
            if($iso instanceof IStrategyOrder)
                $this->executionManager->executeStrategy($s, $iso);
        }

    }

    public function shutdown()
    {
        if(!$this->activeOrderManager instanceof ActiveOrderManager)
            throw new Exception();

        //wait for completion of orders before exit
        $this->activeOrderManager->processActiveOrders();
        while($this->activeOrderManager->getCount() > 0){
            print "Waiting for active orders to complete...\n";
            sleep(5);
            $this->activeOrderManager->processActiveOrders();
        }
    }
}

$strPrc = new StrategyProcessor();
$strPrc->start();