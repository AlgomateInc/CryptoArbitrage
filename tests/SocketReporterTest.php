<?php

namespace CryptoArbitrage\Tests;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../MarketDataMonitor.php';
require_once __DIR__ . '/../ConfigData.php';

use PHPUnit\Framework\TestCase;

class SocketReporterTest extends TestCase
{
    private $mdm; // the market data monitor for testing
    private $logger;
    private $port;

    public function __construct()
    {
        error_reporting(E_ALL);
        parent::__construct();
        $this->logger = \Logger::getLogger(get_class($this));

        // Create MarketDataMonitor with socket
        $testServerName = 'testServer';
        $testHost = 'localhost';
        $port = 0;
        $testSocket = 'localhost:5050';
        $this->mdm = new \MarketDataMonitor();
        $options = [
            'socket'    => $testSocket,
            'servername' => $testServerName,
            'discard-depth' => true,
        ];
        $this->mdm->configure($options);
        $this->mdm->initializeAll();
    }

    public function testConnect()
    {
        // Run the server once
        $this->mdm->runLoop();

        // Try to connect to the socket
        $address = 'localhost';
        $port = 5050;

        /* Create a TCP/IP socket. */
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        $this->logger->info("Attempting to connect to '$address' on port '$port'\n");
        $this->assertTrue(socket_connect($socket, $address, $port));
       
        // Loop
        $this->mdm->runLoop();
        $totalBuf = "";
        while (false != socket_recv($socket, $buf, 1000000, MSG_DONTWAIT)) {
            $totalBuf .= $buf;
        }
        file_put_contents("test1.txt", $totalBuf);
        $data = json_decode($totalBuf, true);
        $err = json_last_error_msg();
        var_dump($totalBuf);
        var_dump($data);
        var_dump($err);
        $this->mdm->runLoop();
        $this->mdm->runLoop();
        $totalBuf = "";
        while (false != socket_recv($socket, $buf, 1000000, MSG_DONTWAIT)) {
            $totalBuf .= $buf;
        }
        file_put_contents("test2.txt", $totalBuf);
        $data = json_decode($totalBuf, true);
        $err = json_last_error_msg();
        var_dump($totalBuf);
        var_dump($data);
        var_dump($err);
    }

}
