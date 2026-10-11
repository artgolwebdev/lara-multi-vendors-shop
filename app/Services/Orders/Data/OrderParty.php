<?php

namespace App\Services\Orders\Data;

/**
 * Immutable customer or delivery party captured at checkout and stored as a
 * snapshot on the order.
 */
final readonly class OrderParty
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone = null,
        public ?string $address = null,
    ) {}

    /**
     * @param  array{name: string, email: string, phone?: string|null, address?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            email: (string) $data['email'],
            phone: isset($data['phone']) && $data['phone'] !== '' ? (string) $data['phone'] : null,
            address: isset($data['address']) && $data['address'] !== '' ? (string) $data['address'] : null,
        );
    }
}
