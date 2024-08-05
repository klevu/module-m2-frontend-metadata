<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\FrontendMetadata\Service\Provider;

use Klevu\FrontendMetadataApi\Service\Provider\Catalog\Category\PathProviderInterface;
use Klevu\FrontendMetadataApi\Service\Provider\PageMetaProviderInterface;
use Klevu\Registry\Api\CategoryRegistryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class CategoryMetaProvider implements PageMetaProviderInterface
{
    /**
     * @var CategoryRegistryInterface
     */
    private readonly CategoryRegistryInterface $categoryRegistry;
    /**
     * @var PathProviderInterface
     */
    private readonly PathProviderInterface $categoryPathProvider;

    /**
     * @param CategoryRegistryInterface $categoryRegistry
     * @param PathProviderInterface $categoryPathProvider
     */
    public function __construct(
        CategoryRegistryInterface $categoryRegistry,
        PathProviderInterface $categoryPathProvider,
    ) {
        $this->categoryRegistry = $categoryRegistry;
        $this->categoryPathProvider = $categoryPathProvider;
    }

    /**
     * @return mixed[]
     * @throws LocalizedException
     */
    public function get(): array
    {
        $category = $this->categoryRegistry->getCurrentCategory();
        if (!$category instanceof CategoryInterface) {
            return [];
        }

        return [
            'categoryUrl' => $this->getCategoryUrl($category),
            'categoryAbsolutePath' => $this->getUrlPath($category),
            'categoryPath' => $this->categoryPathProvider->get($category),
            'categoryName' => $category->getName(),
        ];
    }

    /**
     * @param CategoryInterface $category
     *
     * @return string
     * @throws NoSuchEntityException
     */
    private function getCategoryUrl(CategoryInterface $category): string
    {
        return method_exists($category, 'getUrl')
            ? $category->getUrl()
            : '';
    }

    /**
     * @param CategoryInterface $category
     *
     * @return mixed|string
     */
    private function getUrlPath(CategoryInterface $category): mixed
    {
        return method_exists($category, 'getDataUsingMethod')
            ? $category->getDataUsingMethod('url_path')
            : '';
    }
}
