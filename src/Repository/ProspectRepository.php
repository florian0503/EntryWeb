<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Prospect;
use App\Entity\VilleProspection;
use App\Enum\StatutProspect;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Prospect>
 */
class ProspectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Prospect::class);
    }

    public function findNextPosition(VilleProspection $ville): int
    {
        $result = $this->createQueryBuilder('p')
            ->select('MAX(p.position)')
            ->where('p.ville = :ville')
            ->setParameter('ville', $ville)
            ->getQuery()
            ->getSingleScalarResult();

        return (null === $result) ? 0 : (int) $result + 1;
    }

    /**
     * @return array<int, int> nombre de relances en retard indexe par id de ville
     */
    public function countRelancesEnRetardParVille(): array
    {
        /** @var array<array{villeId: int|string, nb: int|string}> $rows */
        $rows = $this->createQueryBuilder('p')
            ->select('IDENTITY(p.ville) AS villeId, COUNT(p.id) AS nb')
            ->where('p.dateRelance IS NOT NULL')
            ->andWhere('p.dateRelance <= :aujourdhui')
            ->andWhere('p.statut NOT IN (:statutsClos)')
            ->setParameter('aujourdhui', new \DateTimeImmutable('today'))
            ->setParameter('statutsClos', [StatutProspect::Client, StatutProspect::PasInteresse])
            ->groupBy('p.ville')
            ->getQuery()
            ->getArrayResult();

        $compteurs = [];
        foreach ($rows as $row) {
            $compteurs[(int) $row['villeId']] = (int) $row['nb'];
        }

        return $compteurs;
    }
}
