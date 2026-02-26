<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'Username',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a username']),
                    new Length([
                        'min' => 3,
                        'max' => 180,
                        'minMessage' => 'Username must be at least {{ limit }} characters',
                        'maxMessage' => 'Username cannot be longer than {{ limit }} characters',
                    ]),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Password',
                'mapped' => false,
                'required' => !$options['is_edit'],
                'attr' => ['class' => 'form-control'],
                'constraints' => $options['is_edit'] ? [] : [
                    new NotBlank(['message' => 'Please enter a password']),
                    new Length([
                        'min' => 6,
                        'max' => 4096,
                        'minMessage' => 'Password must be at least {{ limit }} characters',
                    ]),
                ],
                'help' => $options['is_edit'] ? 'Leave blank to keep current password' : null,
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Role',
                'choices' => [
                    'Admin' => 'ROLE_ADMIN',
                    'Manager' => 'ROLE_MANAGER',
                    'Staff' => 'ROLE_STAFF',
                ],
                'multiple' => true,
                'expanded' => true,
                'attr' => ['class' => 'form-check'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
        ]);
    }
}
