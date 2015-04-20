<?php

require_once('ActionProcess.php');

class MarketDataMonitor extends ActionProcess {

    //stores market -> last received trade date
    private $lastMktTradeDate = array();

    public function getProgramOptions()
    {
    }

    public function processOptions($options)
    {
    }

    public function init()
    {
        foreach($this->exchanges as $mkt)
            if($mkt instanceof IExchange)
                $this->lastMktTradeDate[$mkt->Name()] = time();
    }

    public function run()
    {
        if(!$this->reporter instanceof IReporter)
            throw new Exception('Reporter is not the right type!');

        foreach($this->exchanges as $mkt)
        {
            if(!$mkt instanceof IExchange)
                continue;

            try {
                foreach ($mkt->supportedCurrencyPairs() as $pair) {
                    $tickData = $mkt->ticker($pair);

                    if ($tickData instanceof Ticker)
                        $this->reporter->market(
                            $mkt->Name(),
                            $tickData->currencyPair,
                            $tickData->bid,
                            $tickData->ask,
                            $tickData->last,
                            $tickData->volume
                        );

                    //get the order book data
                    $depth = $mkt->depth($pair);
                    $this->reporter->depth($mkt->Name(), $pair, $depth);

                    //get recent trade data
                    $trades = $mkt->trades($pair, $this->lastMktTradeDate[$mkt->Name()]);
                    if(count($trades) > 0) {
                        $latestTrade = $trades[0];
                        if($latestTrade instanceof Trade && $latestTrade->timestamp instanceof MongoDate)
                            $this->lastMktTradeDate[$mkt->Name()] = $latestTrade->timestamp->sec + 1;
                        $this->reporter->trades($mkt->Name(), $pair, $trades);
                    }
                }
            }catch(Exception $e){
                syslog(LOG_WARNING, get_class($this) . ' could not get market data for: ' . $mkt->Name() . "\n$e");
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