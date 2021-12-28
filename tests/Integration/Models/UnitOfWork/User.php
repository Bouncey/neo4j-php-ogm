<?php

namespace GraphAware\Neo4j\OGM\Tests\Integration\Models\UnitOfWork;

use GraphAware\Neo4j\OGM\Annotations as OGM;
use GraphAware\Neo4j\OGM\Common\Collection;

/**
 *
 * @OGM\Node(label="users")
 */
class User
{
    /**
     * @OGM\GraphId()
     */
    private int $id;

    /**
     * @OGM\Property(type="string")
     */
    private string $name;

    /**
     * @OGM\Property(type="int")
     */
    private int $age;

    /**
     * @var Collection<User>
     *
     * @OGM\Relationship(type="LOVED_BY", direction="INCOMING", collection=true, mappedBy="loves", targetEntity="User")
     */
    private Collection $lovedBy;

     /**
     * @var Collection<User>
     *
     * @OGM\Relationship(type="LOVES", direction="OUTGOING", collection=true, mappedBy="lovedBy", targetEntity="User")
     */
    private Collection $loves;

    public function __construct(string $name, int $age)
    {
        $this->name = $name;
        $this->age = $age;

        $this->lovedBy = new Collection();
        $this->loves = new Collection();
    }

    public function getId(): int
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

    public function getAge(): int
    {
        return $this->age;
    }

    public function setAge(int $age): self
    {
        $this->age = $age;
        return $this;
    }

    /**
     * @return Collection<User>
     */
    public function getLovedBy(): Collection
    {
        return $this->lovedBy;
    }

    public function addLovedBy(User $user): self
    {
        if (!$this->lovedBy->contains($user)) {
            $this->lovedBy->add($user);
            $user->addLoves($this);
        }
        return $this;
    }

    public function getLoves(): Collection
    {
        return $this->loves;
    }

    public function addLoves(User $user): self
    {
        if (!$this->loves->contains($user)) {
            $this->loves->add($user);
            $user->addLovedBy($this);
        }
        return $this;
    }
}
