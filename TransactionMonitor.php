<?php

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
        $exchanges = array();
        $exchanges[Exchange::Btce] = new BtceExchange();
        $exchanges[Exchange::Bitstamp] = new BitstampExchange();
        $exchanges[Exchange::JPMChase] = new JPMChase($mailbox_name, $mailbox_username, $mailbox_password);

        foreach($exchanges as $mkt)
        {
            $tx = $mkt->transactions(time());

            
        }
    }

    public function shutdown()
    {
        // TODO: Implement shutdown() method.
    }
}