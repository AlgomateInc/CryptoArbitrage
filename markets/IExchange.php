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

    public function ticker();
    public function depth($currencyPair);

    public function buy($quantity, $price);
    public function sell($quantity, $price);
    public function activeOrders();
    public function hasActiveOrders();

    public function isOrderAccepted($orderResponse);
    public function isOrderOpen($orderResponse);

    public function getOrderExecutions($orderResponse);
}

?>