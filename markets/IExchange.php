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
     * @param $pair The pair we want to get minimum order size for
     * @param $pairRate Supply a price for the pair, in case the rate is based on quote currency
     * @return mixed The minimum order size, in the base currency of the pair
     */
    public function minimumOrderSize($pair, $pairRate);

    /**
     * @return array Provides an array of strings listing supported currencies
     */
    public function supportedCurrencies();

    public function ticker($pair);
    public function trades($pair, $sinceDate);
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