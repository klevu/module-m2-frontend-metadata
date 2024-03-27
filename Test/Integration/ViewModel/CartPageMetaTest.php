<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\FrontendMetadata\Test\Integration\ViewModel;

use Klevu\FrontendMetadata\ViewModel\PageMeta;
use Klevu\FrontendMetadataApi\ViewModel\PageMetaInterface;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Checkout\CartFixturePool;
use Klevu\TestFixtures\Checkout\CartTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Model\Product\Type;
use Magento\Downloadable\Model\Product\Type as DownloadableType;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers \Klevu\FrontendMetadata\ViewModel\PageMeta
 * @method PageMetaInterface instantiateTestObject(?array $arguments = null)
 * @method PageMetaInterface instantiateTestObjectFromInterface(?array $arguments = null)
 * @magentoAppArea frontend
 */
class CartPageMetaTest extends TestCase
{
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
     * @var SerializerInterface|null
     */
    private ?SerializerInterface $serializer = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = PageMeta::class;
        $this->interfaceFqcn = PageMetaInterface::class;

        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->productFixturePool = $this->objectManager->get(ProductFixturePool::class);
        $this->cartFixturePool = $this->objectManager->get(CartFixturePool::class);
        $this->serializer = $this->objectManager->get(SerializerInterface::class);

        $request = $this->objectManager->get(RequestInterface::class);
        $request->setModuleName('checkout');
        $request->setControllerName('cart');
        $request->setActionName('index');
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
        $this->storeFixturesPool->rollback();
    }

    public function testGetMeta_ReturnsString_WhenEnabled(): void
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

        $viewModel = $this->instantiateTestObject();
        $serializedResult = $viewModel->getMeta();
        $pageMeta = $this->serializer->unserialize($serializedResult);

        $this->assertArrayHasKey(key: 'page', array: $pageMeta);
        $this->assertArrayHasKey(key: 'pageType', array: $pageMeta['page']);
        $this->assertSame(expected: 'cart', actual: $pageMeta['page']['pageType']);

        $this->assertArrayHasKey(key: 'quick', array: $pageMeta['page']);
        $this->assertArrayHasKey(key: 'products', array: $pageMeta['page']['quick']);
        $this->assertIsArray(actual: $pageMeta['page']['quick']['products']);

        $this->assertArrayHasKey(key: 'cart', array: $pageMeta['page']);
        $this->assertArrayHasKey(key: 'products', array: $pageMeta['page']['cart']);
        $productData = $pageMeta['page']['cart']['products'];

        $this->assertIsArray($productData);
        $this->assertCount(expectedCount: 3, haystack: $productData);

        $result1Array = array_filter(
            array: $productData,
            callback: static fn (array $data): bool => (
                ($data['itemId'] ?? null) === (string)$productFixture1->getId()
            ),
        );
        $result1 = array_shift($result1Array);

        $this->assertArrayHasKey(key: 'itemGroupId', array: $result1);
        $this->assertSame(
            expected: '',
            actual: $result1['itemGroupId'],
            message: 'itemGroupId',
        );

        $this->assertArrayHasKey(key: 'itemName', array: $result1);
        $this->assertSame(
            expected: 'Klevu Product Name 1',
            actual: $result1['itemName'],
            message: 'itemName',
        );

        $this->assertArrayHasKey(key: 'itemSalesPrice', array: $result1);
        $this->assertSame(
            expected: '199.99',
            actual: $result1['itemSalesPrice'],
            message: 'itemSalesPrice',
        );

        $this->assertArrayHasKey(key: 'itemUrl', array: $result1);
        $this->assertStringContainsString(
            needle: str_replace('_', '-', $productFixture1->getSku()) . '.html',
            haystack: $result1['itemUrl'],
            message: 'itemUrl',
        );
        $this->assertSame(expected: 1, actual: $result1['itemQty']);

        $result2Array = array_filter(
            array: $productData,
            callback: static fn (array $data): bool => (
                ($data['itemId'] ?? null) === (string)$productFixture2->getId()
            ),
        );
        $result2 = array_shift($result2Array);

        $this->assertArrayHasKey(key: 'itemGroupId', array: $result2);
        $this->assertSame(
            expected: '',
            actual: $result2['itemGroupId'],
            message: 'itemGroupId',
        );

        $this->assertArrayHasKey(key: 'itemName', array: $result2);
        $this->assertSame(
            expected: 'Klevu Product Name 2',
            actual: $result2['itemName'],
            message: 'itemName',
        );

        $this->assertArrayHasKey(key: 'itemSalesPrice', array: $result2);
        $this->assertSame(
            expected: '19.99',
            actual: $result2['itemSalesPrice'],
            message: 'itemSalesPrice',
        );

        $this->assertArrayHasKey(key: 'itemUrl', array: $result2);
        $this->assertStringContainsString(
            needle: str_replace('_', '-', $productFixture2->getSku()) . '.html',
            haystack: $result2['itemUrl'],
            message: 'itemUrl',
        );
        $this->assertSame(expected: 3, actual: $result2['itemQty']);

        $result3Array = array_filter(
            array: $productData,
            callback: static fn (array $data): bool => (
                ($data['itemId'] ?? null) === (string)$productFixture3->getId()
            ),
        );
        $result3 = array_shift($result3Array);

        $this->assertArrayHasKey(key: 'itemGroupId', array: $result3);
        $this->assertSame(
            expected: '',
            actual: $result3['itemGroupId'],
            message: 'itemGroupId',
        );

        $this->assertArrayHasKey(key: 'itemName', array: $result3);
        $this->assertSame(
            expected: 'Klevu Product Name 3',
            actual: $result3['itemName'],
            message: 'itemName',
        );

        $this->assertArrayHasKey(key: 'itemSalesPrice', array: $result3);
        $this->assertSame(
            expected: '19.99',
            actual: $result3['itemSalesPrice'],
            message: 'itemSalesPrice',
        );

        $this->assertArrayHasKey(key: 'itemUrl', array: $result3);
        $this->assertStringContainsString(
            needle: str_replace('_', '-', $productFixture3->getSku()) . '.html',
            haystack: $result3['itemUrl'],
            message: 'itemUrl',
        );
        $this->assertSame(expected: 2, actual: $result3['itemQty']);
    }
}
