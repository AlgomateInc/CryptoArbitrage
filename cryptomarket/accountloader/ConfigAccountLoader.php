<?php

namespace CryptoMarket\AccountLoader;

use CryptoMarket\AccountLoader\ConfigData;
use CryptoMarket\AccountLoader\IAccountLoader;

use CryptoMarket\Account\BitcoinAddress;
use CryptoMarket\Account\EthereumAccount;
use CryptoMarket\Account\EthereumClassicAccount;
use CryptoMarket\Account\JPMChase;

use CryptoMarket\Exchange\ExchangeName;
use CryptoMarket\Exchange\Bitfinex;
use CryptoMarket\Exchange\Bitstamp;
use CryptoMarket\Exchange\BitVC;
use CryptoMarket\Exchange\Btce;
use CryptoMarket\Exchange\Cryptsy;
use CryptoMarket\Exchange\Gdax;
use CryptoMarket\Exchange\Gemini;
use CryptoMarket\Exchange\Kraken;
use CryptoMarket\Exchange\Poloniex;
use CryptoMarket\Exchange\Yunbi;

class ConfigAccountLoader implements IAccountLoader
{
    protected $accountsConfig;

    public function __construct()
    {
        $this->accountsConfig = ConfigData::accountsConfig;
    }

    function getConfig()
    {
        return $this->accountsConfig;
    }

    function getAccounts(array $mktFilter = null)
    {
        $accounts = array();

        foreach ($this->accountsConfig as $mktName => $mktConfig){

            //filter to specific exchanges, as specified
            if ($mktFilter != null) {
                if (!in_array($mktName, $mktFilter)) {
                    continue;
                }
            }

            switch ($mktName)
            {
                case ExchangeName::Bitstamp:
                    $accounts[ExchangeName::Bitstamp] = new Bitstamp(
                        $mktConfig['custid'],
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;

                case ExchangeName::Btce:
                    $accounts[ExchangeName::Btce] = new Btce(
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;

                case ExchangeName::Cryptsy:
                    $accounts[ExchangeName::Cryptsy] = new Cryptsy(
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;

                case ExchangeName::JPMChase:
                    $accounts[ExchangeName::JPMChase] = new JPMChase(
                        $mktConfig['name'],
                        $mktConfig['username'],
                        $mktConfig['password']
                    );
                    break;

                case ExchangeName::Bitfinex:
                    $accounts[ExchangeName::Bitfinex] = new Bitfinex(
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;

                case ExchangeName::Gemini:
                    $accounts[ExchangeName::Gemini] = new Gemini(
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;

                case ExchangeName::BitVC:
                    $accounts[ExchangeName::BitVC] = new BitVC(
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;

                case ExchangeName::Poloniex:
                    $accounts[ExchangeName::Poloniex] = new Poloniex(
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;

                case ExchangeName::Kraken:
                    $accounts[ExchangeName::Kraken] = new Kraken(
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;

                case ExchangeName::Ethereum:
                    $accounts[ExchangeName::Ethereum] = new EthereumAccount(
                        $mktConfig['address']
                    );
                    break;

                case ExchangeName::EthereumClassic:
                    $accounts[ExchangeName::EthereumClassic] = new EthereumClassicAccount(
                        $mktConfig['address']
                    );
                    break;

                case ExchangeName::Bitcoin:
                    $accounts[ExchangeName::Bitcoin] = new BitcoinAddress(
                        $mktConfig['address']
                    );
                    break;

                case ExchangeName::Gdax:
                    $accounts[ExchangeName::Gdax] = new Gdax(
                        $mktConfig['key'],
                        $mktConfig['secret'],
                        $mktConfig['passphrase']
                    );
                    break;

                case ExchangeName::Yunbi:
                    $accounts[ExchangeName::Yunbi] = new Yunbi(
                        $mktConfig['key'],
                        $mktConfig['secret']
                    );
                    break;
            }
        }

        return $accounts;
    }
}

