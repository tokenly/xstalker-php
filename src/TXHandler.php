<?php

namespace XStalker;

use XStalker\BitcoindEventHandler;

/**
* Handle transactions from bitcoind
*/
class TXHandler extends BitcoindEventHandler
{
    
    public function handleTransaction($txid) {
        $ts = intval(round(microtime(true) * 1000));
        $tx_data = [
            'ts'   => $ts,
            'txid' => $txid,
        ];

        $this->beanstalk_loader->loadTransaction($tx_data);
    }

}
