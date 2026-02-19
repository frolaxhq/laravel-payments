<?php

namespace Frolax\Payment\DTOs;

final readonly class CanonicalSubscriptionPayload
{
    public function __construct(
        public string $idempotencyKey,
        public PlanDTO $plan,
        public ?CustomerDTO $customer = null,
        public ?UrlsDTO $urls = null,
        public ?ContextDTO $context = null,
        public ?int $trialDays = null,
        public ?string $couponCode = null,
        public ?int $quantity = 1,
        public array $items = [],
        public array $metadata = [],
        public array $extra = [],
    ) {}

    public static function fromArray(array $data): static
    {
        $plan = PlanDTO::fromArray($data['plan']);

        return new self(
            idempotencyKey: $data['idempotency_key'] ?? hash('sha256', $plan->id.($data['customer']['email'] ?? '').time()),
            plan: $plan,
            customer: isset($data['customer']) ? CustomerDTO::fromArray($data['customer']) : null,
            urls: isset($data['urls']) ? UrlsDTO::fromArray($data['urls']) : null,
            context: isset($data['context']) ? ContextDTO::fromArray($data['context']) : null,
            trialDays: $data['trial_days'] ?? $plan->trialDays,
            couponCode: $data['coupon_code'] ?? null,
            quantity: $data['quantity'] ?? 1,
            items: array_map(
                fn (array $item) => SubscriptionItemDTO::fromArray($item),
                $data['items'] ?? [],
            ),
            metadata: $data['metadata'] ?? [],
            extra: $data['extra'] ?? [],
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'idempotency_key' => $this->idempotencyKey,
            'plan' => $this->plan->toArray(),
            'customer' => $this->customer?->toArray(),
            'urls' => $this->urls?->toArray(),
            'context' => $this->context?->toArray(),
            'trial_days' => $this->trialDays,
            'coupon_code' => $this->couponCode,
            'quantity' => $this->quantity,
            'items' => array_map(fn ($i) => $i->toArray(), $this->items),
            'metadata' => $this->metadata,
            'extra' => $this->extra,
        ], fn ($v) => $v !== null && $v !== []);
    }
}
