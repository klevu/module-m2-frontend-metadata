<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\FrontendMetadata\Test\Integration\Service\Provider;

use Klevu\FrontendMetadata\Service\Provider\CartMetaProvider;
use Klevu\FrontendMetadataApi\Service\Provider\PageMetaProviderInterface;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Checkout\CartFixturePool;
use Klevu\TestFixtures\Checkout\CartTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Model\Product\Type;
use Magento\Checkout\Model\Session;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Downloadable\Model\Product\Type as DownloadableType;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Eav\Model\Entity\Attribute\AttributeInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers \Klevu\FrontendMetadata\Service\Provider\CartMetaProvider
 * @method PageMetaProviderInterface instantiateTestObject(?array $arguments = null)
 * @method PageMetaProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 * @magentoAppArea frontend
 */
class CartMetaProviderTest extends TestCase
{
    use AttributeTrait;
    use CartTrait;
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
     * @var Session|null
     */
    private ?Session $checkoutSession = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = CartMetaProvider::class;
        $this->interfaceFqcn = PageMetaProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->attributeFixturePool = $this->objectManager->get(AttributeFixturePool::class);
        $this->productFixturePool = $this->objectManager->get(ProductFixturePool::class);
        $this->cartFixturePool = $this->objectManager->get(CartFixturePool::class);
        $this->checkoutSession = $this->objectManager->get(Session::class);
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
        $this->checkoutSession->clearQuote();
    }

    /**
     * @magentoConfigFixture default/klevu_frontend/metadata/enabled 1
     * @magentoConfigFixture default_store klevu_frontend/metadata/enabled 1
     */
    public function testGet_ReturnsEmpty_WhenExceptionThrown(): void
    {
        $exceptionMessage = 'Something went wrong';
        $exception = new LocalizedException(__($exceptionMessage));

        $mockSession = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockSession->expects($this->once())
            ->method('getQuote')
            ->willThrowException($exception);

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $mockLogger->method('error')
            ->with(
                'Method: {method} - Error: {message}',
                [
                    'method' => 'Klevu\FrontendMetadata\Service\Provider\CartMetaProvider::getQuote',
                    'message' => $exceptionMessage,
                    'exception' => $exception,
                ],
            );

        $provider = $this->instantiateTestObject([
            'checkoutSession' => $mockSession,
            'logger' => $mockLogger,
        ]);
        $actualResult = $provider->get();

        $this->assertEmpty($actualResult);
    }

    /**
     * @magentoConfigFixture default/klevu_frontend/metadata/enabled 1
     * @magentoConfigFixture default_store klevu_frontend/metadata/enabled 1
     */
    public function testGet_ReturnsEmptyArray_ForEmptyCart(): void
    {
        $provider = $this->instantiateTestObject();
        $result = $provider->get();

        $this->assertIsArray($result);
        $this->assertArrayHasKey(key: 'products', array: $result);
        $this->assertCount(expectedCount: 0, haystack: $result['products']);
    }

    /**
     * @magentoConfigFixture default/klevu_frontend/metadata/enabled 1
     * @magentoConfigFixture default_store klevu_frontend/metadata/enabled 1
     */
    public function testGet_ReturnsSimpleProductTypesInCart_WhenEnabled(): void
    {
        $this->createProduct([
            'name' => 'Klevu Product Name 1',
            'sku' => 'product_simple_sku_1',
            'price' => 199.99,
            'key' => 'product_simple_cart_key_1',
        ]);
        $productFixture1 = $this->productFixturePool->get('product_simple_cart_key_1');

        $this->createProduct([
            'name' => 'Klevu Product Name 2',
            'sku' => 'product_simple_sku_2',
            'type_id' => Type::TYPE_VIRTUAL,
            'price' => 19.99,
            'key' => 'product_simple_cart_key_2',
        ]);
        $productFixture2 = $this->productFixturePool->get('product_simple_cart_key_2');

        $this->createProduct([
            'name' => 'Klevu Product Name 3',
            'sku' => 'product_simple_sku_3',
            'type_id' => DownloadableType::TYPE_DOWNLOADABLE,
            'price' => 19.99,
            'key' => 'product_simple_cart_key_3',
        ]);
        $productFixture3 = $this->productFixturePool->get('product_simple_cart_key_3');

        $this->createCart([
            'products' => [
                'simple' => [
                    $productFixture1->getSku() => 1,
                ],
                'virtual' => [
                    $productFixture2->getSku() => 3,
                ],
                'downloadable' => [
                    $productFixture3->getSku() => 2,
                ],
            ],
        ]);

        $provider = $this->instantiateTestObject();
        $result = $provider->get();

        $this->assertIsArray($result);
        $this->assertArrayHasKey(key: 'products', array: $result);
        $this->assertCount(expectedCount: 3, haystack: $result['products']);

        $result1Array = array_filter(
            array: $result['products'],
            callback: static fn (array $data): bool => (
                ($data['itemId'] ?? null) === (string)$productFixture1->getId()
            ),
        );
        $result1 = array_shift($result1Array);

        $this->assertArrayHasKey(key: 'itemGroupId', array: $result1);
        $this->assertSame(expected: '', actual: $result1['itemGroupId'], message: 'itemGroupId');

        $this->assertArrayHasKey(key: 'itemName', array: $result1);
        $this->assertSame(expected: 'Klevu Product Name 1', actual: $result1['itemName'], message: 'itemName');

        $this->assertArrayHasKey(key: 'itemSalesPrice', array: $result1);
        $this->assertSame(expected: '199.99', actual: $result1['itemSalesPrice'], message: 'itemSalesPrice');

        $this->assertArrayHasKey(key: 'itemUrl', array: $result1);
        $this->assertStringContainsString(
            needle: str_replace('_', '-', $productFixture1->getSku()) . '.html',
            haystack: $result1['itemUrl'],
            message: 'itemUrl',
        );
        $this->assertSame(expected: 1.0, actual: $result1['itemQty']);

        $result2Array = array_filter(
            array: $result['products'],
            callback: static fn (array $data): bool => (
                ($data['itemId'] ?? null) === (string)$productFixture2->getId()
            ),
        );
        $result2 = array_shift($result2Array);

        $this->assertArrayHasKey(key: 'itemGroupId', array: $result2);
        $this->assertSame(expected: '', actual: $result2['itemGroupId'], message: 'itemGroupId');

        $this->assertArrayHasKey(key: 'itemName', array: $result2);
        $this->assertSame(expected: 'Klevu Product Name 2', actual: $result2['itemName'], message: 'itemName');

        $this->assertArrayHasKey(key: 'itemSalesPrice', array: $result2);
        $this->assertSame(expected: '19.99', actual: $result2['itemSalesPrice'], message: 'itemSalesPrice');

        $this->assertArrayHasKey(key: 'itemUrl', array: $result2);
        $this->assertStringContainsString(
            needle: str_replace('_', '-', $productFixture2->getSku()) . '.html',
            haystack: $result2['itemUrl'],
            message: 'itemUrl',
        );
        $this->assertSame(expected: 3.0, actual: $result2['itemQty']);

        $result3Array = array_filter(
            array: $result['products'],
            callback: static fn (array $data): bool => (
                ($data['itemId'] ?? null) === (string)$productFixture3->getId()
            ),
        );
        $result3 = array_shift($result3Array);

        $this->assertArrayHasKey(key: 'itemGroupId', array: $result3);
        $this->assertSame(expected: '', actual: $result3['itemGroupId'], message: 'itemGroupId');

        $this->assertArrayHasKey(key: 'itemName', array: $result3);
        $this->assertSame(expected: 'Klevu Product Name 3', actual: $result3['itemName'], message: 'itemName');

        $this->assertArrayHasKey(key: 'itemSalesPrice', array: $result3);
        $this->assertSame(expected: '19.99', actual: $result3['itemSalesPrice'], message: 'itemSalesPrice');

        $this->assertArrayHasKey(key: 'itemUrl', array: $result3);
        $this->assertStringContainsString(
            needle: str_replace('_', '-', $productFixture3->getSku()) . '.html',
            haystack: $result3['itemUrl'],
            message: 'itemUrl',
        );
        $this->assertSame(expected: 2.0, actual: $result3['itemQty']);
    }

    /**
     * @magentoConfigFixture default/klevu_frontend/metadata/enabled 1
     * @magentoConfigFixture default_store klevu_frontend/metadata/enabled 1
     */
    public function testGet_ReturnsConfigurableProductsInCart_WhenEnabled(): void
    {
        $this->createAttribute(['attribute_type' => 'configurable']);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        /** @var AbstractAttribute&AttributeInterface $attribute */
        $attribute = $attributeFixture->getAttribute();
        $attributeSource = $attribute->getSource();

        $this->createProduct([
            'key' => 'product_simple',
            'name' => 'Klevu Simple Product Name',
            'sku' => 'product_simple_sku',
            'price' => 123.45,
            'data' => [
                $attributeFixture->getAttributeCode() => $attributeSource->getOptionId('Option 1'),
            ],
        ]);
        $simpleProductFixture = $this->productFixturePool->get('product_simple');

        $this->createProduct([
            'key' => 'product_configurable',
            'name' => 'Klevu Configurable Product Name',
            'sku' => 'product_configurable_sku',
            'price' => 19.99,
            'type_id' => Configurable::TYPE_CODE,
            'configurable_attributes' => [$attributeFixture->getAttribute()],
            'variants' => [
                $simpleProductFixture->getProduct(),
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

        $provider = $this->instantiateTestObject();
        $result = $provider->get();

        $this->assertIsArray($result);
        $this->assertArrayHasKey(key: 'products', array: $result);

        $this->assertCount(expectedCount: 1, haystack: $result['products']);

        $result1 = array_shift($result['products']);

        $this->assertArrayHasKey(key: 'itemId', array: $result1);
        $this->assertSame(
            expected: $configurableProductFixture->getId() . '-' . $simpleProductFixture->getId(),
            actual: $result1['itemId'],
            message: 'itemId',
        );

        $this->assertArrayHasKey(key: 'itemGroupId', array: $result1);
        $this->assertSame(
            expected: (string)$configurableProductFixture->getId(),
            actual: $result1['itemGroupId'],
            message: 'itemGroupId',
        );

        $this->assertArrayHasKey(key: 'itemName', array: $result1);
        $this->assertSame(
            expected: 'Klevu Configurable Product Name',
            actual: $result1['itemName'],
            message: 'itemName',
        );

        $this->assertArrayHasKey(key: 'itemSalesPrice', array: $result1);
        $this->assertSame(expected: '123.45', actual: $result1['itemSalesPrice'], message: 'itemSalesPrice');

        $this->assertArrayHasKey(key: 'itemUrl', array: $result1);
        $this->assertStringContainsString(
            needle: str_replace('_', '-', $configurableProductFixture->getSku()) . '.html',
            haystack: $result1['itemUrl'],
            message: 'itemUrl',
        );
        $this->assertSame(expected: 1.0, actual: $result1['itemQty']);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture default/klevu_frontend/metadata/enabled 1
     * @magentoConfigFixture default_store klevu_frontend/metadata/enabled 1
     */
    public function testGet_ReturnsGroupedProductsInCart_WhenEnabled(): void
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
        $simpleProduct1 = $simpleProductFixture1->getProduct();
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
        $simpleProduct2 = $simpleProductFixture2->getProduct();

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

        $provider = $this->instantiateTestObject();
        $result = $provider->get();

        $this->assertIsArray($result);
        $this->assertArrayHasKey(key: 'products', array: $result);

        $this->assertCount(expectedCount: 2, haystack: $result['products']);

        $result1 = array_shift($result['products']);

        $this->assertArrayHasKey(key: 'itemId', array: $result1);
        $this->assertSame(
            expected: (string)$productFixture->getId(),
            actual: $result1['itemId'],
            message: 'itemId',
        );

        $this->assertArrayHasKey(key: 'itemGroupId', array: $result1);
        $this->assertSame(
            expected: '',
            actual: $result1['itemGroupId'],
            message: 'itemGroupId',
        );

        $this->assertArrayHasKey(key: 'itemName', array: $result1);
        $this->assertSame(
            expected: $simpleProduct1->getName(),
            actual: $result1['itemName'],
            message: 'itemName',
        );

        $this->assertArrayHasKey(key: 'itemSalesPrice', array: $result1);
        $this->assertSame(
            expected: '44.99',
            actual: $result1['itemSalesPrice'],
            message: 'itemSalesPrice',
        );

        $this->assertArrayHasKey(key: 'itemUrl', array: $result1);
        $this->assertStringContainsString(
            needle: str_replace('_', '-', strtolower($simpleProduct1->getSku())) . '.html',
            haystack: $result1['itemUrl'],
            message: 'itemUrl',
        );
        $this->assertSame(expected: 1.0, actual: $result1['itemQty']);

        $result2 = array_shift($result['products']);

        $this->assertArrayHasKey(key: 'itemId', array: $result2);
        $this->assertSame(
            expected: (string)$productFixture->getId(),
            actual: $result2['itemId'],
            message: 'itemId',
        );

        $this->assertArrayHasKey(key: 'itemGroupId', array: $result2);
        $this->assertSame(
            expected: '',
            actual: $result2['itemGroupId'],
            message: 'itemGroupId',
        );

        $this->assertArrayHasKey(key: 'itemName', array: $result2);
        $this->assertSame(
            expected: $simpleProduct2->getName(),
            actual: $result2['itemName'],
            message: 'itemName',
        );

        $this->assertArrayHasKey(key: 'itemSalesPrice', array: $result2);
        $this->assertSame(
            expected: '49.99',
            actual: $result2['itemSalesPrice'],
            message: 'itemSalesPrice',
        );

        $this->assertArrayHasKey(key: 'itemUrl', array: $result2);
        $this->assertStringContainsString(
            needle: str_replace('_', '-', strtolower($simpleProduct2->getSku())) . '.html',
            haystack: $result2['itemUrl'],
            message: 'itemUrl',
        );
        $this->assertSame(expected: 2.0, actual: $result2['itemQty']);
    }

    /**
     * @testWith ["checkout_index_index", ["checkout_cart_index"]]
     *           ["checkout_cart_index", []]
     *           ["checkout_cart_index", ["cms_index_index", "catalog_product_view"]]
     * @magentoDbIsolation disabled
     * @magentoConfigFixture default/klevu_frontend/metadata/enabled 1
     * @magentoConfigFixture default_store klevu_frontend/metadata/enabled 1
     *
     * @param string $route
     * @param string[] $outputOnRoutes
     *
     * @return void
     * @throws LocalizedException
     */
    public function testGet_DoesNotReturn_OnUnmappedRoute(
        string $route,
        array $outputOnRoutes,
    ): void {
        $this->createProduct([
            'name' => 'Klevu Product Name 1',
            'sku' => 'product_simple_sku_1',
            'price' => 199.99,
            'key' => 'product_simple_cart_key_1',
        ]);
        $productFixture1 = $this->productFixturePool->get('product_simple_cart_key_1');

        $this->createProduct([
            'name' => 'Klevu Product Name 2',
            'sku' => 'product_simple_sku_2',
            'type_id' => Type::TYPE_VIRTUAL,
            'price' => 19.99,
            'key' => 'product_simple_cart_key_2',
        ]);
        $productFixture2 = $this->productFixturePool->get('product_simple_cart_key_2');

        $this->createProduct([
            'name' => 'Klevu Product Name 3',
            'sku' => 'product_simple_sku_3',
            'type_id' => DownloadableType::TYPE_DOWNLOADABLE,
            'price' => 19.99,
            'key' => 'product_simple_cart_key_3',
        ]);
        $productFixture3 = $this->productFixturePool->get('product_simple_cart_key_3');

        $this->createCart([
            'products' => [
                'simple' => [
                    $productFixture1->getSku() => 1,
                ],
                'virtual' => [
                    $productFixture2->getSku() => 3,
                ],
                'downloadable' => [
                    $productFixture3->getSku() => 2,
                ],
            ],
        ]);

        $mockRequest = $this->getMockRequest(
            route: $route,
        );
        $provider = $this->instantiateTestObject(
            arguments: [
                'request' => $mockRequest,
                'outputOnRoutes' => $outputOnRoutes,
            ],
        );

        $result = $provider->get();

        $this->assertSame(
            expected: [],
            actual: $result,
        );
    }

    /**
     * @testWith ["checkout_cart_index", ["checkout_cart_index"]]
     *           ["catalog_product_view", ["cms_index_index", "catalog_product_view"]]
     * @magentoDbIsolation disabled
     * @magentoConfigFixture default/klevu_frontend/metadata/enabled 1
     * @magentoConfigFixture default_store klevu_frontend/metadata/enabled 1
     *
     * @param string $route
     * @param string[] $outputOnRoutes
     *
     * @return void
     * @throws LocalizedException
     */
    public function testGet_Returns_OnMappedRoute(
        string $route,
        array $outputOnRoutes,
    ): void {
        $this->createProduct([
            'name' => 'Klevu Product Name 1',
            'sku' => 'product_simple_sku_1',
            'price' => 199.99,
            'key' => 'product_simple_cart_key_1',
        ]);
        $productFixture1 = $this->productFixturePool->get('product_simple_cart_key_1');

        $this->createProduct([
            'name' => 'Klevu Product Name 2',
            'sku' => 'product_simple_sku_2',
            'type_id' => Type::TYPE_VIRTUAL,
            'price' => 19.99,
            'key' => 'product_simple_cart_key_2',
        ]);
        $productFixture2 = $this->productFixturePool->get('product_simple_cart_key_2');

        $this->createProduct([
            'name' => 'Klevu Product Name 3',
            'sku' => 'product_simple_sku_3',
            'type_id' => DownloadableType::TYPE_DOWNLOADABLE,
            'price' => 19.99,
            'key' => 'product_simple_cart_key_3',
        ]);
        $productFixture3 = $this->productFixturePool->get('product_simple_cart_key_3');

        $this->createCart([
            'products' => [
                'simple' => [
                    $productFixture1->getSku() => 1,
                ],
                'virtual' => [
                    $productFixture2->getSku() => 3,
                ],
                'downloadable' => [
                    $productFixture3->getSku() => 2,
                ],
            ],
        ]);

        $mockRequest = $this->getMockRequest(
            route: $route,
        );
        $provider = $this->instantiateTestObject(
            arguments: [
                'request' => $mockRequest,
                'outputOnRoutes' => $outputOnRoutes,
            ],
        );
        $result = $provider->get();

        $this->assertIsArray($result);
        $this->assertArrayHasKey(key: 'products', array: $result);
        $this->assertCount(expectedCount: 3, haystack: $result['products']);

        $result1Array = array_filter(
            array: $result['products'],
            callback: static fn (array $data): bool => (
                ($data['itemId'] ?? null) === (string)$productFixture1->getId()
            ),
        );
        $result1 = array_shift($result1Array);

        $this->assertArrayHasKey(key: 'itemGroupId', array: $result1);
        $this->assertSame(expected: '', actual: $result1['itemGroupId'], message: 'itemGroupId');

        $this->assertArrayHasKey(key: 'itemName', array: $result1);
        $this->assertSame(expected: 'Klevu Product Name 1', actual: $result1['itemName'], message: 'itemName');

        $this->assertArrayHasKey(key: 'itemSalesPrice', array: $result1);
        $this->assertSame(expected: '199.99', actual: $result1['itemSalesPrice'], message: 'itemSalesPrice');

        $this->assertArrayHasKey(key: 'itemUrl', array: $result1);
        $this->assertStringContainsString(
            needle: str_replace('_', '-', $productFixture1->getSku()) . '.html',
            haystack: $result1['itemUrl'],
            message: 'itemUrl',
        );
        $this->assertSame(expected: 1.0, actual: $result1['itemQty']);

        $result2Array = array_filter(
            array: $result['products'],
            callback: static fn (array $data): bool => (
                ($data['itemId'] ?? null) === (string)$productFixture2->getId()
            ),
        );
        $result2 = array_shift($result2Array);

        $this->assertArrayHasKey(key: 'itemGroupId', array: $result2);
        $this->assertSame(expected: '', actual: $result2['itemGroupId'], message: 'itemGroupId');

        $this->assertArrayHasKey(key: 'itemName', array: $result2);
        $this->assertSame(expected: 'Klevu Product Name 2', actual: $result2['itemName'], message: 'itemName');

        $this->assertArrayHasKey(key: 'itemSalesPrice', array: $result2);
        $this->assertSame(expected: '19.99', actual: $result2['itemSalesPrice'], message: 'itemSalesPrice');

        $this->assertArrayHasKey(key: 'itemUrl', array: $result2);
        $this->assertStringContainsString(
            needle: str_replace('_', '-', $productFixture2->getSku()) . '.html',
            haystack: $result2['itemUrl'],
            message: 'itemUrl',
        );
        $this->assertSame(expected: 3.0, actual: $result2['itemQty']);

        $result3Array = array_filter(
            array: $result['products'],
            callback: static fn (array $data): bool => (
                ($data['itemId'] ?? null) === (string)$productFixture3->getId()
            ),
        );
        $result3 = array_shift($result3Array);

        $this->assertArrayHasKey(key: 'itemGroupId', array: $result3);
        $this->assertSame(expected: '', actual: $result3['itemGroupId'], message: 'itemGroupId');

        $this->assertArrayHasKey(key: 'itemName', array: $result3);
        $this->assertSame(expected: 'Klevu Product Name 3', actual: $result3['itemName'], message: 'itemName');

        $this->assertArrayHasKey(key: 'itemSalesPrice', array: $result3);
        $this->assertSame(expected: '19.99', actual: $result3['itemSalesPrice'], message: 'itemSalesPrice');

        $this->assertArrayHasKey(key: 'itemUrl', array: $result3);
        $this->assertStringContainsString(
            needle: str_replace('_', '-', $productFixture3->getSku()) . '.html',
            haystack: $result3['itemUrl'],
            message: 'itemUrl',
        );
        $this->assertSame(expected: 2.0, actual: $result3['itemQty']);
    }

    /**
     * @param string $route
     *
     * @return MockObject
     */
    private function getMockRequest(string $route): MockObject
    {
        $mockRequest = $this->getMockBuilder(HttpRequest::class)
            ->disableOriginalConstructor()
            ->getMock();

        $routeParts = explode(
                separator: '_',
                string: $route,
            ) + ['index', 'index', 'index'];
        $mockRequest->method('getModuleName')
            ->willReturn($routeParts[0]);
        $mockRequest->method('getControllerName')
            ->willReturn($routeParts[1]);
        $mockRequest->method('getActionName')
            ->willReturn($routeParts[2]);

        return $mockRequest;
    }
}
