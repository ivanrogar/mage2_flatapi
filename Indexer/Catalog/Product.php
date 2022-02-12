<?php

declare(strict_types=1);

namespace JohnRogar\FlatApi\Indexer\Catalog;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Indexer\ActionInterface;
use Magento\Framework\Search\Request\Dimension;
use Magento\Store\Model\ResourceModel\Store\CollectionFactory as StoreCollectionFactory;
use Magento\Store\Model\ResourceModel\Store\Collection as StoreCollection;
use Magento\Framework\Indexer\SaveHandler\IndexerInterface;
use JohnRogar\FlatApi\Indexer\SaveHandler\ProductSaveHandlerFactory;

/**
 * @SuppressWarnings(Long)
 * @SuppressWarnings(Short)
 */
class Product implements ActionInterface, \Magento\Framework\Mview\ActionInterface
{
    private ProductSaveHandlerFactory $saveHandlerFactory;
    private ProductCollectionFactory $productCollectionFactory;
    private ProductRepositoryInterface $productRepository;
    private StoreCollectionFactory $storeCollectionFactory;

    private ?IndexerInterface $saveHandler = null;
    private ?array $storeIds = null;

    public function __construct(
        ProductSaveHandlerFactory $saveHandlerFactory,
        ProductCollectionFactory $productCollectionFactory,
        ProductRepositoryInterface $productRepository,
        StoreCollectionFactory $storeCollectionFactory
    ) {
        $this->saveHandlerFactory = $saveHandlerFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productRepository = $productRepository;
        $this->storeCollectionFactory = $storeCollectionFactory;
    }

    /**
     * @inheritDoc
     */
    public function execute($ids)
    {
        $this->reindexProducts(false, $ids);
    }

    /**
     * @inheritDoc
     */
    public function executeFull()
    {
        $this->reindexProducts(true);
    }

    /**
     * @inheritDoc
     */
    public function executeList(array $ids)
    {
        $this->reindexProducts(false, $ids);
    }

    /**
     * @inheritDoc
     */
    public function executeRow($id)
    {
        $this->reindexProducts(false, [$id]);
    }

    public function reindexProducts(bool $full, array $filterIds = [])
    {
        foreach ($this->getStoreIds() as $storeId) {
            /**
             * @var ProductCollection $productCollection
             */
            $productCollection = $this->productCollectionFactory->create();

            $productCollection
                ->addAttributeToSelect('*')
                ->addStoreFilter($storeId);

            if (!empty($filterIds)) {
                $productCollection->addAttributeToFilter('entity_id', ['in' => $filterIds]);
            }

            $dimensions = [];

            if ($full) {
                $dimensions[] = new Dimension('full', true);
            }

            $this->getSaveHandler()->saveIndex($dimensions, $productCollection);
        }
    }

    private function getSaveHandler(): IndexerInterface
    {
        if ($this->saveHandler === null) {
            $this->saveHandler = $this->saveHandlerFactory->create();
        }

        return $this->saveHandler;
    }

    private function getStoreIds(): array
    {
        if ($this->storeIds === null) {
            $this->storeIds = [];

            /**
             * @var StoreCollection $storeCollection
             */
            $storeCollection = $this->storeCollectionFactory->create();

            foreach ($storeCollection as $store) {
                $this->storeIds[] = (int)$store->getId();
            }
        }

        return $this->storeIds;
    }
}
