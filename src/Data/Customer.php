<?php

namespace Frolax\Payment\Data;

final readonly class Customer
{
    public function __construct(
        public ?string $id,
        public ?string $name = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?Address $address = null,
    ) {}

    public static function fromArray(?array $data): ?self
    {
        if ($data === null || $data === []) {
            return null;
        }

        return new self(
            id: $data['id'] ?? null,
            name: $data['name'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            address: Address::fromArray($data['address'] ?? null),
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address?->toArray(),
        ], fn ($v) => $v !== null);
    }
}
