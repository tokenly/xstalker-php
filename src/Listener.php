<?php

namespace XStalker;

use Pheanstalk\Pheanstalk;
use XStalker\BeanstalkLoader;
use XStalker\BlockHandler;
use XStalker\Health\HealthHandler;
use XStalker\TXHandler;
use \DateTime;
use \Exception;

/**
* Bitcoin peer listener
*/
class Listener
{

    var $DEBUG_LOG_TX_COUNT = 50;

    public function __construct() {
        $this->init();
    }

    public function init() {
        // setup event loop
        $this->loop = \React\EventLoop\Factory::create();

        $this->buildHandlers();

        $this->state = new \ArrayObject([
            'sub'        => null,
            'start'      => time(),
            'lastTx'     => 0,
            'txCount'    => 0,
            'lastBlock'  => 0,
            'blockCount' => 0,
        ]);

        return $this;
    }

    public function run() {
        // connect
        $this->connect();

        // add periodic timer
        $this->loop->addPeriodicTimer(60, [$this, 'periodic']);

        // add health timer
        if (env('CONSUL_ACTIVE')) {
            $health_handler = new HealthHandler(env('CONSUL_URL'), env('CONSUL_HEALTH_SERVICE_ID_PREFIX'));
            $this->loop->addPeriodicTimer(env('CONSUL_LOOP_DELAY'), function() use ($health_handler) {
                $health_handler->update($this->state);
            });
        }

        // run loop
        $this->loop->run();
    }

    // ------------------------------------------------------------------------

    protected function buildHandlers() {
        // initialize our beanstalk connection
        $pheanstalk = new Pheanstalk(env('BEANSTALK_HOST'), env('BEANSTALK_PORT'));

        // setup TXHandler and BlockHandler
        $beanstalk_loader = new BeanstalkLoader($pheanstalk);
        $this->tx_handler = new TXHandler($beanstalk_loader);
        $this->block_handler = new BlockHandler($beanstalk_loader);

    }

    public function periodic() {
        $seconds = (time() - $this->state['start']);

        $dtF = new DateTime("@0");
        $dtT = new DateTime("@$seconds");
        $desc = $dtF->diff($dtT)
            ->format('%a day(s), %h hour(s), %i minute(s) and %s second(s).')
            .' Handled '.$this->state['blockCount'].' blocks and '.$this->state['txCount'].' transactions.';

        $this->wlog("Been running for $desc");
    }

    public function connect() {
        // connect
        $context = new \React\ZMQ\Context($this->loop);
        $sub = $context->getSocket(\ZMQ::SOCKET_SUB);
        $connection_string = 'tcp://'.gethostbyname(env('BITCOIND_HOST')).':'.env('BITCOIND_ZMQ_PORT');
        $this->wlog("--- Connecting to ZMQ publisher at ".$connection_string);
        $sub->connect($connection_string);


        // listen to tx and block messages
        $sub->subscribe('hashtx');
        $sub->subscribe('hashblock');
        $sub->on('messages', function ($msg) use (&$tx_count, &$break) {
            $channel = $msg[0];

            if ($channel == 'hashtx') {
                $txid = bin2hex($msg[1]);
                if ($this->state['txCount'] < $this->DEBUG_LOG_TX_COUNT) {
                    $this->wlog("TX {$this->state['txCount']}: $txid");
                } else if ($this->state['txCount'] == $this->DEBUG_LOG_TX_COUNT) {
                    $this->wlog("End logging every TX ID");
                }

                $this->tx_handler->handleTransaction($txid);
                $this->state['lastTx'] = time();
                ++$this->state['txCount'];
            } else if ($channel == 'hashblock') {
                $block_hash = bin2hex($msg[1]);

                $this->wlog("=== BLOCK ".($this->state['blockCount']+1)." received: $block_hash ===");

                $this->block_handler->handleBlock($block_hash);
                $this->state['lastBlock'] = time();
                ++$this->state['blockCount'];
            } else {
                $this->werror("Unknown channel: $channel");
            }
        });

        $sub->on('error', function ($e) {
            $this->werror($e->getMessage());
        });

        // handle disconnection
        $sub->on('end', function () {
            $this->wlog("Disconnected.");
        });

        $this->state['sub'] = $sub;
    }

    protected function werror($msg) {
        return $this->wlog("ERROR: ".$msg);
    }

    protected function wlog($msg) {
        print "[".date("Y-m-d H:i:s")."] ".rtrim($msg)."\n";
    }
}
