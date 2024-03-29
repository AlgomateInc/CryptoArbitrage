<?php

require_once __DIR__ . '/vendor/autoload.php';

require_once('ActionProcess.php');
require_once('strategy/MarketCommandStrategy.php');
require_once('trading/ActiveOrderManager.php');
require_once('trading/ExecutionManager.php');

use CryptoArbitrage\Reporting\IListener;
use CryptoArbitrage\Helper\CommandLineProcessor;

class MarketCommandDispatcher extends ActionProcess {

    private $liveTrade = false;
    public $requiresListener = false;

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
    }

    public function init()
    {
        $this->activeOrderManager = new ActiveOrderManager('activeOrders.json', $this->exchanges, $this->reporter);
        $this->executionManager = new ExecutionManager($this->activeOrderManager, $this->exchanges, $this->reporter);

        $this->executionManager->setLiveTrade($this->liveTrade);
    }

    public function run()
    {
        if(!$this->listener instanceof IListener)
            throw new \Exception('Listener is not the right type!');

        if(!$this->activeOrderManager instanceof ActiveOrderManager)
            throw new \Exception('Wrong active order manager!');

        if(!$this->executionManager instanceof ExecutionManager)
            throw new \Exception('Wrong execution manager type!');

        //get the command from the server
        $command = $this->listener->receive();
        $cmdName = $command['Name'];
        $cmdData = $command['Data'];

        //handle the desired command
        switch($cmdName)
        {
            case 'NewOrder': {
                $strategy = new MarketCommandStrategy();
                $strategyOrder = $strategy->run($cmdData, $this->exchanges, null);

                if($strategyOrder instanceof IStrategyOrder)
                    $this->executionManager->executeStrategy($strategy, $strategyOrder);

                break;
            }

            case 'CancelOrder': {
                $this->executionManager->cancel($cmdData['Exchange'], $cmdData['OrderId'], $cmdData['StrategyId']);
                break;
            }

            default:
                break;
        }
    }

    public function shutdown()
    {

    }
}

if (!count(debug_backtrace()))
{
    $txMon = new MarketCommandDispatcher();
    $options = CommandLineProcessor::processCommandLine($txMon);
    $txMon->start($options);
}
