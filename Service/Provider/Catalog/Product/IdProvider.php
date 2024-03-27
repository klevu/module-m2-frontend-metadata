<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\FrontendMetadata\Service\Provider\Catalog\Product;

use Klevu\FrontendMetadataApi\Service\Provider\Catalog\Product\IdProviderInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\ConfigurableProduct\Api\LinkManagementInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

class IdProvider implements IdProviderInterface
{
    /**
     * @var LinkManagementInterface
     */
    private readonly LinkManagementInterface $linkManagement;

    /**
     * @param LinkManagementInterface $linkManagement
     */
    public function __construct(LinkManagementInterface $linkManagement)
    {
        $this->linkManagement = $linkManagement;
    }

    /**
     * @param ProductInterface $product
     *
     * @return string
     */
    public function getItemId(ProductInterface $product): string
    {
        return match ($product->getTypeId()) {
            Configurable::TYPE_CODE => $this->getConfigurableChildProductId(product: $product),
            default => (string)$product->getId(),
        };
    }

    /**
     * @param ProductInterface $product
     *
     * @return string
     */
    public function getItemGroupId(ProductInterface $product): string
    {
        return match ($product->getTypeId()) {
            Configurable::TYPE_CODE => (string)$product->getId(),
            default => '',
        };
    }

    /**
     * @param ProductInterface $product
     *
     * @return string
     */
    private function getConfigurableChildProductId(ProductInterface $product): string
    {
        $childProductId = $this->getChildProductId(product: $product);

        return $childProductId
            ? $product->getId() . '-' . $childProductId
            : (string)$product->getId();
    }

    /**
     * @param ProductInterface $product
     *
     * @return int|null
     */
    private function getChildProductId(ProductInterface $product): ?int
    {
        $childProducts = $this->linkManagement->getChildren(sku: $product->getSku());
        $return = null;
        foreach ($childProducts as $childProduct) {
            if (method_exists($childProduct, 'isAvailable') && $childProduct->isAvailable()) {
                $return = (int)$childProduct->getId();
                break;
            }
        }

        return $return;
    }
}
