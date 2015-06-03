<?php

require_once('ActionProcess.php');

class MarketCommandDispatcher extends ActionProcess {

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
        if(!$this->listener instanceof IListener)
            throw new Exception('Listener is not the right type!');

        //get the command from the server
        $command = $this->listener->receive();
        var_dump($command);
        $cmdName = $command['Name'];
        $cmdData = $command['Data'];

        //handle the desired command
        switch($cmdName)
        {
            case 'NewOrder': {
                $o = new Order();
                $o->currencyPair = $cmdData['CurrencyPair'];
                $o->exchange = $cmdData['Market'];
                $o->limit = $cmdData['Price'];
                $o->quantity = $cmdData['Quantity'];
                $o->orderType = $cmdData['OrderType'];

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

$txMon = new MarketCommandDispatcher();
$txMon->start();