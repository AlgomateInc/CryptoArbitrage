<?php

require_once('ActionProcess.php');

class MarketDataMonitor extends ActionProcess {

    public function getProgramOptions()
    {
    }

    public function processOptions($options)
    {
    }

    public function init()
    {
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
                }
            }catch(Exception $e){
                syslog(LOG_WARNING, get_class($this) . ' could not get market data for: ' . $mkt->Name() . "\n$e");
            }
        }
    }

    public function shutdown()
    {
    }
}

$txMon = new MarketDataMonitor();
$txMon->start();