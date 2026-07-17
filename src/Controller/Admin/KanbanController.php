<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Prospect;
use App\Enum\StatutProspect;
use App\Repository\ProspectRepository;
use App\Repository\VilleProspectionRepository;
use App\Service\GeocodingService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class KanbanController extends AbstractController
{
    private const MAX_GEOCODE_PAR_CHARGEMENT = 10;

    #[Route('/admin/kanban', name: 'admin_kanban', methods: ['GET'])]
    public function index(
        Request $request,
        VilleProspectionRepository $villeRepository,
        ProspectRepository $prospectRepository,
        AdminUrlGenerator $urlGenerator,
        GeocodingService $geocodingService,
        EntityManagerInterface $em,
    ): Response {
        $villes = $villeRepository->findBy([], ['nom' => 'ASC']);
        $relancesEnRetard = $prospectRepository->countRelancesEnRetardParVille();

        $villeId = $request->query->getInt('ville');
        $villeActive = null;
        foreach ($villes as $ville) {
            if ($ville->getId() === $villeId) {
                $villeActive = $ville;

                break;
            }
        }
        if (null === $villeActive && [] !== $villes) {
            $villeActive = $villes[0];
        }

        $colonnes = [];
        foreach (StatutProspect::cases() as $statut) {
            $colonnes[$statut->value] = [
                'statut' => $statut,
                'prospects' => [],
            ];
        }

        $editUrls = [];
        $fiches = [];
        if (null !== $villeActive) {
            $geocodes = 0;
            foreach ($villeActive->getProspects() as $prospect) {
                if (null === $prospect->getLatitude() && $geocodes < self::MAX_GEOCODE_PAR_CHARGEMENT) {
                    $geocodingService->geocodeProspect($prospect);
                    ++$geocodes;
                }
            }
            if ($geocodes > 0) {
                $em->flush();
            }

            foreach ($villeActive->getProspects() as $prospect) {
                $colonnes[$prospect->getStatut()->value]['prospects'][] = $prospect;
                $editUrl = $urlGenerator
                    ->setController(ProspectCrudController::class)
                    ->setAction(Action::EDIT)
                    ->setEntityId($prospect->getId())
                    ->set('retour', 'kanban')
                    ->generateUrl();
                $editUrls[$prospect->getId()] = $editUrl;
                $fiches[$prospect->getId()] = [
                    'nomBoite' => $prospect->getNomBoite(),
                    'adresse' => $prospect->getAdresse(),
                    'wazeUrl' => $prospect->getWazeUrl(),
                    'telephone' => $prospect->getTelephone(),
                    'horaires' => $prospect->getHoraires(),
                    'statutLabel' => $prospect->getStatut()->label(),
                    'statutValue' => $prospect->getStatut()->value,
                    'siteWebActuel' => $prospect->getSiteWebActuel(),
                    'notes' => $prospect->getNotes(),
                    'dateContact' => $prospect->getDateContact()?->format('d/m/Y'),
                    'dateRelance' => $prospect->getDateRelance()?->format('d/m/Y'),
                    'dateRelanceIso' => $prospect->getDateRelance()?->format('Y-m-d'),
                    'relanceEnRetard' => $prospect->isRelanceEnRetard(),
                    'lat' => $prospect->getLatitude(),
                    'lng' => $prospect->getLongitude(),
                    'editUrl' => $editUrl,
                ];
            }
        }

        $newProspectUrl = $urlGenerator
            ->setController(ProspectCrudController::class)
            ->setAction(Action::NEW)
            ->set('ville', $villeActive?->getId())
            ->set('retour', 'kanban')
            ->generateUrl();

        $statuts = array_map(
            static fn (StatutProspect $statut): array => [
                'value' => $statut->value,
                'label' => $statut->label(),
            ],
            StatutProspect::cases()
        );

        return $this->render('admin/kanban.html.twig', [
            'villes' => $villes,
            'villeActive' => $villeActive,
            'colonnes' => $colonnes,
            'editUrls' => $editUrls,
            'relancesEnRetard' => $relancesEnRetard,
            'newProspectUrl' => $newProspectUrl,
            'fichesJson' => json_encode($fiches, \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_HEX_AMP | \JSON_THROW_ON_ERROR),
            'statutsJson' => json_encode($statuts, \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_HEX_AMP | \JSON_THROW_ON_ERROR),
        ]);
    }

    #[Route('/admin/kanban/prospect/{id}/statut', name: 'admin_kanban_statut', methods: ['POST'])]
    public function updateStatut(
        Prospect $prospect,
        Request $request,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var array{statut?: string, _token?: string} $data */
        $data = json_decode($request->getContent(), true) ?: [];

        if (!$this->isCsrfTokenValid('kanban', $data['_token'] ?? null)) {
            return new JsonResponse(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $statut = StatutProspect::tryFrom($data['statut'] ?? '');
        if (null === $statut) {
            return new JsonResponse(['error' => 'Statut inconnu.'], Response::HTTP_BAD_REQUEST);
        }

        $prospect->setStatut($statut);
        if (StatutProspect::AContacter !== $statut) {
            $prospect->setDateContact(new \DateTime('today'));
        }
        $em->flush();

        return new JsonResponse([
            'ok' => true,
            'statut' => $statut->value,
            'label' => $statut->label(),
            'dateContact' => $prospect->getDateContact()?->format('d/m/Y'),
            'relanceEnRetard' => $prospect->isRelanceEnRetard(),
        ]);
    }

    #[Route('/admin/kanban/prospect/{id}/note', name: 'admin_kanban_note', methods: ['POST'])]
    public function ajouterNote(
        Prospect $prospect,
        Request $request,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var array{note?: string, _token?: string} $data */
        $data = json_decode($request->getContent(), true) ?: [];

        if (!$this->isCsrfTokenValid('kanban', $data['_token'] ?? null)) {
            return new JsonResponse(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $note = trim($data['note'] ?? '');
        if ('' === $note) {
            return new JsonResponse(['error' => 'Note vide.'], Response::HTTP_BAD_REQUEST);
        }

        $ligne = sprintf('[%s] %s', (new \DateTimeImmutable())->format('d/m/Y'), $note);
        $notes = $prospect->getNotes();
        $prospect->setNotes(null === $notes || '' === $notes ? $ligne : $notes."\n".$ligne);
        $em->flush();

        return new JsonResponse([
            'ok' => true,
            'notes' => $prospect->getNotes(),
        ]);
    }

    #[Route('/admin/kanban/prospect/{id}/relance', name: 'admin_kanban_relance', methods: ['POST'])]
    public function updateRelance(
        Prospect $prospect,
        Request $request,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var array{date?: string, _token?: string} $data */
        $data = json_decode($request->getContent(), true) ?: [];

        if (!$this->isCsrfTokenValid('kanban', $data['_token'] ?? null)) {
            return new JsonResponse(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $dateStr = trim($data['date'] ?? '');
        if ('' === $dateStr) {
            $prospect->setDateRelance(null);
        } else {
            $date = \DateTime::createFromFormat('Y-m-d', $dateStr);
            if (false === $date) {
                return new JsonResponse(['error' => 'Date invalide.'], Response::HTTP_BAD_REQUEST);
            }
            $date->setTime(0, 0);
            $prospect->setDateRelance($date);
        }
        $em->flush();

        return new JsonResponse([
            'ok' => true,
            'dateRelance' => $prospect->getDateRelance()?->format('d/m/Y'),
            'dateRelanceIso' => $prospect->getDateRelance()?->format('Y-m-d'),
            'relanceEnRetard' => $prospect->isRelanceEnRetard(),
        ]);
    }
}
