<?php

namespace XStalker;

use Pheanstalk\Pheanstalk;


/**
* Loads events into Beanstalk
*/
class BeanstalkLoader
{

    protected $pheanstalk      = null;
    protected $tx_tube_name    = 'btctx';
    protected $block_tube_name = 'btcblock';
    
    public function __construct(Pheanstalk $pheanstalk) {
        $this->pheanstalk = $pheanstalk;
    }

    public function loadTransaction($tx_data) {
        // echo "loadTransaction \$tx_data: ".json_encode($tx_data, 192)."\n";
        $this->pheanstalk->putInTube($this->tx_tube_name, $this->buildBeanstalkString('BTCTransactionJob', $tx_data));
    }

    public function loadBlock($block_data) {
        // echo "loadBlock \$block_data: ".json_encode($block_data, 192)."\n";
        $this->pheanstalk->putInTube($this->block_tube_name, $this->buildBeanstalkString('BTCBlockJob', $block_data));
    }

    protected function buildBeanstalkString($job_name, $data) {
        return json_encode([
            'job'  => "App\\Listener\\Job\\${job_name}",
            'data' => $data,
        ]);
    }
}