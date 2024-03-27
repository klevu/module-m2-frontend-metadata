<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\FrontendMetadata\Service\Provider\Checkout\Cart;

use Klevu\FrontendMetadataApi\Service\Provider\Checkout\Cart\ItemIdProviderInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Quote\Api\Data\CartItemInterface;

class ItemIdProvider implements ItemIdProviderInterface
{
    /**
     * @var SerializerInterface
     */
    private readonly SerializerInterface $serializer;

    /**
     * @param SerializerInterface $serializer
     */
    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * @param CartItemInterface $item
     *
     * @return string
     */
    public function getItemId(CartItemInterface $item): string
    {
        return match ($item->getProductType()) {
            Configurable::TYPE_CODE => $this->getConfigurableProductId($item),
            Grouped::TYPE_CODE => $this->getGroupedProductId($item),
            default => $this->getDefaultProductId($item),
        };
    }

    /**
     * @param CartItemInterface $item
     *
     * @return string|null
     */
    public function getItemGroupId(CartItemInterface $item): ?string
    {
        if (!method_exists($item, 'getData')) {
            return null;
        }

        return match ($item->getProductType()) {
            Configurable::TYPE_CODE => (string)$item->getData('product_id'),
            default => null,
        };
    }

    /**
     * @param CartItemInterface $item
     *
     * @return string
     */
    private function getConfigurableProductId(CartItemInterface $item): string
    {
        if (!(method_exists($item, 'getData') || method_exists($item, 'getChildren'))) {
            return '';
        }
        $itemId = (string)$item->getData('product_id');
        $children = $item->getChildren();
        $child = array_shift($children);

        return $itemId . '-' . $child->getData('product_id');
    }

    /**
     * @param CartItemInterface $item
     *
     * @return string
     */
    private function getGroupedProductId(CartItemInterface $item): string
    {
        if (!method_exists($item, 'getOptionByCode')) {
            return '';
        }
        $buyRequest = $item->getOptionByCode('info_buyRequest');
        if (!$buyRequest) {
            return '';
        }
        $buyRequestArray = $this->serializer->unserialize($buyRequest->getValue());
        $productConfig = $buyRequestArray['super_product_config'] ?? null;

        return $productConfig['product_id'] ?? '';
    }

    /**
     * @param CartItemInterface $item
     *
     * @return string
     */
    private function getDefaultProductId(CartItemInterface $item): string
    {
        if (!method_exists($item, 'getData')) {
            return '';
        }

        return (string)$item->getData('product_id');
    }
}
