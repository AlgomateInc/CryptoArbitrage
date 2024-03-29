<?php

require_once __DIR__ . '/vendor/autoload.php';

use CryptoArbitrage\Helper\CommandLineProcessor;
use CryptoArbitrage\Reporting\IReporter;

use CryptoMarket\Account\IAccount;
use CryptoMarket\Exchange\IExchange;
use CryptoMarket\Exchange\IMarginExchange;
use CryptoMarket\Record\ActiveOrder;
use CryptoMarket\Record\Order;
use CryptoMarket\Record\Trade;

require_once('ConfigData.php');
require_once('ActionProcess.php');

require_once('strategy/ConfigStrategyLoader.php');
require_once('strategy/MongoStrategyLoader.php');

require_once('strategy/arbitrage/ArbitrageStrategy.php');
require_once('strategy/position/PositionStrategy.php');
require_once('strategy/position/MakerEstablishPositionStrategy.php');

require_once('trading/ActiveOrderManager.php');
require_once('trading/BalanceManager.php');
require_once('trading/ExchangeManager.php');
require_once('trading/ExecutionManager.php');

class StrategyProcessor extends ActionProcess {

    private $liveTrade = false;

    private $instructionLoader;

    private $activeOrderManager;
    private $balanceManager;
    private $exchangeManager;
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
            $this->instructionLoader = new MongoStrategyLoader(\ConfigData::MONGODB_URI, \ConfigData::MONGODB_DBNAME);
        else{
            $this->instructionLoader = new ConfigStrategyLoader(\ConfigData::STRATEGY_INSTRUCTIONS);
        }
    }

    public function init()
    {
        $this->activeOrderManager = new ActiveOrderManager('activeOrders.json', $this->exchanges, $this->reporter);
        $this->executionManager = new ExecutionManager($this->activeOrderManager, $this->exchanges, $this->reporter);
        $this->balanceManager = new BalanceManager($this->reporter);
        $this->exchangeManager = new ExchangeManager($this->reporter);

        $this->executionManager->setLiveTrade($this->liveTrade);
    }

    public function run()
    {
        $logger = \Logger::getLogger(get_class($this));

        if(!$this->activeOrderManager instanceof ActiveOrderManager)
            throw new \Exception();

        if(!$this->instructionLoader instanceof IStrategyLoader)
            throw new \Exception();

        if(!$this->executionManager instanceof ExecutionManager)
            throw new \Exception();

        if(!$this->balanceManager instanceof BalanceManager)
            throw new \Exception();

        if(!$this->exchangeManager instanceof ExchangeManager)
            throw new \Exception();

        if(!$this->reporter instanceof IReporter)
            throw new \Exception();

        //////////////////////////////////////////
        // Fetch the account balances and transaction history
        //////////////////////////////////////////
        static $positions = array();
        $depth = array();

        foreach($this->exchanges as $mkt)
        {
            if($mkt instanceof IAccount)
            {
                //initialize local data structures
                if(!array_key_exists($mkt->Name(), $depth))
                    $depth[$mkt->Name()] = array();

                //get balances
                $this->balanceManager->fetch($mkt);

                //get the exchange info, including fees
                $this->exchangeManager->fetch($mkt);

                if($mkt instanceof IMarginExchange){
                    $posList = array();
                    try{
                        $posList = $mkt->positions();
                    }catch(\Exception $e){
                        $logger->warn('Problem getting positions for market: ' . $mkt->Name() . ', ' . $e->getMessage());
                    }

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

        //track the new active strategies
        $newActiveStrategies = array();

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

            //track active strategies
            $newActiveStrategies[] = $s->getStrategyId();

            //let the strategy update itself if it is already active
            //otherwise, run the new strategy
            $updateIsoList = $this->activeOrderManager->updateActiveStrategy($s);
            if($updateIsoList !== null) {
                foreach ($updateIsoList as $iso) {
                    if ($iso instanceof IStrategyOrder)
                        try{
                            $this->executionManager->updateStrategy($iso);
                        }catch (\Exception $e){
                            $logger->error('Strategy update failed: ' . $e->getMessage());
                        }
                }
                continue;
            }

            //////////////////////////////////////////
            // Execute the order(s) returned
            //////////////////////////////////////////
            try{
                $iso = $s->run($inst->data, $this->exchanges, $this->balanceManager->getBalances());
                if($iso instanceof IStrategyOrder){
                    $this->executionManager->executeStrategy($s, $iso);
                }
            }catch (\Exception $e){
                $logger->error('Strategy execution failed: ' . $e->getMessage());
            }
        }

        //get inactive strategies that have active orders. cancel those orders
        foreach($this->activeOrderManager->getInactiveStrategyOrders($newActiveStrategies) as $ao)
        {
            if(!$ao instanceof ActiveOrder)
                continue;

            if(!$ao->marketObj instanceof IExchange)
                continue;

            $this->executionManager->cancel($ao->marketObj->Name(), $ao->orderId, $ao->strategyOrderId);
        }
    }

    public function shutdown()
    {
        if(!$this->activeOrderManager instanceof ActiveOrderManager)
            throw new \Exception();

        //wait for completion of orders before exit
        $this->activeOrderManager->processActiveOrders();
        while($this->activeOrderManager->getCount() > 0){
            print "Waiting for active orders to complete...\n";
            sleep(5);
            $this->activeOrderManager->processActiveOrders();
        }
    }
}

if (!count(debug_backtrace()))
{
    $strPrc = new StrategyProcessor();
    $options = CommandLineProcessor::processCommandLine($strPrc);
    $strPrc->start($options);
}
