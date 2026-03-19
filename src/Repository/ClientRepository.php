<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Client>
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    public function save(Client $client): Client
    {
        $this->getEntityManager()->persist($client);
        $this->getEntityManager()->flush();

        return $client;
    }

    public function findOneByClientId(string $clientId): ?Client
    {
        return $this->findOneBy(['clientId' => $clientId]);
    }
}
