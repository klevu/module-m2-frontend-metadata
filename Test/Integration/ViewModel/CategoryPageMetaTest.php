<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\FrontendMetadata\Test\Integration\ViewModel;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Frontend\Service\IsEnabledCondition\IsStoreIntegratedCondition;
use Klevu\FrontendMetadata\ViewModel\PageMeta;
use Klevu\FrontendMetadata\ViewModel\PageMeta\Category as CategoryPageMetaVirtualType;
use Klevu\FrontendMetadataApi\Service\Provider\PageMetaProviderInterface;
use Klevu\FrontendMetadataApi\ViewModel\PageMetaInterface;
use Klevu\Registry\Api\CategoryRegistryInterface;
use Klevu\TestFixtures\Catalog\CategoryTrait;
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
use TddWizard\Fixtures\Catalog\CategoryFixturePool;

/**
 * @covers \Klevu\FrontendMetadata\ViewModel\PageMeta
 * @method PageMetaInterface instantiateTestObject(?array $arguments = null)
 * @method PageMetaInterface instantiateTestObjectFromInterface(?array $arguments = null)
 * @magentoAppArea frontend
 */
class CategoryPageMetaTest extends TestCase
{
    use CategoryTrait;
    use ObjectInstantiationTrait;
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

        $this->implementationFqcn = CategoryPageMetaVirtualType::class; // @phpstan-ignore-line
        $this->interfaceFqcn = PageMetaInterface::class;
        $this->implementationForVirtualType = PageMeta::class;

        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->categoryFixturePool = $this->objectManager->get(CategoryFixturePool::class);// @phpstan-ignore-line
        $this->serializer = $this->objectManager->get(SerializerInterface::class);
        $this->scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);

        $request = $this->objectManager->get(RequestInterface::class);
        $request->setModuleName('catalog');
        $request->setControllerName('category');
        $request->setActionName('view');
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->scopeProvider->unsetCurrentScope();
        $this->storeFixturesPool->rollback();
        $this->categoryFixturePool->rollback();
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetMeta_ReturnsString_WhenEnabled(): void
    {
        $this->createCategory([
            'name' => 'Klevu Test Category',
            'url_key' => 'klevu-test-category-1',
        ]);
        $categoryFixture = $this->categoryFixturePool->get('test_category');

        $categoryRegistry = $this->objectManager->get(CategoryRegistryInterface::class);
        $categoryRegistry->setCurrentCategory($categoryFixture->getCategory());

        $viewModel = $this->instantiateTestObject();
        $serializedPageMeta = $viewModel->getMeta();
        $pageMeta = $this->serializer->unserialize($serializedPageMeta);

        $this->assertArrayHasKey(key: 'page', array: $pageMeta);
        $this->assertArrayHasKey(key: 'pageType', array: $pageMeta['page']);
        $this->assertSame(expected: 'category', actual: $pageMeta['page']['pageType']);

        $this->assertArrayNotHasKey(key: 'cart', array: $pageMeta['page']);

        $this->assertArrayHasKey(key: 'category', array: $pageMeta['page']);
        $categoryData = $pageMeta['page']['category'];
        $expectedArrayKeys = [
            'categoryUrl',
            'categoryAbsolutePath',
            'categoryPath',
            'categoryName',
        ];
        $this->assertSameSize($expectedArrayKeys, $categoryData);
        foreach ($expectedArrayKeys as $expectedArrayKey) {
            $this->assertArrayHasKey($expectedArrayKey, $categoryData);
        }
        $categoryData = $pageMeta['page']['category'];
        $this->assertSame(
            expected: 'Klevu Test Category',
            actual: $categoryData['categoryName'],
        );
        $this->assertStringContainsString(
            needle: 'http',

            haystack: $categoryData['categoryUrl'],
        );
        $this->assertStringContainsString(
            needle: 'klevu-test-category-1',
            haystack: $categoryData['categoryUrl'],
        );
        $this->assertSame(
            expected: 'klevu-test-category-1',
            actual: $categoryData['categoryAbsolutePath'],
        );
        $this->assertSame(
            expected: 'Klevu Test Category',
            actual: $categoryData['categoryPath'],
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetMeta_ReturnsString_SecondLevel_WhenEnabled(): void
    {
        $this->createCategory([
            'name' => 'Mens',
            'url_key' => 'mens',
            'key' => 'category1',
        ]);
        $parentCategoryFixture = $this->categoryFixturePool->get('category1');

        $this->createCategory([
            'name' => 'Shirts',
            'url_key' => 'shirts',
            'parent' => $parentCategoryFixture,
            'key' => 'category2',
        ]);
        $categoryFixture = $this->categoryFixturePool->get('category2');

        $categoryRegistry = $this->objectManager->get(CategoryRegistryInterface::class);
        $categoryRegistry->setCurrentCategory($categoryFixture->getCategory());

        $viewModel = $this->instantiateTestObject();
        $serializedPageMeta = $viewModel->getMeta();
        $pageMeta = $this->serializer->unserialize($serializedPageMeta);

        $this->assertArrayHasKey('page', $pageMeta);
        $this->assertArrayHasKey('pageType', $pageMeta['page']);
        $this->assertSame(expected: 'category', actual: $pageMeta['page']['pageType']);

        $this->assertArrayNotHasKey(key: 'cart', array: $pageMeta['page']);

        $this->assertArrayHasKey('category', $pageMeta['page']);
        $categoryData = $pageMeta['page']['category'];
        $expectedArrayKeys = [
            'categoryUrl',
            'categoryAbsolutePath',
            'categoryPath',
            'categoryName',
        ];
        $this->assertSameSize($expectedArrayKeys, $categoryData);
        foreach ($expectedArrayKeys as $expectedArrayKey) {
            $this->assertArrayHasKey($expectedArrayKey, $categoryData);
        }
        $this->assertSame(
            expected: 'Shirts',
            actual: $categoryData['categoryName'],
        );
        $this->assertSame(
            expected: 'Mens/Shirts',
            actual: $categoryData['categoryPath'],
        );
        $this->assertSame(
            expected: 'mens/shirts',
            actual: $categoryData['categoryAbsolutePath'],
        );
        $this->assertStringContainsString(needle: 'http', haystack: $categoryData['categoryUrl']);
        $this->assertStringContainsString(needle: 'shirts', haystack: $categoryData['categoryUrl']);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetMeta_ReturnsString_SecondLevelSpecialChars_WhenEnabled(): void
    {
        $this->createCategory([
            'name' => 'Home & Kitchen',
            'url_key' => 'home-kitchen',
            'key' => 'category1',
        ]);
        $parentCategoryFixture = $this->categoryFixturePool->get('category1');

        $this->createCategory([
            'name' => 'Home & Décor',
            'url_key' => 'home-decor',
            'parent' => $parentCategoryFixture,
            'key' => 'category2',
        ]);
        $categoryFixture = $this->categoryFixturePool->get('category2');

        $categoryRegistry = $this->objectManager->get(CategoryRegistryInterface::class);
        $categoryRegistry->setCurrentCategory($categoryFixture->getCategory());

        $viewModel = $this->instantiateTestObject();
        $serializedPageMeta = $viewModel->getMeta();
        $pageMeta = $this->serializer->unserialize($serializedPageMeta);

        $this->assertArrayHasKey('page', $pageMeta);
        $this->assertArrayHasKey('pageType', $pageMeta['page']);
        $this->assertSame(expected: 'category', actual: $pageMeta['page']['pageType']);

        $this->assertArrayNotHasKey(key: 'cart', array: $pageMeta['page']);

        $this->assertArrayHasKey(key: 'quick', array: $pageMeta['page']);
        $this->assertArrayHasKey(key: 'products', array: $pageMeta['page']['quick']);
        $this->assertIsArray(actual: $pageMeta['page']['quick']['products']);

        $this->assertArrayHasKey('category', $pageMeta['page']);
        $categoryData = $pageMeta['page']['category'];
        $expectedArrayKeys = [
            'categoryUrl',
            'categoryAbsolutePath',
            'categoryPath',
            'categoryName',
        ];
        $this->assertSameSize($expectedArrayKeys, $categoryData);
        foreach ($expectedArrayKeys as $expectedArrayKey) {
            $this->assertArrayHasKey($expectedArrayKey, $categoryData);
        }
        $this->assertSame(
            expected: 'Home & Décor',
            actual: $categoryData['categoryName'],
        );
        $this->assertSame(
            expected: 'Home & Kitchen/Home & Décor',
            actual: $categoryData['categoryPath'],
        );
        $this->assertSame(
            expected: 'home-kitchen/home-decor',
            actual: $categoryData['categoryAbsolutePath'],
        );
        $this->assertStringContainsString(
            needle: 'http',
            haystack: $categoryData['categoryUrl'],
        );
        $this->assertStringContainsString(
            needle: '/home-decor',
            haystack: $categoryData['categoryUrl'],
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoConfigFixture default/klevu_frontend/metadata/enabled 1
     * @magentoConfigFixture default_store klevu_frontend/metadata/enabled 1
     */
    public function testGetMeta_ReturnsTrue_WhenEnabled(): void
    {
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $this->scopeProvider->setCurrentScope($store->get());

        $this->setAuthKeys(
            scopeProvider: $this->scopeProvider,
            jsApiKey: 'klevu_js_api_key',
            restAuthKey: 'klevu_rest_auth_key',
        );

        $categoryKey = 'kl_test_category_other_key';
        $this->createCategory([
            'name' => 'Klevu Test Category',
            'url_key' => 'klevu-test-category-other-1',
            'key' => $categoryKey,
        ]);
        $categoryFixture = $this->categoryFixturePool->get($categoryKey);

        $categoryRegistry = $this->objectManager->get(CategoryRegistryInterface::class);
        $categoryRegistry->setCurrentCategory($categoryFixture->getCategory());
        $viewModel = $this->instantiateTestObject([
            'isEnabledConditions' => [
                'klevu_integrated' => $this->objectManager->get(IsStoreIntegratedCondition::class),
            ],
        ]);

        $this->assertTrue(condition: $viewModel->isEnabled());
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoConfigFixture default/klevu_frontend/metadata/enabled 0
     * @magentoConfigFixture default_store klevu_frontend/metadata/enabled 0
     */
    public function testGetMeta_ReturnsFalse_WhenDisabled(): void
    {
        $mockProvider = $this->getMockBuilder(PageMetaProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockProvider->expects($this->never())
            ->method('get');

        $this->createCategory([
            'name' => 'Klevu Test Category',
            'url_key' => 'klevu-test-category-1',
        ]);
        $categoryFixture = $this->categoryFixturePool->get('test_category');

        $categoryRegistry = $this->objectManager->get(CategoryRegistryInterface::class);
        $categoryRegistry->setCurrentCategory($categoryFixture->getCategory());
        $viewModel = $this->instantiateTestObject([
            'pageMetaProviders' => [
                'section' => $mockProvider,
            ],
        ]);

        $this->assertFalse(condition: $viewModel->isEnabled());
    }
}
