<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\FrontendMetadata\Test\Integration\Service\Provider\Catalog\Product;

use Klevu\Configuration\Service\Provider\StoreScopeProvider;
use Klevu\FrontendMetadata\Service\Provider\Catalog\Product\PriceProvider;
use Klevu\FrontendMetadataApi\Service\Provider\Catalog\Product\PriceProviderInterface;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Catalog\Model\Product\Type;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Downloadable\Model\Product\Type as DownloadableType;
use Magento\Framework\ObjectManagerInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers PriceProvider
 * @method PriceProviderInterface instantiateTestObject(?array $arguments = null)
 * @method PriceProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 * @magentoAppArea frontend
 */
class PriceProviderTest extends TestCase
{
    use AttributeTrait;
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

        $this->implementationFqcn = PriceProvider::class;
        $this->interfaceFqcn = PriceProviderInterface::class;
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

    public function testGet_ReturnsSimpleProductPrice(): void
    {
        $this->createProduct([
            'price' => 99.99,
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $provider = $this->instantiateTestObject();
        $result = $provider->get($productFixture->getProduct());

        $this->assertSame(expected: 99.99, actual: $result);
    }

    public function testGet_ReturnsVirtualProductPrice(): void
    {
        $this->createProduct([
            'price' => 88.88,
            'type_id' => Type::TYPE_VIRTUAL,
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $provider = $this->instantiateTestObject();
        $result = $provider->get($productFixture->getProduct());

        $this->assertSame(expected: 88.88, actual: $result);
    }

    public function testGet_ReturnsDownloadableProductPrice(): void
    {
        $this->createProduct([
            'price' => 77.77,
            'type_id' => DownloadableType::TYPE_DOWNLOADABLE,
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $provider = $this->instantiateTestObject();
        $result = $provider->get($productFixture->getProduct());

        $this->assertSame(expected: 77.77, actual: $result);
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testGet_ReturnsMinConfigurableProductPrice(): void
    {
        $this->createAttribute(['attribute_type' => 'configurable']);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $storeScopeProvider = $this->objectManager->create(StoreScopeProvider::class);
        $storeScopeProvider->setCurrentStore($storeFixture->get());

        $this->createProduct([
            'key' => 'klevu_simple_test_product',
            'price' => 66.66,
            'qty' => 100,
            'data' => [
                $attributeFixture->getAttributeCode() => '1',
            ],
        ]);
        $productFixtureSimple = $this->productFixturePool->get('klevu_simple_test_product');

        $this->createProduct([
            'key' => 'klevu_configurable_test_product',
            'type_id' => Configurable::TYPE_CODE,
            'price' => 100.00,
            'configurable_attributes' => [$attributeFixture->getAttribute()],
            'variants' => [
                $productFixtureSimple->getProduct(),
            ],
        ]);
        $productFixture = $this->productFixturePool->get('klevu_configurable_test_product');

        $provider = $this->instantiateTestObject();
        $result = $provider->get($productFixture->getProduct());

        $this->assertSame(expected: 66.66, actual: $result);
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testGet_ReturnsGroupedProductPrice(): void
    {
        $this->createProduct([
            'key' => 'klevu_test_grouped_child_1',
            'price' => 99.99,
        ]);
        $productFixtureChild1 = $this->productFixturePool->get('klevu_test_grouped_child_1');

        $this->createProduct([
            'key' => 'klevu_test_grouped_child_2',
            'price' => 55.55,
        ]);
        $productFixtureChild2 = $this->productFixturePool->get('klevu_test_grouped_child_2');

        $this->createProduct([
            'key' => 'klevu_test_grouped',
            'type_id' => Grouped::TYPE_CODE,
            'price' => 999.99,
            'linked_products' => [
                $productFixtureChild1,
                $productFixtureChild2,
            ],
        ]);
        $productFixture = $this->productFixturePool->get('klevu_test_grouped');

        $provider = $this->instantiateTestObject();
        $result = $provider->get($productFixture->getProduct());

        $this->assertSame(expected: 55.55, actual: $result);
    }
}
