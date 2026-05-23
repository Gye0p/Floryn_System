<?php

namespace App\Form;

use App\Entity\Reservation;
use App\Entity\ReservationDetail;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;

class ReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('customer', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'fullName',
                'query_builder' => function (\App\Repository\UserRepository $repo) {
                    return $repo->createQueryBuilder('u')
                        ->where('u.roles LIKE :role')
                        ->setParameter('role', '%ROLE_CUSTOMER%')
                        ->orderBy('u.fullName', 'ASC');
                },
                'placeholder' => 'Select a customer',
                'attr' => ['class' => 'form-select'],
                'label' => 'Customer',
            ])
            ->add('pickupDate', DateType::class, [
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-control'],
                'label' => 'Pickup Date',
            ])
            ->add('reservationDetails', CollectionType::class, [
                'entry_type' => ReservationDetailType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => false,
                'attr' => ['class' => 'reservation-details-collection'],
                'constraints' => [
                    new Count(min: 1, minMessage: 'Add at least one flower to the reservation.'),
                ],
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

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $reservation = $event->getData();
            if (!$reservation instanceof Reservation) {
                return;
            }

            $totalAmount = 0.0;
            foreach ($reservation->getReservationDetails() as $detail) {
                if (!$detail instanceof ReservationDetail) {
                    continue;
                }
                $flower = $detail->getFlower();
                if ($flower === null) {
                    continue;
                }
                $price = $flower->getEffectivePrice();
                $qty = $detail->getQuantity() ?? 0;
                $subtotal = $price * $qty;
                $detail->setSubtotal($subtotal);
                $totalAmount += $subtotal;
            }

            if ($totalAmount > 0) {
                $reservation->setTotalAmount($totalAmount);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reservation::class,
            'validation_groups' => function (FormInterface $form): array {
                $reservation = $form->getData();

                return ($reservation instanceof Reservation && $reservation->getId() !== null)
                    ? ['Default', 'Edit']
                    : ['Default', 'Create'];
            },
        ]);
    }
}
