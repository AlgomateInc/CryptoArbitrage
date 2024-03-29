<?php

require_once __DIR__ . '/vendor/autoload.php';

use CryptoArbitrage\Helper\CommandLineProcessor;
use CryptoArbitrage\Reporting\IReporter;

use CryptoMarket\Account\IAccount;
use CryptoMarket\Record\Transaction;

require_once('ActionProcess.php');

class TransactionMonitor extends ActionProcess{

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
            if(!$mkt instanceof IAccount)
                continue;

            $txList = $mkt->transactions();

            foreach($txList as $tx)
                if($tx instanceof Transaction)
                    $this->reporter->transaction(
                        $tx->exchange,
                        $tx->id,
                        $tx->type,
                        $tx->currency,
                        $tx->amount,
                        $tx->timestamp
                    );
        }
    }

    public function shutdown()
    {

    }
}

if (!count(debug_backtrace()))
{
    $txMon = new TransactionMonitor();
    $options = CommandLineProcessor::processCommandLine($txMon);
    $txMon->start($options);
}
