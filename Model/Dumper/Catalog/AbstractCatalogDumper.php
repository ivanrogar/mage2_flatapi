<?php

declare(strict_types=1);

namespace JohnRogar\FlatApi\Model\Dumper\Catalog;

use JohnRogar\FlatApi\Api\DumperInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\ProductLinkRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Gallery\ReadHandler;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection as AttributeCollection;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Catalog\Api\ProductAttributeManagementInterface;
use Magento\ConfigurableProduct\Api\LinkManagementInterface as ConfigurableLinkManagement;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Eav\Api\AttributeSetRepositoryInterface;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory as AttributeSetCollectionFactory;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Eav\Api\Data\AttributeSetInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * @SuppressWarnings(Long)
 * @SuppressWarnings(Coupling)
 * @SuppressWarnings(Excessive)
 * @SuppressWarnings(TooMany)
 */
abstract class AbstractCatalogDumper implements DumperInterface
{
    protected bool $cli = false;
    protected int $cliTotalTime = 0;
    protected array $data = [];
    protected int $currentStore = 0;
    protected bool $relativeUrls = false;

    protected AttributeRepositoryInterface $attributeRepository;
    protected ProductCollectionFactory $productCollectionFactory;
    protected ProductRepositoryInterface $productRepository;
    protected AttributeSetRepositoryInterface $attributeSetRepository;
    protected ReadHandler $readHandler;
    protected StoreManagerInterface $storeManager;
    protected CategoryCollectionFactory $categoryCollectionFactory;
    protected ConfigurableLinkManagement $configurableLinkManagement;
    protected ProductLinkRepositoryInterface $productLinkRepository;
    protected AttributeCollectionFactory $attributeCollectionFactory;
    protected EavConfig $eavConfig;
    protected AttributeSetCollectionFactory $attributeSetCollectionFactory;
    protected ProductAttributeManagementInterface $productAttributeManagement;

    /**
     * @var AttributeInterface[]
     */
    protected array $attributeCache = [];

    /**
     * @var AttributeSetInterface[]
     */
    protected array $attributeSetCache = [];

    protected ?array $visibleAttributes = null;
    protected ?array $filterableAttributes = null;

    protected array $attributeStoreLabels = [];

    protected ?string $currentStoreBaseUrl = null;

    protected array $passThroughAttributes = [];

    /**
     * @var array<int, array<int, CategoryInterface>>|null
     */
    protected ?array $categoryCache = null;

    public function __construct(
        AttributeRepositoryInterface $attributeRepository,
        ProductCollectionFactory $productCollectionFactory,
        ProductRepositoryInterface $productRepository,
        AttributeSetRepositoryInterface $attributeSetRepository,
        StoreManagerInterface $storeManager,
        ReadHandler $readHandler,
        CategoryCollectionFactory $categoryCollectionFactory,
        ConfigurableLinkManagement $configurableLinkManagement,
        ProductLinkRepositoryInterface $productLinkRepository,
        AttributeCollectionFactory $attributeCollectionFactory,
        EavConfig $eavConfig,
        AttributeSetCollectionFactory $attributeSetCollectionFactory,
        ProductAttributeManagementInterface $productAttributeManagement
    ) {
        $this->attributeRepository = $attributeRepository;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productRepository = $productRepository;
        $this->attributeSetRepository = $attributeSetRepository;
        $this->readHandler = $readHandler;
        $this->storeManager = $storeManager;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->configurableLinkManagement = $configurableLinkManagement;
        $this->productLinkRepository = $productLinkRepository;
        $this->attributeCollectionFactory = $attributeCollectionFactory;
        $this->eavConfig = $eavConfig;
        $this->attributeSetCollectionFactory = $attributeSetCollectionFactory;
        $this->productAttributeManagement = $productAttributeManagement;
    }

    public function setRelativeUrls(bool $relative): DumperInterface
    {
        $this->relativeUrls = $relative;
        return $this;
    }

    public function isRelativeUrls(): bool
    {
        return $this->relativeUrls;
    }

    /**
     * @SuppressWarnings(Unused)
     */
    public function process($key, &$value): void
    {
        //
    }

    protected function getBaseUrlPrefix(): string
    {
        return (!$this->isRelativeUrls())
            ? (string)$this->currentStoreBaseUrl
            : '';
    }

    /**
     * @param string $entityType
     * @param mixed $key
     * @param mixed $value
     * @SuppressWarnings(Complexity)
     */
    protected function normalizeAttributeValue(string $entityType, $key, &$value)
    {
        $attribute = $this->getAttribute($entityType, $key);

        $labels = [];

        if ($attribute instanceof AbstractAttribute && $attribute->usesSource()) {
            $type = $attribute->getFrontendInput();

            if (in_array($type, ['select', 'multiselect'])) {
                if (empty((array)$value)) {
                    $value = null;
                    return;
                }

                $attributeValues = (is_array($value)) ? $value : explode(',', $value);

                foreach ($attribute->getOptions() as $option) {
                    if ($option->getValue() && in_array($option->getValue(), $attributeValues)) {
                        $storeLabels = $option->getStoreLabels();

                        $hasStoreLabels = false;

                        if (is_iterable($storeLabels)) {
                            foreach ($storeLabels as $storeLabel) {
                                if ((int)$this->currentStore === (int)$storeLabel->getStoreId()) {
                                    $labels[] = $storeLabel->getLabel();
                                    $hasStoreLabels = true;
                                }
                            }
                        }

                        if ($hasStoreLabels) {
                            continue;
                        }

                        $labels[] = $option->getLabel();
                    }
                }

                if (empty($labels)) {
                    $value = null;
                    return;
                }

                reset($labels);

                $value = ($type === 'select')
                    ? current($labels)
                    : $labels;
            }
        }
    }

    protected function getAttribute(string $entityType, string $attributeCode): ?AttributeInterface
    {
        $cacheKey = $entityType . '_' . $attributeCode;

        if (array_key_exists($cacheKey, $this->attributeCache)) {
            return $this->attributeCache[$cacheKey];
        }

        try {
            $this->attributeCache[$cacheKey] = $this
                ->attributeRepository
                ->get($entityType, $attributeCode);

            return $this->attributeCache[$cacheKey];
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * @throws LocalizedException
     */
    protected function preloadAttributeFlags(string $entityType): void
    {
        if ($this->visibleAttributes === null) {
            $this->visibleAttributes = [];
            $this->filterableAttributes = [];

            /**
             * @var AttributeCollection $collection
             */
            $collection = $this->attributeCollectionFactory->create();

            $entityTypeId = $this
                ->eavConfig
                ->getEntityType($entityType)
                ->getId();

            $collection->setEntityTypeFilter($entityTypeId);

            foreach ($collection->getItems() as $item) {
                if ((int)$item->getData('is_visible_on_front')) {
                    $this->visibleAttributes[] = $item->getAttributeCode();
                }

                if ((int)$item->getData('is_filterable')) {
                    $this->filterableAttributes[] = $item->getAttributeCode();
                }
            }
        }
    }

    /**
     * @throws LocalizedException
     */
    protected function preloadCategories(): void
    {
        if ($this->categoryCache === null) {
            $this->categoryCache = [];

            foreach ($this->storeManager->getStores(true) as $store) {
                $storeId = (int)$store->getId();

                $this->categoryCache[$storeId] = [];

                /**
                 * @var CategoryCollection $collection
                 */
                $collection = $this->categoryCollectionFactory->create();

                $collection
                    ->addAttributeToSelect('*')
                    ->setStoreId($storeId);

                if ($collection->count()) {
                    /**
                     * @var CategoryInterface $category
                     */
                    foreach ($collection as $category) {
                        $this->categoryCache[$storeId][(int)$category->getId()] = $category;
                    }
                }
            }
        }
    }

    protected function getCategoryById(int $categoryId): ?CategoryInterface
    {
        if (isset($this->categoryCache[$this->currentStore][$categoryId])) {
            return $this->categoryCache[$this->currentStore][$categoryId];
        }

        return null;
    }

    protected function getAttributeSet(int $attributeSetId): ?AttributeSetInterface
    {
        if (array_key_exists($attributeSetId, $this->attributeSetCache)) {
            return $this->attributeSetCache[$attributeSetId];
        }

        try {
            $this->attributeSetCache[$attributeSetId] = $this->attributeSetRepository->get($attributeSetId);
            return $this->attributeSetCache[$attributeSetId];
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }
}
