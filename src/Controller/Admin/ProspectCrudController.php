<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Prospect;
use App\Entity\VilleProspection;
use App\Enum\StatutProspect;
use App\Repository\ProspectRepository;
use App\Service\GeocodingService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractCrudController<Prospect>
 */
class ProspectCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ProspectRepository $prospectRepository,
        private readonly EntityManagerInterface $em,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly GeocodingService $geocodingService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Prospect::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Prospect')
            ->setEntityLabelInPlural('Prospects')
            ->setSearchFields(['nomBoite', 'adresse', 'telephone', 'notes'])
            ->setDefaultSort(['position' => 'ASC'])
            ->setPaginatorPageSize(50);
    }

    public function configureFilters(Filters $filters): Filters
    {
        $statutChoices = [];
        foreach (StatutProspect::cases() as $statut) {
            $statutChoices[$statut->label()] = $statut->value;
        }

        return $filters
            ->add(EntityFilter::new('ville', 'Ville'))
            ->add(ChoiceFilter::new('statut', 'Statut')->setChoices($statutChoices));
    }

    public function configureActions(Actions $actions): Actions
    {
        $monter = Action::new('monter', '', 'fa fa-arrow-up')
            ->linkToCrudAction('monter')
            ->addCssClass('btn btn-sm btn-outline-secondary')
            ->setLabel('');

        $descendre = Action::new('descendre', '', 'fa fa-arrow-down')
            ->linkToCrudAction('descendre')
            ->addCssClass('btn btn-sm btn-outline-secondary')
            ->setLabel('');

        return $actions
            ->add(Crud::PAGE_INDEX, $monter)
            ->add(Crud::PAGE_INDEX, $descendre);
    }

    public function configureFields(string $pageName): iterable
    {
        $statutChoices = [];
        foreach (StatutProspect::cases() as $statut) {
            $statutChoices[$statut->label()] = $statut;
        }

        yield IdField::new('id')->onlyOnIndex();

        yield IntegerField::new('position', '#')
            ->setColumns(1)
            ->onlyOnIndex();

        yield TextField::new('nomBoite', 'Nom de la boite')
            ->setRequired(true);

        yield TextField::new('adresse', 'Adresse')
            ->setRequired(true)
            ->formatValue(static function (mixed $value): string {
                $str = (string) $value;
                $url = 'https://waze.com/ul?q='.urlencode($str);

                return sprintf('<a href="%s" target="_blank" title="Ouvrir dans Waze">📍 %s</a>', $url, htmlspecialchars($str));
            })
            ->renderAsHtml()
            ->onlyOnIndex();

        yield TextField::new('adresse', 'Adresse')
            ->setRequired(true)
            ->onlyOnForms();

        yield TextField::new('telephone', 'Téléphone')
            ->setRequired(false)
            ->formatValue(static function (mixed $value): string {
                if (null === $value || '' === $value) {
                    return '—';
                }
                $str = (string) $value;

                return sprintf('<a href="tel:%s">📞 %s</a>', htmlspecialchars($str), htmlspecialchars($str));
            })
            ->renderAsHtml()
            ->onlyOnIndex();

        yield TextField::new('telephone', 'Téléphone')
            ->setRequired(false)
            ->onlyOnForms();

        yield TextField::new('horaires', 'Horaires')
            ->setRequired(false)
            ->hideOnIndex();

        yield ChoiceField::new('statut', 'Statut')
            ->setChoices($statutChoices)
            ->renderAsBadges([
                'a_contacter' => 'secondary',
                'contacte' => 'primary',
                'a_relancer' => 'warning',
                'interesse' => 'info',
                'client' => 'success',
                'pas_interesse' => 'danger',
            ]);

        yield TextField::new('siteWebActuel', 'Site web actuel')
            ->setRequired(false)
            ->formatValue(static function (mixed $value): string {
                if (null === $value || '' === $value) {
                    return '—';
                }
                $str = (string) $value;

                return sprintf('<a href="%s" target="_blank">🌐 %s</a>', htmlspecialchars($str), htmlspecialchars($str));
            })
            ->renderAsHtml()
            ->hideOnIndex();

        yield TextareaField::new('notes', 'Notes')
            ->setRequired(false)
            ->hideOnIndex()
            ->setNumOfRows(4);

        yield DateField::new('dateContact', 'Dernier contact')
            ->setRequired(false)
            ->setFormat('dd/MM/yyyy');

        yield AssociationField::new('ville', 'Ville')
            ->setRequired(true);
    }

    /**
     * @param AdminContext<Prospect> $context
     */
    public function monter(AdminContext $context): Response
    {
        /** @var Prospect $prospect */
        $prospect = $context->getEntity()->getInstance();
        $ville = $prospect->getVille();

        if (null !== $ville) {
            $voisin = $this->prospectRepository->findOneBy([
                'ville' => $ville,
                'position' => $prospect->getPosition() - 1,
            ]);

            if (null !== $voisin) {
                $voisin->setPosition($prospect->getPosition());
                $prospect->setPosition($prospect->getPosition() - 1);
                $this->em->flush();
            }
        }

        return $this->redirect($this->getListUrl($ville));
    }

    /**
     * @param AdminContext<Prospect> $context
     */
    public function descendre(AdminContext $context): Response
    {
        /** @var Prospect $prospect */
        $prospect = $context->getEntity()->getInstance();
        $ville = $prospect->getVille();

        if (null !== $ville) {
            $voisin = $this->prospectRepository->findOneBy([
                'ville' => $ville,
                'position' => $prospect->getPosition() + 1,
            ]);

            if (null !== $voisin) {
                $voisin->setPosition($prospect->getPosition());
                $prospect->setPosition($prospect->getPosition() + 1);
                $this->em->flush();
            }
        }

        return $this->redirect($this->getListUrl($ville));
    }

    private function getListUrl(?VilleProspection $ville): string
    {
        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX);

        if (null !== $ville && null !== $ville->getId()) {
            $url->set('filters[ville][value]', (string) $ville->getId())
                ->set('filters[ville][comparison]', '=');
        }

        return $url->generateUrl();
    }

    public function createEntity(string $entityFqcn): Prospect
    {
        $prospect = new Prospect();

        $villeId = $this->getContext()?->getRequest()->query->getInt('ville') ?? 0;
        if ($villeId > 0) {
            $ville = $this->em->getRepository(VilleProspection::class)->find($villeId);
            if (null !== $ville) {
                $prospect->setVille($ville);
            }
        }

        return $prospect;
    }

    /**
     * @param AdminContext<Prospect> $context
     */
    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        $request = $context->getRequest();
        $ea = $request->request->all('ea');
        $bouton = $ea['newForm']['btn'] ?? $ea['editForm']['btn'] ?? Action::SAVE_AND_RETURN;

        if ('kanban' === $request->query->get('retour') && Action::SAVE_AND_RETURN === $bouton) {
            /** @var Prospect $prospect */
            $prospect = $context->getEntity()->getInstance();
            $villeId = $prospect->getVille()?->getId() ?? $request->query->getInt('ville');

            return $this->redirectToRoute('admin_kanban', $villeId > 0 ? ['ville' => $villeId] : []);
        }

        return parent::getRedirectResponseAfterSave($context, $action);
    }

    public function persistEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        /** @var Prospect $entityInstance */
        $ville = $entityInstance->getVille();
        if (null !== $ville) {
            $entityInstance->setPosition($this->prospectRepository->findNextPosition($ville));
        }

        $this->geocodingService->geocodeProspect($entityInstance);

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        /* @var Prospect $entityInstance */
        $this->geocodingService->geocodeProspect($entityInstance);

        parent::updateEntity($entityManager, $entityInstance);
    }
}
