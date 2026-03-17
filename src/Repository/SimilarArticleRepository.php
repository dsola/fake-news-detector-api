<?php

namespace App\Repository;

use App\Entity\SimilarArticle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<SimilarArticle>
 *
 * @method SimilarArticle|null find($id, $lockMode = null, $lockVersion = null)
 * @method SimilarArticle|null findOneBy(array $criteria, array $orderBy = null)
 * @method SimilarArticle[]    findAll()
 * @method SimilarArticle[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SimilarArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SimilarArticle::class);
    }

    public function save(SimilarArticle $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(SimilarArticle $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }

    /**
     * Find similar articles by article ID
     *
     * @param Uuid $articleId
     * @return SimilarArticle[]
     */
    public function findByArticleId(Uuid $articleId): array
    {
        return $this->createQueryBuilder('sa')
            ->andWhere('sa.article = :articleId')
            ->setParameter('articleId', $articleId)
            ->orderBy('sa.score', 'DESC')
            ->addOrderBy('sa.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
