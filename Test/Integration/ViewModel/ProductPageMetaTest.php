<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\FrontendMetadata\Test\Integration\ViewModel;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\FrontendMetadata\ViewModel\PageMeta;
use Klevu\FrontendMetadata\ViewModel\PageMeta\Product as ProductPageMetaVirtualType;
use Klevu\FrontendMetadataApi\Service\Provider\PageMetaProviderInterface;
use Klevu\FrontendMetadataApi\ViewModel\PageMetaInterface;
use Klevu\Registry\Api\ProductRegistryInterface;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
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
class ProductPageMetaTest extends TestCase
{
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
    /**
     * @var ScopeProviderInterface
     */
    private ?ScopeProviderInterface $scopeProvider = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = ProductPageMetaVirtualType::class; // @phpstan-ignore-line
        $this->interfaceFqcn = PageMetaInterface::class;
        $this->implementationForVirtualType = PageMeta::class;

        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->productFixturePool = $this->objectManager->get(ProductFixturePool::class);
        $this->serializer = $this->objectManager->get(SerializerInterface::class);
        $this->scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $this->scopeProvider->unsetCurrentScope();

        $request = $this->objectManager->get(RequestInterface::class);
        $request->setModuleName('catalog');
        $request->setControllerName('product');
        $request->setActionName('view');
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->productFixturePool->rollback();
        $this->storeFixturesPool->rollback();
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoAppIsolation enabled
     */
    public function testGetMeta_ReturnsString_WhenEnabled(): void
    {
        $this->createProduct([
            'name' => 'Klevu Product Name',
            'price' => 1299.99123,
        ]);
        $viewModel = $this->instantiateTestObject();

        $productFixture = $this->productFixturePool->get('test_product');
        $productRegistry = $this->objectManager->get(ProductRegistryInterface::class);
        $currentProduct = $productFixture->getProduct();
        $productRegistry->setCurrentProduct($currentProduct);

        $viewModelPageMeta = $viewModel->getMeta();
        $pageMeta = $this->serializer->unserialize($viewModelPageMeta);

        $this->assertArrayHasKey('page', $pageMeta);
        $this->assertArrayHasKey('pageType', $pageMeta['page']);
        $this->assertSame(expected: 'pdp', actual: $pageMeta['page']['pageType']);

        $this->assertArrayNotHasKey(key: 'cart', array: $pageMeta['page']);

        $this->assertArrayHasKey(key: 'quick', array: $pageMeta['page']);
        $this->assertArrayHasKey(key: 'products', array: $pageMeta['page']['quick']);
        $this->assertIsArray(actual: $pageMeta['page']['quick']['products']);

        $this->assertArrayHasKey('pdp', $pageMeta['page']);
        $this->assertArrayHasKey('products', $pageMeta['page']['pdp']);

        $productData = $pageMeta['page']['pdp']['products'];
        $expectedArrayKeys = [
            'itemUrl',
            'itemId',
            'itemGroupId',
            'itemName',
            'itemSalePrice',
            'itemCurrency',
        ];
        $this->assertSameSize($expectedArrayKeys, $productData);
        foreach ($expectedArrayKeys as $expectedArrayKey) {
            $this->assertArrayHasKey($expectedArrayKey, $productData);
        }
        $this->assertSame(expected: 'Klevu Product Name', actual: $productData['itemName']);
        $this->assertStringContainsString(needle: 'http', haystack: $productData['itemUrl']);
        $this->assertSame(expected: $currentProduct->getId(), actual: $productData['itemId']);
        $this->assertSame(expected: '', actual: $productData['itemGroupId']);
        $this->assertSame(expected: '1,299.99', actual: $productData['itemSalePrice']);
        $this->assertSame(expected: 'USD', actual: $productData['itemCurrency']);
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoAppIsolation enabled
     * @magentoConfigFixture default/klevu_frontend/metadata/enabled 0
     * @magentoConfigFixture default_store klevu_frontend/metadata/enabled 0
     * @magentoDataFixtureBeforeTransaction Magento/Catalog/_files/enable_reindex_schedule.php
     */
    public function testGetMeta_ReturnsFalse_WhenDisabled(): void
    {
        $mockProvider = $this->getMockBuilder(PageMetaProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockProvider->expects($this->never())
            ->method('get');

        $this->createProduct([
            'name' => 'Klevu Product Name',
            'price' => 99.99123,
        ]);
        $viewModel = $this->instantiateTestObject([
            'pageMetaProviders' => [
                'section' => $mockProvider,
            ],
        ]);

        $productFixture = $this->productFixturePool->get('test_product');
        $productRegistry = $this->objectManager->get(ProductRegistryInterface::class);
        $productRegistry->setCurrentProduct($productFixture->getProduct());

        $this->assertFalse(condition: $viewModel->isEnabled());
    }
}
