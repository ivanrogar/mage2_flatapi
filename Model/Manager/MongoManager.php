<?php

declare(strict_types=1);

namespace JohnRogar\FlatApi\Model\Manager;

use InvalidArgumentException;
use JohnRogar\FlatApi\Api\ManagerInterface;
use JohnRogar\FlatApi\Api\ObjectRepositoryInterface;
use JohnRogar\FlatApi\Factory\Client\MongoClientFactory;
use JohnRogar\FlatApi\Model\Io\Dto;
use JohnRogar\FlatApi\Model\Io\ResultSet;
use MongoDB\Client;

class MongoManager implements ManagerInterface
{
    public const MANAGER_NAME = 'mongo';

    private MongoClientFactory $clientFactory;

    private ?Client $client = null;

    public function __construct(
        MongoClientFactory $clientFactory
    ) {
        $this->clientFactory = $clientFactory;
    }

    public function getName(): string
    {
        return self::MANAGER_NAME;
    }

    /**
     * @SuppressWarnings(Complexity)
     */
    public function query(
        string $tableName,
        array $filters = [],
        array $order = [],
        int $page = self::DEFAULT_PAGE,
        int $size = self::DEFAULT_SIZE
    ): ResultSet {
        $client = $this->getClient();

        // @phpstan-ignore-next-line
        $collection = $client->flat_api->$tableName;

        $totalItems = $collection->countDocuments($filters);
        $totalPages = 0;
        $items = [];

        if ($totalItems) {
            $totalPages = (int)ceil($totalItems / $size);

            if ($totalPages < 1) {
                $totalPages = 1;
            }

            if ($page <= $totalPages) {
                $skip = (int)($size * ($page - 1));

                $sort = [];

                foreach ($order as $value) {
                    if (is_string($value)) {
                        $sort[$value] = 1;
                    } elseif (is_array($value)) {
                        foreach ($value as $key => $sortDirection) {
                            if (
                                is_string($key) &&
                                is_string($sortDirection) &&
                                in_array($sortDirection, [self::SORT_ASC, self::SORT_DESC])
                            ) {
                                switch ($sortDirection) {
                                    case self::SORT_ASC:
                                        $sort[$key] = 1;
                                        break;
                                    case self::SORT_DESC:
                                        $sort[$key] = -1;
                                        break;
                                }
                            }
                        }
                    }
                }

                $cursor = $collection
                    ->find(
                        $filters,
                        [
                            'limit' => $size,
                            'skip' => $skip,
                            'sort' => $sort,
                        ]
                    );

                $items = [];

                foreach ($cursor as $item) {
                    unset($item['_id']);
                    $items[] = new Dto((array)\json_decode(\json_encode($item), true));
                }
            }
        }

        return new ResultSet(
            $totalPages,
            $totalItems,
            $items
        );
    }

    public function insertUpdate(string $tableName, array $items): void
    {
        $data = $this->toArray($items);

        $client = $this->getClient();

        // @phpstan-ignore-next-line
        $collection = $client->flat_api->$tableName;

        foreach ($data as $item) {
            $itemId = $item[ObjectRepositoryInterface::DEFAULT_IDENTIFIER] ?? null;

            if ($itemId !== null) {
                $exists = $collection->findOne([ObjectRepositoryInterface::DEFAULT_IDENTIFIER => $itemId]);

                if (!$exists) {
                    $collection->insertOne($item);
                    continue;
                }

                $collection->replaceOne(
                    [ObjectRepositoryInterface::DEFAULT_IDENTIFIER => $itemId],
                    $item
                );
            }
        }
    }

    public function delete(string $tableName, array $items): void
    {
        $data = $this->toArray($items);

        $client = $this->getClient();

        $allIds = [];

        foreach ($data as $item) {
            $itemId = $item[ObjectRepositoryInterface::DEFAULT_IDENTIFIER] ?? null;

            if ($itemId !== null) {
                $allIds[] = $itemId;
            }
        }

        // @phpstan-ignore-next-line
        $collection = $client->flat_api->$tableName;

        // @phpstan-ignore-next-line
        $collection->deleteMany(
            [ObjectRepositoryInterface::DEFAULT_IDENTIFIER => $allIds],
        );
    }

    public function truncate(string $tableName): void
    {
        $client = $this->getClient();

        // @phpstan-ignore-next-line
        $collection = $client->flat_api->$tableName;

        $collection->deleteMany([]);
    }

    private function getClient(): Client
    {
        if ($this->client === null) {
            $this->client = $this->clientFactory->create();
        }

        return $this->client;
    }

    private function toArray(array $items): array
    {
        $data = [];

        /**
         * @var array|Dto $item
         */
        foreach ($items as $item) {
            if (is_object($item)) {
                if (!$item instanceof Dto) {
                    throw new InvalidArgumentException(sprintf(
                        'Item must be an instance of %s',
                        Dto::class
                    ));
                }

                $data[] = $item->getData();

                continue;
            }

            if (is_array($item)) {
                $data[] = $item;
            }
        }

        return $data;
    }
}
