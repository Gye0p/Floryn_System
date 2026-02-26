<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Current Password',
                'mapped' => false,
                'attr' => ['class' => 'form-control', 'autocomplete' => 'current-password'],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter your current password']),
                ],
            ])
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'New Password',
                    'attr' => ['class' => 'form-control', 'autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'Confirm New Password',
                    'attr' => ['class' => 'form-control', 'autocomplete' => 'new-password'],
                ],
                'invalid_message' => 'The password fields must match.',
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a new password']),
                    new Length([
                        'min' => 6,
                        'max' => 4096,
                        'minMessage' => 'Password must be at least {{ limit }} characters',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
