<?php

declare(strict_types=1);

namespace PrestaShop\Module\Lowestshipping\Form;

use Country;
use PrestaShopBundle\Form\Admin\Type\SwitchType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

final class LowestShippingConfigurationFormType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $langId = (int) \Context::getContext()->language->id;
        $countries = Country::getCountries($langId, true, false, false);
        $choices = [];
        foreach ($countries as $country) {
            $choices[(string) $country['name']] = (int) $country['id_country'];
        }

        $builder
            ->add('default_country', ChoiceType::class, [
                'label' => $this->trans('Default country (guests)', [], 'Modules.Lowestshipping.Admin'),
                'help' => $this->trans('Used when the customer is not logged in or has no delivery address.', [], 'Modules.Lowestshipping.Admin'),
                'choices' => $choices,
                'constraints' => [new NotBlank()],
            ])
            ->add('price_with_tax', SwitchType::class, [
                'label' => $this->trans('Display price with tax', [], 'Modules.Lowestshipping.Admin'),
                'required' => false,
            ])
            ->add('text_prefix', TextType::class, [
                'label' => $this->trans('Text prefix', [], 'Modules.Lowestshipping.Admin'),
                'help' => $this->trans('Example: "From " or "Cheapest delivery: "', [], 'Modules.Lowestshipping.Admin'),
                'required' => false,
            ])
            ->add('enable_visibility_filter', SwitchType::class, [
                'label' => $this->trans('Limit display to allowed products/categories', [], 'Modules.Lowestshipping.Admin'),
                'help' => $this->trans('When enabled, the block is hidden for products or categories listed below.', [], 'Modules.Lowestshipping.Admin'),
                'required' => false,
            ])
            ->add('excluded_product_ids', TextareaType::class, [
                'label' => $this->trans('Excluded product IDs', [], 'Modules.Lowestshipping.Admin'),
                'help' => $this->trans('Comma or space separated (e.g. 12, 34). Only used when the option above is enabled.', [], 'Modules.Lowestshipping.Admin'),
                'required' => false,
            ])
            ->add('excluded_category_ids', TextareaType::class, [
                'label' => $this->trans('Excluded category IDs', [], 'Modules.Lowestshipping.Admin'),
                'help' => $this->trans('Comma or space separated. Only used when the option above is enabled.', [], 'Modules.Lowestshipping.Admin'),
                'required' => false,
            ]);
    }
}
