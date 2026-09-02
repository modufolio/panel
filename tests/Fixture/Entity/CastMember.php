<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Fixture\Entity;

use Doctrine\ORM\Mapping as ORM;
use Modufolio\Panel\Delete\OnDelete;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * A repeater row: it cannot exist without its movie (CASCADE) and is
 * meaningless without its actor (PROTECT). `position` is bookkeeping the
 * guesser must leave out of the row's fields.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cast_members')]
class CastMember
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $uuid;

    #[ORM\ManyToOne(targetEntity: Movie::class, inversedBy: 'cast')]
    #[ORM\JoinColumn(name: 'movie_id', nullable: false, onDelete: 'CASCADE')]
    #[OnDelete(OnDelete::CASCADE)]
    private ?Movie $movie = null;

    #[ORM\ManyToOne(targetEntity: Actor::class)]
    #[ORM\JoinColumn(name: 'actor_id', nullable: true, onDelete: 'SET NULL')]
    #[OnDelete(OnDelete::PROTECT)]
    private ?Actor $actor = null;

    // `character` is a reserved word on MySQL, so the column is named for
    // the property without becoming a quoting exercise in every engine.
    #[ORM\Column(name: 'character_name', length: 120)]
    private string $character = '';

    #[ORM\Column(type: 'integer')]
    private int $position = 0;

    public function __construct()
    {
        $this->uuid = Uuid::uuid4();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): UuidInterface
    {
        return $this->uuid;
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

    public function getActor(): ?Actor
    {
        return $this->actor;
    }

    public function setActor(?Actor $actor): self
    {
        $this->actor = $actor;

        return $this;
    }

    public function getCharacter(): string
    {
        return $this->character;
    }

    public function setCharacter(string $character): self
    {
        $this->character = $character;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }
}
