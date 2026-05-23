<?php

namespace App\Form;

use App\Entity\Flower;
use App\Entity\Supplier;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FlowerType extends AbstractType
{
    private const FLOWER_CATEGORY_MAP = [
        'Red Rose' => 'Bouquet Flowers',
        'White Lily' => 'Wedding Flowers',
        'Chrysanthemum' => 'Funeral Flowers',
        'Gerbera Daisy' => 'Bouquet Flowers',
        'Carnation' => 'Bouquet Flowers',
        'Sunflower' => 'Garden Flowers',
        'Tulip' => 'Wedding Flowers',
        "Baby's Breath" => 'Wedding Flowers',
        'Orchid' => 'Exotic Flowers',
        'Anthurium' => 'Tropical Flowers',
        'Sampaguita' => 'Tropical Flowers',
        'Lisianthus' => 'Decorative Plants',
        'Hydrangea' => 'Decorative Plants',
        'Peony' => 'Wedding Flowers',
        'Statice' => 'Seasonal Flowers',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', ChoiceType::class, [
                'label' => 'Flower Name',
                'choices' => array_combine(array_keys(self::FLOWER_CATEGORY_MAP), array_keys(self::FLOWER_CATEGORY_MAP)),
                'placeholder' => 'Select a flower',
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#39A8C9] focus:border-[#39A8C9]',
                ]
            ])
            ->add('category', HiddenType::class, [
                'required' => false,
            ])
            ->add('categoryDisplay', TextType::class, [
                'label' => 'Category',
                'mapped' => false,
                'disabled' => true,
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-700',
                ],
            ])
            ->add('imageFilename', TextType::class, [
                'label' => 'Image Filename',
                'required' => false,
                'help' => 'Filename only, e.g. red_rose.jpg. Images are loaded from public/uploads/flowers/.',
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#39A8C9] focus:border-[#39A8C9]',
                    'placeholder' => 'e.g., red_rose.jpg'
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
                'data' => new \DateTime('+7 days'), // Auto-set to 7 days from today
                'help' => 'Defaults to 7 days from today. Adjust if needed — this determines freshness status and automatic discounts.',
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#39A8C9] focus:border-[#39A8C9]'
                ]
            ])
            // Note: status & freshnessStatus are automatically managed by the system
            ->add('supplier', EntityType::class, [
                'class' => Supplier::class,
                'choice_label' => 'supplierName',
                'label' => 'Supplier',
                'placeholder' => 'Select a supplier',
                'required' => true,
                'attr' => [
                    'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#39A8C9] focus:border-[#39A8C9]'
                ]
            ])
        ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $flower = $event->getData();
            if (!$flower instanceof Flower) {
                return;
            }

            $category = $flower->getCategory();
            if (!$category && $flower->getName() && isset(self::FLOWER_CATEGORY_MAP[$flower->getName()])) {
                $category = self::FLOWER_CATEGORY_MAP[$flower->getName()];
            }

            $form = $event->getForm();
            if ($form->has('categoryDisplay')) {
                $form->get('categoryDisplay')->setData($category);
            }
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }

            $name = $data['name'] ?? null;
            if (!is_string($name) || $name === '') {
                return;
            }

            if (!isset(self::FLOWER_CATEGORY_MAP[$name])) {
                return;
            }

            $data['category'] = self::FLOWER_CATEGORY_MAP[$name];
            $event->setData($data);
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $flower = $event->getData();
            if (!$flower instanceof Flower) {
                return;
            }

            $name = $flower->getName();
            if ($name && !$flower->getCategory() && isset(self::FLOWER_CATEGORY_MAP[$name])) {
                $flower->setCategory(self::FLOWER_CATEGORY_MAP[$name]);
            }
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['flower_category_map'] = self::FLOWER_CATEGORY_MAP;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Flower::class,
        ]);
    }
}
