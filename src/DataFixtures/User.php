<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class User extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $admin = new \App\Entity\User();
        $admin->setUsername('admin');
        $admin->setRoles(['ROLE_ADMIN']);
        $hashedPassword = $this->passwordHasher->hashPassword(
            $admin,
            'adminpass'
        );
        $admin->setPassword($hashedPassword);
        $manager->persist($admin);

        $staff = new \App\Entity\User();
        $staff->setUsername('staff');
        $staff->setRoles(['ROLE_STAFF']);
        $hashedPassword = $this->passwordHasher->hashPassword(  
            $staff,
            'staffpass'
        );
        $staff->setPassword($hashedPassword);
        $manager->persist($staff); 

        $manager->flush();
    }
}
