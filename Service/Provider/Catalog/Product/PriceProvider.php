<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\FrontendMetadata\Service\Provider\Catalog\Product;

use Klevu\FrontendMetadataApi\Service\Provider\Catalog\Product\PriceProviderInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Framework\Pricing\PriceInfoInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Psr\Log\LoggerInterface;

class PriceProvider implements PriceProviderInterface
{
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param ProductInterface $product
     *
     * @return float
     */
    public function get(ProductInterface $product): float
    {
        return match ($product->getTypeId()) {
            Grouped::TYPE_CODE => $this->getGroupedProductPrice($product),
            default => $this->getFinalPrice($product),
        };
    }

    /**
     * @param ProductInterface $product
     *
     * @return float
     */
    private function getGroupedProductPrice(ProductInterface $product): float
    {
        $price = $this->getPriceInfoFinalPrice($product);

        return $price ?? $this->getFinalPrice($product);
    }

    /**
     * @param ProductInterface $product
     *
     * @return float|null
     */
    private function getPriceInfoFinalPrice(ProductInterface $product): ?float
    {
        if (!method_exists($product, 'getPriceInfo')) {
            return null;
        }
        /** @var PriceInfoInterface $priceInfo */
        $priceInfo = $product->getPriceInfo();
        $price = $priceInfo->getPrice(FinalPrice::PRICE_CODE);

        return $price->getValue();
    }

    /**
     * @param ProductInterface $product
     *
     * @return float
     */
    private function getFinalPrice(ProductInterface $product): float
    {
        if (method_exists($product, 'getFinalPrice')) {
            return (float)$product->getFinalPrice();
        }
        $this->logger->warning(
            message: 'Method: {method}, Warning: {message}',
            context: [
                'method' => __METHOD__,
                'message' => sprintf(
                    'Supplied product model (%s) does not have method getFinalPrice. '
                    . '0.00 returned as price for product ID (%s)',
                    get_debug_type($product),
                    $product->getId(),
                ),
            ],
        );

        return 0.00;
    }
}
