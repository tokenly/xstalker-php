<?php

namespace XStalker;

use XStalker\BitcoindEventHandler;


/**
* Handle transactions from bitcoind
*/
class BlockHandler extends BitcoindEventHandler
{
    
    public function handleBlock($block_hash) {
        $ts = intval(round(microtime(true) / 1000));

        $block_data = [
            'ts'   => $ts,
            'hash' => $block_hash,
        ];

        $this->beanstalk_loader->loadBlock($block_data);

    }



}

