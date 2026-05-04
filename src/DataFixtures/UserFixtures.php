<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $userRepository = $manager->getRepository(User::class);

        if (!$userRepository->findOneBy(['username' => 'admin2'])) {
            $admin = new User();
            $admin->setUsername('admin2');
            $admin->setRoles(['ROLE_ADMIN']);
            $hashedPassword = $this->passwordHasher->hashPassword(
                $admin,
                'adminpass123'
            );
            $admin->setPassword($hashedPassword);
            $admin->setIsApproved(true);
            $admin->setIsVerified(true);
            $admin->setCreatedAt(new \DateTime());
            $manager->persist($admin);
        }

        if (!$userRepository->findOneBy(['username' => 'staff2'])) {
            $staff = new User();
            $staff->setUsername('staff2');
            $staff->setRoles(['ROLE_STAFF']);
            $hashedPassword = $this->passwordHasher->hashPassword(
                $staff,
                'staffpass123'
            );
            $staff->setPassword($hashedPassword);
            $staff->setIsApproved(true);
            $staff->setIsVerified(true);
            $staff->setCreatedAt(new \DateTime());
            $manager->persist($staff);
        }

        $manager->flush();
    }
}
