<?php

namespace XStalker\Health;

use Pheanstalk\Pheanstalk;
use XStalker\Health\ConsulClient;
use \DateTime;
use \Exception;

/**
* Consul health handler
*/
class HealthHandler
{

    public function __construct($consul_url, $service_prefix) {
        $this->service_prefix = $service_prefix;

        $this->consul_client = new ConsulClient($consul_url);
        $this->pheanstalk = new Pheanstalk(env('BEANSTALK_HOST'), env('BEANSTALK_PORT'));
    }

    public function update($state) {
        // called periodically

        // pheanstalk
        $this->checkQueueConnection();

        // connected state
        $this->checkPeerStatuses($state);
    }

    
    public function checkQueueConnection($connection=null) {

        $service_id = $this->service_prefix."queue";
        try {
            $stats = $this->pheanstalk->stats();
            if ($stats['uptime'] < 1) { throw new Exception("Unexpected Queue Connection", 1); }
            $this->consul_client->checkPass($service_id);

        } catch (Exception $e) {
            $this->consul_client->checkFail($service_id, $e->getMessage());
            $this->werror("Queue Connection Failed: ".$e->getMessage());
        }
    }

    public function checkPeerStatuses($state) {
        try {

            $run_time = time() - $state['start'];

            // peer
            $service_id = $this->service_prefix."peer";
            if ($state['connected'] OR $run_time < 15) {
                $this->consul_client->checkPass($service_id);
            } else {
                $this->consul_client->checkFail($service_id, "Disconnected");
            }

            // tx
            $service_id = $this->service_prefix."tx";
            $tx_delay = time() - $state['lastTx'];
            if ($tx_delay < 65 OR $run_time < 65) {
                $this->consul_client->checkPass($service_id);
            } else {
                $this->consul_client->checkFail($service_id, "Last TX was ".($state['lastTx'] > 0 ? "{$tx_delay} sec. ago" : "unknown").".");
            }

            // block
            $service_id = $this->service_prefix."block";
            $block_delay = time() - $state['lastBlock'];
            if ($block_delay < 3600 OR $run_time < 1800) {
                $this->consul_client->checkPass($service_id);
            } else {
                $this->consul_client->checkFail($service_id, "Last Block was ".($state['lastTx'] > 0 ? "{$block_delay} sec. ago" : "unknown").".");
            }

        } catch (Exception $e) {
            $this->werror("Check Peer Connection Failed: ".$e->getMessage());
        }

    }

    // ------------------------------------------------------------------------
    
    protected function werror($msg) {
        return $this->wlog("ERROR: ".$msg);
    }

    protected function wlog($msg) {
        print "[".date("Y-m-d H:i:s")."] ".rtrim($msg)."\n";
    }
}