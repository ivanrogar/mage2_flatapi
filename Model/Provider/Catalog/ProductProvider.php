<?php

declare(strict_types=1);

namespace JohnRogar\FlatApi\Model\Provider\Catalog;

use JohnRogar\FlatApi\Api\DataProviderInterface;
use JohnRogar\FlatApi\Api\ManagerInterface;
use JohnRogar\FlatApi\Api\ObjectRepositoryInterface;
use JohnRogar\FlatApi\Model\Io\ResultSet;
use Magento\Store\Model\StoreManagerInterface;

class ProductProvider implements DataProviderInterface
{
    private ObjectRepositoryInterface $objectRepository;
    private StoreManagerInterface $storeManager;

    public function __construct(
        ObjectRepositoryInterface $objectRepository,
        StoreManagerInterface $storeManager
    ) {
        $this->objectRepository = $objectRepository;
        $this->storeManager = $storeManager;
    }

    public function getMany(
        array $filters = [],
        array $order = [],
        int $page = ManagerInterface::DEFAULT_PAGE,
        int $size = ManagerInterface::DEFAULT_SIZE
    ): ResultSet {
        $results = $this
            ->objectRepository
            ->getMany(
                $filters,
                $order,
                (int)$this->storeManager->getStore()->getId(),
                $page,
                $size
            );

        foreach ($results->getItems() as $item) {
            $item
                ->addData(
                    [
                        '@id' => '/rest/V1/flatApi/products/' . $item->getSku(),
                        '@type' => 'CatalogProduct',
                        'id' => $item->getEntityId(),
                    ]
                );

            $item->unsetData('entity_id');
        }

        return $results;
    }
}
