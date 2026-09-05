<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Fixture\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A child with TWO associations to its parent's class.
 *
 * `movie` is the inverse side of Movie::$remakes — structural, the row already
 * belongs to the record being edited. `original` is another Movie entirely,
 * and a genuine field of the row. The guesser has to tell them apart by what
 * the mapping says (`mappedBy`), not by the class they point at; the same
 * shape as a contact's connections, which reference other contacts.
 */
#[ORM\Entity]
#[ORM\Table(name: 'remakes')]
class Remake
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Movie::class, inversedBy: 'remakes')]
    #[ORM\JoinColumn(name: 'movie_id', nullable: false, onDelete: 'CASCADE')]
    private ?Movie $movie = null;

    #[ORM\ManyToOne(targetEntity: Movie::class)]
    #[ORM\JoinColumn(name: 'original_id', nullable: false, onDelete: 'CASCADE')]
    private ?Movie $original = null;

    #[ORM\Column(type: 'text')]
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

    public function getOriginal(): ?Movie
    {
        return $this->original;
    }

    public function setOriginal(?Movie $original): self
    {
        $this->original = $original;

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
