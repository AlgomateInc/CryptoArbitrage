<?php

namespace CryptoMarket\AccountLoader;

use CryptoMarket\AccountLoader\ConfigData;
use CryptoMarket\AccountLoader\IAccountLoader;

use Mongodb\Client;

class MongoAccountLoader extends ConfigAccountLoader
{
    private $mongo;
    private $mdb;

    private $serverName = null;

    public function __construct($serverName = null){
        parent::__construct();

        $this->mongo = new Client(ConfigData::mongodb_uri);
        $this->mdb = $this->mongo->selectDatabase(ConfigData::mongodb_db);

        if ($serverName === null) {
            $serverName = gethostname();
        }
        $this->serverName = $serverName;
    }

    function loadAccountConfig($serverName)
    {
        $serverAccounts = $this->mdb->servers;

        //get the name of this server
        $machineName = $serverName;

        //find the config for this server
        $acc = $serverAccounts->findOne(array('ServerName' => $machineName));
        if ($acc === null)
            return;

        $mktConfig = $acc['ExchangeSettings'];

        //rework the exchange settings to expected, legacy, format used by ConfigAccountLoader
        //which expects an associative array
        $this->accountsConfig = array();
        foreach ($mktConfig as $mktSetItem) {
            $this->accountsConfig[$mktSetItem['Name']] = $mktSetItem['Settings'];
        }
    }

    function getConfig()
    {
        $this->loadAccountConfig($this->serverName);
        return parent::getConfig();
    }

    function getAccounts(array $mktFilter = null)
    {
        $this->loadAccountConfig($this->serverName);

        return parent::getAccounts($mktFilter);
    }
}

