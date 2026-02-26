<?php

namespace App\Form;

use App\Entity\Flower;
use App\Entity\Supplier;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FlowerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Flower Name',
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#39A8C9] focus:border-[#39A8C9]',
                    'placeholder' => 'e.g., Red Rose, White Lily, Bird of Paradise'
                ]
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Category',
                'choices' => [
                    'Bouquet Flowers' => 'Bouquet Flowers',
                    'Tropical Flowers' => 'Tropical Flowers',
                    'Wedding Flowers' => 'Wedding Flowers',
                    'Funeral Flowers' => 'Funeral Flowers',
                    'Seasonal Flowers' => 'Seasonal Flowers',
                    'Potted Plants' => 'Potted Plants',
                    'Garden Flowers' => 'Garden Flowers',
                    'Exotic Flowers' => 'Exotic Flowers',
                    'Indoor Plants' => 'Indoor Plants',
                    'Decorative Plants' => 'Decorative Plants'
                ],
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#39A8C9] focus:border-[#39A8C9]'
                ]
            ])
            ->add('price', MoneyType::class, [
                'label' => 'Price per Unit',
                'currency' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#39A8C9] focus:border-[#39A8C9]',
                    'placeholder' => '0.00'
                ]
            ])
            ->add('stockQuantity', IntegerType::class, [
                'label' => 'Stock Quantity',
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#39A8C9] focus:border-[#39A8C9]',
                    'placeholder' => 'Enter stock quantity',
                    'min' => 0
                ]
            ])
            ->add('dateReceived', DateType::class, [
                'label' => 'Date Received',
                'widget' => 'single_text',
                'data' => new \DateTime(), // Default to today
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#39A8C9] focus:border-[#39A8C9]'
                ]
            ])
            ->add('expiryDate', DateType::class, [
                'label' => 'Expiry Date',
                'widget' => 'single_text',
                'help' => 'When will this flower expire? This determines freshness status and automatic discounts.',
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#39A8C9] focus:border-[#39A8C9]'
                ]
            ])
            // Note: status & freshnessStatus are automatically managed by the system
            ->add('supplier', EntityType::class, [
                'class' => Supplier::class,
                'choice_label' => 'supplierName',
                'label' => 'Supplier',
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#39A8C9] focus:border-[#39A8C9]'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Flower::class,
        ]);
    }
}
