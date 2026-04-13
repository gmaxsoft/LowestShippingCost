<?php
/**
 * Back-office route for lowestshipping module configuration.
 *
 * @author    Maxsoft
 * @copyright 2007-2026 Maxsoft
 * @license   https://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

namespace PrestaShop\Module\Lowestshipping\Controller;

use PrestaShop\PrestaShop\Core\Form\FormHandlerInterface;
use PrestaShopBundle\Controller\Admin\PrestaShopAdminController;
use PrestaShopBundle\Security\Attribute\AdminSecurity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class LowestShippingConfigurationController extends PrestaShopAdminController
{
    #[AdminSecurity("is_granted('update', 'AdminModules')")]
    public function index(
        Request $request,
        #[Autowire(service: 'prestashop.module.lowestshipping.form.configuration_form_handler')]
        FormHandlerInterface $configurationFormHandler,
    ): Response {
        $form = $configurationFormHandler->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $errors = $configurationFormHandler->save($form->getData());

            if ($errors === []) {
                $this->addFlash('success', $this->trans('Ustawienia zapisane.', [], 'Modules.Lowestshipping.Admin'));

                return $this->redirectToRoute('lowestshipping_configuration');
            }

            foreach ($errors as $error) {
                $message = match ($error) {
                    'Default country is required.' => $this->trans(
                        'Wybierz domyślny kraj.',
                        [],
                        'Modules.Lowestshipping.Admin',
                    ),
                    'Invalid configuration payload.' => $this->trans(
                        'Przesłane dane są nieprawidłowe.',
                        [],
                        'Modules.Lowestshipping.Admin',
                    ),
                    default => $error,
                };
                $this->addFlash('error', $message);
            }
        }

        return $this->render('@Modules/lowestshipping/views/templates/admin/configure.html.twig', [
            'configurationForm' => $form->createView(),
        ]);
    }
}
