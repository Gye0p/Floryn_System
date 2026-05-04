<?php

namespace App\DataFixtures;

use App\Entity\Flower;
use App\Entity\Supplier;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class FlowerFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $supplier = $manager->getRepository(Supplier::class)->findOneBy([]);

        if (!$supplier instanceof Supplier) {
            $supplier = new Supplier();
            $supplier->setSupplierName('Default Flower Supplier');
            $supplier->setContactPerson('Maria Santos');
            $supplier->setPhone('+639171234567');
            $supplier->setEmail('supplier@example.com');
            $supplier->setAddress('Dangwa Flower Market, Sampaloc, Manila');
            $supplier->setDeliverySchedule('Mon-Wed-Fri');
            $supplier->setDateAdded(new \DateTime());
            $manager->persist($supplier);
        }

        $today = new \DateTimeImmutable('today');

        $flowers = [
            ['name' => 'Red Rose', 'category' => 'Bouquet Flowers', 'price' => 120.00, 'stock' => 40, 'expiresIn' => 7, 'image' => 'red_rose.jpg'],
            ['name' => 'White Lily', 'category' => 'Wedding Flowers', 'price' => 150.00, 'stock' => 24, 'expiresIn' => 6, 'image' => 'white_lily.jpg'],
            ['name' => 'Chrysanthemum', 'category' => 'Funeral Flowers', 'price' => 90.00, 'stock' => 35, 'expiresIn' => 10, 'image' => 'chrysanthemum.jpg'],
            ['name' => 'Gerbera Daisy', 'category' => 'Bouquet Flowers', 'price' => 85.00, 'stock' => 30, 'expiresIn' => 8, 'image' => 'gerbera_daisy.jpg'],
            ['name' => 'Carnation', 'category' => 'Bouquet Flowers', 'price' => 75.00, 'stock' => 32, 'expiresIn' => 10, 'image' => 'carnation.jpg'],
            ['name' => 'Sunflower', 'category' => 'Garden Flowers', 'price' => 95.00, 'stock' => 28, 'expiresIn' => 7, 'image' => 'sunflower.jpg'],
            ['name' => 'Tulip', 'category' => 'Wedding Flowers', 'price' => 170.00, 'stock' => 18, 'expiresIn' => 6, 'image' => 'tulip.jpg'],
            ['name' => 'Baby\'s Breath', 'category' => 'Wedding Flowers', 'price' => 110.00, 'stock' => 20, 'expiresIn' => 9, 'image' => 'babys_breath.jpg'],
            ['name' => 'Orchid', 'category' => 'Exotic Flowers', 'price' => 180.00, 'stock' => 15, 'expiresIn' => 9, 'image' => 'orchid.jpg'],
            ['name' => 'Anthurium', 'category' => 'Tropical Flowers', 'price' => 160.00, 'stock' => 16, 'expiresIn' => 11, 'image' => 'anthurium.jpg'],
            ['name' => 'Sampaguita', 'category' => 'Tropical Flowers', 'price' => 60.00, 'stock' => 50, 'expiresIn' => 4, 'image' => 'sampaguita.jpg'],
            ['name' => 'Lisianthus', 'category' => 'Decorative Plants', 'price' => 165.00, 'stock' => 14, 'expiresIn' => 6, 'image' => 'lisianthus.jpg'],
            ['name' => 'Hydrangea', 'category' => 'Decorative Plants', 'price' => 200.00, 'stock' => 12, 'expiresIn' => 7, 'image' => 'hydrangea.jpg'],
            ['name' => 'Peony', 'category' => 'Wedding Flowers', 'price' => 240.00, 'stock' => 10, 'expiresIn' => 5, 'image' => 'peony.jpg'],
            ['name' => 'Statice', 'category' => 'Seasonal Flowers', 'price' => 130.00, 'stock' => 22, 'expiresIn' => 8, 'image' => 'statice.jpg'],
        ];

        foreach ($flowers as $item) {
            $flower = new Flower();
            $flower->setName($item['name']);
            $flower->setCategory($item['category']);
            $flower->setPrice((float) $item['price']);
            $flower->setStockQuantity((int) $item['stock']);
            $flower->setFreshnessStatus('Fresh');
            $flower->setDateReceived(new \DateTime());
            $flower->setExpiryDate(new \DateTime($today->modify('+' . $item['expiresIn'] . ' days')->format('Y-m-d')));
            $flower->setStatus('Available');
            $flower->setImageFilename($item['image']);
            $flower->setSupplier($supplier);

            $manager->persist($flower);
        }

        $manager->flush();
    }
}
