<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\FrontendMetadata\Service\Provider;

use Klevu\FrontendMetadataApi\Service\Provider\Checkout\Cart\ItemIdProviderInterface;
use Klevu\FrontendMetadataApi\Service\Provider\PageMetaProviderInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Psr\Log\LoggerInterface;

class CartMetaProvider implements PageMetaProviderInterface
{
    /**
     * @var CheckoutSession
     */
    private readonly CheckoutSession $checkoutSession;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var ItemIdProviderInterface
     */
    private readonly ItemIdProviderInterface $itemIdProvider;

    /**
     * @param CheckoutSession $checkoutSession
     * @param LoggerInterface $logger
     * @param ItemIdProviderInterface $itemIdProvider
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        LoggerInterface $logger,
        ItemIdProviderInterface $itemIdProvider,
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        $this->itemIdProvider = $itemIdProvider;
    }

    /**
     * @return mixed[]
     */
    public function get(): array
    {
        $return = [];
        try {
            $quote = $this->getQuote();
            if (null !== $quote) {
                $return['products'] = $this->cartItems($quote);
            }
        } catch (\Exception $exception) {
            $this->logger->error(
                message: 'Method: {method} - Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                    'exception' => $exception,
                ],
            );
        }

        return $return;
    }

    /**
     * @return CartInterface|null
     */
    private function getQuote(): ?CartInterface
    {
        $return = null;
        try {
            $return = $this->checkoutSession->getQuote();
        } catch (LocalizedException $exception) {
            $this->logger->error(
                message: 'Method: {method} - Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                    'exception' => $exception,
                ],
            );
        }

        return $return;
    }

    /**
     * @param CartInterface $cart
     *
     * @return mixed[]
     */
    private function cartItems(CartInterface $cart): array
    {
        $cartItems = method_exists($cart, 'getAllVisibleItems')
            ? $cart->getAllVisibleItems()
            : [];

        return array_filter(array_map([$this, 'getMetaForCartItem'], $cartItems));
    }

    /**
     * @param DataObject&CartItemInterface $cartItem
     *
     * @return string[]
     */
    private function getMetaForCartItem(CartItemInterface $cartItem): array
    {
        if (!method_exists($cartItem, 'getProduct')) {
            return [];
        }
        $product = $cartItem->getProduct();

        return [
            'itemId' => $this->itemIdProvider->getItemId($cartItem),
            'itemGroupId' => $this->itemIdProvider->getItemGroupId($cartItem) ?? '',
            'itemName' => $cartItem->getDataUsingMethod('name'),
            'itemSalesPrice' => $this->getSalePrice($cartItem),
            'itemUrl' => $this->getItemUrl($product),
            'itemQty' => $cartItem->getDataUsingMethod('qty'),
        ];
    }

    /**
     * @param CartItemInterface $cartItem
     *
     * @return string
     */
    private function getSalePrice(CartItemInterface $cartItem): string
    {
        if (!method_exists($cartItem, 'getDataUsingMethod')) {
            return '';
        }
        $itemSalePrice = (float)$cartItem->getDataUsingMethod('price');

        return $itemSalePrice
            ? number_format($itemSalePrice, 2)
            : '';
    }

    /**
     * @param ProductInterface $product
     *
     * @return string
     */
    private function getItemUrl(ProductInterface $product): string
    {
        $itemUrl = '';
        if (method_exists($product, 'getUrlModel')) {
            $productUrlModel = $product->getUrlModel();
            // phpcs:ignore SlevomatCodingStandard.Commenting.InlineDocCommentDeclaration.MissingVariable
            /** @var Product $product $itemUrl */
            $itemUrl = method_exists($productUrlModel, 'getUrl')
                ? $productUrlModel->getUrl($product)
                : '';
        }

        return $itemUrl;
    }
}
