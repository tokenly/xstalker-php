<?php

namespace XStalker;

use XStalker\BeanstalkLoader;



/**
* Handle events from bitcoind
*/
class BitcoindEventHandler
{

    protected $beanstalk_loader = null;

    public function __construct(BeanstalkLoader $beanstalk_loader) {
        $this->beanstalk_loader = $beanstalk_loader;
    }

}