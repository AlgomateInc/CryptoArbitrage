<?php
/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 6/2/2015
 * Time: 5:06 PM
 */
namespace CryptoArbitrage\Reporting;

interface IListener {
    public function receive();
    public function acceptConnection();
}
