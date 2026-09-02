<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Fixture\Entity;

use Doctrine\ORM\Mapping as ORM;
use Modufolio\Panel\Delete\OnDelete;

/**
 * Exists for one delete behaviour: RESTRICT. A credit goes with its movie
 * (CASCADE) but refuses to outlive its cast member — unless that cast member
 * is going in the same operation, which is exactly what deleting the movie
 * does. Deleting the cast member on its own must be refused.
 */
#[ORM\Entity]
#[ORM\Table(name: 'credits')]
class Credit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Movie::class)]
    #[ORM\JoinColumn(name: 'movie_id', nullable: false, onDelete: 'CASCADE')]
    #[OnDelete(OnDelete::CASCADE)]
    private ?Movie $movie = null;

    #[ORM\ManyToOne(targetEntity: CastMember::class)]
    #[ORM\JoinColumn(name: 'cast_member_id', nullable: true)]
    #[OnDelete(OnDelete::RESTRICT)]
    private ?CastMember $castMember = null;

    #[ORM\Column(length: 120)]
    private string $note = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMovie(): ?Movie
    {
        return $this->movie;
    }

    public function setMovie(?Movie $movie): self
    {
        $this->movie = $movie;

        return $this;
    }

    public function getCastMember(): ?CastMember
    {
        return $this->castMember;
    }

    public function setCastMember(?CastMember $castMember): self
    {
        $this->castMember = $castMember;

        return $this;
    }

    public function getNote(): string
    {
        return $this->note;
    }

    public function setNote(string $note): self
    {
        $this->note = $note;

        return $this;
    }
}
