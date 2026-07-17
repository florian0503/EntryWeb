<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Client;
use App\Entity\ContactMessage;
use App\Entity\Devis;
use App\Entity\NewsletterSubscriber;
use App\Entity\Realisation;
use App\Entity\VilleProspection;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    private string $lockFile;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        string $projectDir,
    ) {
        $this->lockFile = $projectDir.'/var/maintenance.lock';
    }

    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);

        return $this->redirect($adminUrlGenerator->setController(RealisationCrudController::class)->generateUrl());
    }

    #[Route('/admin/maintenance', name: 'admin_maintenance', methods: ['GET'])]
    public function maintenance(): Response
    {
        return $this->render('admin/maintenance.html.twig', [
            'maintenance_active' => file_exists($this->lockFile),
        ]);
    }

    #[Route('/admin/maintenance/toggle', name: 'admin_maintenance_toggle', methods: ['POST'])]
    public function maintenanceToggle(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('maintenance_toggle', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        if (file_exists($this->lockFile)) {
            unlink($this->lockFile);
            $this->addFlash('success', 'Mode maintenance désactivé.');
        } else {
            touch($this->lockFile);
            $this->addFlash('warning', 'Mode maintenance activé.');
        }

        return $this->redirectToRoute('admin_maintenance');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('EntryWeb Admin')
            ->setFaviconPath('favicon-32.png');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::section('Prospection');
        yield MenuItem::linkToCrud('Villes', 'fa fa-map-marker', VilleProspection::class);
        yield MenuItem::section('Clients');
        yield MenuItem::linkToCrud('Base de données clients', 'fa fa-users', Client::class);
        yield MenuItem::section('Devis');
        yield MenuItem::linkToUrl('Créer un devis', 'fa fa-plus-circle', '/devis/builder');
        yield MenuItem::linkToCrud('Tous les devis', 'fa fa-file-invoice', Devis::class);
        yield MenuItem::section('Contenu');
        yield MenuItem::linkToCrud('Messages', 'fa fa-envelope', ContactMessage::class);
        yield MenuItem::linkToCrud('Newsletter', 'fa fa-paper-plane', NewsletterSubscriber::class);
        yield MenuItem::linkToCrud('Réalisations', 'fa fa-images', Realisation::class);
        yield MenuItem::linkToCrud('Articles Blog', 'fa fa-newspaper', Article::class);
        yield MenuItem::linkToCrud('Catégories', 'fa fa-folder', Category::class);
        yield MenuItem::section('');
        yield MenuItem::linkToRoute('Mode maintenance', 'fa fa-tools', 'admin_maintenance');
        yield MenuItem::linkToRoute('Voir le site', 'fa fa-eye', 'app_home');
        yield MenuItem::linkToLogout('Déconnexion', 'fa fa-sign-out');
    }
}
