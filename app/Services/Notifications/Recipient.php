<?php

namespace App\Services\Notifications;

/** Someone the hub can reach: a guardian, student, employee, portal user or admission applicant. */
final class Recipient
{
    public function __construct(
        public readonly string $type,
        public readonly ?int $id,
        public readonly string $name,
        public readonly ?string $phone = null,
        public readonly ?string $email = null,
        public readonly ?int $userId = null,
        public readonly ?string $locale = null,
        public readonly array $vars = [],
    ) {}

    public function key(): string
    {
        return $this->type.':'.($this->id ?? md5(($this->phone ?? '').($this->email ?? '')));
    }

    public function with(array $vars): self
    {
        return new self($this->type, $this->id, $this->name, $this->phone, $this->email, $this->userId, $this->locale, $vars + $this->vars);
    }
}
