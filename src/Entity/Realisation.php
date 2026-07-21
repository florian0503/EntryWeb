<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RealisationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: RealisationRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
class Realisation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\ManyToOne(inversedBy: 'realisations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

    #[Vich\UploadableField(mapping: 'realisations', fileNameProperty: 'imageName')]
    private ?File $imageFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageName = null;

    #[Vich\UploadableField(mapping: 'realisations', fileNameProperty: 'imageTabletName')]
    private ?File $imageTabletFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageTabletName = null;

    #[Vich\UploadableField(mapping: 'realisations', fileNameProperty: 'imageMobileName')]
    private ?File $imageMobileFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageMobileName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $clientName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column]
    private bool $isPublished = false;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function setImageFile(?File $imageFile = null): void
    {
        $this->imageFile = $imageFile;

        if (null !== $imageFile) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function getImageName(): ?string
    {
        return $this->imageName;
    }

    public function setImageName(?string $imageName): static
    {
        $this->imageName = $imageName;

        return $this;
    }

    public function setImageTabletFile(?File $imageTabletFile = null): void
    {
        $this->imageTabletFile = $imageTabletFile;

        if (null !== $imageTabletFile) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getImageTabletFile(): ?File
    {
        return $this->imageTabletFile;
    }

    public function getImageTabletName(): ?string
    {
        return $this->imageTabletName;
    }

    public function setImageTabletName(?string $imageTabletName): static
    {
        $this->imageTabletName = $imageTabletName;

        return $this;
    }

    public function setImageMobileFile(?File $imageMobileFile = null): void
    {
        $this->imageMobileFile = $imageMobileFile;

        if (null !== $imageMobileFile) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getImageMobileFile(): ?File
    {
        return $this->imageMobileFile;
    }

    public function getImageMobileName(): ?string
    {
        return $this->imageMobileName;
    }

    public function setImageMobileName(?string $imageMobileName): static
    {
        $this->imageMobileName = $imageMobileName;

        return $this;
    }

    public function hasDeviceMockup(): bool
    {
        return null !== $this->imageName && null !== $this->imageTabletName && null !== $this->imageMobileName;
    }

    public function getClientName(): ?string
    {
        return $this->clientName;
    }

    public function setClientName(?string $clientName): static
    {
        $this->clientName = $clientName;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;

        return $this;
    }

    public function __toString(): string
    {
        return $this->title ?? '';
    }
}
