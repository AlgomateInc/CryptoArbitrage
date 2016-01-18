<?php

require_once('IAccount.php');

interface IExchange extends IAccount
{
    /**
     * @param string $currencyPair A currency pair to check support for
     * @return bool True if the pair is supported, false otherwise
     */
    public function supports($currencyPair);

    /**
     * @return array Provides an array of strings listing supported currency pairs
     */
    public function supportedCurrencyPairs();

    /**
     * @param $pair String The CurrencyPair pair we want to get minimum order size for
     * @param $pairRate float Supply a price for the pair, in case the rate is based on quote currency
     * @return mixed The minimum order size, in the base currency of the pair
     */
    public function minimumOrderSize($pair, $pairRate);

    /**
     * @return string[] Provides an array of strings listing supported currencies
     */
    public function supportedCurrencies();

    /**
     * @param $pair String The CurrencyPair string that we want ticker information for
     * @return Ticker An object with last tick information about the pair
     */
    public function ticker($pair);

    /**
     * @param $pair String The CurrencyPair string that we want last trade data for
     * @param $sinceDate int The unix timestamp of the date we are interested in
     * @return Trade[] An array of Trade objects, representing each trade that
     * has occurred since sinceDate
     */
    public function trades($pair, $sinceDate);

    /**
     * @param $currencyPair String The CurrencyPair string that we want depth data for
     * @return OrderBook The object containing order book depth data for the pair
     */
    public function depth($currencyPair);

    public function buy($pair, $quantity, $price);
    public function sell($pair, $quantity, $price);
    public function activeOrders();
    public function hasActiveOrders();
    public function cancel($orderId);

    public function isOrderAccepted($orderResponse);
    public function isOrderOpen($orderResponse);

    public function getOrderExecutions($orderResponse);

    public function tradeHistory($desiredCount);

    public function getOrderID($orderResponse);
}

?>