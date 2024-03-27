<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\FrontendMetadata\Test\Integration\Service\Provider;

use Klevu\FrontendMetadata\Service\Provider\Catalog\Category\PathProvider;
use Klevu\FrontendMetadataApi\Service\Provider\Catalog\Category\PathProviderInterface;
use Klevu\TestFixtures\Catalog\CategoryTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TddWizard\Fixtures\Catalog\CategoryFixturePool;

/**
 * @covers PathProvider
 * @method PathProviderInterface instantiateTestObject(?array $arguments = null)
 * @method PathProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 * @magentoAppArea frontend
 */
class PathProviderTest extends TestCase
{
    use CategoryTrait;
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;
    use TestInterfacePreferenceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; //@phpstan-ignore-line

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = PathProvider::class;
        $this->interfaceFqcn = PathProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->categoryFixturePool = $this->objectManager->get(CategoryFixturePool::class);
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
     * @magentoAppIsolation enabled
     */
    public function testGet_ReturnsEmptyString_WhenCategoryIsNotInRegistry(): void
    {
        $provider = $this->instantiateTestObject();
        $result = $provider->get();

        $this->assertSame(expected: '', actual: $result);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGet_ReturnsCategoryName_WhenOneCategoryLevel(): void
    {
        $this->createCategory();
        $categoryFixture = $this->categoryFixturePool->get('test_category');
        $category = $categoryFixture->getCategory();

        $viewModel = $this->instantiateTestObject();
        $result = $viewModel->get($category);

        $this->assertSame(expected: $category->getName(), actual: $result);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGet_ReturnsCategoryName_WhenMultipleCategoryLevels(): void
    {
        $this->createCategory([
            'key' => 'top_category',
        ]);
        $topCategoryFixture = $this->categoryFixturePool->get('top_category');
        $topCategory = $topCategoryFixture->getCategory();

        $this->createCategory([
            'key' => 'middle_category',
            'parent' => $topCategoryFixture,
        ]);
        $middleCategoryFixture = $this->categoryFixturePool->get('middle_category');
        $middleCategory = $middleCategoryFixture->getCategory();

        $this->createCategory([
            'key' => 'bottom_category',
            'parent' => $middleCategoryFixture,
        ]);
        $bottomCategoryFixture = $this->categoryFixturePool->get('bottom_category');
        $bottomCategory = $bottomCategoryFixture->getCategory();

        $viewModel = $this->instantiateTestObject();
        $result = $viewModel->get($bottomCategory);

        $this->assertSame(
            expected: $topCategory->getName() . '/' . $middleCategory->getName() . '/' . $bottomCategory->getName(),
            actual: $result,
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGet_logsErrorReturnsEmptyString_WhenExceptionThrown(): void
    {
        $exceptionMessage = 'There was an error';
        $mockCategoryCollection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockCategoryCollection->expects($this->once())
            ->method('addAttributeToSelect')
            ->willThrowException(
                new LocalizedException(__($exceptionMessage)),
            );
        $mockCollectionFactory = $this->getMockBuilder(CollectionFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockCollectionFactory->expects($this->once())
            ->method('create')
            ->willReturn($mockCategoryCollection);

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Method: {method}, Error: {message}',
                [
                    'method' => 'Klevu\FrontendMetadata\Service\Provider\Catalog\Category\PathProvider::get',
                    'message' => $exceptionMessage,
                ],
            );

        $this->createCategory([
            'key' => 'top_category',
        ]);
        $topCategoryFixture = $this->categoryFixturePool->get('top_category');

        $this->createCategory([
            'key' => 'bottom_category',
            'parent' => $topCategoryFixture,
        ]);
        $bottomCategoryFixture = $this->categoryFixturePool->get('bottom_category');
        $bottomCategory = $bottomCategoryFixture->getCategory();

        $viewModel = $this->instantiateTestObject([
            'categoryCollectionFactory' => $mockCollectionFactory,
            'logger' => $mockLogger,
        ]);
        $result = $viewModel->get($bottomCategory);

        $this->assertSame(expected: '', actual: $result);
    }
}
