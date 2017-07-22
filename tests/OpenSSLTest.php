<?php

namespace CryptoArbitrage\Tests;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../StrategyProcessor.php';

use PHPUnit\Framework\TestCase;

class OpenSSLTest extends TestCase
{
    public function testLoadingKey()
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
    }

    public function testActionProcessStartup()
    {
        $strPrc = new \StrategyProcessor();
        $options = [
            'mongodb'=>false,
            'keypair'=>false,
            'live'=>false
        ];
        $strPrc->start($options);
    }
}
