<?php

declare(strict_types=1);

namespace JohnRogar\FlatApi\Factory\Client;

use JohnRogar\FlatApi\Model\Config;
use MongoDB\Client;

class MongoClientFactory
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function create(): Client
    {
        return new Client($this->config->getMongoUri());
    }
}
