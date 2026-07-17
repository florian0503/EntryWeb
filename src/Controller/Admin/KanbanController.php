<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Prospect;
use App\Enum\StatutProspect;
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
        AdminUrlGenerator $urlGenerator,
        GeocodingService $geocodingService,
        EntityManagerInterface $em,
    ): Response {
        $villes = $villeRepository->findBy([], ['nom' => 'ASC']);

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

        return $this->render('admin/kanban.html.twig', [
            'villes' => $villes,
            'villeActive' => $villeActive,
            'colonnes' => $colonnes,
            'editUrls' => $editUrls,
            'newProspectUrl' => $newProspectUrl,
            'fichesJson' => json_encode($fiches, \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_HEX_AMP | \JSON_THROW_ON_ERROR),
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

        if (!$this->isCsrfTokenValid('kanban_statut', $data['_token'] ?? null)) {
            return new JsonResponse(['error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $statut = StatutProspect::tryFrom($data['statut'] ?? '');
        if (null === $statut) {
            return new JsonResponse(['error' => 'Statut inconnu.'], Response::HTTP_BAD_REQUEST);
        }

        $prospect->setStatut($statut);
        $em->flush();

        return new JsonResponse([
            'ok' => true,
            'statut' => $statut->value,
            'label' => $statut->label(),
        ]);
    }
}
