<?php

namespace XStalker;

use BitWasp\Bitcoin\Networking\Messages\Inv;
use BitWasp\Bitcoin\Networking\Messages\Ping;
use BitWasp\Bitcoin\Networking\Messages\Tx;
use BitWasp\Bitcoin\Networking\Messages\Version;
use BitWasp\Bitcoin\Networking\Peer\Peer;
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

    public function __construct() {
        $this->init();
    }

    public function init() {
        // setup event loop
        $this->loop = \React\EventLoop\Factory::create();

        $this->initPeerConnector();
        $this->buildHandlers();

        $this->state = new \ArrayObject([
            'peer'       => null,
            'connected'  => false,
            'start'      => time(),
            'lastTx'     => 0,
            'txCount'    => 0,
            'lastBlock'  => 0,
            'blockCount' => 0,
        ]);

        return $this;
    }

    public function run() {
        // start connecting
        $this->connect();

        // add periodic timer
        $this->loop->addPeriodicTimer(60, [$this, 'periodic']);

        // add health timer
        if (getenv('CONSUL_ACTIVE')) {
            $health_handler = new HealthHandler(getenv('CONSUL_URL'), getenv('CONSUL_HEALTH_SERVICE_ID_PREFIX'));
            $this->loop->addPeriodicTimer(getenv('CONSUL_LOOP_DELAY'), function() use ($health_handler) {
                $health_handler->update($this->state);
            });
        }

        // run loop
        $this->loop->run();
    }

    // ------------------------------------------------------------------------
    
    protected function initPeerConnector() {
        $factory = new \BitWasp\Bitcoin\Networking\Factory($this->loop);
        $this->peer_factory = $factory->getPeerFactory($factory->getDns());
        $this->host = $this->peer_factory->getAddress(gethostbyname(getenv('BITCOIND_HOST')), getenv('BITCOIND_PORT'));
        $this->connector = $this->peer_factory->getConnector();
    }

    protected function buildHandlers() {
        // initialize our beanstalk connection
        $pheanstalk = new Pheanstalk(getenv('BEANSTALK_HOST'), getenv('BEANSTALK_PORT'));

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

        if ($this->state['connected'] AND $this->state['peer']) {
            $this->state['peer']->ping();
        } else if (!$this->state['connected']) {
            $this->werror("Found disconnected state");
            $this->loop->futureTick([$this, 'connect']);
        }
    }

    public function connect() {
        if ($this->state['connected']) {
            $this->wlog("Already connected.  Ignoring connection request.");
            return;
        }

        $peer = $this->peer_factory->getPeer();
        $this->state['peer'] = $peer;
        $this->state['connected'] = true;

        $this->wlog("--- Connecting to peer at ".$this->host->getIp().":".$this->host->getPort());
        $peer->requestRelay()
            ->timeoutWithoutVersion(10)
            ->connect($this->connector, $this->host)
            ->otherwise(function ($e) {
                $this->state['connected'] = false;
                $this->wlog("Connection failed ".$e->getMessage());
                $this->loop->addTimer(2, [$this, 'connect']);
            });

        $peer->on('version', function (Peer $peer, Version $ver) {
            $this->wlog("--- Bitcoind Version is ".$ver->getVersion());
        });

        $peer->on('ready', function (Peer $peer) {
            $remote_addr = $peer->getRemoteAddr();
            $this->wlog("+++ Connected to peer at ".$remote_addr->getIp().":".$remote_addr->getPort());
        });

        $peer->on('inv', function (Peer $peer, Inv $inv) {
            try {
                foreach ($inv->getItems() as $item) {
                    if ($item->isBlock() OR $item->isFilteredBlock()) {
                        $this->block_handler->handleBlock($item->getHash()->getHex());
                        $this->state['lastBlock'] = time();
                        ++$this->state['blockCount'];

                    } else if ($item->isTx()) {
                        $txid = $item->getHash()->getHex();
                        if ($this->state['txCount'] < 40) {
                            $this->wlog("TX {$this->state['txCount']}: $txid");
                        } else if ($this->state['txCount'] == 40) {
                            $this->wlog("End logging every TX ID");
                        }

                        $this->tx_handler->handleTransaction($txid);
                        $this->state['lastTx'] = time();
                        ++$this->state['txCount'];
                    }
                }
            } catch (Exception $e) {
                $this->werror("ERROR: ".$e->getMessage());
            }
        });

        $peer->on('ping', function (Peer $peer, Ping $ping) {
            try {
                $this->wlog("PING received from peer");
                $peer->pong($ping);
            } catch (Exception $e) {
                $this->werror("ERROR: ".$e->getMessage());
            }
        });

        // handle disconnection and reconnect
        $peer->on('close', function () {
            $this->state['connected'] = false;
            $this->wlog("Disconnected..");

            // received a disconnect notice
            // connect again in a few seconds
            $this->loop->addTimer(4, [$this, 'connect']);
        });
    }

    protected function werror($msg) {
        return $this->wlog("ERROR: ".$msg);
    }

    protected function wlog($msg) {
        print "[".date("Y-m-d H:i:s")."] ".rtrim($msg)."\n";
    }
}