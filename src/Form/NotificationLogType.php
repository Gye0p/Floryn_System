<?php

namespace App\Form;

use App\Entity\Customer;
use App\Entity\NotificationLog;
use App\Entity\Reservation;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NotificationLogType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('message')
            ->add('channel', ChoiceType::class, [
                'choices' => [
                    'SMS' => 'SMS',
                    'Email' => 'Email',
                    'Phone Call' => 'Phone Call',
                    'WhatsApp' => 'WhatsApp',
                    'Messenger' => 'Messenger',
                ],
                'placeholder' => 'Select notification channel',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('dateSent')
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Sent' => 'Sent',
                    'Pending' => 'Pending',
                    'Failed' => 'Failed',
                    'Delivered' => 'Delivered',
                ],
                'placeholder' => 'Select notification status',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('customer', EntityType::class, [
                'class' => Customer::class,
                'choice_label' => 'id',
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
            'data_class' => NotificationLog::class,
        ]);
    }
}
