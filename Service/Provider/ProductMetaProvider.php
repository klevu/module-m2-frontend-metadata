<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\FrontendMetadata\Service\Provider;

use Klevu\FrontendMetadataApi\Service\Provider\Catalog\Product\IdProviderInterface;
use Klevu\FrontendMetadataApi\Service\Provider\Catalog\Product\PriceProviderInterface;
use Klevu\FrontendMetadataApi\Service\Provider\PageMetaProviderInterface;
use Klevu\Registry\Api\ProductRegistryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;

class ProductMetaProvider implements PageMetaProviderInterface
{
    /**
     * @var ProductRegistryInterface
     */
    private readonly ProductRegistryInterface $productRegistry;
    /**
     * @var PriceCurrencyInterface
     */
    private readonly PriceCurrencyInterface $priceCurrency;
    /**
     * @var PriceProviderInterface
     */
    private readonly PriceProviderInterface $priceProvider;
    /**
     * @var IdProviderInterface
     */
    private readonly IdProviderInterface $idProvider;

    /**
     * @param ProductRegistryInterface $productRegistry
     * @param PriceCurrencyInterface $priceCurrency
     * @param PriceProviderInterface $priceProvider
     * @param IdProviderInterface $idProvider
     */
    public function __construct(
        ProductRegistryInterface $productRegistry,
        PriceCurrencyInterface $priceCurrency,
        PriceProviderInterface $priceProvider,
        IdProviderInterface $idProvider,
    ) {
        $this->productRegistry = $productRegistry;
        $this->priceCurrency = $priceCurrency;
        $this->priceProvider = $priceProvider;
        $this->idProvider = $idProvider;
    }

    /**
     * @return array<string, array<int, array<string, string|null>>>
     */
    public function get(): array
    {
        $product = $this->productRegistry->getCurrentProduct();
        if (!$product instanceof ProductInterface) {
            return [];
        }

        return [
            'products' => [
                [
                    'itemId' => $this->idProvider->getItemId($product),
                    'itemGroupId' => $this->idProvider->getItemGroupId($product),
                    'itemName' => $product->getName(),
                    'itemUrl' => $this->getItemUrl($product),
                    'itemSalePrice' => $this->getItemSalePrice($product),
                    'itemCurrency' => $this->getItemCurrency(),
                ],
            ],
        ];
    }

    /**
     * @param ProductInterface $product
     *
     * @return string
     */
    private function getItemUrl(ProductInterface $product): string
    {
        return method_exists($product, 'getProductUrl')
            ? $product->getProductUrl()
            : '';
    }

    /**
     * @param ProductInterface $product
     *
     * @return string
     */
    private function getItemSalePrice(ProductInterface $product): string
    {
        return number_format(
            num: $this->priceProvider->get($product),
            decimals: 2,
        );
    }

    /**
     * @return string
     */
    private function getItemCurrency(): string
    {
        $currency = $this->priceCurrency->getCurrency();

        return $currency->getDataUsingMethod('currency_code');
    }
}
