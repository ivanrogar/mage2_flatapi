<?php

declare(strict_types=1);

namespace JohnRogar\FlatApi\Model\Dumper\Catalog;

use JohnRogar\FlatApi\Api\DumperInterface;
use JohnRogar\FlatApi\Model\Io\Dto;
use Magento\CatalogInventory\Model\Stock\Item;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Catalog\Api\Data\ProductInterface;

/**
 * @SuppressWarnings(Long)
 * @SuppressWarnings(Coupling)
 * @SuppressWarnings(Excessive)
 * @SuppressWarnings(TooMany)
 */
class Product extends AbstractCatalogDumper implements DumperInterface
{
    protected array $passThroughAttributes = [
        'sku',
        'type_id',
        'category_ids',
    ];

    public const NAME = 'catalog_product';

    public function getName(): string
    {
        return self::NAME;
    }

    public function supports(iterable $objects = []): bool
    {
        foreach ($objects as $object) {
            if (!$object instanceof \Magento\Catalog\Model\Product) {
                return false;
            }
        }

        return true;
    }

    /**
     * @SuppressWarnings(Complexity)
     * @SuppressWarnings(Excessive)
     * @throws LocalizedException
     */
    public function dump(iterable $objects): array
    {
        $this->preloadCategories();
        $this->preloadAttributeFlags(\Magento\Catalog\Model\Product::ENTITY);

        $this->data = [];

        $total = 0;

        /**
         * @var \Magento\Catalog\Model\Product $product
         */
        foreach ($objects as $product) {
            $this->currentStore = (int)$product->getStoreId();

            $this->currentStoreBaseUrl = $this->storeManager->getStore($this->currentStore)->getBaseUrl();

            $sku = $product->getSku();

            if (!trim((string)$sku)) {
                continue;
            }

            $total++;

            $productType = $product->getTypeId();

            $this->data[$sku] = $this->toArray($product);

            switch ($productType) {
                case 'configurable':
                    /**
                     * @var \Magento\Catalog\Model\Product[] $children
                     */
                    $children = $this->configurableLinkManagement->getChildren($sku);

                    if (is_iterable($children)) {
                        $this->data[$sku]['children'] = [];

                        foreach ($children as $child) {
                            /**
                             * @var \Magento\Catalog\Model\Product $childProduct
                             */
                            $childProduct = $this->getProduct($child->getSku());

                            $this->data[$sku]['children'][] = $this
                                ->toArray($this->getProduct($childProduct->getSku()));

                            $childProduct->clearInstance();
                        }
                    }

                    break;
                case 'grouped':
                    $links = $this->productLinkRepository->getList($product);

                    if (is_iterable($links)) {
                        $this->data[$sku]['children'] = [];

                        foreach ($links as $link) {
                            if ($link->getLinkType() === 'associated') {
                                /**
                                 * @var \Magento\Catalog\Model\Product|null $linkedProduct
                                 */
                                $linkedProduct = $this->getProduct($link->getLinkedProductSku());

                                if ($linkedProduct !== null) {
                                    $this->data[$sku]['children'][] = $this->toArray($linkedProduct);
                                    $linkedProduct->clearInstance();
                                }
                            }
                        }
                    }

                    break;
            }

            gc_collect_cycles();

            if ($total > 5) {
                break;
            }
        }

        foreach ($this->data as $sku => $data) {
            $this->data[$sku] = new Dto($data);
        }

        return $this->data;
    }

    /**
     * @inheritDoc
     */
    public function process($key, &$value): void
    {
        if (in_array($key, ['image', 'small_image', 'thumbnail', 'swatch_image']) && !empty($value)) {
            $value = $this->getBaseUrlPrefix() . 'media/catalog/product' . $value;
        }
    }

    /**
     * @param mixed $sku
     */
    private function getProduct($sku): ?ProductInterface
    {
        try {
            return $this->productRepository->get($sku, false, $this->currentStore, true);
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * @SuppressWarnings(Complexity)
     * @SuppressWarnings(Excessive)
     * @throws LocalizedException
     */
    private function toArray(ProductInterface $product): array
    {
        if ($product instanceof \Magento\Catalog\Model\Product) {
            $data = $product->getData();

            $data['url'] = $this->getBaseUrlPrefix() . $product->getUrlKey();
            $data['category_ids'] = (array)$product->getCategoryIds();

            sort($data['category_ids']);

            $categoryData = [];

            foreach ($data['category_ids'] as $categoryId) {
                $categoryId = (int)$categoryId;

                $categoryModel = $this->getCategoryById($categoryId);

                if ($categoryModel !== null) {
                    $categoryData[] = [
                        'id' => $categoryId,
                        'name' => $categoryModel->getName(),
                    ];
                }
            }

            $data['categories'] = $categoryData;

            $data['attributes'] = [];

            foreach ($data as $key => &$value) {
                $attribute = $this->getAttribute(\Magento\Catalog\Model\Product::ENTITY, $key);

                if ($attribute && !in_array($key, $this->passThroughAttributes)) {
                    $originalValue = $value;

                    $this->normalizeAttributeValue(\Magento\Catalog\Model\Product::ENTITY, $key, $value);

                    $data['attributes'][$key] = $value;

                    if (in_array($attribute->getFrontendInput(), ['select', 'multiselect'])) {
                        $data['attributes'][$key . '_value'] = $originalValue;
                    }

                    unset($data[$key]);

                    $this->process($key, $data['attributes'][$key]);

                    continue;
                }

                $this->process($key, $value);
            }

            $attributeSet = $this->getAttributeSet((int)$product->getAttributeSetId());

            $data['item_attribute_set'] = $attributeSet->getAttributeSetName();
            $data['item_tax_rate'] = $product->getAttributeText('tax_class_id');

            $defaultStock = [
                'manage_stock' => 1,
                'is_in_stock' => 0,
                'qty' => 0,
                'use_config_manage_stock' => 0,
                'use_config_notify_stock_qty' => 0,
            ];

            $data['stock_data'] = $defaultStock;

            if ($product->getTypeId() === 'simple') {
                $productInterface = $this->getProduct($product->getSku());

                if (
                    $productInterface !== null &&
                    $productInterface->getExtensionAttributes() &&
                    $productInterface->getExtensionAttributes()->getStockItem()
                ) {
                    /**
                     * @var Item $stockItem
                     */
                    $stockItem = $productInterface->getExtensionAttributes()->getStockItem();

                    $data['stock_data']['manage_stock'] = (int)$stockItem->getManageStock();
                    $data['stock_data']['is_in_stock'] = (int)$stockItem->getIsInStock();
                    $data['stock_data']['qty'] = $stockItem->getQty();
                    $data['stock_data']['min_sale_qty'] = $stockItem->getMinSaleQty() ?? 1;
                    $data['stock_data']['max_sale_qty'] = $stockItem->getMaxSaleQty() ?? 99999;
                    $data['stock_data']['qty_increments'] = $stockItem->getQtyIncrements();
                    $data['stock_data']['backorders'] = $stockItem->getBackorders();
                }
            }

            $data['media_gallery'] = [];

            $this->readHandler->execute($product);

            $mediaGallery = $product->getMediaGalleryEntries();

            $productImage = $product->getImage();

            if (is_iterable($mediaGallery)) {
                foreach ($mediaGallery as $item) {
                    $primaryItem = false;

                    if ($item->getMediaType() === 'image') {
                        if ($productImage === $item->getFile()) {
                            $primaryItem = true;
                        }

                        $data['media_gallery'][] = [
                            'url' => $this->getBaseUrlPrefix() . 'media/catalog/product' . $item->getFile(),
                            'is_primary' => (int)$primaryItem,
                        ];
                    }
                }
            }

            return $data;
        }

        return [];
    }
}
