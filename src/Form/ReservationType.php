<?php

namespace App\Form;

use App\Entity\Customer;
use App\Entity\Reservation;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('customer', EntityType::class, [
                'class' => Customer::class,
                'choice_label' => 'fullName',
                'placeholder' => 'Select a customer',
                'attr' => ['class' => 'form-select'],
                'label' => 'Customer',
            ])
            ->add('pickupDate', DateType::class, [
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'label' => 'Pickup Date',
            ])
            ->add('reservationDetails', CollectionType::class, [
                'entry_type' => ReservationDetailType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => 'Flowers',
                'attr' => ['class' => 'reservation-details-collection'],
            ])
            ->add('paymentStatus', ChoiceType::class, [
                'choices' => [
                    'Unpaid' => 'Unpaid',
                    'Paid' => 'Paid',
                ],
                'placeholder' => 'Select payment status',
                'attr' => ['class' => 'form-select'],
                'label' => 'Payment Status',
            ])
            ->add('reservationStatus', ChoiceType::class, [
                'choices' => [
                    'Pending' => 'Pending',
                    'Confirmed' => 'Confirmed',
                    'Ready for Pickup' => 'Ready for Pickup',
                    'Completed' => 'Completed',
                    'Cancelled' => 'Cancelled',
                ],
                'placeholder' => 'Select reservation status',
                'attr' => ['class' => 'form-select'],
                'label' => 'Reservation Status',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reservation::class,
        ]);
    }
}
