<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\FrontendMetadata\Test\Integration\Service\Provider\Catalog\Product;

use Klevu\Configuration\Service\Provider\StoreScopeProvider;
use Klevu\FrontendMetadata\Service\Provider\Catalog\Product\IdProvider;
use Klevu\FrontendMetadataApi\Service\Provider\Catalog\Product\IdProviderInterface;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Catalog\ProductTrait;
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
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers IdProvider
 * @method IdProviderInterface instantiateTestObject(?array $arguments = null)
 * @method IdProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 * @magentoAppArea frontend
 */
class IdProviderTest extends TestCase
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

        $this->implementationFqcn = IdProvider::class;
        $this->interfaceFqcn = IdProviderInterface::class;
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

    public function testGetItemId_ForSimpleProduct(): void
    {
        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $provider = $this->instantiateTestObject();
        $result = $provider->getItemId($productFixture->getProduct());

        $this->assertSame(expected: (string)$productFixture->getId(), actual: $result);
    }

    public function testGetItemId_ForVirtualProduct(): void
    {
        $this->createProduct([
            'type_id' => Type::TYPE_VIRTUAL,
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $provider = $this->instantiateTestObject();
        $result = $provider->getItemId($productFixture->getProduct());

        $this->assertSame(expected: (string)$productFixture->getId(), actual: $result);
    }

    public function testGetItemId_ForDownloadableProduct(): void
    {
        $this->createProduct([
            'type_id' => DownloadableType::TYPE_DOWNLOADABLE,
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $provider = $this->instantiateTestObject();
        $result = $provider->getItemId($productFixture->getProduct());

        $this->assertSame(expected: (string)$productFixture->getId(), actual: $result);
    }

    public function testGetItemId_ForBundleProduct(): void
    {
        $this->createProduct([
            'type_id' => BundleType::TYPE_CODE,
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $provider = $this->instantiateTestObject();
        $result = $provider->getItemId($productFixture->getProduct());

        $this->assertSame(expected: (string)$productFixture->getId(), actual: $result);
    }

    public function testGetItemId_ForGroupedProduct(): void
    {
        $this->createProduct();
        $productFixtureChild1 = $this->productFixturePool->get('test_product');

        $this->createProduct([
            'key' => 'klevu_test_grouped',
            'type_id' => Grouped::TYPE_CODE,
            'price' => 999.99,
            'linked_products' => [
                $productFixtureChild1,
            ],
        ]);
        $productFixture = $this->productFixturePool->get('klevu_test_grouped');

        $provider = $this->instantiateTestObject();
        $result = $provider->getItemId($productFixture->getProduct());

        $this->assertSame(expected: (string)$productFixture->getId(), actual: $result);
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

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $storeScopeProvider = $this->objectManager->create(StoreScopeProvider::class);
        $storeScopeProvider->setCurrentStore($storeFixture->get());

        $this->createProduct([
            'key' => 'klevu_simple_test_product',
            'data' => [
                $attributeFixture->getAttributeCode() => $attributeSource->getOptionId('Option 1'),
            ],
        ]);
        $productFixtureSimple = $this->productFixturePool->get('klevu_simple_test_product');

        $this->createProduct([
            'key' => 'klevu_configurable_test_product',
            'type_id' => Configurable::TYPE_CODE,
            'configurable_attributes' => [$attributeFixture->getAttribute()],
            'variants' => [
                $productFixtureSimple->getProduct(),
            ],
        ]);
        $productFixture = $this->productFixturePool->get('klevu_configurable_test_product');

        $provider = $this->instantiateTestObject();
        $result = $provider->getItemId($productFixture->getProduct());

        $this->assertSame(
            expected: $productFixture->getId() . '-' . $productFixtureSimple->getId(),
            actual: $result,
        );
    }

    public function testGetItemGroupId_ForSimpleProduct(): void
    {
        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $provider = $this->instantiateTestObject();
        $result = $provider->getItemGroupId($productFixture->getProduct());

        $this->assertSame(expected: '', actual: $result);
    }

    public function testGetItemGroupId_ForVirtualProduct(): void
    {
        $this->createProduct([
            'type_id' => Type::TYPE_VIRTUAL,
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $provider = $this->instantiateTestObject();
        $result = $provider->getItemGroupId($productFixture->getProduct());

        $this->assertSame(expected: '', actual: $result);
    }

    public function testGetItemGroupId_ForDownloadableProduct(): void
    {
        $this->createProduct([
            'type_id' => DownloadableType::TYPE_DOWNLOADABLE,
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $provider = $this->instantiateTestObject();
        $result = $provider->getItemGroupId($productFixture->getProduct());

        $this->assertSame(expected: '', actual: $result);
    }

    public function testGetItemGroupId_ForBundleProduct(): void
    {
        $this->createProduct([
            'type_id' => BundleType::TYPE_CODE,
        ]);
        $productFixture = $this->productFixturePool->get('test_product');

        $provider = $this->instantiateTestObject();
        $result = $provider->getItemGroupId($productFixture->getProduct());

        $this->assertSame(expected: '', actual: $result);
    }

    public function testGetItemGroupId_ForGroupedProduct(): void
    {
        $this->createProduct();
        $productFixtureChild1 = $this->productFixturePool->get('test_product');

        $this->createProduct([
            'key' => 'klevu_test_grouped',
            'type_id' => Grouped::TYPE_CODE,
            'price' => 999.99,
            'linked_products' => [
                $productFixtureChild1,
            ],
        ]);
        $productFixture = $this->productFixturePool->get('klevu_test_grouped');

        $provider = $this->instantiateTestObject();
        $result = $provider->getItemGroupId($productFixture->getProduct());

        $this->assertSame(expected: '', actual: $result);
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testGetItemGroupId_ForConfigurableProduct(): void
    {
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
            'key' => 'klevu_simple_test_product',
            'data' => [
                $attributeFixture->getAttributeCode() => $attributeSource->getOptionId('Option 1'),
            ],
        ]);
        $productFixtureSimple = $this->productFixturePool->get('klevu_simple_test_product');

        $this->createProduct([
            'key' => 'klevu_configurable_test_product',
            'type_id' => Configurable::TYPE_CODE,
            'configurable_attributes' => [$attributeFixture->getAttribute()],
            'variants' => [
                $productFixtureSimple->getProduct(),
            ],
        ]);
        $productFixture = $this->productFixturePool->get('klevu_configurable_test_product');

        $provider = $this->instantiateTestObject();
        $result = $provider->getItemGroupId($productFixture->getProduct());

        $this->assertSame(expected: (string)$productFixture->getId(), actual: $result);
    }
}
