<?php

declare(strict_types=1);

namespace JohnRogar\FlatApi\Model\Repository;

use JohnRogar\FlatApi\Api\ManagerInterface;
use JohnRogar\FlatApi\Api\ObjectRepositoryInterface;
use JohnRogar\FlatApi\Model\Io\ResultSet;
use JohnRogar\FlatApi\Model\Manager\Pool;

class ProductRepository implements ObjectRepositoryInterface
{
    public const TABLE_NAME = 'catalog_product';

    private Pool $pool;

    public function __construct(Pool $pool)
    {
        $this->pool = $pool;
    }

    public function getMany(
        array $filters = [],
        array $order = [],
        int $storeId = 0,
        int $page = ManagerInterface::DEFAULT_PAGE,
        int $size = ManagerInterface::DEFAULT_SIZE
    ): ResultSet {
        // todo: allow only particular filters

        return $this
            ->pool
            ->get()
            ->query(
                self::TABLE_NAME . '_' . $storeId,
                $filters,
                $order,
                $page,
                $size
            );
    }

    public function save(array $items): void
    {
        foreach ($this->chunkByStores($items) as $storeId => $itemsByStore) {
            $this
                ->pool
                ->get()
                ->insertUpdate(self::TABLE_NAME . '_' . $storeId, $itemsByStore);
        }
    }

    public function delete(array $items): void
    {
        foreach ($this->chunkByStores($items) as $storeId => $itemsByStore) {
            $this
                ->pool
                ->get()
                ->delete(self::TABLE_NAME . '_' . $storeId, $itemsByStore);
        }
    }

    public function deleteById(array $ids, int $storeId = 0): void
    {
        $items = array_map(function ($itemId) {
            return [
                self::DEFAULT_IDENTIFIER => $itemId
            ];
        }, $ids);

        $this
            ->pool
            ->get()
            ->delete(self::TABLE_NAME . '_' . $storeId, $items);
    }

    public function truncate(int $storeId = 0): void
    {
        $this
            ->pool
            ->get()
            ->truncate(self::TABLE_NAME . '_' . $storeId);
    }

    private function chunkByStores(array $items): array
    {
        $storeData = [];

        foreach ($items as $item) {
            $storeId = (int)$item['store_id'] ?? 0;

            if (!array_key_exists($storeId, $storeData)) {
                $storeData[$storeId] = [];
            }

            $storeData[$storeId][] = $item;
        }

        return $storeData;
    }
}
