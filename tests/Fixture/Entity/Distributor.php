<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Fixture\Entity;

use Doctrine\ORM\Mapping as ORM;
use Modufolio\Panel\Blueprint\LabelField;

/**
 * Referred to by its code, not its name.
 *
 * Maps a `name`, which the guesser's convention would pick first — and marks
 * `code` with #[LabelField], so the entity's own declaration has to win for a
 * lookup over distributors to read "UAR" rather than "United Artists Releasing".
 */
#[ORM\Entity]
#[ORM\Table(name: 'distributors')]
class Distributor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $name = '';

    #[ORM\Column(length: 20)]
    #[LabelField]
    private string $code = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }
}
