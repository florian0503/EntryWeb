<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\StatutProspect;
use App\Repository\ProspectRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProspectRepository::class)]
class Prospect
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nomBoite = null;

    #[ORM\Column(length: 500)]
    private ?string $adresse = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $horaires = null;

    #[ORM\Column(length: 50, enumType: StatutProspect::class)]
    private StatutProspect $statut = StatutProspect::AContacter;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $siteWebActuel = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateContact = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateRelance = null;

    #[ORM\Column(nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\ManyToOne(targetEntity: VilleProspection::class, inversedBy: 'prospects')]
    #[ORM\JoinColumn(nullable: false)]
    private ?VilleProspection $ville = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->nomBoite ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomBoite(): ?string
    {
        return $this->nomBoite;
    }

    public function setNomBoite(string $nomBoite): static
    {
        $this->nomBoite = $nomBoite;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getHoraires(): ?string
    {
        return $this->horaires;
    }

    public function setHoraires(?string $horaires): static
    {
        $this->horaires = $horaires;

        return $this;
    }

    public function getStatut(): StatutProspect
    {
        return $this->statut;
    }

    public function setStatut(StatutProspect $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getSiteWebActuel(): ?string
    {
        return $this->siteWebActuel;
    }

    public function setSiteWebActuel(?string $siteWebActuel): static
    {
        $this->siteWebActuel = $siteWebActuel;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getDateContact(): ?\DateTimeInterface
    {
        return $this->dateContact;
    }

    public function setDateContact(?\DateTimeInterface $dateContact): static
    {
        $this->dateContact = $dateContact;

        return $this;
    }

    public function getDateRelance(): ?\DateTimeInterface
    {
        return $this->dateRelance;
    }

    public function setDateRelance(?\DateTimeInterface $dateRelance): static
    {
        $this->dateRelance = $dateRelance;

        return $this;
    }

    public function isRelanceEnRetard(): bool
    {
        if (null === $this->dateRelance) {
            return false;
        }

        if (\in_array($this->statut, [StatutProspect::Client, StatutProspect::PasInteresse], true)) {
            return false;
        }

        return $this->dateRelance <= new \DateTimeImmutable('today');
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getVille(): ?VilleProspection
    {
        return $this->ville;
    }

    public function setVille(?VilleProspection $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getWazeUrl(): string
    {
        return 'https://waze.com/ul?q='.urlencode($this->adresse ?? '');
    }
}
