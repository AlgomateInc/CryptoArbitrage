<?php

require_once('IExchange.php');

abstract class BaseExchange implements IExchange {

    public function supports($currencyPair){
        $findBase = CurrencyPair::Base($currencyPair);
        $findQuote = CurrencyPair::Quote($currencyPair);

        foreach ($this->supportedCurrencyPairs() as $pair)
        {
            $base = CurrencyPair::Base($pair);
            $quote = CurrencyPair::Quote($pair);

            if ($base == $findBase && $quote == $findQuote)
                return true;
        }

        return false;
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

    public function tickers()
    {
        $ret = array();
        foreach ($this->supportedCurrencyPairs() as $pair) {
            $ret[] = $this->ticker($pair);
        }
        return $ret;
    }

    public function minimumOrderIncrement($pair, $pairRate)
    {
        $basePrecision = Currency::getPrecision(CurrencyPair::Base($pair));
        return max(pow(10, -1 * $basePrecision),
            round(pow(10, -1 * $this->quotePrecision($pair, $pairRate)) / $pairRate, $basePrecision));
    }

    public function quotePrecision($pair, $pairRate)
    {
        return Currency::getPrecision(CurrencyPair::Quote($pair));
    }
} 
