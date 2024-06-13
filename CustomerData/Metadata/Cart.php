<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\FrontendMetadata\CustomerData\Metadata;

use Klevu\FrontendMetadataApi\Service\Provider\PageMetaProviderInterface;
use Magento\Customer\CustomerData\SectionSourceInterface;

class Cart implements SectionSourceInterface
{
    /**
     * @var PageMetaProviderInterface
     */
    private readonly PageMetaProviderInterface $cartMetadataProvider;

    /**
     * @param PageMetaProviderInterface $cartMetadataProvider
     */
    public function __construct(PageMetaProviderInterface $cartMetadataProvider)
    {
        $this->cartMetadataProvider = $cartMetadataProvider;
    }

    /**
     * @return mixed[]
     */
    public function getSectionData(): array
    {
        return $this->cartMetadataProvider->get();
    }
}
