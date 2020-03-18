<?php

namespace pool;

use Smf\ConnectionPool\Connectors\ConnectorInterface;

class connectionPool extends \Smf\ConnectionPool\ConnectionPool
{

    public function __construct(array $poolConfig, ConnectorInterface $connector, array $connectionConfig)
    {
        parent::__construct($poolConfig, $connector, $connectionConfig);
    }

    public function gcConnection($conn)
    {
        $this->removeConnection($conn);
        return $this->createConnection();
    }

}