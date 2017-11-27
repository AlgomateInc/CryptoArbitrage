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

    public function __construct()
    {
        error_reporting(E_ALL);
        parent::__construct();
        $this->logger = \Logger::getLogger(get_class($this));

        // Create MarketDataMonitor with socket
        $testServerName = 'testServer';
        $port = 0;
        $testSocket = 'localhost:5050';
        $this->mdm = new \MarketDataMonitor();
        $options = [
            'socket'    => $testSocket,
            'servername' => $testServerName,
        ];
        $this->mdm->configure($options);
        $this->mdm->initializeAll();
    }

    private function checkJSONData($dataString)
    {
        $separator = "\r\n";
        $allData = [];
        $line = strtok($dataString, $separator);
        while ($line !== false) {
            $allData[] = json_decode($line, true);
            $err = json_last_error();
            $this->assertEquals(JSON_ERROR_NONE, $err);
            $line = strtok($separator);
        }
        return $allData;
    }

    private function getDataFromSocket($socket)
    {
        $totalBuf = "";
        while (false != socket_recv($socket, $buf, 1000000, MSG_DONTWAIT)) {
            $totalBuf .= $buf;
        }
        return $totalBuf;
    }

    public function testMarketDataRead()
    {
        $this->mdm->runLoop();

        // Try to connect to the socket
        $address = 'localhost';
        $port = 5050;

        // Create a TCP/IP socket to listen
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        $this->logger->info("Attempting to connect to '$address' on port '$port'\n");
        $this->assertTrue(socket_connect($socket, $address, $port));
       
        // Loop, test validity
        $this->mdm->runLoop();
        $totalBuf = $this->getDataFromSocket($socket);
        $data1 = $this->checkJSONData($totalBuf);

        // Loop twice, test validity, should get more data
        $this->mdm->runLoop();
        $this->mdm->runLoop();
        $totalBuf = $this->getDataFromSocket($socket);
        $data2 = $this->checkJSONData($totalBuf);

        $this->assertGreaterThan(count($data1), count($data2));
    }
}
