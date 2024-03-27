<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\FrontendMetadata\Service\Provider\Catalog\Category;

use Klevu\FrontendMetadataApi\Service\Provider\Catalog\Category\PathProviderInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Eav\Model\Entity;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class PathProvider implements PathProviderInterface
{
    public const CATEGORY_PATH_SEPARATOR = '/';

    /**
     * @var CategoryCollectionFactory
     */
    private readonly CategoryCollectionFactory $categoryCollectionFactory;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var string|null
     */
    private ?string $fullCategoryPath = null;

    /**
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        CategoryCollectionFactory $categoryCollectionFactory,
        LoggerInterface $logger,
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->logger = $logger;
    }

    /**
     * @param CategoryInterface|null $category
     *
     * @return string
     */
    public function get(?CategoryInterface $category = null): string
    {
        if (null === $this->fullCategoryPath) {
            $this->fullCategoryPath = '';
            if ($category) {
                try {
                    $this->fullCategoryPath = $this->getPath(category: $category);
                } catch (LocalizedException $exception) {
                    $this->logger->error(
                        message: 'Method: {method}, Error: {message}',
                        context: [
                            'method' => __METHOD__,
                            'message' => $exception->getMessage(),
                        ],
                    );
                }
            }
        }

        return $this->fullCategoryPath;
    }

    /**
     * @param CategoryInterface $category
     *
     * @return string
     * @throws LocalizedException
     */
    private function getPath(CategoryInterface $category): string
    {
        $categoryPathIds = $this->getCategoryPathIds(category: $category);
        $categoryNames = (count($categoryPathIds) === 1)
            ? [$category->getName()]
            : $this->getCategoryNamesById(categoryIds: $categoryPathIds);

        return implode(separator: static::CATEGORY_PATH_SEPARATOR, array: $categoryNames);
    }

    /**
     * @param CategoryInterface $category
     *
     * @return int[]
     */
    private function getCategoryPathIds(CategoryInterface $category): array
    {
        $categoryPathIds = [];
        if (method_exists($category, 'getPathIds')) {
            $categoryPathIds = array_map(
                callback: 'intval',
                array: $category->getPathIds(),
            );
            unset($categoryPathIds[0], $categoryPathIds[1]);
        }

        return $categoryPathIds;
    }

    /**
     * @param int[] $categoryIds
     *
     * @return string[]
     * @throws LocalizedException
     */
    private function getCategoryNamesById(array $categoryIds): array
    {
        /** @var CategoryCollection $categoryCollection */
        $categoryCollection = $this->categoryCollectionFactory->create();
        $categoryCollection->addFieldToFilter(
            Entity::DEFAULT_ENTITY_ID_FIELD,
            ['in' => $categoryIds],
        );
        $categoryCollection->addAttributeToSelect(attribute: CategoryInterface::KEY_NAME);

        $return = [];
        foreach ($categoryIds as $categoryId) {
            $category = $categoryCollection->getItemById($categoryId);
            // getItemById is nullable, however Magento has not type-hinted that
            // @phpstan-ignore-next-line
            $return[$categoryId] = (string)$category?->getDataUsingMethod(key: CategoryInterface::KEY_NAME);
        }

        return $return;
    }
}
