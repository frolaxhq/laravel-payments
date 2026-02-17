# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] — 2025-02-16

### Added

- **Subscriptions & Recurring Billing**
  - `SupportsRecurring` capability interface
  - `SubscriptionStatus` and `BillingInterval` enums
  - `PlanDTO`, `CanonicalSubscriptionPayload`, `SubscriptionItemDTO`
  - `Subscription`, `SubscriptionItem`, `SubscriptionUsage` models
  - Full lifecycle: create, cancel, pause, resume, update subscriptions
  - 6 subscription events (`SubscriptionCreated`, `Renewed`, `Paused`, `Resumed`, `Cancelled`, `TrialEnding`)

- **Tokenization**
  - Updated `SupportsTokenization` contract
  - `PaymentMethod` model with token storage, expiry, and default handling
  - `PaymentMethodSaved` and `PaymentMethodDeleted` events

- **Invoicing**
  - `Invoice`, `InvoiceItem`, `CreditNote`, `TaxRate` models
  - `InvoiceGenerator` and `TaxCalculator` services
  - State management (`markPaid()`, `markVoided()`) and scopes

- **Payouts & Splits**
  - `SupportsPayout` capability interface
  - `PayoutRecipient`, `Payout`, `PaymentSplit` models
  - Marketplace-style split payment support

- **Fraud & Risk**
  - `SupportsThreeDSecure` capability interface
  - `BlocklistEntry` and `RiskAssessment` models
  - `RiskScorer` service with configurable factors

- **Analytics**
  - `RevenueAnalytics` service (revenue summary, MRR/ARR, gateway success rates, conversion funnels)

- **Coupons & Discounts**
  - `Coupon` and `CouponUsage` models with validation
  - Percentage and fixed discount types
  - Per-customer usage limits, minimum spend, expiry

- **Advanced Webhooks**
  - `WebhookRouter` service with wildcard matching
  - `WebhookRetryPolicy` with fixed, linear, and exponential backoff

- **Multi-Currency**
  - `ExchangeRate` model
  - `CurrencyConverter` service with bidirectional rate resolution

- **Payment Method Contracts**
  - 5 new capability interfaces: `SupportsWallets`, `SupportsBankTransfer`, `SupportsBuyNowPayLater`, `SupportsQRCode`, `SupportsCOD`

- **Developer Experience**
  - `PaymentLink` model with auto-slug and expiry
  - `SandboxSimulator` for testing without a real gateway
  - `SchemaValidator` for pre-flight payload validation
  - 8 new VitePress documentation pages

### Changed

- Service provider now registers 10 new migration stubs and 9 singleton services
- `Payment` manager extended with subscription lifecycle methods

---

## [1.0.0] — 2024-XX-XX

### Added

- **Core Architecture**
  - `Payment` manager with fluent API (`gateway()`, `withProfile()`, `usingContext()`, `usingCredentials()`)
  - `GatewayRegistry` for managing core and addon gateway registrations
  - `GatewayDriverContract` — Core driver interface
  - 6 capability interfaces: `SupportsHostedRedirect`, `SupportsWebhookVerification`, `SupportsRefund`, `SupportsStatusQuery`, `SupportsTokenization`, `SupportsInstallments`

- **Canonical Payload System**
  - 12 immutable, readonly DTOs: `CanonicalPayload`, `MoneyDTO`, `OrderDTO`, `OrderItemDTO`, `CustomerDTO`, `AddressDTO`, `UrlsDTO`, `ContextDTO`, `CanonicalRefundPayload`, `CanonicalStatusPayload`, `CredentialsDTO`, `GatewayResult`
  - Auto-generated idempotency keys
  - Dot-notation flattening for logging

- **Credential Management**
  - 3 credential repositories: `EnvCredentialsRepository`, `DatabaseCredentialsRepository`, `CompositeCredentialsRepository`
  - Multi-tenant isolation with tenant IDs
  - Time-windowed credential rotation
  - Priority-based resolution
  - Encrypted database storage

- **Persistence Layer**
  - 7 Eloquent models with ULID primary keys
  - 7 migration stubs with configurable table names
  - Full payment lifecycle tracking (payments, attempts, webhooks, refunds, logs)

- **HTTP Layer**
  - Universal `WebhookController` with signature verification and idempotency
  - `ReturnController` and `CancelController` for callback handling
  - Configurable routes with middleware support

- **Logging**
  - `PaymentLogger` with dot-notation categories
  - Dual output: Laravel log channels + database table
  - Configurable verbosity levels (off → debug)
  - Automatic redaction of sensitive keys

- **CLI Commands**
  - `payments:make-gateway` — Generate driver skeletons (inline or --addon)
  - `payments:gateways` — List all discovered gateways
  - `payments:credentials:sync` — Validate credential presence
  - `payments:webhooks:replay` — Safely replay stored webhook events

- **Auto-Discovery**
  - `GatewayAddonServiceProvider` for addon packages
  - `GatewayAddonContract` for addon metadata

- **Events**
  - `PaymentCreated`, `PaymentVerified`, `PaymentFailed`, `PaymentCancelled`
  - `PaymentRefundRequested`, `PaymentRefunded`, `WebhookReceived`

- **Exceptions**
  - 7 typed exceptions for specific failure scenarios
