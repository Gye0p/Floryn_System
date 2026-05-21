<?php

namespace App\Command;

use App\Entity\Flower;
use App\Entity\Supplier;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed-production',
    description: 'Seeds production-safe starter users and flower data.',
)]
class SeedProductionDataCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->seedUsers($output);
        $this->seedFlowers($output);

        $this->entityManager->flush();
        $output->writeln('<info>Production seed data is ready.</info>');

        return Command::SUCCESS;
    }

    private function seedUsers(OutputInterface $output): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);

        $adminUsername = $_ENV['SEED_ADMIN_USERNAME'] ?? 'admin2';
        $adminPassword = $_ENV['SEED_ADMIN_PASSWORD'] ?? 'adminpass123';
        $staffUsername = $_ENV['SEED_STAFF_USERNAME'] ?? 'staff2';
        $staffPassword = $_ENV['SEED_STAFF_PASSWORD'] ?? 'staffpass123';

        if (!$userRepository->findOneBy(['username' => $adminUsername])) {
            $admin = $this->createUser($adminUsername, $adminPassword, ['ROLE_ADMIN']);
            $this->entityManager->persist($admin);
            $output->writeln(sprintf('<comment>Created admin user: %s</comment>', $adminUsername));
        }

        if (!$userRepository->findOneBy(['username' => $staffUsername])) {
            $staff = $this->createUser($staffUsername, $staffPassword, ['ROLE_STAFF']);
            $this->entityManager->persist($staff);
            $output->writeln(sprintf('<comment>Created staff user: %s</comment>', $staffUsername));
        }
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $username, string $plainPassword, array $roles): User
    {
        $user = (new User())
            ->setUsername($username)
            ->setRoles($roles)
            ->setIsApproved(true)
            ->setIsVerified(true)
            ->setCreatedAt(new \DateTime());

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        return $user;
    }

    private function seedFlowers(OutputInterface $output): void
    {
        $supplier = $this->entityManager->getRepository(Supplier::class)->findOneBy([]);

        if (!$supplier instanceof Supplier) {
            $supplier = (new Supplier())
                ->setSupplierName('Default Flower Supplier')
                ->setContactPerson('Maria Santos')
                ->setPhone('+639171234567')
                ->setEmail('supplier@example.com')
                ->setAddress('Dangwa Flower Market, Sampaloc, Manila')
                ->setDeliverySchedule('Mon-Wed-Fri')
                ->setDateAdded(new \DateTime());

            $this->entityManager->persist($supplier);
            $output->writeln('<comment>Created default supplier.</comment>');
        }

        $today = new \DateTimeImmutable('today');
        $flowerRepository = $this->entityManager->getRepository(Flower::class);

        $flowers = [
            ['Red Rose', 'Bouquet Flowers', 120.00, 40, 7, 'red_rose.jpg'],
            ['White Lily', 'Wedding Flowers', 150.00, 24, 6, 'white_lily.jpg'],
            ['Chrysanthemum', 'Funeral Flowers', 90.00, 35, 10, 'chrysanthemum.jpg'],
            ['Gerbera Daisy', 'Bouquet Flowers', 85.00, 30, 8, 'gerbera_daisy.jpg'],
            ['Carnation', 'Bouquet Flowers', 75.00, 32, 10, 'carnation.jpg'],
            ['Sunflower', 'Garden Flowers', 95.00, 28, 7, 'sunflower.jpg'],
            ['Tulip', 'Wedding Flowers', 170.00, 18, 6, 'tulip.jpg'],
            ["Baby's Breath", 'Wedding Flowers', 110.00, 20, 9, 'babys_breath.jpg'],
            ['Orchid', 'Exotic Flowers', 180.00, 15, 9, 'orchid.jpg'],
            ['Anthurium', 'Tropical Flowers', 160.00, 16, 11, 'anthurium.jpg'],
            ['Sampaguita', 'Tropical Flowers', 60.00, 50, 4, 'sampaguita.jpg'],
            ['Lisianthus', 'Decorative Plants', 165.00, 14, 6, 'lisianthus.jpg'],
            ['Hydrangea', 'Decorative Plants', 200.00, 12, 7, 'hydrangea.jpg'],
            ['Peony', 'Wedding Flowers', 240.00, 10, 5, 'peony.jpg'],
            ['Statice', 'Seasonal Flowers', 130.00, 22, 8, 'statice.jpg'],
        ];

        foreach ($flowers as [$name, $category, $price, $stock, $expiresIn, $image]) {
            if ($flowerRepository->findOneBy(['name' => $name])) {
                continue;
            }

            $flower = (new Flower())
                ->setName($name)
                ->setCategory($category)
                ->setPrice($price)
                ->setStockQuantity($stock)
                ->setFreshnessStatus('Fresh')
                ->setDateReceived(new \DateTime())
                ->setExpiryDate(new \DateTime($today->modify("+{$expiresIn} days")->format('Y-m-d')))
                ->setStatus('Available')
                ->setImageFilename($image)
                ->setSupplier($supplier);

            $this->entityManager->persist($flower);
        }
    }
}
