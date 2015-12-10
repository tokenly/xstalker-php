<?php

namespace XStalker;

use BitWasp\Bitcoin\Address\AddressFactory;
use BitWasp\Bitcoin\Address\ScriptHashAddress;
use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Key\PublicKeyFactory;
use BitWasp\Bitcoin\Script\Classifier\InputClassifier;
use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Bitcoin\Transaction\Transaction;
use XStalker\BitcoindEventHandler;

/**
* Handle transactions from bitcoind
*/
class TXHandler extends BitcoindEventHandler
{
    
    public function handleTransaction(Transaction $transaction) {
        $tx_data = $this->buildTXData($transaction);
        $this->beanstalk_loader->loadTransaction($tx_data);
    }


    protected function buildTXData(Transaction $transaction) {
        $ts = intval(round(microtime(true) / 1000));
        $txid = $transaction->getTxId()->getHex();

        $xstalker_data = [
            'ts'   => $ts,
            'txid' => $txid,
        ];
        return $xstalker_data;
    }

}

/*
{
    "blockhash": "00000000000000000d45b7b575aada5c6e7f45d33455683d9e37292fa916b27c",
    "blocktime": 1426358977,
    "confirmations": 320,
    "fees": 3.5e-05,
    "locktime": 0,
    "size": 339,
    "time": 1426358977,
    "txid": "d0010d7ddb1662e381520d29177ea83f81f87428879b57735a894cad8dcae2a2",
    "valueIn": 0.0587343,
    "valueOut": 0.0586993,
    "version": 1,
    "vin": [
        {
            "addr": "1BqFKh5wwQpT3bor1kvTi6qFiBBdjDyVok",
            "doubleSpentTxID": null,
            "n": 0,
            "scriptSig": {
                "asm": "30440220717c7b92050cdf282b3b5140f932378527f18ebfffc4956eb19bd439d615589002201b14263b09d7dd9cc3c49503345e8a07f4f715d0b82b17508ba459aaa7af9e9c01 020ba30b3409037b6653fdcc916775fe7f2a2dbca9b934cd51e5d207f56a8178e7",
                "hex": "4730440220717c7b92050cdf282b3b5140f932378527f18ebfffc4956eb19bd439d615589002201b14263b09d7dd9cc3c49503345e8a07f4f715d0b82b17508ba459aaa7af9e9c0121020ba30b3409037b6653fdcc916775fe7f2a2dbca9b934cd51e5d207f56a8178e7"
            },
            "sequence": 4294967295,
            "txid": "b61bff55d9c5b14f32515b2b00eaf72d3f1c1b951f0747b3199ade27544efd74",
            "value": 0.0587343,
            "valueSat": 5873430,
            "vout": 2
        }
    ],
    "vout": [
        {
            "n": 0,
            "scriptPubKey": {
                "addresses": [
                    "1JztLWos5K7LsqW5E78EASgiVBaCe6f7cD"
                ],
                "asm": "OP_DUP OP_HASH160 c56cb39f9b289c0ec4ef6943fa107c904820fe09 OP_EQUALVERIFY OP_CHECKSIG",
                "hex": "76a914c56cb39f9b289c0ec4ef6943fa107c904820fe0988ac",
                "reqSigs": 1,
                "type": "pubkeyhash"
            },
            "value": "0.00001250"
        },
        {
            "n": 1,
            "scriptPubKey": {
                "addresses": [
                    "1ByqUj6Z3AfXXSnPfDUYUPC1yq2MBkduh2",
                    "12J8ihB2bXjZoTWcPMGYDDtRrog9CcsRLL",
                    "1BqFKh5wwQpT3bor1kvTi6qFiBBdjDyVok"
                ],
                "asm": "1 022cacedc1a455d6665433a2d852951b52014def0fb4769a3323a7ab0cccc1adc3 03e74c5611fa7923392e1390838caf81814003baba2fab77e2c8a4bd9834ae6fa1 020ba30b3409037b6653fdcc916775fe7f2a2dbca9b934cd51e5d207f56a8178e7 3 OP_CHECKMULTISIG",
                "hex": "5121022cacedc1a455d6665433a2d852951b52014def0fb4769a3323a7ab0cccc1adc32103e74c5611fa7923392e1390838caf81814003baba2fab77e2c8a4bd9834ae6fa121020ba30b3409037b6653fdcc916775fe7f2a2dbca9b934cd51e5d207f56a8178e753ae",
                "reqSigs": 1,
                "type": "multisig"
            },
            "value": "0.00001250"
        },
        {
            "n": 2,
            "scriptPubKey": {
                "addresses": [
                    "1BqFKh5wwQpT3bor1kvTi6qFiBBdjDyVok"
                ],
                "asm": "OP_DUP OP_HASH160 76d12dfbe58981a0008ab832f6f02ebfd2f78661 OP_EQUALVERIFY OP_CHECKSIG",
                "hex": "76a91476d12dfbe58981a0008ab832f6f02ebfd2f7866188ac",
                "reqSigs": 1,
                "type": "pubkeyhash"
            },
            "spentIndex": 0,
            "spentTs": 1426358977,
            "spentTxId": "78c78509336c651a14cc2abae4f953c64a2654ee0f6b3b15736521ea354f92f8",
            "value": "0.05867430"
        }
    ]
}

 */