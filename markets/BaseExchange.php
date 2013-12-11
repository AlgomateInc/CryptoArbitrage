<?php

require_once('IExchange.php');

abstract class BaseExchange implements IExchange {

    public function supports($currencyPair){
        return in_array($currencyPair, $this->supportedCurrencyPairs());
    }

    public function supportedCurrencies(){

        $currList = array();

        foreach($this->supportedCurrencyPairs() as $pair)
        {
            $base = CurrencyPair::Base($pair);
            $quote = CurrencyPair::Quote($pair);

            if(!in_array($base, $currList))
                $currList[] = $base;

            if(!in_array($quote, $currList))
                $currList[] = $quote;
        }

        return $currList;
    }
} 