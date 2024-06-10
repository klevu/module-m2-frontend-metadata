<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\FrontendMetadata\Test\Integration\Service\Provider\Checkout\Cart;

use Klevu\FrontendMetadata\Service\Provider\Checkout\Cart\ItemIdProvider;
use Klevu\FrontendMetadataApi\Service\Provider\Checkout\Cart\ItemIdProviderInterface;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Checkout\CartFixturePool;
use Klevu\TestFixtures\Checkout\CartTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Bundle\Model\Product\Type as BundleType;
use Magento\Catalog\Model\Product\Type;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Downloadable\Model\Product\Type as DownloadableType;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Eav\Model\Entity\Attribute\AttributeInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers ItemIdProvider
 * @method ItemIdProviderInterface instantiateTestObject(?array $arguments = null)
 * @method ItemIdProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 * @magentoAppArea frontend
 */
class ItemIdProviderTest extends TestCase
{
    use AttributeTrait;
    use CartTrait;
    use ObjectInstantiationTrait;
    use ProductTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;
    use TestInterfacePreferenceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = ItemIdProvider::class;
        $this->interfaceFqcn = ItemIdProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->attributeFixturePool = $this->objectManager->get(AttributeFixturePool::class);
        $this->productFixturePool = $this->objectManager->get(ProductFixturePool::class);
        $this->cartFixturePool = $this->objectManager->get(CartFixturePool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->cartFixturePool->rollback();
        $this->productFixturePool->rollback();
        $this->attributeFixturePool->rollback();
        $this->storeFixturesPool->rollback();
    }

    public function testGetItemId_ForSimpleProduct(): void
    {
        $this->createProduct();
        $productFixture1 = $this->productFixturePool->get('test_product');

        $this->createCart([
            'products' => [
                'simple' => [
                    $productFixture1->getSku() => 1,
                ],
            ],
        ]);
        $cartFixture = $this->cartFixturePool->get('test_cart');
        $cart = $cartFixture->getCart();

        $quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $quote = $quoteRepository->get($cart->getId());

        $this->assertSame(expected: 1, actual: (int)$quote->getItemsCount());
        $items = $quote->getItems();
        $this->assertCount(expectedCount: 1, haystack: $items);
        $item = array_shift($items);

        $provider = $this->instantiateTestObject();
        $result = $provider->getItemId($item);

        $this->assertSame(expected: (string)$productFixture1->getId(), actual: $result);
    }

    public function testGetItemId_ForVirtualProduct(): void
    {
        $this->createProduct([
            'type_id' => Type::TYPE_VIRTUAL,
        ]);
        $productFixture1 = $this->productFixturePool->get('test_product');

        $this->createCart([
            'products' => [
                'simple' => [
                    $productFixture1->getSku() => 1,
                ],
            ],
        ]);
        $cartFixture = $this->cartFixturePool->get('test_cart');
        $cart = $cartFixture->getCart();

        $quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $quote = $quoteRepository->get($cart->getId());

        $this->assertSame(expected: 1, actual: (int)$quote->getItemsCount());
        $items = $quote->getItems();
        $this->assertCount(expectedCount: 1, haystack: $items);
        $item = array_shift($items);

        $provider = $this->instantiateTestObject();
        $result = $provider->getItemId($item);

        $this->assertSame(expected: (string)$productFixture1->getId(), actual: $result);
    }

    public function testGetItemId_ForDownloadableProduct(): void
    {
        $this->createProduct([
            'type_id' => DownloadableType::TYPE_DOWNLOADABLE,
        ]);
        $productFixture1 = $this->productFixturePool->get('test_product');

        $this->createCart([
            'products' => [
                'simple' => [
                    $productFixture1->getSku() => 1,
                ],
            ],
        ]);
        $cartFixture = $this->cartFixturePool->get('test_cart');
        $cart = $cartFixture->getCart();

        $quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $quote = $quoteRepository->get($cart->getId());

        $this->assertSame(expected: 1, actual: (int)$quote->getItemsCount());
        $items = $quote->getItems();
        $this->assertCount(expectedCount: 1, haystack: $items);
        $item = array_shift($items);

        $provider = $this->instantiateTestObject();
        $result = $provider->getItemId($item);

        $this->assertSame(expected: (string)$productFixture1->getId(), actual: $result);
    }

    public function testGetItemId_ForBundleProduct(): void
    {
        $this->createProduct([
            'type_id' => BundleType::TYPE_CODE,
        ]);
        $productFixture1 = $this->productFixturePool->get('test_product');

        // @TODO update to create bundle product
        $this->createCart([
            'products' => [
                'simple' => [
                    $productFixture1->getSku() => 1,
                ],
            ],
        ]);
        $cartFixture = $this->cartFixturePool->get('test_cart');
        $cart = $cartFixture->getCart();

        $quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $quote = $quoteRepository->get($cart->getId());

        $this->assertSame(expected: 1, actual: (int)$quote->getItemsCount());
        $items = $quote->getItems();
        $this->assertCount(expectedCount: 1, haystack: $items);
        $item = array_shift($items);

        $provider = $this->instantiateTestObject();
        $result = $provider->getItemId($item);

        $this->assertSame(expected: (string)$productFixture1->getId(), actual: $result);
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testGetItemId_ForConfigurableProduct(): void
    {
        $this->createAttribute(['attribute_type' => 'configurable']);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        /** @var AbstractAttribute&AttributeInterface $attribute */
        $attribute = $attributeFixture->getAttribute();
        $attributeSource = $attribute->getSource();

        $this->createProduct([
            'key' => 'product_simple_1',
            'name' => 'Klevu Simple Product Name 1',
            'sku' => 'product_simple_sku_1',
            'price' => 123.45,
            'data' => [
                $attributeFixture->getAttributeCode() => $attributeSource->getOptionId('Option 1'),
            ],
        ]);
        $simpleProductFixture1 = $this->productFixturePool->get('product_simple_1');
        $this->createProduct([
            'key' => 'product_simple_2',
            'name' => 'Klevu Simple Product Name 2',
            'sku' => 'product_simple_sku_2',
            'price' => 456.78,
            'data' => [
                $attributeFixture->getAttributeCode() => $attributeSource->getOptionId('Option 2'),
            ],
        ]);
        $simpleProductFixture2 = $this->productFixturePool->get('product_simple_2');

        $this->createProduct([
            'key' => 'product_configurable',
            'name' => 'Klevu Configurable Product Name',
            'sku' => 'product_configurable_sku',
            'price' => 19.99,
            'type_id' => Configurable::TYPE_CODE,
            'configurable_attributes' => [$attributeFixture->getAttribute()],
            'variants' => [
                $simpleProductFixture1->getProduct(),
                $simpleProductFixture2->getProduct(),
            ],
        ]);
        $configurableProductFixture = $this->productFixturePool->get('product_configurable');

        $this->createCart([
            'products' => [
                'configurable' => [
                    $configurableProductFixture->getSku() => [
                        'qty' => 1,
                        'options' => [
                            $attributeFixture->getAttributeCode() => $attributeSource->getOptionId('Option 1'),
                        ],
                    ],
                ],
            ],
        ]);
        $cartFixture = $this->cartFixturePool->get('test_cart');
        $cart = $cartFixture->getCart();

        $quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $quote = $quoteRepository->get($cart->getId());

        $this->assertSame(expected: 1, actual: (int)$quote->getItemsCount());
        $items = $quote->getItems();
        $this->assertCount(expectedCount: 1, haystack: $items);
        $item = array_shift($items);

        $provider = $this->instantiateTestObject();
        $result = $provider->getItemId($item);

        $this->assertSame(
            expected: (string)$configurableProductFixture->getId() . '-' . $simpleProductFixture1->getId(),
            actual: $result,
        );
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testGetItemId_ForGroupedProduct(): void
    {
        $this->createProduct([
            'key' => 'test_product_simple_1',
            'name' => 'Klevu Simple Product Test',
            'sku' => 'KLEVU-SIMPLE-SKU-001',
            'price' => 69.99,
            'data' => [
                'special_price' => 44.99,
                'special_price_from' => '1970-01-01',
                'special_price_to' => '2099-12-31',
            ],
        ]);
        $simpleProductFixture1 = $this->productFixturePool->get('test_product_simple_1');
        $this->createProduct([
            'key' => 'test_product_simple_2',
            'name' => 'Klevu Simple Product Test',
            'sku' => 'KLEVU-SIMPLE-SKU-002',
            'price' => 79.99,
            'data' => [
                'special_price' => 49.99,
                'special_price_from' => '1970-01-01',
                'special_price_to' => '2099-12-31',
            ],
        ]);
        $simpleProductFixture2 = $this->productFixturePool->get('test_product_simple_2');

        $this->createProduct([
            'type_id' => Grouped::TYPE_CODE,
            'name' => 'Klevu Grouped Product Test',
            'sku' => 'KLEVU-GROUPED-SKU-001',
            'price' => 99.99,
            'linked_products' => [
                $simpleProductFixture1,
                $simpleProductFixture2,
            ],
            'data' => [
                'special_price' => 54.99,
                'special_price_from' => '1970-01-01',
                'special_price_to' => '2099-12-31',
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $this->createCart([
            'products' => [
                'grouped' => [
                    $productFixture->getSku() => [
                        'qty' => 1,
                        'options' => [
                            $simpleProductFixture1->getSku() => 1,
                            $simpleProductFixture2->getSku() => 2,
                        ],
                    ],
                ],
            ],
        ]);

        $cartFixture = $this->cartFixturePool->get('test_cart');
        $cart = $cartFixture->getCart();

        $quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $quote = $quoteRepository->get($cart->getId());

        $this->assertSame(expected: 2, actual: (int)$quote->getItemsCount());
        $items = $quote->getItems();
        $this->assertCount(expectedCount: 2, haystack: $items);
        $item = array_shift($items);

        $provider = $this->instantiateTestObject();
        $result = $provider->getItemId($item);

        $this->assertSame(expected: (string)$productFixture->getId(), actual: $result);
    }

    public function testGetItemGroupId_ReturnsNull_ForSimpleProduct(): void
    {
        $this->createProduct();
        $productFixture1 = $this->productFixturePool->get('test_product');

        $this->createCart([
            'products' => [
                'simple' => [
                    $productFixture1->getSku() => 1,
                ],
            ],
        ]);
        $cartFixture = $this->cartFixturePool->get('test_cart');
        $cart = $cartFixture->getCart();

        $quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $quote = $quoteRepository->get($cart->getId());

        $this->assertSame(expected: 1, actual: (int)$quote->getItemsCount());
        $items = $quote->getItems();
        $this->assertCount(expectedCount: 1, haystack: $items);
        $item = array_shift($items);

        $provider = $this->instantiateTestObject();
        $result = $provider->getItemGroupId($item);

        $this->assertNull(actual: $result);
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testGetItemGroupId_ReturnsParentID_ForConfigurableProduct(): void
    {
        $this->createAttribute(['attribute_type' => 'configurable']);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        /** @var AbstractAttribute&AttributeInterface $attribute */
        $attribute = $attributeFixture->getAttribute();
        $attributeSource = $attribute->getSource();

        $this->createProduct([
            'key' => 'product_simple_1',
            'name' => 'Klevu Simple Product Name 1',
            'sku' => 'product_simple_sku_1',
            'price' => 123.45,
            'data' => [
                $attributeFixture->getAttributeCode() => $attributeSource->getOptionId('Option 1'),
            ],
        ]);
        $simpleProductFixture1 = $this->productFixturePool->get('product_simple_1');
        $this->createProduct([
            'key' => 'product_simple_2',
            'name' => 'Klevu Simple Product Name 2',
            'sku' => 'product_simple_sku_2',
            'price' => 456.78,
            'data' => [
                $attributeFixture->getAttributeCode() => $attributeSource->getOptionId('Option 2'),
            ],
        ]);
        $simpleProductFixture2 = $this->productFixturePool->get('product_simple_2');

        $this->createProduct([
            'key' => 'product_configurable',
            'name' => 'Klevu Configurable Product Name',
            'sku' => 'product_configurable_sku',
            'price' => 19.99,
            'type_id' => Configurable::TYPE_CODE,
            'configurable_attributes' => [$attributeFixture->getAttribute()],
            'variants' => [
                $simpleProductFixture1->getProduct(),
                $simpleProductFixture2->getProduct(),
            ],
        ]);
        $configurableProductFixture = $this->productFixturePool->get('product_configurable');

        $this->createCart([
            'products' => [
                'configurable' => [
                    $configurableProductFixture->getSku() => [
                        'qty' => 1,
                        'options' => [
                            $attributeFixture->getAttributeCode() => $attributeSource->getOptionId('Option 1'),
                        ],
                    ],
                ],
            ],
        ]);
        $cartFixture = $this->cartFixturePool->get('test_cart');
        $cart = $cartFixture->getCart();

        $quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $quote = $quoteRepository->get($cart->getId());

        $this->assertSame(expected: 1, actual: (int)$quote->getItemsCount());
        $items = $quote->getItems();
        $this->assertCount(expectedCount: 1, haystack: $items);
        $item = array_shift($items);

        $provider = $this->instantiateTestObject();
        $result = $provider->getItemGroupId($item);

        $this->assertSame(
            expected: (string)$configurableProductFixture->getId(),
            actual: $result,
        );
    }
}
