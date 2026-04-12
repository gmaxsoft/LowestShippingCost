<?php
/**
 * Symfony form type — module configuration (back office).
 *
 * @author    Maxsoft
 * @copyright 2007-2026 Maxsoft
 * @license   https://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

namespace PrestaShop\Module\Lowestshipping\Form;

use Configuration;
use Country;
use PrestaShopBundle\Form\Admin\Type\SwitchType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class LowestShippingConfigurationFormType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $langId = (int) Configuration::get('PS_LANG_DEFAULT');
        $countries = Country::getCountries($langId, true, false, false);
        $choices = [];
        foreach ($countries as $country) {
            $choices[(string) $country['name']] = (int) $country['id_country'];
        }

        $builder
            ->add('default_country', ChoiceType::class, [
                'label' => $this->trans('Default country for shipping estimate', 'Modules.Lowestshipping.Admin'),
                'help' => $this->trans('Used for guests and for customers without a delivery address.', 'Modules.Lowestshipping.Admin'),
                'choices' => $choices,
                'constraints' => [new NotBlank()],
            ])
            ->add('price_with_tax', SwitchType::class, [
                'label' => $this->trans('Show cost including tax', 'Modules.Lowestshipping.Admin'),
                'required' => false,
            ])
            ->add('text_prefix', TextType::class, [
                'label' => $this->trans('Price prefix', 'Modules.Lowestshipping.Admin'),
                'help' => $this->trans('Example: "From " or "Cheapest delivery: "', 'Modules.Lowestshipping.Admin'),
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'label' => $this->trans('Extra text under the price', 'Modules.Lowestshipping.Admin'),
                'help' => $this->trans('Optional note shown below the shipping price on the product page.', 'Modules.Lowestshipping.Admin'),
                'required' => false,
            ])
            ->add('enable_product_page', SwitchType::class, [
                'label' => $this->trans('Show block on product page', 'Modules.Lowestshipping.Admin'),
                'help' => $this->trans('Turn off to hide the estimate everywhere on product pages.', 'Modules.Lowestshipping.Admin'),
                'required' => false,
            ]);
    }
}
