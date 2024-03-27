<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\FrontendMetadata\Service\IsEnabledCondition;

use Klevu\Frontend\Exception\OutputDisabledException;
use Klevu\FrontendApi\Service\IsEnabledCondition\IsEnabledConditionInterface;
use Klevu\FrontendApi\Service\Provider\SettingsProviderInterface;

class IsMetadataEnabledCondition implements IsEnabledConditionInterface
{
    /**
     * @var SettingsProviderInterface
     */
    private readonly SettingsProviderInterface $metadataEnabledProvider;

    /**
     * @param SettingsProviderInterface $metadataEnabledProvider
     */
    public function __construct(SettingsProviderInterface $metadataEnabledProvider)
    {
        $this->metadataEnabledProvider = $metadataEnabledProvider;
    }

    /**
     * @return bool
     * @throws OutputDisabledException
     */
    public function execute(): bool
    {
        return (bool)$this->metadataEnabledProvider->get();
    }
}
