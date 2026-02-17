# Introduction

## What is Laravel Payments?

**Laravel Payments** is a production-grade, gateway-agnostic payment abstraction layer for Laravel applications. It provides a **single canonical payload** structure that works with every payment gateway, allowing you to switch between gateways without changing your application code.

## Why Laravel Payments?

### The Problem

Every payment gateway has a different API, different payload shapes, different authentication mechanisms, and different webhook formats. This leads to:

- Gateway-specific code scattered throughout your application
- Painful gateway migrations requiring sweeping code changes
- Duplicated logic for logging, credential management, and error handling
- No standard approach to multi-tenant credential management

### The Solution

Laravel Payments introduces a **canonical layer** between your application and payment gateways:

```
┌─────────────────┐     ┌──────────────────┐     ┌──────────────────┐
│  Your App Code  │ ──► │  Canonical Layer  │ ──► │  Gateway Driver  │
│  (never changes)│     │  (one payload)    │     │  (maps to API)   │
└─────────────────┘     └──────────────────┘     └──────────────────┘
```

Your application always speaks in the **same canonical payload shape**. Gateway drivers translate that shape into gateway-specific API calls internally.

## Key Principles

### 1. One Canonical Payload

Every payment operation uses the same immutable DTO structure—regardless of which gateway processes it.

```php
Payment::gateway('stripe')->create([
    'order' => ['id' => 'ORD-001', 'description' => 'Premium Plan'],
    'money' => ['amount' => 2999, 'currency' => 'USD'],
    'customer' => ['email' => 'user@example.com'],
]);
```

The exact same `create()` call works with **any** registered gateway driver.

### 2. Open/Closed Principle

The core package never branches on gateway names. Instead, drivers implement **capability interfaces** for the features they support:

- `SupportsHostedRedirect` — Redirect to hosted checkout page
- `SupportsWebhookVerification` — Verify webhook signatures
- `SupportsRefund` — Process refunds
- `SupportsStatusQuery` — Query payment status

### 3. Auto-Discovery

Gateway addon packages are automatically discovered when installed via Composer. No manual registration, no config changes.

```bash
composer require frolax/payment-bkash
# Done. bKash is now available as a gateway.
```

### 4. Production-Ready by Default

Built-in support for:

- **Idempotency** — Duplicate-safe payment creation
- **Multi-tenant credentials** — Database-backed with tenant isolation
- **Structured logging** — Dot-notation with automatic redaction
- **Replay-safe webhooks** — Stored, deduplicated, replayable via CLI

## Architecture Overview

```
frolax/laravel-payments
├── Core
│   ├── Payment Manager (fluent API)
│   ├── GatewayRegistry (driver management)
│   ├── Canonical Payload DTOs
│   └── Capability Contracts
├── Credentials
│   ├── EnvCredentialsRepository
│   ├── DatabaseCredentialsRepository
│   └── CompositeCredentialsRepository
├── HTTP
│   ├── WebhookController
│   ├── ReturnController
│   └── CancelController
├── Persistence
│   ├── 7 Eloquent Models
│   └── 7 Migration Stubs
├── Logging
│   └── PaymentLogger (DB + channels)
└── CLI
    ├── payments:make-gateway
    ├── payments:gateways
    ├── payments:credentials:sync
    └── payments:webhooks:replay
```

## Next Steps

- [Installation](/guide/installation) — Install and configure the package
- [Quick Start](/guide/quick-start) — Build your first payment flow in 5 minutes
- [Core Concepts](/guide/canonical-payload) — Understand the canonical payload system
