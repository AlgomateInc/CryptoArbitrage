<?php

namespace CryptoArbitrage\Tests;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../StrategyProcessor.php';
require_once __DIR__ . '/../ConfigData.php';

use PHPUnit\Framework\TestCase;
use MongoDB;

class OpenSSLTest extends TestCase
{
    private $mongo;
    private $mdb;

    public function __construct()
    {
        error_reporting(E_ALL);
        parent::__construct();

        $this->mongo = new MongoDB\Client(\ConfigData::MONGODB_URI);
        $this->mdb = $this->mongo->selectDatabase(\ConfigData::MONGODB_DBNAME);
    }

    private function getPublicKey($serverName)
    {
        $keyCursor = $this->mdb->servers->find([
            'ServerName'=> $serverName,
            'PublicKey' => [ '$exists' => true ]
        ]);
        $currentKey = null;
        foreach ($keyCursor as $key) {
            $this->assertNull($currentKey);
            $currentKey = $key['PublicKey'];
        }
        return $currentKey;
    }

    public function testKeyCreation()
    {
        $config = [
            "digest_alg" => "sha512",
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ];
        $keyPair1 = openssl_pkey_new($config);
        $pubKey1 = openssl_pkey_get_details($keyPair1)['key'];
        openssl_pkey_export($keyPair1, $privateKey1);

        openssl_pkey_export_to_file($keyPair1, 'test.pem');
        
        $privateKey2 = file_get_contents('test.pem');
        $keyPair2 = openssl_pkey_get_private($privateKey2);
        $pubKey2 = openssl_pkey_get_details($keyPair2)['key'];

        $this->assertEquals($pubKey1, $pubKey2);
        $this->assertEquals($privateKey1, $privateKey2);

        // Clean up file
        unlink('test.pem');
    }

    public function testActionProcessStartup()
    {
        // Make sure that a new key is in the database
        $testServerName = 'testServer';
        $oldKey = $this->getPublicKey($testServerName);

        $strPrc = new \StrategyProcessor();
        $options = [
            'mongodb'    => false,
            'keypair'    => false,
            'live'       => false,
            'servername' => $testServerName,
        ];
        $strPrc->start($options);

        $newKey = $this->getPublicKey($testServerName);
        $this->assertNotEquals($oldKey, $newKey);
    }

    public function testAddAndRemoveExchange()
    {
        $testServerName = 'testServer';
        $strPrc = new \StrategyProcessor();
        $options = [
            'mongodb'    => false,
            'keypair'    => false,
            'live'       => false,
            'servername' => $testServerName,
        ];

        // Get everything setup so the public key is available
        $strPrc->configure($options);
        $strPrc->initializeAll();
        $strPrc->runLoop();
        $initialExchanges = $strPrc->getConfiguredExchanges();

        // Put in new data, re-run the loop, and should see more exchanges
        $publicKey = $this->getPublicKey($testServerName);
        $testExchange = 'Bitstamp';
        $data = ['key' => 'exampleapikey',
            'secret' => 'exampleapisecret',
            'custid' => 'exampleapicustid'
        ];
        $dataString = json_encode($data);
        openssl_public_encrypt($dataString, $encryptedData, $publicKey);
        $encryptedString = base64_encode($encryptedData);
        $this->mdb->servers->updateOne(
            [ 'ServerName' => $testServerName ],
            [ '$setOnInsert' => [
                'ServerName' => $testServerName,
              ],
              '$push' => [
                'ServerExchangeSettings' => [
                    'Name' => $testExchange,
                    'Data' => $encryptedString
                ]
              ]
            ],
            [ 'upsert' => true ]
        );

        // Will fail because of bad data inserted
        try {
            $strPrc->runLoop();
            $this->assertTrue(false);
        } catch (\Exception $e) {
        }

        // Check that the new exchange was included
        $encryptedExchanges = $strPrc->getConfiguredExchanges();
        $this->assertNotEquals($initialExchanges, $encryptedExchanges);

        // Clean up
        $this->mdb->servers->updateOne(
            [ 'ServerName' => $testServerName ],
            [ '$pull' => [
                'ServerExchangeSettings' => [
                    'Name' => $testExchange,
                    'Data' => $encryptedString
                ]
              ]
            ]
        );

        // Test that the next iteration works, back to old exchanges
        $strPrc->runLoop();
        $finalExchanges = $strPrc->getConfiguredExchanges();
        $this->assertEquals($initialExchanges, $finalExchanges);
    }
}
