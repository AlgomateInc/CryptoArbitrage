<?php

use CryptoMarket\AccountLoader\ConfigData;
use CryptoMarket\AccountLoader\ConfigAccountLoader;
use CryptoMarket\AccountLoader\IAccountLoader;
use CryptoMarket\AccountLoader\MongoAccountLoader;

use CryptoMarket\Exchange\ILifecycleHandler;

include_once('log4php/Logger.php');
Logger::configure(ConfigData::log4phpConfig);

//require_once('legacy/TestAccountLoader.php');
require_once('reporting/MultiReporter.php');
require_once('reporting/ConsoleReporter.php');
require_once('reporting/MongoReporter.php');
require_once('reporting/FileReporter.php');
require_once('reporting/SocketReporter.php');

abstract class ActionProcess {

    protected $reporter;
    protected $listener;
    protected $requiresListener = false;
    private $monitor = false;
    private $monitor_timeout = 20;
    private $fork = false;

    abstract public function getProgramOptions();
    abstract public function processOptions($options);

    abstract public function init();
    abstract public function run();
    abstract public function shutdown();

    private $accountLoader = null;
    protected $exchanges = array();
    protected $configuredExchanges;
    protected $retryInitExchanges;

    private function processCommandLine()
    {
        $objOptions = $this->getProgramOptions();

        $shortopts = "";
        $longopts = array(
            'console',
            "mongodb::",
            "file:",
            "monitor::",
            "fork",
            'socket:',
            'testmarket',
            'servername:'
        );
        if(is_array($objOptions))
            $longopts = array_merge($longopts, $objOptions);

        /////////////////////////////////

        $options = getopt($shortopts, $longopts);

        /////////////////////////////////
        // Configure reporters
        $this->reporter = new MultiReporter();

        if(array_key_exists("mongodb", $options)) {
            if(isset($options['mongodb']) && $options['mongodb'] !== false)
                $fullUri = $options['mongodb'];
            else{
                $fullUri = ConfigData::mongodb_uri . '/' . ConfigData::mongodb_db;
            }

            $this->reporter->add(new MongoReporter($fullUri));
        }

        if(array_key_exists("file", $options) && isset($options['file']))
            $this->reporter->add(new FileReporter($options['file']));

        if(array_key_exists('socket', $options) && isset($options['socket']))
        {
            $host = parse_url($options['socket'], PHP_URL_HOST);
            $port = parse_url($options['socket'], PHP_URL_PORT);

            if($host != null && $port != null) {
                $sr = new SocketReporter($host, $port, $this->requiresListener);
                $this->reporter->add($sr);

                if($this->requiresListener)
                    $this->listener = $sr;
            }
        }

        if(array_key_exists('console', $options) || $this->reporter->count() == 0)
            $this->reporter->add(new ConsoleReporter());

        ////////////////////////////////
        // Load all the accounts data
        if(array_key_exists('testmarket', $options)) {
            //$this->accountLoader = new TestAccountLoader($this->requiresListener);
        } else {
            if(array_key_exists("mongodb", $options)) {
                if(array_key_exists('servername', $options) && isset($options['servername']))
                    $this->accountLoader = new MongoAccountLoader($options['servername']);
                else
                    $this->accountLoader = new MongoAccountLoader();
            }
            else
                $this->accountLoader = new ConfigAccountLoader();
        }

        $this->checkConfiguration($this->accountLoader);

        ////////////////////////////////
        if(array_key_exists("monitor", $options)){
            $this->monitor = true;

            if(is_numeric($options['monitor']))
                $this->monitor_timeout = intval($options['monitor']);
        }

        if(array_key_exists("fork", $options))
            $this->fork = true;

        ////////////////////////////////////

        $this->processOptions($options);
    }

    private function checkConfiguration(IAccountLoader $loader)
    {
        $config = $loader->getConfig();

        if($this->configuredExchanges == null){
            $this->configuredExchanges = $config;
            return;
        }

        if($this->configuredExchanges != $config)
            throw new \Exception('Configuration changed');
    }

    private function prepareMarkets(IAccountLoader $loader)
    {
        $this->retryInitExchanges = $this->initializeMarkets($loader->getAccounts());
    }

    private function initializeMarkets(array $marketList)
    {
        $logger = Logger::getLogger(get_class($this));

        $failedInitExchanges = array();

        foreach($marketList as $name => $mkt)
        {
            if(isset($this->exchanges[$name]))
                continue;

            if ($mkt instanceof ILifecycleHandler) {
                try {
                    $mkt->init();
                } catch (\Exception $e) {
                    $failedInitExchanges[$name] = $mkt;
                    $logger->error('Error initializing market: ' . $e->getMessage());
                    continue;
                }
            }

            $this->exchanges[$name] = $mkt;
        }

        return $failedInitExchanges;
    }

    public function start()
    {
        date_default_timezone_set('UTC');
        error_reporting(E_ALL);

        $logger = Logger::getLogger(get_class($this));
        $logger->info(get_class($this) . ' is starting');

        try{
            $this->processCommandLine();
        }catch(\Exception $e){
            $logger->error('Preparation error: ' . $e->getMessage());
            exit(1);
        }

        //if we are here, we are monitoring.
        //fork the process depending on setup and loop
        if($this->fork){
            $pid = pcntl_fork();

            if($pid == -1){
                die('Could not fork process for monitoring!');
            }else if ($pid){
                //parent process can now exit
                exit;
            }
        }

        //perform the monitoring loop
        try{
            $logger->info(get_class($this) . ' - starting');
            $this->prepareMarkets($this->accountLoader);
            $this->init();

            try{
                do {
                    $this->checkConfiguration($this->accountLoader);
                    $this->retryInitExchanges = $this->initializeMarkets($this->retryInitExchanges);
                    $this->run();
                    if($this->monitor)
                        sleep($this->monitor_timeout);
                }while($this->monitor);

                $this->shutdown();
                $logger->info(get_class($this) . ' - finished');
            }catch(\Exception $e){
                $this->shutdown();
                $logger->info(get_class($this) . ' - finished');
                throw $e;
            }

        }catch(\Exception $e){
            $logger->error('ActionProcess runtime error: ' . $e->getMessage());
            exit(1);
        }

    }
} 
