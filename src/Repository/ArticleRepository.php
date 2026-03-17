<?php

namespace App\Repository;

use App\Entity\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Article>
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    /**
     * Save an article to the database
     */
    public function save(Article $article): Article
    {
        $this->getEntityManager()->persist($article);
        $this->getEntityManager()->flush();
        
        return $article;
    }

    public function findWithVerifications(Uuid $id): ?Article
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.verifications', 'v')
            ->addSelect('v')
            ->andWhere('a.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
