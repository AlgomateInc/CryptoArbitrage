<?php

use CryptoArbitrage\Reporting\ConsoleReporter;
use CryptoArbitrage\Reporting\FileReporter;
use CryptoArbitrage\Reporting\MongoReporter;
use CryptoArbitrage\Reporting\MultiReporter;
use CryptoArbitrage\Reporting\SocketReporter;
use CryptoArbitrage\Tests\TestAccountLoader;

use CryptoMarket\AccountLoader\ConfigAccountLoader;
use CryptoMarket\AccountLoader\IAccountLoader;
use CryptoMarket\AccountLoader\MongoAccountLoader;
use CryptoMarket\Exchange\ILifecycleHandler;

require_once('ConfigData.php');
\Logger::configure(\ConfigData::LOG4PHP_CONFIG);

abstract class ActionProcess {

    protected $reporter;
    protected $listener;
    private $monitor = false;
    private $monitor_timeout = 20;
    private $fork = false;
    private $publicKey;
    private $privateKey;

    abstract public function getProgramOptions();
    abstract public function processOptions($options);

    abstract public function init();
    abstract public function run();
    abstract public function shutdown();

    private $accountLoader = null;
    protected $exchanges = array();
    protected $configuredExchanges;
    protected $retryInitExchanges;

    public function getAllProgramOptions()
    {
        $objOptions = $this->getProgramOptions();

        $shortopts = "";
        $longopts = array(
            'console',
            "mongodb::",
            "discard-mongodb-depth",
            "cap-collections",
            "file:",
            "monitor::",
            "fork",
            'socket:',
            'testmarket',
            'servername:',
            'keypair::'
        );

        if(is_array($objOptions))
            $longopts = array_merge($longopts, $objOptions);

        return $longopts;
    }

    private function processCommandLine($options)
    {
        // Configure reporters
        $this->reporter = new MultiReporter();

        if(array_key_exists("mongodb", $options)) {
            if (isset($options['mongodb']) && $options['mongodb'] !== false) {
                //expect 'servername/databasename' url format
                $mongodb_uri = $options['mongodb'];
                $pos = mb_strrpos($mongodb_uri,'/');
                if ($pos === false) {
                    throw new \Exception('MongoDB database name not specified');
                }
                $mongodb_dbname = mb_substr($mongodb_uri, $pos + 1);
            } else {
                $mongodb_uri = \ConfigData::MONGODB_URI;
                $mongodb_dbname = \ConfigData::MONGODB_DBNAME;
            }

            $store_mongo_depth = true;
            if (isset($options['discard-mongodb-depth']))
                $store_mongo_depth = false;
            $cap_collections = false;
            if (isset($options['cap-collections']))
                $cap_collections = true;
            $this->reporter->add(new MongoReporter($mongodb_uri, $mongodb_dbname, $store_mongo_depth, $cap_collections));
        }

        if(array_key_exists("file", $options) && isset($options['file']))
            $this->reporter->add(new FileReporter($options['file']));

        if(array_key_exists('socket', $options) && isset($options['socket']))
        {
            $host = parse_url($options['socket'], PHP_URL_HOST);
            $port = parse_url($options['socket'], PHP_URL_PORT);

            if($host != null && $port != null) {
                $sr = new SocketReporter($host, $port);
                $this->reporter->add($sr);
                $this->listener = $sr;
            }
        }

        if(array_key_exists('console', $options) || $this->reporter->count() == 0)
            $this->reporter->add(new ConsoleReporter());

        ////////////////////////////////
        // Load all the accounts data
        if(array_key_exists('servername', $options) && isset($options['servername'])) {
            $this->serverName = $options['servername'];
        } else {
            $this->serverName = null;
        }

        if(array_key_exists('testmarket', $options)) {
            $this->accountLoader = new TestAccountLoader($this->requiresListener);
        } else {
            if(array_key_exists("mongodb", $options)) {
                $this->accountLoader = new MongoAccountLoader(
                    \ConfigData::MONGODB_URI,
                    \ConfigData::MONGODB_DBNAME,
                    \ConfigData::ACCOUNTS_CONFIG,
                    $this->serverName);
            }
            else {
                $this->accountLoader = new ConfigAccountLoader(\ConfigData::ACCOUNTS_CONFIG);
            }
        }

        // Set configured exchanges
        $this->configuredExchanges = $this->accountLoader->getConfig($this->privateKey);

        ////////////////////////////////
        if(array_key_exists("monitor", $options)){
            $this->monitor = true;

            if(is_numeric($options['monitor']))
                $this->monitor_timeout = intval($options['monitor']);
        }

        if(array_key_exists("fork", $options))
            $this->fork = true;

        if(array_key_exists('keypair', $options)) {
            if(isset($options['keypair']) && $options['keypair'] !== false) {
                $this->privateKey = file_get_contents($options['keypair']);
                $keyPair = openssl_pkey_get_private($this->privateKey);
            } else {
                
                $keyPair = openssl_pkey_new([
                    "digest_alg" => "sha512",
                    "private_key_bits" => 2048,
                    "private_key_type" => OPENSSL_KEYTYPE_RSA,
                ]);
                openssl_pkey_export($keyPair, $this->privateKey);
            }

            // Extract the public key and report it
            $this->publicKey = openssl_pkey_get_details($keyPair)['key'];
            $this->reporter->publicKey($this->serverName, $this->publicKey);
        }

        ////////////////////////////////////

        $this->processOptions($options);
    }

    private function initializeMarkets(array $marketList)
    {
        $logger = \Logger::getLogger(get_class($this));

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
                    $logger->error("Error initializing market [$name]: " . $e->getMessage(). "\n");
                    continue;
                }
            }

            $this->exchanges[$name] = $mkt;
        }

        return $failedInitExchanges;
    }

    public function configure($options)
    {
        date_default_timezone_set('UTC');
        error_reporting(E_ALL);

        $logger = \Logger::getLogger(get_class($this));
        $logger->info(get_class($this) . " - configuring\n");

        try{
            $this->processCommandLine($options);
        }catch(\Exception $e){
            $logger->error('Preparation error: ' . $e->getMessage());
            exit(1);
        }

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
    }

    public function initializeAll()
    {
        $this->retryInitExchanges = $this->initializeMarkets(
            $this->accountLoader->getAccounts(null, $this->privateKey));
        $this->init();
    }

    public function runLoop()
    {
        $config = $this->accountLoader->getConfig($this->privateKey);
        if ($this->configuredExchanges != $config) {
            // Reset all markets
            $this->configuredExchanges = $config;
            $this->exchanges = array();
            $this->retryInitExchanges = $this->initializeMarkets(
                $this->accountLoader->getAccounts(null, $this->privateKey));
        } else {
            // Only try to initialize failed markets
            $this->retryInitExchanges = $this->initializeMarkets(
                $this->retryInitExchanges);
        }
        if ($this->listener) {
            $this->listener->acceptConnection();
        }
        $this->run();
    }

    public function start($options)
    {
        $logger = \Logger::getLogger(get_class($this));
        $this->configure($options);

        //perform the monitoring loop
        try{
            $logger->info(get_class($this) . " - starting\n");
            $this->initializeAll();

            try{
                do {
                    $this->runLoop();
                    if($this->monitor)
                        sleep($this->monitor_timeout);
                }while($this->monitor);

                $this->shutdown();
                $logger->info(get_class($this) . " - finished\n");
            }catch(\Exception $e){
                $this->shutdown();
                $logger->info(get_class($this) . " - finished\n");
                throw $e;
            }

        }catch(\Exception $e){
            $logger->error('ActionProcess runtime error: ' . $e->getMessage() . "\n");
            ob_start();                    // start capture
            var_dump($e->getTrace() );     // dump the values
            $contents = ob_get_contents(); // put the buffer into a variable
            ob_end_clean();                // end capture
            $logger->error( $contents );
            exit(1);
        }
    }

    public function getConfiguredExchanges()
    {
        return $this->configuredExchanges;
    }
} 

