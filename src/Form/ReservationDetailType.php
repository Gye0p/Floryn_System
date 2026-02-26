<?php

namespace App\Form;

use App\Entity\Flower;
use App\Entity\ReservationDetail;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;

class ReservationDetailType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('flower', EntityType::class, [
                'class' => Flower::class,
                'choice_label' => function (Flower $flower) {
                    $price = $flower->getDiscountPrice() > 0 ? $flower->getDiscountPrice() : $flower->getPrice();
                    return sprintf('%s - ₱%.2f (Stock: %d)', 
                        $flower->getName(), 
                        $price, 
                        $flower->getStockQuantity()
                    );
                },
                'placeholder' => 'Select a flower',
                'attr' => ['class' => 'form-select flower-select'],
                'label' => 'Flower',
                'constraints' => [
                    new NotBlank(['message' => 'Please select a flower.']),
                ],
            ])
            ->add('quantity', IntegerType::class, [
                'attr' => ['class' => 'form-control quantity-input', 'min' => 1],
                'label' => 'Quantity',
                'constraints' => [
                    new NotBlank(['message' => 'Quantity is required.']),
                    new GreaterThan(['value' => 0, 'message' => 'Quantity must be at least 1.']),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReservationDetail::class,
        ]);
    }
}
