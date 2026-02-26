<?php

namespace App\Form;

use App\Entity\Payment;
use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaymentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];

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
                    'Paid' => 'Paid',
                    'Cancelled' => 'Cancelled',
                ],
                'placeholder' => 'Select payment status',
                'attr' => ['class' => 'form-select'],
            ])
        ;

        if ($isEdit) {
            $builder->add('reservation', EntityType::class, [
                'class' => Reservation::class,
                'choice_label' => function (Reservation $r) {
                    return sprintf('Reservation #%d — %s (₱%s)',
                        $r->getId(),
                        $r->getCustomer()?->getFullName() ?? 'N/A',
                        number_format($r->getTotalAmount(), 2)
                    );
                },
                'disabled' => true,
                'attr' => ['class' => 'form-select'],
            ]);
        } else {
            $builder->add('reservation', EntityType::class, [
                'class' => Reservation::class,
                'choice_label' => function (Reservation $r) {
                    return sprintf('Reservation #%d — %s (₱%s)',
                        $r->getId(),
                        $r->getCustomer()?->getFullName() ?? 'N/A',
                        number_format($r->getTotalAmount(), 2)
                    );
                },
                'query_builder' => function (ReservationRepository $repo) {
                    return $repo->createQueryBuilder('r')
                        ->where('r.paymentStatus = :status')
                        ->setParameter('status', 'Unpaid')
                        ->orderBy('r.id', 'DESC');
                },
                'placeholder' => 'Select a reservation to pay',
                'attr' => ['class' => 'form-select'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Payment::class,
            'is_edit' => false,
        ]);
    }
}
