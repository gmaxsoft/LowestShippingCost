<?php

declare(strict_types=1);

namespace PrestaShop\Module\Lowestshipping\Form;

use PrestaShop\PrestaShop\Core\Configuration\DataConfigurationInterface;
use PrestaShop\PrestaShop\Core\Form\FormDataProviderInterface;

final class LowestShippingConfigurationFormDataProvider implements FormDataProviderInterface
{
    private DataConfigurationInterface $dataConfiguration;

    public function __construct(DataConfigurationInterface $dataConfiguration)
    {
        $this->dataConfiguration = $dataConfiguration;
    }

    public function getData(): array
    {
        return $this->dataConfiguration->getConfiguration();
    }

    public function setData(array $data): array
    {
        return $this->dataConfiguration->updateConfiguration($data);
    }
}
