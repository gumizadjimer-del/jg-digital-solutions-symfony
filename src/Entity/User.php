<?php

namespace App\Entity;

use App\Repository\UserRepository;
use App\Entity\StaffRecord;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $username = null;

    #[ORM\Column(length: 150, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 20)]
    private ?string $role = null;

    #[ORM\Column(options: ['default' => 1])]
    private bool $isActive = true;

    /**
     * @var Collection<int, ServiceRequest>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ServiceRequest::class)]
    private Collection $serviceRequests;

    /**
     * @var Collection<int, StaffRecord>
     */
    #[ORM\OneToMany(mappedBy: 'createdBy', targetEntity: StaffRecord::class)]
    private Collection $staffRecords;

    public function __construct()
    {
        $this->serviceRequests = new ArrayCollection();
        $this->staffRecords = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getRoles(): array
    {
        return match ($this->role) {
            'admin' => ['ROLE_ADMIN', 'ROLE_USER'],
            'staff' => ['ROLE_STAFF', 'ROLE_USER'],
            default => ['ROLE_USER'],
        };
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): static
    {
        $this->isActive = $isActive;

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

    /**
     * @return Collection<int, ServiceRequest>
     */
    public function getServiceRequests(): Collection
    {
        return $this->serviceRequests;
    }

    public function addServiceRequest(ServiceRequest $serviceRequest): static
    {
        if (!$this->serviceRequests->contains($serviceRequest)) {
            $this->serviceRequests->add($serviceRequest);
            $serviceRequest->setUser($this);
        }

        return $this;
    }

    public function removeServiceRequest(ServiceRequest $serviceRequest): static
    {
        if ($this->serviceRequests->removeElement($serviceRequest)) {
            if ($serviceRequest->getUser() === $this) {
                $serviceRequest->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, StaffRecord>
     */
    public function getStaffRecords(): Collection
    {
        return $this->staffRecords;
    }

    public function addStaffRecord(StaffRecord $staffRecord): static
    {
        if (!$this->staffRecords->contains($staffRecord)) {
            $this->staffRecords->add($staffRecord);
            $staffRecord->setCreatedBy($this);
        }

        return $this;
    }

    public function eraseCredentials(): void
    {
        // No temporary sensitive data to erase.
    }
}
