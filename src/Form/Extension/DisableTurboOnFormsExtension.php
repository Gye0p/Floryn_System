<?php

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

/**
 * Prevents Turbo Drive from intercepting admin form POSTs (avoids CSRF/session issues).
 */
final class DisableTurboOnFormsExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        if ($form->getParent() !== null) {
            return;
        }

        $view->vars['attr'] = array_merge(
            $view->vars['attr'] ?? [],
            ['data-turbo' => 'false']
        );
    }
}
