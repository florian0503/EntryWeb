<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\VilleProspection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

/**
 * @extends AbstractCrudController<VilleProspection>
 */
class VilleProspectionCrudController extends AbstractCrudController
{
    public function __construct(private readonly AdminUrlGenerator $adminUrlGenerator)
    {
    }

    public static function getEntityFqcn(): string
    {
        return VilleProspection::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Ville')
            ->setEntityLabelInPlural('Villes de prospection')
            ->setSearchFields(['nom', 'codePostal'])
            ->setDefaultSort(['nom' => 'ASC'])
            ->setPaginatorPageSize(50);
    }

    public function configureActions(Actions $actions): Actions
    {
        $voirProspects = Action::new('voirProspects', 'Voir les prospects', 'fa fa-users')
            ->linkToUrl(fn (VilleProspection $ville): string => $this->getProspectsUrl($ville))
            ->addCssClass('btn btn-info');

        return $actions
            ->add(Crud::PAGE_INDEX, $voirProspects)
            ->add(Crud::PAGE_DETAIL, $voirProspects);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();

        yield TextField::new('nom', 'Ville')
            ->setRequired(true)
            ->formatValue(function (mixed $value, VilleProspection $ville): string {
                return sprintf(
                    '<a href="%s" title="Ouvrir le kanban de cette ville">%s</a>',
                    $this->generateUrl('admin_kanban', ['ville' => $ville->getId()]),
                    htmlspecialchars((string) $value)
                );
            })
            ->renderAsHtml();

        yield TextField::new('codePostal', 'Code postal')
            ->setRequired(true);

        yield Field::new('nombreProspects', 'Nb prospects')
            ->onlyOnIndex()
            ->setTemplatePath('admin/field/nombre_prospects.html.twig');
    }

    private function getProspectsUrl(VilleProspection $ville): string
    {
        return $this->adminUrlGenerator
            ->setController(ProspectCrudController::class)
            ->setAction(Action::INDEX)
            ->set('filters[ville][value]', (string) ($ville->getId() ?? 0))
            ->set('filters[ville][comparison]', '=')
            ->generateUrl();
    }
}
