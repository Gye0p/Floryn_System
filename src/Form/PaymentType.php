<?php

namespace App\Form;

use App\Entity\Payment;
use App\Entity\Reservation;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaymentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('paymentDate')
            ->add('amountPaid')
            ->add('paymentMethod', ChoiceType::class, [
                'choices' => [
                    'Cash' => 'Cash',
                    'Credit Card' => 'Credit Card',
                    'Debit Card' => 'Debit Card',
                    'GCash' => 'GCash',
                    'PayMaya' => 'PayMaya',
                    'Bank Transfer' => 'Bank Transfer',
                ],
                'placeholder' => 'Select payment method',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('referenceNo')
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Pending' => 'Pending',
                    'Completed' => 'Completed',
                    'Failed' => 'Failed',
                    'Refunded' => 'Refunded',
                ],
                'placeholder' => 'Select payment status',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('reservation', EntityType::class, [
                'class' => Reservation::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Payment::class,
        ]);
    }
}
