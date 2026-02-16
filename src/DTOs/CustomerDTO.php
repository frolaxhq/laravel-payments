<?php

namespace Frolax\Payment\DTOs;

final readonly class CustomerDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?AddressDTO $address = null,
    ) {}

    public static function fromArray(?array $data): ?self
    {
        if ($data === null || $data === []) {
            return null;
        }

        return new self(
            name: $data['name'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            address: AddressDTO::fromArray($data['address'] ?? null),
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address?->toArray(),
        ], fn ($v) => $v !== null);
    }
}
