<?php

declare(strict_types=1);

namespace JohnRogar\FlatApi\Api;

use JohnRogar\FlatApi\Model\Io\ResultSet;

interface DataProviderInterface
{
    public const DEFAULT_PARAM_ORDER = 'order';
    public const DEFAULT_PARAM_PAGE = 'page';
    public const DEFAULT_PARAM_SIZE = 'size';

    public function getMany(
        array $filters = [],
        array $order = [],
        int $page = ManagerInterface::DEFAULT_PAGE,
        int $size = ManagerInterface::DEFAULT_SIZE
    ): ResultSet;
}
