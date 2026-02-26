<?php

namespace App\Form;

use App\Entity\Supplier;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SupplierType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('supplierName')
            ->add('contactPerson')
            ->add('phone')
            ->add('email')
            ->add('address')
            ->add('deliverySchedule', ChoiceType::class, [
                'choices' => [
                    'Daily' => 'Daily',
                    'Monday, Wednesday, Friday' => 'Monday, Wednesday, Friday',
                    'Tuesday, Thursday, Saturday' => 'Tuesday, Thursday, Saturday',
                    'Weekly (Mondays)' => 'Weekly (Mondays)',
                    'Bi-Weekly' => 'Bi-Weekly',
                    'Monthly' => 'Monthly',
                    'On Demand' => 'On Demand',
                ],
                'placeholder' => 'Select delivery schedule',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('dateAdded')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Supplier::class,
        ]);
    }
}
