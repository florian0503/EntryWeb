<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Realisation;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Vich\UploaderBundle\Form\Type\VichImageType;

/**
 * @extends AbstractCrudController<Realisation>
 */
class RealisationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Realisation::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Réalisation')
            ->setEntityLabelInPlural('Réalisations')
            ->setSearchFields(['title', 'description', 'clientName', 'category.name'])
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(20);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('category', 'Catégorie'))
            ->add(BooleanFilter::new('isPublished'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();

        yield TextField::new('title', 'Titre')
            ->setRequired(true);

        yield TextareaField::new('description', 'Description')
            ->setRequired(true)
            ->hideOnIndex();

        yield AssociationField::new('category', 'Catégorie')
            ->setRequired(true);

        yield TextField::new('imageFile', 'Screenshot desktop')
            ->setFormType(VichImageType::class)
            ->setFormTypeOptions([
                'required' => false,
                'allow_delete' => true,
                'delete_label' => 'Supprimer l\'image',
                'download_uri' => false,
                'image_uri' => true,
                'asset_helper' => true,
            ])
            ->onlyOnForms();

        yield TextField::new('imageTabletFile', 'Screenshot tablette')
            ->setFormType(VichImageType::class)
            ->setFormTypeOptions([
                'required' => false,
                'allow_delete' => true,
                'delete_label' => 'Supprimer l\'image',
                'download_uri' => false,
                'image_uri' => true,
                'asset_helper' => true,
            ])
            ->onlyOnForms();

        yield TextField::new('imageMobileFile', 'Screenshot mobile')
            ->setFormType(VichImageType::class)
            ->setFormTypeOptions([
                'required' => false,
                'allow_delete' => true,
                'delete_label' => 'Supprimer l\'image',
                'download_uri' => false,
                'image_uri' => true,
                'asset_helper' => true,
            ])
            ->onlyOnForms();

        yield ImageField::new('imageName', 'Image')
            ->setBasePath('/uploads/realisations')
            ->onlyOnIndex();

        yield TextField::new('clientName', 'Nom du client')
            ->setRequired(false);

        yield UrlField::new('url', 'URL du site')
            ->setRequired(false)
            ->hideOnIndex();

        yield BooleanField::new('isPublished', 'Publié')
            ->renderAsSwitch(true);

        yield DateTimeField::new('createdAt', 'Créé le')
            ->hideOnForm()
            ->setFormat('dd/MM/yyyy HH:mm');

        yield DateTimeField::new('updatedAt', 'Modifié le')
            ->hideOnForm()
            ->onlyOnDetail()
            ->setFormat('dd/MM/yyyy HH:mm');
    }
}
