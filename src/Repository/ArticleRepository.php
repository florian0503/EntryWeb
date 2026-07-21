<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
     * @return Article[]
     */
    public function findPublished(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.isPublished = true')
            ->orderBy('a.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Article[]
     */
    public function findPublishedPaginated(int $offset, int $limit, ?string $query = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.isPublished = true')
            ->orderBy('a.publishedAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        if (null !== $query) {
            $qb->andWhere('a.title LIKE :query OR a.excerpt LIKE :query OR a.content LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        return $qb->getQuery()->getResult();
    }

    public function countPublished(?string $query = null): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.isPublished = true');

        if (null !== $query) {
            $qb->andWhere('a.title LIKE :query OR a.excerpt LIKE :query OR a.content LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findOnePublishedBySlug(string $slug): ?Article
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.isPublished = true')
            ->andWhere('a.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
