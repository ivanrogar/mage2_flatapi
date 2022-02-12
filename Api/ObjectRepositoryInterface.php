<?php

declare(strict_types=1);

namespace JohnRogar\FlatApi\Api;

use JohnRogar\FlatApi\Model\Io\Dto;
use JohnRogar\FlatApi\Model\Io\ResultSet;

interface ObjectRepositoryInterface
{
    public const DEFAULT_PARAM_ORDER = 'order';
    public const DEFAULT_PARAM_PAGE = 'page';
    public const DEFAULT_PARAM_SIZE = 'size';

    public const DEFAULT_IDENTIFIER = 'entity_id';

    public function getMany(
        array $filters = [],
        array $order = [],
        int $storeId = 0,
        int $page = ManagerInterface::DEFAULT_PAGE,
        int $size = ManagerInterface::DEFAULT_SIZE
    ): ResultSet;

    /**
     * @param array<Dto> $items
     */
    public function save(array $items): void;

    /**
     * @param array<Dto> $items
     */
    public function delete(array $items): void;

    public function deleteById(array $ids, int $storeId = 0): void;

    public function truncate(): void;
}
