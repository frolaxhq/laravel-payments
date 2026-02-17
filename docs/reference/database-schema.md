# Database Schema

All tables use ULID primary keys and configurable table names.

## payment_gateways

Stores gateway definitions.

| Column | Type | Description |
|--------|------|-------------|
| `id` | ULID (PK) | Primary key |
| `name` | string (unique) | Gateway key (e.g., `stripe`) |
| `driver` | string | Driver class name |
| `display_name` | string (nullable) | Human-readable name |
| `supports` | JSON (nullable) | Supported capabilities array |
| `is_active` | boolean | Whether gateway is active |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

## payment_gateway_credentials

Encrypted credential storage with multi-tenant support.

| Column | Type | Description |
|--------|------|-------------|
| `id` | ULID (PK) | Primary key |
| `gateway_name` | string | Gateway key |
| `profile` | string | `test` or `live` |
| `tenant_id` | string (nullable) | Tenant isolation key |
| `label` | string (nullable) | Human-readable label |
| `credentials` | text (encrypted) | Encrypted JSON credentials |
| `is_active` | boolean | Whether credential set is active |
| `effective_from` | timestamp (nullable) | Start of time window |
| `effective_to` | timestamp (nullable) | End of time window |
| `priority` | integer | Resolution order (higher = preferred) |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Indexes:**
- `(gateway_name, profile, is_active)` — Fast credential resolution
- `(gateway_name, profile, tenant_id, is_active)` — Tenant-specific resolution
- `(effective_from, effective_to)` — Time window filtering
- `priority` — Priority ordering

## payments

Core payment records.

| Column | Type | Description |
|--------|------|-------------|
| `id` | ULID (PK) | Primary key |
| `order_id` | string | Application order ID |
| `gateway_name` | string | Gateway used |
| `profile` | string | Credential profile |
| `tenant_id` | string (nullable) | Tenant ID |
| `status` | string | Payment status enum value |
| `amount` | decimal(16,2) | Payment amount |
| `currency` | string(3) | ISO 4217 currency code |
| `gateway_reference` | string (nullable) | Gateway transaction ID |
| `idempotency_key` | string (unique, nullable) | Idempotency key |
| `customer_email` | string (nullable) | Customer email |
| `customer_phone` | string (nullable) | Customer phone |
| `canonical_payload` | JSON (nullable) | Full canonical payload snapshot |
| `metadata` | JSON (nullable) | Application metadata |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Indexes:**
- `idempotency_key` (unique)
- `order_id`, `gateway_name`, `gateway_reference`, `status`, `tenant_id`
- `(gateway_name, status)`, `(tenant_id, gateway_name, status)`, `customer_email`

## payment_attempts

Per-create attempt history.

| Column | Type | Description |
|--------|------|-------------|
| `id` | ULID (PK) | Primary key |
| `payment_id` | ULID (FK) | → payments.id |
| `attempt_no` | unsigned int | Sequential attempt number |
| `status` | string | Attempt status |
| `gateway_reference` | string (nullable) | Gateway reference for this attempt |
| `request_payload` | JSON (nullable) | Request sent to gateway |
| `response_payload` | JSON (nullable) | Response from gateway |
| `errors` | JSON (nullable) | Error details |
| `duration_ms` | decimal(10,2) (nullable) | Request duration |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

## payment_webhook_events

Raw webhook storage for replay and audit.

| Column | Type | Description |
|--------|------|-------------|
| `id` | ULID (PK) | Primary key |
| `gateway_name` | string | Originating gateway |
| `payment_id` | ULID (FK, nullable) | → payments.id |
| `event_type` | string (nullable) | Parsed event type |
| `gateway_reference` | string (nullable) | Gateway transaction reference |
| `headers` | JSON (nullable) | Raw HTTP headers |
| `payload` | JSON (nullable) | Raw webhook body |
| `signature_valid` | boolean | Signature verification result |
| `processed` | boolean | Whether webhook was processed |
| `processed_at` | timestamp (nullable) | Processing timestamp |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Idempotency index:** `(gateway_name, gateway_reference, event_type)` — Prevents duplicate processing.

## payment_refunds

Refund lifecycle records.

| Column | Type | Description |
|--------|------|-------------|
| `id` | ULID (PK) | Primary key |
| `payment_id` | ULID (FK) | → payments.id |
| `amount` | decimal(16,2) | Refund amount |
| `currency` | string(3) | Currency code |
| `status` | string | Refund status |
| `refund_reference` | string (nullable) | Gateway refund reference |
| `reason` | string (nullable) | Refund reason |
| `request_payload` | JSON (nullable) | Refund request |
| `response_payload` | JSON (nullable) | Gateway response |
| `metadata` | JSON (nullable) | Additional metadata |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

## payment_logs

Structured log storage.

| Column | Type | Description |
|--------|------|-------------|
| `id` | ULID (PK) | Primary key |
| `level` | string | Log level |
| `category` | string | Dot-notation category |
| `message` | text | Human-readable message |
| `gateway_name` | string (nullable) | Gateway name |
| `profile` | string (nullable) | Profile |
| `tenant_id` | string (nullable) | Tenant ID |
| `payment_id` | ULID (nullable) | Payment ID |
| `attempt_id` | ULID (nullable) | Attempt ID |
| `context_flat` | JSON (nullable) | Dot-notation flattened context |
| `context_nested` | JSON (nullable) | Original nested context |
| `occurred_at` | timestamp | Event timestamp |

**Indexes:** `level`, `category`, `gateway_name`, `tenant_id`, `payment_id`, `occurred_at`, `(gateway_name, level)`

## Entity Relationships

```
payment_gateways ──┐
                   │ (by name)
payments ◄─────────┘
│
├── payment_attempts (1:N)
├── payment_webhook_events (1:N)
├── payment_refunds (1:N)
└── payment_logs (1:N)

payment_gateway_credentials (standalone, resolved by name+profile+tenant)
```
