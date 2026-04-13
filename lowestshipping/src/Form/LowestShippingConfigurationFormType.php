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
                'label' => $this->trans('Domyślny kraj do szacunku dostawy', 'Modules.Lowestshipping.Admin'),
                'help' => $this->trans('Dla gości oraz klientów bez zapisanego adresu dostawy.', 'Modules.Lowestshipping.Admin'),
                'choices' => $choices,
                'constraints' => [new NotBlank()],
            ])
            ->add('price_with_tax', SwitchType::class, [
                'label' => $this->trans('Pokazuj koszt z podatkiem', 'Modules.Lowestshipping.Admin'),
                'required' => false,
            ])
            ->add('text_prefix', TextType::class, [
                'label' => $this->trans('Prefiks przed ceną', 'Modules.Lowestshipping.Admin'),
                'help' => $this->trans('Przykład: „Od ” lub „Najtańsza dostawa: ”', 'Modules.Lowestshipping.Admin'),
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'label' => $this->trans('Dodatkowy opis pod ceną', 'Modules.Lowestshipping.Admin'),
                'help' => $this->trans('Opcjonalna notka pod ceną dostawy na stronie produktu.', 'Modules.Lowestshipping.Admin'),
                'required' => false,
            ])
            ->add('enable_product_page', SwitchType::class, [
                'label' => $this->trans('Włącz na karcie produktu', 'Modules.Lowestshipping.Admin'),
                'help' => $this->trans('Wyłączenie ukrywa blok na wszystkich kartach produktów.', 'Modules.Lowestshipping.Admin'),
                'required' => false,
            ]);
    }
}
