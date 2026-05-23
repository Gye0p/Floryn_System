<?php

namespace App\Form;

use App\Entity\Payment;
use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaymentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];

        $builder
            ->add('paymentDate', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Payment Date',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('amountPaid', null, [
                'label' => 'Amount Paid',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Auto-filled from reservation total',
                    'step' => '0.01',
                    'min' => '0.01',
                ],
                'help' => $isEdit ? null : 'Leave blank to use the reservation total automatically.',
            ])
            ->add('paymentMethod', ChoiceType::class, [
                'choices' => [
                    'Cash' => 'Cash',
                    'Credit Card' => 'Credit Card',
                    'Bank Transfer' => 'Bank Transfer',
                    'GCash' => 'GCash',
                    'PayPal' => 'PayPal',
                ],
                'placeholder' => 'Select payment method',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('referenceNo', null, [
                'label' => 'Reference Number',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'e.g. OR-2026-00123',
                ],
            ])
        ;

        if ($isEdit) {
            $builder->add('status', ChoiceType::class, [
                'choices' => [
                    'Paid' => 'Paid',
                    'Cancelled' => 'Cancelled',
                ],
                'placeholder' => 'Select payment status',
                'attr' => ['class' => 'form-select'],
            ]);
        }

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
                        ->leftJoin('r.payment', 'existingPayment')
                        ->where('r.paymentStatus = :status')
                        ->andWhere('existingPayment.id IS NULL')
                        ->setParameter('status', 'Unpaid')
                        ->orderBy('r.id', 'DESC');
                },
                'placeholder' => 'Select a reservation to pay',
                'required' => true,
                'attr' => ['class' => 'form-select'],
            ]);
        }

        if (!$isEdit) {
            $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
                $payment = $event->getData();
                if (!$payment instanceof Payment) {
                    return;
                }

                $reservation = $payment->getReservation();
                if ($reservation !== null) {
                    $payment->setAmountPaid($reservation->getTotalAmount());
                }
            });
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
