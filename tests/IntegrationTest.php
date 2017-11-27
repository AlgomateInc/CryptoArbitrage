<?php

namespace CryptoArbitrage\Tests;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../ConfigData.php';

require_once __DIR__ . '/../MarketDataMonitor.php';
require_once __DIR__ . '/../ReportingServer.php';
require_once __DIR__ . '/../StrategyProcessor.php';


use PHPUnit\Framework\TestCase;

class IntegrationTest extends TestCase
{
    const TEST_LENGTH = 10;
    private $logger;

    public function __construct()
    {
        error_reporting(E_ALL);
        parent::__construct();
        $this->logger = \Logger::getLogger(get_class($this));
    }

    public function testMarketDataMonitor()
    {
        // Create MarketDataMonitor with socket
        $testServerName = 'testServer';
        $port = 0;
        $testSocket = 'localhost:5050';
        $mdm = new \MarketDataMonitor();
        $options = [
            'socket'    => $testSocket,
            'servername' => $testServerName,
            'discard-depth' => true,
            'mongodb' => null,
            'monitor' => 10,
        ];
        $mdm->configure($options);
        $mdm->initializeAll();

        for ($i = 0; $i < self::TEST_LENGTH; $i++) {
            $mdm->runLoop();
        }
        $this->assertTrue(true);
    }

    public function testStrategyProcessor()
    {
        $testServerName = 'testServer';
        $strPrc = new \StrategyProcessor();
        $options = [
            'servername' => $testServerName,
            'mongodb' => null,
            'monitor' => null,
            'live' => null,
        ];
        $strPrc->configure($options);
        $strPrc->initializeAll();

        for ($i = 0; $i < self::TEST_LENGTH; $i++) {
            $strPrc->runLoop();
        }
        $this->assertTrue(true);
    }

    public function testReportingServer()
    {
        $testServerName = 'testServer';
        $strPrc = new \ReportingServer();
        $options = [
            'servername' => $testServerName,
            'mongodb' => null,
            'monitor' => 0,
        ];
        $strPrc->configure($options);
        $strPrc->initializeAll();

        for ($i = 0; $i < self::TEST_LENGTH; $i++) {
            $strPrc->runLoop();
        }
        $this->assertTrue(true);
    }
}
