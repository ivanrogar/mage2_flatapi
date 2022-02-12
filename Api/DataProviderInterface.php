<?php

declare(strict_types=1);

namespace JohnRogar\FlatApi\Api;

use Magento\Framework\DataObject;

interface DataProviderInterface
{
    public const DEFAULT_PAGE = 1;
    public const DEFAULT_SIZE = 12;

    public const DEFAULT_PARAM_ORDER = 'order';
    public const DEFAULT_PARAM_PAGE = 'page';
    public const DEFAULT_PARAM_SIZE = 'size';

    /**
     * @return DataObject[]
     */
    public function getMany(
        array $filters = [],
        array $order = [],
        int $page = self::DEFAULT_PAGE,
        int $size = self::DEFAULT_SIZE
    ): array;
}
