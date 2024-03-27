<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\FrontendMetadata\Test\Integration\Service\Provider;

use Klevu\FrontendMetadata\Service\Provider\CategoryMetaProvider;
use Klevu\FrontendMetadataApi\Service\Provider\PageMetaProviderInterface;
use Klevu\Registry\Api\CategoryRegistryInterface;
use Klevu\TestFixtures\Catalog\CategoryTrait;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Website\WebsiteTrait;
use Magento\CatalogUrlRewrite\Model\CategoryUrlPathGenerator;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\CategoryFixturePool;

/**
 * @covers \Klevu\FrontendMetadata\Service\Provider\CategoryMetaProvider
 * @method PageMetaProviderInterface instantiateTestObject(?array $arguments = null)
 * @method PageMetaProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 * @magentoAppArea frontend
 */
class CategoryMetaProviderTest extends TestCase
{
    use CategoryTrait;
    use ObjectInstantiationTrait;
    use SetAuthKeysTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;
    use WebsiteTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line
    /**
     * @var StoreManagerInterface|null
     */
    private ?StoreManagerInterface $storeManager = null;
    /**
     * @var ScopeConfigInterface|null
     */
    private ?ScopeConfigInterface $scopeConfig = null;
    /**
     * @var string
     */
    private string $urlSuffix;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = CategoryMetaProvider::class;
        $this->interfaceFqcn = PageMetaProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $this->categoryFixturePool = $this->objectManager->get(CategoryFixturePool::class);
        $this->scopeConfig = $this->objectManager->get(ScopeConfigInterface::class);
        $this->urlSuffix = $this->scopeConfig->getValue(
            CategoryUrlPathGenerator::XML_PATH_CATEGORY_URL_SUFFIX,
            ScopeInterface::SCOPE_STORE,
        );
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->categoryFixturePool->rollback();
    }

    /**
     * @magentoConfigFixture default/klevu_frontend/metadata/enabled 1
     * @magentoConfigFixture default_store klevu_frontend/metadata/enabled 1
     */
    public function testExecute_ReturnsData_WhenEnabled(): void
    {
        $this->createCategory();
        $topCategoryFixture = $this->categoryFixturePool->get('test_category');

        $this->createCategory([
            'key' => 'child_category',
            'parent' => $topCategoryFixture,
            'name' => '[Klevu] Men',
            'url_key' => 'klevu-test-mens-category-1',
            'description' => '[Klevu Test Fixtures] Parent category 1',
            'is_active' => true,
        ]);
        $childCategoryFixture = $this->categoryFixturePool->get('child_category');

        $categoryRegistry = $this->objectManager->get(CategoryRegistryInterface::class);
        $categoryRegistry->setCurrentCategory($childCategoryFixture->getCategory());

        $provider = $this->instantiateTestObject();
        $actualResult = $provider->get();

        $expectedArrayKeys = [
            'categoryUrl',
            'categoryAbsolutePath',
            'categoryPath',
            'categoryName',
        ];
        $this->assertSameSize($expectedArrayKeys, $actualResult);
        foreach ($expectedArrayKeys as $expectedArrayKey) {
            $this->assertArrayHasKey($expectedArrayKey, $actualResult);
        }
        $this->assertSame(
            expected: $this->prepareUrl(
                urlKey: 'klevu-test-mens-category-1',
            ),
            actual: $actualResult['categoryUrl'],
        );
        $this->assertSame(expected: '[Klevu] Men', actual: $actualResult['categoryName']);
        $this->assertStringContainsString(
            needle: '/klevu-test-mens-category-1',
            haystack: $actualResult['categoryAbsolutePath'],
        );
    }

    /**
     * @param string $urlKey
     *
     * @return string
     * @throws NoSuchEntityException
     */
    private function prepareUrl(
        string $urlKey,
    ): string {
        $return = '';
        /** @var Store $store */
        $store = $this->storeManager->getStore();
        $return .= $store->getBaseUrl();
        $return .= $urlKey;
        $return .= $this->urlSuffix;

        return $return;
    }
}
