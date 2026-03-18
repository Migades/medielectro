<?php

namespace App\Repository;

use App\Entity\Customer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Customer>
 */
class CustomerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class);
    }

    /** Busca por email o crea un nuevo cliente */
    public function findOrCreate(string $email, string $name, string $phone): Customer
    {
        $customer = $this->findOneBy(['email' => $email]);

        if (!$customer) {
            $customer = new Customer();
            $customer->setEmail($email);
        }

        // Actualizar datos con los del último pedido
        $customer->setName($name);
        $customer->setPhone($phone);

        return $customer;
    }
}
