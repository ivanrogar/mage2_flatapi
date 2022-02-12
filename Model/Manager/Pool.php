<?php

declare(strict_types=1);

namespace JohnRogar\FlatApi\Model\Manager;

use JohnRogar\FlatApi\Api\ManagerInterface;
use JohnRogar\FlatApi\Model\Config;
use RuntimeException;

class Pool
{
    private Config $config;

    private iterable $managers;

    /**
     * @param ManagerInterface[] $managers
     */
    public function __construct(
        Config $config,
        iterable $managers = []
    ) {
        $this->config = $config;
        $this->managers = $managers;
    }

    /**
     * @throws RuntimeException
     */
    public function get(): ManagerInterface
    {
        $selectedManager = $this->config->getManager();

        if ($selectedManager && array_key_exists($selectedManager, $this->managers)) {
            return $this->managers[$selectedManager];
        }

        throw new RuntimeException('Manager not found');
    }
}
