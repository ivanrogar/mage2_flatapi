<?php

declare(strict_types=1);

namespace JohnRogar\FlatApi\Api;

use Magento\Framework\Webapi\Exception as WebException;

interface FlatApiInterface
{
    /**
     * @param string $dataIdentifier
     * @return array
     * @throws WebException
     */
    public function fetch(string $dataIdentifier): array;

    /**
     * @param string $dataIdentifier
     * @param string $sku
     * @return array
     * @throws WebException
     */
    public function fetchBySku(string $dataIdentifier, string $sku): array;
}
