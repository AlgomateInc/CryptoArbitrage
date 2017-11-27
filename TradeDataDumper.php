<?php

require_once __DIR__ . '/vendor/autoload.php';

use CryptoArbitrage\Helper\CommandLineProcessor;
use CryptoArbitrage\Reporting\IReporter;

use CryptoMarket\Exchange\Bitstamp;
use CryptoMarket\Record\Trade;

require_once('ActionProcess.php');

class TradeDataDumper extends ActionProcess {

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
            throw new \Exception('Reporter is not the right type!');

        foreach($this->exchanges as $mkt)
        {
            if(!$mkt instanceof Bitstamp)
                continue;

            foreach($mkt->tradeHistory() as $tx){

                if($tx instanceof Trade)
                    $this->reporter->trade(
                        $mkt->Name(),
                        $tx->currencyPair,
                        null,
                        null,
                        $tx->orderType,
                        $tx->price,
                        $tx->quantity,
                        $tx->timestamp
                    );
            }
        }
    }

    public function shutdown()
    {

    }
}

if (!count(debug_backtrace()))
{
    $tdDumper = new TradeDataDumper();
    $options = CommandLineProcessor::processCommandLine($tdDumper);
    $tdDumper->start($options);
}
