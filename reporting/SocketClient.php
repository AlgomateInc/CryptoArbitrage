<?php
/**
 * User: Jon
 * Date: 10/30/2017
 */
namespace CryptoArbitrage\Reporting;

class SocketClient
{
    public $retryBuffer = [];
    public $socket = null;
    public $lastRetryTime = 0;
    public $reconnectCount = 0;

    public function __construct($socket)
    {
        $this->socket = $socket;
    }
}
