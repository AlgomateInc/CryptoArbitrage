<?php

require_once('config.php');
require_once('common.php');
require_once('IAccountLoader.php');

class MongoAccountLoader extends ConfigAccountLoader{

    private $mongo;
    private $mdb;

    public function __construct(){
        global $mongodb_uri, $mongodb_db;

        $this->mongo = new MongoClient($mongodb_uri);
        $this->mdb = $this->mongo->selectDB($mongodb_db);

        $this->loadAccountConfig();
    }

    function loadAccountConfig()
    {
        $serverAccounts = $this->mdb->servers;

        //get the name of this server
        $machineName = gethostname();

        //find the config for this server
        $acc = $serverAccounts->findOne(array('ServerName' => $machineName));
        if($acc == null)
            return;

        $mktConfig = $acc['ExchangeSettings'];

        //rework the exchange settings to expected, legacy, format used by ConfigAccountLoader
        //which expects an associative array
        $this->accountsConfig = array();
        foreach ($mktConfig as $mktSetItem) {
            $this->accountsConfig[$mktSetItem['Name']] = $mktSetItem['Settings'];
        }
    }
}