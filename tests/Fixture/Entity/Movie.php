<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Fixture\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Modufolio\Panel\Blueprint\FormType;
use Modufolio\Panel\Field\UrlType;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The record every database-backed test lists, edits or deletes.
 *
 * Deliberately one column of every kind the guesser distinguishes — string
 * with a length, text, integer, smallint, decimal with a scale, boolean, date
 * — plus each association shape: a nullable to-one that a constraint still
 * makes required, an owning many-to-many, and a mapped-by one-to-many whose
 * rows carry a relation of their own. `deletedAt` gives the list query a
 * soft-delete scope to honour.
 */
#[ORM\Entity]
#[ORM\Table(name: 'movies')]
class Movie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $uuid;

    #[ORM\Column(length: 160)]
    private string $title = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $synopsis = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $year = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $runtime = null;

    #[ORM\Column(type: 'decimal', precision: 3, scale: 1, nullable: true)]
    private ?string $rating = null;

    #[ORM\Column(type: 'boolean')]
    private bool $released = false;

    #[ORM\Column(name: 'released_on', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $releasedOn = null;

    /** A moment, not a day: guessed as a datetime control, which a date picker could not read. */
    #[ORM\Column(name: 'premiere_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $premiereAt = null;

    /** A choice among enum cases: the mapping says so through enumType, and the form is a select. */
    #[ORM\Column(length: 20, nullable: true, enumType: Genre::class)]
    private ?Genre $genre = null;

    /** A string column the mapping can only call text; the attribute says what it is. */
    #[ORM\Column(length: 200, nullable: true)]
    #[FormType(UrlType::class)]
    private ?string $website = null;

    /**
     * Nullable at the column so the schema can `ON DELETE SET NULL`, while the
     * constraint keeps the form treating it as mandatory — the case the
     * guesser's `isConstrainedRequired()` exists for.
     */
    #[ORM\ManyToOne(targetEntity: Studio::class)]
    #[ORM\JoinColumn(name: 'studio_id', nullable: true, onDelete: 'SET NULL')]
    #[Assert\NotNull]
    private ?Studio $studio = null;

    /** @var Collection<int, Tag> */
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[ORM\JoinTable(name: 'movie_tags')]
    private Collection $tags;

    /** @var Collection<int, CastMember> */
    #[ORM\OneToMany(targetEntity: CastMember::class, mappedBy: 'movie', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $cast;

    /** @var Collection<int, Remake> */
    #[ORM\OneToMany(targetEntity: Remake::class, mappedBy: 'movie', cascade: ['persist'], orphanRemoval: true)]
    private Collection $remakes;

    /** Optional, and labelled by the target's own #[LabelField]. */
    #[ORM\ManyToOne(targetEntity: Distributor::class)]
    #[ORM\JoinColumn(name: 'distributor_id', nullable: true, onDelete: 'SET NULL')]
    private ?Distributor $distributor = null;

    #[ORM\Column(name: 'deleted_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->uuid      = Uuid::uuid4();
        $this->tags      = new ArrayCollection();
        $this->cast      = new ArrayCollection();
        $this->remakes = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): UuidInterface
    {
        return $this->uuid;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getSynopsis(): ?string
    {
        return $this->synopsis;
    }

    public function setSynopsis(?string $synopsis): self
    {
        $this->synopsis = $synopsis;

        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(?int $year): self
    {
        $this->year = $year;

        return $this;
    }

    public function getRuntime(): ?int
    {
        return $this->runtime;
    }

    public function setRuntime(?int $runtime): self
    {
        $this->runtime = $runtime;

        return $this;
    }

    public function getRating(): ?string
    {
        return $this->rating;
    }

    public function setRating(?string $rating): self
    {
        $this->rating = $rating;

        return $this;
    }

    public function isReleased(): bool
    {
        return $this->released;
    }

    /** Named as a getter too, because sortValue() resolves `get` + field. */
    public function getReleased(): bool
    {
        return $this->released;
    }

    public function setReleased(bool $released): self
    {
        $this->released = $released;

        return $this;
    }

    public function getReleasedOn(): ?\DateTimeImmutable
    {
        return $this->releasedOn;
    }

    /** @return Collection<int, Remake> */
    public function getRemakes(): Collection
    {
        return $this->remakes;
    }

    public function getDistributor(): ?Distributor
    {
        return $this->distributor;
    }

    public function setDistributor(?Distributor $distributor): self
    {
        $this->distributor = $distributor;

        return $this;
    }

    public function getPremiereAt(): ?\DateTimeImmutable
    {
        return $this->premiereAt;
    }

    public function setPremiereAt(?\DateTimeImmutable $premiereAt): self
    {
        $this->premiereAt = $premiereAt;

        return $this;
    }

    public function getGenre(): ?Genre
    {
        return $this->genre;
    }

    public function setGenre(?Genre $genre): self
    {
        $this->genre = $genre;

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): self
    {
        $this->website = $website;

        return $this;
    }

    public function setReleasedOn(?\DateTimeImmutable $releasedOn): self
    {
        $this->releasedOn = $releasedOn;

        return $this;
    }

    public function getStudio(): ?Studio
    {
        return $this->studio;
    }

    public function setStudio(?Studio $studio): self
    {
        $this->studio = $studio;

        return $this;
    }

    /** @return Collection<int, Tag> */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): self
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): self
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    /** @return Collection<int, CastMember> */
    public function getCast(): Collection
    {
        return $this->cast;
    }

    public function addCastMember(CastMember $member): self
    {
        if (!$this->cast->contains($member)) {
            $this->cast->add($member);
            $member->setMovie($this);
        }

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): self
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
