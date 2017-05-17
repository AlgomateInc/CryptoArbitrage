<?php

require_once __DIR__ . '/vendor/autoload.php';

require_once('ActionProcess.php');
require_once('trading/ActiveOrderManager.php');
require_once('trading/BalanceManager.php');
require_once('trading/ExchangeManager.php');

use CryptoMarket\Exchange\IExchange;
use CryptoMarket\Record\Ticker;
use CryptoMarket\Record\Trade;

class MarketDataMonitor extends ActionProcess {

    private $activeOrderManager;
    private $balanceManager;
    private $exchangeManager;

    private $useActiveOrderManager = false;
    private $storeDepth = true;

    //stores market -> last received trade date
    private $lastMktTradeDate = array();

    public function getProgramOptions()
    {
        return array('activeorders', 'balances', 'discard-depth', 'fees');
    }

    public function processOptions($options)
    {
        if(array_key_exists("activeorders", $options))
            $this->useActiveOrderManager = true;
        if(array_key_exists('balances', $options))
            $this->balanceManager = new BalanceManager($this->reporter);
        if(array_key_exists('discard-depth', $options))
            $this->storeDepth = false;
        if(array_key_exists('fees', $options))
            $this->exchangeManager = new ExchangeManager($this->reporter);
    }

    public function init()
    {
        foreach($this->exchanges as $mkt)
            if($mkt instanceof IExchange)
                $this->lastMktTradeDate[$mkt->Name()] = time();

        if($this->useActiveOrderManager)
            $this->activeOrderManager = new ActiveOrderManager('activeOrders.json', $this->exchanges, $this->reporter);
    }

    public function run()
    {
        if(!$this->reporter instanceof IReporter)
            throw new \Exception('Reporter is not the right type!');

        foreach($this->exchanges as $mkt)
        {
            if(!$mkt instanceof IExchange)
                continue;

            $logger = Logger::getLogger(get_class($this));

            try {

                $fullTickData = $mkt->tickers();
                foreach($fullTickData as $tickData)
                    if ($tickData instanceof Ticker)
                        $this->reporter->market(
                            $mkt->Name(),
                            $tickData->currencyPair,
                            $tickData->bid,
                            $tickData->ask,
                            $tickData->last,
                            $tickData->volume
                        );

                foreach ($mkt->supportedCurrencyPairs() as $pair) {
                    //get the order book data
                    if($this->storeDepth) {
                        $depth = $mkt->depth($pair);
                        $this->reporter->depth($mkt->Name(), $pair, $depth);
                    }

                    //get recent trade data
                    $trades = $mkt->trades($pair, $this->lastMktTradeDate[$mkt->Name()]);
                    if(count($trades) > 0) {
                        $latestTrade = $trades[0];
                        if($latestTrade instanceof Trade && $latestTrade->timestamp instanceof MongoDB\BSON\UTCDateTime)
                            $this->lastMktTradeDate[$mkt->Name()] = $latestTrade->timestamp->toDateTime()->getTimestamp() + 1;
                        $this->reporter->trades($mkt->Name(), $pair, $trades);
                    }
                }

                //get the balances
                if($this->balanceManager instanceof BalanceManager)
                    $this->balanceManager->fetch($mkt);

                //get the exchange info, including fees
                if($this->exchangeManager instanceof ExchangeManager)
                    $this->exchangeManager->fetch($mkt);

                //process active orders and report executions
                if($this->activeOrderManager instanceof ActiveOrderManager)
                    $this->activeOrderManager->processActiveOrders();

            }catch(\Exception $e){
                $logger->warn('Could not get market data for: ' . $mkt->Name(), $e);
            }
        }

        //compute stats, if supported by reporter
        if($this->reporter instanceof IStatisticsGenerator)
            $this->reporter->computeMarketStats();
    }

    public function shutdown()
    {

    }
}

$txMon = new MarketDataMonitor();
$txMon->start();
