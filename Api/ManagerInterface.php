<?php

declare(strict_types=1);

namespace JohnRogar\FlatApi\Api;

use InvalidArgumentException;
use JohnRogar\FlatApi\Model\Io\Dto;
use JohnRogar\FlatApi\Model\Io\ResultSet;

interface ManagerInterface
{
    public const DEFAULT_PAGE = 1;
    public const DEFAULT_SIZE = 12;

    public const SORT_ASC = 'ASC';
    public const SORT_DESC = 'DESC';

    public function getName(): string;

    public function query(
        string $tableName,
        array $filters = [],
        array $order = [],
        int $page = self::DEFAULT_PAGE,
        int $size = self::DEFAULT_SIZE
    ): ResultSet;

    /**
     * @param array<Dto> $items
     * @throws InvalidArgumentException
     */
    public function insertUpdate(string $tableName, array $items): void;

    /**
     * @param array<Dto> $items
     * @throws InvalidArgumentException
     */
    public function delete(string $tableName, array $items): void;

    public function truncate(string $tableName): void;
}
