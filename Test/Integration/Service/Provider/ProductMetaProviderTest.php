<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\FrontendMetadata\Test\Integration\Service\Provider;

use Klevu\Configuration\Service\Provider\StoreScopeProvider;
use Klevu\FrontendMetadata\Service\Provider\ProductMetaProvider;
use Klevu\FrontendMetadataApi\Service\Provider\PageMetaProviderInterface;
use Klevu\Registry\Api\ProductRegistryInterface;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Model\Product\Type;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Directory\Model\Currency;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Eav\Model\Entity\Attribute\AttributeInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers \Klevu\FrontendMetadata\Service\Provider\ProductMetaProvider
 * @method PageMetaProviderInterface instantiateTestObject(?array $arguments = null)
 * @method PageMetaProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 * @magentoAppArea frontend
 */
class ProductMetaProviderTest extends TestCase
{
    use AttributeTrait;
    use ObjectInstantiationTrait;
    use ProductTrait;
    use SetAuthKeysTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;

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

        $this->implementationFqcn = ProductMetaProvider::class; // @phpstan-ignore-line
        $this->interfaceFqcn = PageMetaProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->attributeFixturePool = $this->objectManager->get(AttributeFixturePool::class);
        $this->productFixturePool = $this->objectManager->get(ProductFixturePool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->productFixturePool->rollback();
        $this->attributeFixturePool->rollback();
        $this->storeFixturesPool->rollback();
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoConfigFixture default/currency/options/base USD
     * @magentoConfigFixture default/currency/options/default USD
     * @magentoConfigFixture klevu_test_store_1_store currency/options/default GBP
     * @magentoConfigFixture klevu_test_store_1_store currency/options/allow GBP,USD
     */
    public function testGet_ReturnsProductData_WhenEnabled_ForSimpleProduct(): void
    {
        $currencyFactory = $this->objectManager->get(CurrencyFactory::class);
        /** @var Currency $currency */
        $currency = $currencyFactory->create();
        $currency->load('GBP'); // @phpstan-ignore-line There is no repository for currency

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        /** @var Store&StoreInterface $store */
        $store = $storeFixture->get();
        $store->setCurrentCurrency($currency);
        $storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $storeManager->setCurrentStore($store);

        $url = 'klevu-test-product-' . random_int(1, 999999999);
        $this->createProduct([
            'name' => 'Klevu Product Name',
            'price' => 99.99,
            'custom_attributes' => [
                'url_key' => $url,
            ],
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $productRegistry = $this->objectManager->get(ProductRegistryInterface::class);
        $productRegistry->setCurrentProduct($productFixture->getProduct());

        $provider = $this->instantiateTestObject();
        $result = $provider->get();

        $this->assertArrayHasKey(key: 'products', array: $result);
        $actualResult = $result['products'];

        $expectedArrayKeys = [
            'itemId',
            'itemName',
            'itemUrl',
            'itemGroupId',
            'itemSalePrice',
            'itemCurrency',
        ];
        foreach ($expectedArrayKeys as $expectedArrayKey) {
            $this->assertArrayHasKey($expectedArrayKey, $actualResult);
        }
        $this->assertCount(expectedCount: 6, haystack: $actualResult);
        $this->assertSame(expected: $productFixture->getId(), actual: (int)$actualResult['itemId'], message: 'itemId');
        $this->assertSame(
            expected: 'Klevu Product Name',
            actual: $actualResult['itemName'],
            message: 'itemName',
        );
        $this->assertStringContainsString(
            needle: '/' . $url . '.html',
            haystack: $actualResult['itemUrl'],
            message: 'itemUrl',
        );
        $this->assertSame(expected: '', actual: $actualResult['itemGroupId'], message: 'itemGroupId');
        $this->assertSame(expected: '99.99', actual: $actualResult['itemSalePrice'], message: 'itemSalePrice');
        $this->assertSame(expected: 'GBP', actual: $actualResult['itemCurrency']);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture default/klevu_frontend/metadata/enabled 1
     * @magentoConfigFixture default_store klevu_frontend/metadata/enabled 0
     */
    public function testGet_ReturnsMeta_WhenEnabled_ForGroupedProductWithNoChildren(): void
    {
        $key = 'test_gp_with_no_child_1';
        $url = 'klevu-test-product-' . random_int(1, 999999999);
        $this->createProduct([
            'key' => $key,
            'type_id' => Grouped::TYPE_CODE,
            'name' => 'Klevu Grouped Product',
            'price' => 999.99,
            'custom_attributes' => [
                'url_key' => $url,
            ],
        ]);
        $productFixture = $this->productFixturePool->get($key);

        $productRegistry = $this->objectManager->get(ProductRegistryInterface::class);
        $productRegistry->setCurrentProduct($productFixture->getProduct());

        $provider = $this->instantiateTestObject();
        $result = $provider->get();

        $this->assertArrayHasKey(key: 'products', array: $result);
        $actualResult = $result['products'];

        $expectedArrayKeys = [
            'itemId',
            'itemName',
            'itemUrl',
            'itemGroupId',
            'itemSalePrice',
            'itemCurrency',
        ];
        foreach ($expectedArrayKeys as $expectedArrayKey) {
            $this->assertArrayHasKey($expectedArrayKey, $actualResult);
        }
        $this->assertCount(expectedCount: 6, haystack: $actualResult);
        $this->assertSame(expected: $productFixture->getId(), actual: (int)$actualResult['itemId'], message: 'itemId');
        $this->assertSame(
            expected: 'Klevu Grouped Product',
            actual: $actualResult['itemName'],
        );
        $this->assertStringContainsString(
            needle: '/' . $url . '.html',
            haystack: $actualResult['itemUrl'],
            message: 'itemUrl',
        );
        $this->assertSame('', $actualResult['itemGroupId']);
        $this->assertSame('0.00', $actualResult['itemSalePrice']);
        $this->assertSame(expected: 'USD', actual: $actualResult['itemCurrency']);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture default/klevu_frontend/metadata/enabled 1
     * @magentoConfigFixture default_store klevu_frontend/metadata/enabled 0
     */
    public function testGet_ReturnsMeta_WhenEnabled_ForGroupedProductWithTwoChildren(): void
    {
        $key = 'test_gp_with_child_1';
        $url = 'klevu-test-product-' . random_int(1, 999999999);
        $this->createProduct([
            'key' => $key . '_child_1',
            'name' => 'Klevu Simple Product Child 1',
            'price' => 99.99,
        ]);
        $productFixture1 = $this->productFixturePool->get($key . '_child_1');

        $this->createProduct([
            'key' => $key . '_child_2',
            'name' => 'Klevu Simple Product Child 2',
            'price' => 9.99,
            'type_id' => Type::TYPE_SIMPLE,
        ]);
        $productFixture2 = $this->productFixturePool->get($key . '_child_2');

        $this->createProduct([
            'key' => $key,
            'type_id' => Grouped::TYPE_CODE,
            'name' => 'Klevu Grouped Product With Children',
            'price' => 999.99,
            'linked_products' => [
                $productFixture1,
                $productFixture2,
            ],
            'custom_attributes' => [
                'url_key' => $url,
            ],
        ]);
        $productFixture = $this->productFixturePool->get($key);

        $productRegistry = $this->objectManager->get(ProductRegistryInterface::class);
        $productRegistry->setCurrentProduct($productFixture->getProduct());

        $provider = $this->instantiateTestObject();
        $result = $provider->get();

        $this->assertArrayHasKey(key: 'products', array: $result);
        $actualResult = $result['products'];

        $expectedArrayKeys = [
            'itemId',
            'itemName',
            'itemUrl',
            'itemGroupId',
            'itemSalePrice',
            'itemCurrency',
        ];
        foreach ($expectedArrayKeys as $expectedArrayKey) {
            $this->assertArrayHasKey($expectedArrayKey, $actualResult);
        }
        $this->assertCount(expectedCount: 6, haystack: $actualResult);
        $this->assertSame(expected: $productFixture->getId(), actual: (int)$actualResult['itemId'], message: 'itemId');
        $this->assertSame(
            expected: 'Klevu Grouped Product With Children',
            actual: $actualResult['itemName'],
            message: 'itemName',
        );
        $this->assertStringContainsString(
            needle: '/' . $url . '.html',
            haystack: $actualResult['itemUrl'],
            message: 'itemUrl',
        );
        $this->assertSame(expected: '', actual: $actualResult['itemGroupId'], message: 'itemGroupId');
        $this->assertSame(expected: '9.99', actual: $actualResult['itemSalePrice'], message: 'itemSalePrice');
        $this->assertSame(expected: 'USD', actual: $actualResult['itemCurrency'], message: 'itemCurrency');
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture default/klevu_frontend/metadata/enabled 1
     * @magentoConfigFixture default_store klevu_frontend/metadata/enabled 0
     */
    public function testGet_ReturnsMeta_WhenEnabled_ForConfigProductWithChildren(): void
    {
        $productKey = 'test_cp_with_child_1';
        $this->createAttribute(['attribute_type' => 'configurable']);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        /** @var AbstractAttribute&AttributeInterface $attribute */
        $attribute = $attributeFixture->getAttribute();
        $attributeSource = $attribute->getSource();

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $storeScopeProvider = $this->objectManager->create(StoreScopeProvider::class);
        $storeScopeProvider->setCurrentStore($storeFixture->get());

        $this->createProduct([
            'key' => $productKey . '_simple',
            'price' => 123.45,
            'qty' => 100,
            'data' => [
                $attributeFixture->getAttributeCode() => $attributeSource->getOptionId('Option 1'),
            ],
        ]);
        $productFixtureSimple = $this->productFixturePool->get($productKey . '_simple');

        $url = 'klevu-test-product-' . random_int(1, 999999999);
        $this->createProduct([
            'key' => $productKey,
            'type_id' => Configurable::TYPE_CODE,
            'name' => 'Klevu Configurable Product With Children',
            'price' => 100.00,
            'configurable_attributes' => [$attributeFixture->getAttribute()],
            'variants' => [
                $productFixtureSimple->getProduct(),
            ],
            'custom_attributes' => [
                'url_key' => $url,
            ],
        ]);
        $productFixture = $this->productFixturePool->get($productKey);

        $productRegistry = $this->objectManager->get(ProductRegistryInterface::class);
        $productRegistry->setCurrentProduct($productFixture->getProduct());

        $provider = $this->instantiateTestObject();
        $result = $provider->get();

        $this->assertArrayHasKey(key: 'products', array: $result);
        $actualResult = $result['products'];

        $expectedArrayKeys = [
            'itemId',
            'itemName',
            'itemUrl',
            'itemGroupId',
            'itemSalePrice',
            'itemCurrency',
        ];
        foreach ($expectedArrayKeys as $expectedArrayKey) {
            $this->assertArrayHasKey($expectedArrayKey, $actualResult);
        }
        $this->assertCount(expectedCount: 6, haystack: $actualResult);
        $klevuItemId = $productFixture->getId() . '-' . $productFixtureSimple->getId();
        $this->assertSame(expected: $klevuItemId, actual: $actualResult['itemId'], message: 'itemId');
        $this->assertSame(
            expected: 'Klevu Configurable Product With Children',
            actual: $actualResult['itemName'],
            message: 'itemName',
        );
        $this->assertStringContainsString(
            needle: '/' . $url . '.html',
            haystack: $actualResult['itemUrl'],
            message: 'itemUrl',
        );
        $this->assertSame(
            expected: $productFixture->getId(),
            actual: (int)$actualResult['itemGroupId'],
            message: 'itemGroupId',
        );
        $this->assertSame(expected: '123.45', actual: $actualResult['itemSalePrice'], message: 'itemSalePrice');
        $this->assertSame(expected: 'USD', actual: $actualResult['itemCurrency'], message: 'itemCurrency');
    }
}
