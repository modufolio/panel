<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Fixture\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
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

    #[ORM\Column(name: 'deleted_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->uuid      = Uuid::uuid4();
        $this->tags      = new ArrayCollection();
        $this->cast      = new ArrayCollection();
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
