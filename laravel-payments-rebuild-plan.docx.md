

| laravel-payments Complete Rebuild Plan A disciplined, test-first, production-grade rebuild Target: PHPStan Level 9  ·  100% Critical Path Coverage  ·  Zero Baseline Suppressions |
| :---- |

| Document Type Engineering Rebuild Specification Package frolaxhq/laravel-payments | Target Standards PHPStan Level 9, Pest 3, Laravel 11/12, PHP 8.2+ Approach Prompt-by-prompt AI-assisted build with human review gates |
| :---- | :---- |

| SECTION 1 — WHY THIS MUST BE REBUILT FROM SCRATCH |
| :---- |

The original package was vibe-coded: the architecture looks professional at a glance but contains fundamental bugs that would cause silent failures in production. Patching them is riskier than a clean rebuild because each fix exposes another layer of assumptions. The problems fall into four categories:

## **1.1  The Six Critical Bugs That Cannot Be Patched**

| \# | Bug | Location | Production Impact |
| :---- | :---- | :---- | :---- |
| 1 | PaymentFake static $recorded leaks across tests | Testing/PaymentFake.php | Every test assertion is invalid — earlier tests contaminate later ones silently |
| 2 | Idempotency check allows duplicate DB inserts | Pipeline/Steps/CheckIdempotency.php | A pending payment \+ same key → raw unique constraint DB exception instead of clean idempotent return |
| 3 | Double credential decryption swallows credentials | Credentials/DatabaseCredentialsRepository.php | Laravel cast decrypts, then manual Crypt::decryptString fails, fallback returns \[\] — all DB credentials silently fail |
| 4 | customer\_id stores customer email instead of id | SubscriptionManager.php line \~97 | Subscription records are corrupted; customer lookup by ID always fails |
| 5 | verifyFromRequest passes gatewayReference as paymentId | Payment.php in verifyFromRequest() | PaymentVerified event carries wrong ID; all event listeners and downstream logic operate on wrong record |
| 6 | WebhookController calls verify() for push webhooks | Http/Controllers/WebhookController.php | verify() is designed for redirect return flows — request structure is incompatible with push webhooks for any real gateway |

## **1.2  The Five Architectural Flaws**

| FLAW 1 | Pipeline context is mutable despite appearing immutable.  PaymentContext is declared final but all properties are public and non-readonly. Any pipeline step can corrupt shared state with no type-safety net. Fix requires proper readonly properties and explicit pass-by-value semantics. |
| :---- | :---- |

| FLAW 2 | No database transaction wraps the payment pipeline.  PersistPaymentRecord creates a record, then the gateway is called, then UpdatePaymentStatus writes back. A crash anywhere between these leaves orphaned pending records. The rebuild wraps the DB operations in a transaction with a gateway call outside it, using compensation logic. |
| :---- | :---- |

| FLAW 3 | GatewayRegistry capability metadata is decorative.  Registered capabilities are never enforced. A gateway registered without SupportsRefund::class still passes instanceof checks in RefundManager. The rebuild enforces strict capability validation at registration time. |
| :---- | :---- |

| FLAW 4 | MakeGatewayCommand generates broken PHP.  When \--capabilities=refund or status are selected, RefundPayload and StatusPayload types are used in method signatures but never added to the use block. Generated files produce PHP parse errors. |
| :---- | :---- |

| FLAW 5 | PHPStan baseline suppresses 23 real bugs.  The baseline was used to silence errors rather than track them. Level 5 analysis with 23 suppressions is far below enterprise standards. The rebuild targets Level 9 with a zero-suppression policy from day one. |
| :---- | :---- |

| SECTION 2 — REBUILD PRINCIPLES & NON-NEGOTIABLES |
| :---- |

## **2.1  Core Engineering Principles**

| Principle | Concrete Rule |
| :---- | :---- |
| Test-First | Every public method has a test written before the implementation. No code ships without a test. |
| Types First | PHPStan Level 9 from commit \#1. No mixed, no suppressions. Readonly where state must not change. |
| Explicit over Implicit | No silent fallbacks. Credentials fail loudly. Missing capabilities throw typed exceptions. |
| Single Responsibility | Each pipeline step does exactly one thing. Each class has one reason to change. |
| Zero Baseline Policy | phpstan-baseline.neon starts empty and stays empty. Every error is fixed, not suppressed. |
| Immutability by Default | DTOs use readonly properties. Enums replace magic strings everywhere. |
| Seam-based Architecture | Every external I/O (gateway HTTP, DB writes, clock) is injectable for testing. |
| Atomic Operations | DB writes that belong together are wrapped in transactions. Compensation logic for failures. |

## **2.2  Technology Stack**

| Component | Choice | Reason |
| :---- | :---- | :---- |
| PHP | 8.2+ (readonly, enums, fibers) | Required for proper immutability and enum-based state machines |
| Laravel | 11 / 12 | Target platforms per original spec |
| Static Analysis | PHPStan 2.x \+ Larastan 3.x at Level 9 | Catch bugs at development time, not runtime |
| Testing | Pest 3 \+ pest-plugin-arch \+ pest-plugin-laravel | Architecture tests enforce layer boundaries |
| Code Generation | Nette PHP Generator 4.x | Already used; keep for MakeGatewayCommand |
| Test DB | SQLite in-memory via Orchestra Testbench | Fast, zero-config, no external dependencies |
| Encryption | Laravel encrypted:array cast exclusively | No manual Crypt calls — removes the double-decrypt bug permanently |
| HTTP (gateway drivers) | Guzzle via Laravel HTTP Client | Mockable, has built-in fake, consistent with Laravel ecosystem |

| SECTION 3 — CORRECT ARCHITECTURE |
| :---- |

## **3.1  Layer Map**

The rebuild maintains the same high-level shape but fixes the internal contracts between layers:

| Layer | Classes | Key Fix from Original |
| :---- | :---- | :---- |
| **Contracts** | GatewayDriverContract, SupportsRefund, SupportsRecurring, SupportsWebhookVerification, etc. | Add WebhookHandlerContract. Separate verify() from handleWebhook() at the contract level. |
| **Data (DTOs)** | Payload, Money, Customer, GatewayResult, Credentials, WebhookData, etc. | All properties readonly. Named constructors only. No direct construction outside DTO. |
| **Enums** | PaymentStatus, SubscriptionStatus, RefundStatus, WebhookEventType, etc. | All state values are enum cases — no magic strings anywhere in the codebase. |
| **Credentials** | CredentialsRepositoryContract, EnvRepo, DatabaseRepo, CompositeRepo | Remove manual Crypt calls entirely. Use encrypted:array cast exclusively. Add proper interface-level caching. |
| **Pipeline** | PaymentContext (readonly), CheckIdempotency, PersistPaymentRecord, ExecuteGatewayCall, PersistAttempt, UpdatePaymentStatus, DispatchPaymentEvent | PaymentContext all readonly. Fix idempotency logic. Wrap DB steps in DB::transaction(). Add compensation on failure. |
| **Managers** | Payment, SubscriptionManager, RefundManager | Fix customer\_id assignment. Fix verifyFromRequest paymentId. Add proper return types everywhere. |
| **HTTP** | WebhookController, ReturnController, CancelController | Split WebhookController: use handleWebhook() not verify(). Add proper 4xx/5xx response codes. |
| **Models** | PaymentModel, Subscription, PaymentRefund, etc. | Fix PaymentMethod table config key (methods vs payment\_methods). Add missing model relationships. |
| **Testing** | PaymentFake, FakeDriver, TestCase base classes | Make $recorded instance-level, not static. Add FakeDriver::implements SupportsWebhookVerification properly. |
| **Commands** | MakeGatewayCommand, ListGatewaysCommand, ValidateCredentialsCommand, ReplayWebhookCommand | Fix namespace use block generation in MakeGatewayCommand. Fix ReplayWebhookCommand synthetic request body. |

## **3.2  The Corrected Payment Pipeline**

| KEY FIX | The original pipeline mutates a non-readonly context object and has no transaction boundary. The rebuilt pipeline uses an immutable context with each step returning a new context. DB writes (before and after gateway call) are separated by design so only DB operations are in transactions, not the gateway HTTP call itself. |
| :---- | :---- |

| Step | Class | Responsibility | Fixed From Original |
| :---- | :---- | :---- | :---- |
| 1 | ResolveIdempotency | Check if key exists. If so, return existing result immediately — all statuses, including pending. | Original only short-circuits on non-pending; allows duplicate DB inserts for pending payments. |
| 2 | ValidatePayload | Run SchemaValidator. Throw typed InvalidPayloadException early. | Original had no validation step in pipeline — schema validator existed but was never wired in. |
| 3 | PersistPaymentRecord | Wrap in DB::transaction(). Create payment record. Assign paymentId to new context. | Original had no transaction. Added DB::transaction() with rollback on exception. |
| 4 | ExecuteGatewayCall | Call driver-\>create(). Measure timing. Catch Throwable — store in context, never rethrow here. | Same as original but gateway exception is stored, not lost. |
| 5 | PersistAttempt | Wrap in DB::transaction(). Write attempt record with timing, request/response payloads. | Original had no transaction on attempt persistence. |
| 6 | UpdatePaymentStatus | Wrap in DB::transaction(). Update payment record status and gateway\_reference. | Same as original but in transaction. |
| 7 | DispatchPaymentEvent | Dispatch PaymentCreated or PaymentFailed. Rethrow stored exception if present. | Same as original. |

## **3.3  The Corrected Webhook Flow**

The original WebhookController incorrectly calls driver-\>verify() — the method meant for return URL callbacks. The rebuilt controller calls a dedicated handleWebhook() method defined in a new SupportsWebhookHandling interface:

| Interface Method | Used For | Request Type |
| :---- | :---- | :---- |
| verify(Request, Credentials) | User returns from hosted checkout page | GET/POST with query parameters or form fields |
| verifyWebhookSignature(Request, Credentials) | Check push webhook authenticity (signature only) | POST with raw JSON body and signature header |
| handleWebhook(Request, Credentials) | NEW — process push webhook payload, return WebhookData | POST with raw JSON body |
| parseWebhookData(Request) | Parse webhook body into canonical WebhookData DTO | POST with raw JSON body |

| SECTION 4 — PHASE-BY-PHASE BUILD PLAN |
| :---- |

Each phase has a clear input, output, and acceptance gate. No phase begins until the previous phase's gate is passed. Human review is required at gates marked ★.

## **Phase 0 — Repository & Toolchain Setup**

| OUTPUT | Fresh repo with CI passing, PHPStan Level 9 clean on empty src/, Pest running, all config files locked. |
| :---- | :---- |

| Task | Detail | Prompt Guidance |
| :---- | :---- | :---- |
| Init package structure | composer.json, src/, config/, database/migrations/, tests/, routes/ | Prompt: "Create the complete Laravel package scaffold for frolaxhq/laravel-payments with PHP 8.2+, Orchestra Testbench 10, Pest 3, PHPStan 2 \+ Larastan 3 at level 9\. Show all config files." |
| PHPStan config | phpstan.neon.dist at level 9\. Empty phpstan-baseline.neon. Add checkModelProperties, checkOctaneCompatibility. | Prompt: "Write phpstan.neon.dist for level 9 with larastan, no baseline suppressions." |
| Pest config | pest.php with uses(TestCase::class) in Arch group, Dataset declarations. | Prompt: "Write pest.php configuration for a Laravel package with arch tests enabled." |
| CI workflow | GitHub Actions: test on PHP 8.2/8.3 × Laravel 11/12, run phpstan, run pint. | Prompt: "Write .github/workflows/run-tests.yml matrix covering PHP 8.2, 8.3 × Laravel 11, 12." |
| EditorConfig & Pint | Copy from original. Run pint on empty src/ to confirm baseline. | No AI needed — direct copy. |

**★ Gate 0:** composer test passes, composer analyse returns 0 errors, GitHub Actions green.

## **Phase 1 — Enums & Exceptions (Zero Logic)**

| OUTPUT | All enum files and exception classes committed. PHPStan still clean. Arch test: no enum imports outside src/Enums/. |
| :---- | :---- |

| File | Changes from Original | Prompt |
| :---- | :---- | :---- |
| Enums/PaymentStatus.php | Add isTerminal(), isSuccessful(), canTransitionTo(self $next): bool — state machine validation | "Write PaymentStatus enum with a canTransitionTo() guard that rejects invalid transitions e.g. Completed→Pending." |
| Enums/SubscriptionStatus.php | Same as original — methods already correct | "Copy SubscriptionStatus enum, confirm all helper methods are correct." |
| Enums/RefundStatus.php | Same as original | Copy directly. |
| Enums/WebhookEventType.php | Same as original | Copy directly. |
| Enums/AttemptStatus.php | Same as original | Copy directly. |
| Enums/BillingInterval.php | Same as original | Copy directly. |
| Enums/LogLevel.php | Same as original | Copy directly. |
| Exceptions/\*.php | All 7 exception classes unchanged | Copy directly. Confirm each extends correct base. |

**★ Gate 1:** PHPStan Level 9 clean. Arch test confirms no business logic in enums beyond helper methods.

## **Phase 2 — Data Transfer Objects**

| OUTPUT | All DTO classes with readonly properties, named constructors, and toArray(). Unit tests for each DTO covering construction, validation, and serialization. |
| :---- | :---- |

| DTO | Key Fix / Addition | Test Requirements |
| :---- | :---- | :---- |
| Money | readonly, negative amount throws InvalidArgumentException | test: negative amount throws, zero allowed, currency uppercased automatically |
| Address | readonly, all nullable | test: fromArray with partial data, toArray omits nulls |
| Customer | readonly | test: fromArray with and without address |
| Order \+ OrderItem | readonly | test: fromArray with items array |
| Urls | readonly | test: fromArray with partial URLs |
| Context | readonly | test: fromArray |
| Payload | readonly, auto-generate idempotency key when config enabled | test: missing order throws, missing money throws, idempotency key auto-generated, idempotency key respected when provided |
| Credentials | readonly, get() method, toSafeArray() redacts credentials | test: get() returns correct value, toSafeArray() never exposes credentials |
| GatewayResult | readonly, requiresRedirect(), isSuccessful(), isPending() | test: all helper methods with each status |
| RefundPayload | readonly | test: missing payment\_id throws |
| StatusPayload | readonly | test: construction and serialization |
| SubscriptionPayload \+ Plan \+ SubscriptionItem | readonly throughout | test: nested fromArray, trial\_days inheritance from plan |
| WebhookData | readonly | test: isPaymentEvent(), isSubscriptionEvent(), isInvoiceEvent() |

**Prompt template for each DTO:**

"Write a PHP 8.2 readonly DTO class \[Name\] in namespace Frolax\\Payment\\Data. Properties: \[list\]. Include static fromArray(array): self with validation. Include toArray(): array omitting nulls. Write Pest tests covering: valid construction, invalid construction, toArray output, fromArray with missing required fields."

**★ Gate 2:** All DTO tests pass. PHPStan Level 9 clean. No mutable properties on any DTO.

## **Phase 3 — Contracts (Interfaces)**

| OUTPUT | All interface files. New WebhookHandlerContract added. SupportsWebhookVerification updated to require handleWebhook(). No implementations yet. |
| :---- | :---- |

| Interface | Change from Original | Prompt |
| :---- | :---- | :---- |
| GatewayDriverContract | No change. verify() stays for return-URL flow. | Copy directly. |
| SupportsWebhookVerification | Add handleWebhook(Request, Credentials): WebhookData method. This is the missing method that separates webhook handling from return-URL verification. | "Add handleWebhook(Request $request, Credentials $credentials): WebhookData to SupportsWebhookVerification." |
| SupportsHostedRedirect | No change | Copy directly. |
| SupportsRefund | No change | Copy directly. |
| SupportsStatusQuery | No change | Copy directly. |
| SupportsRecurring | No change | Copy directly. |
| SupportsTokenization | No change | Copy directly. |
| SupportsCOD | No change | Copy directly. |
| SupportsBillingPortal | No change | Copy directly. |
| CredentialsRepositoryContract | No change | Copy directly. |
| PaymentLoggerContract | No change | Copy directly. |
| GatewayAddonContract | No change | Copy directly. |

**★ Gate 3:** PHPStan Level 9 clean. Arch test: all Supports\* interfaces live in src/Contracts/.

## **Phase 4 — Credentials System**

| OUTPUT | Three repository implementations with tests. The double-decrypt bug is permanently eliminated. Integration tests against SQLite. |
| :---- | :---- |

| Class | Key Fix | Tests Required |
| :---- | :---- | :---- |
| Credentials DTO | Already done in Phase 2 | — |
| EnvCredentialsRepository | No change from original — this one was correct | test: returns null when config key absent, returns Credentials when present, tenantId flows through |
| DatabaseCredentialsRepository | REMOVE all manual Crypt:: calls. Use encrypted:array cast on the model — the cast handles encryption/decryption automatically. DatabaseRepo just reads the array. | test: returns null when no record, returns correct Credentials, tenant-specific record takes priority over global, time-window filtering works, priority ordering works |
| CompositeCredentialsRepository | No change — logic was correct | test: database-first, falls back to env, has() checks both |
| PaymentGatewayCredential model | credentials cast is encrypted:array — this is correct. DatabaseRepo must NOT manually decrypt. Verify cast handles it end-to-end. | test: storing and retrieving credentials round-trip correctly in SQLite |

**Critical prompt for DatabaseCredentialsRepository:**

"Rewrite DatabaseCredentialsRepository. The PaymentGatewayCredential model already uses credentials cast as encrypted:array — Laravel's cast handles encryption/decryption automatically. The repository should simply read $record-\>credentials as an array. Do NOT call Crypt::decryptString() anywhere in this class."

**★ Gate 4:** All credential tests pass including round-trip DB test. PHPStan Level 9 clean.

## **Phase 5 — GatewayRegistry**

| OUTPUT | GatewayRegistry with enforced capability validation at registration time. Unit tests for all registry operations. |
| :---- | :---- |

| Method | Change from Original | Tests |
| :---- | :---- | :---- |
| register() | When capabilities array is non-empty, validate each entry is an interface that exists via interface\_exists(). Throw InvalidArgumentException on unknown capability. | test: valid registration succeeds, unknown capability string throws, duplicate key overwrites |
| registerAddon() | No logic change — delegates to register() | test: addon properties flow through correctly |
| resolve() | No change | test: throws GatewayNotFoundException for unknown key, returns driver instance for known key |
| supporting() | No change | test: returns correct subset |
| capabilities() | No change | test: returns registered capabilities array |
| credentialSchema() | No change | test: returns empty array when no addon, returns schema from addon |
| resolvedCapabilities() | No change — reflects driver implements | test: returns correct interfaces from a concrete driver |

**★ Gate 5:** All registry tests pass. Arch test: GatewayRegistry has no dependency on any HTTP or DB layer.

## **Phase 6 — Database Migrations & Models**

| OUTPUT | Migration file fixed (adds methods table key, subscription config key). All Eloquent models with correct table resolution, casts, relationships, and scopes. Model tests using SQLite. |
| :---- | :---- |

| Fix | Detail |
| :---- | :---- |
| PaymentMethod::getTable() | Original uses config key payment\_methods — config file defines key as methods. Fix: config('payments.tables.methods', 'payment\_methods') |
| Subscription::plan() | Add models.plan to config/payments.php with default App\\Models\\Plan. Plan relationship now reads from correct key. |
| config/payments.php | Add tables.methods key, tables.subscription\_items key, tables.subscription\_usage key, models.plan key. |
| All models | Add explicit @property PHPDoc for all $guarded columns so PHPStan Level 9 can resolve property access. |
| PaymentModel | Add missing scopeForOrder() scope for completeness. |
| Subscription | Remove belongsTo($planModel) with string variable — use @phpstan-ignore only if model is configurable (document the limitation). |

**Prompt for models:**

"Write the PaymentModel Eloquent model for Laravel 11\. Use HasUlids. Table name from config payments.tables.payments. All column properties documented with @property PHPDoc for PHPStan Level 9\. Enum casts for status. Relationships: hasMany attempts, webhookEvents, refunds, logs. Scopes: pending(), completed(), failed(), forGateway(), forTenant(). Write Pest tests using Orchestra Testbench with SQLite."

**★ Gate 6:** All model tests pass. Migrations run without errors on fresh SQLite. PHPStan Level 9 clean on all model files.

## **Phase 7 — The Corrected Payment Pipeline**

| OUTPUT | All pipeline step classes with proper readonly context, transaction boundaries, and corrected idempotency logic. This is the most critical phase. |
| :---- | :---- |

| Step Class | Critical Implementation Detail | Tests |
| :---- | :---- | :---- |
| PaymentContext | All properties readonly except result and exception which use a withResult()/withException() named constructor pattern to return a new context. | test: context is immutable, withResult() returns new instance, original unchanged |
| ResolveIdempotency | If idempotency key exists AND status is any value (including pending): return existing result. Only proceed if no record exists at all. | test: pending key returns existing, completed key returns existing, absent key proceeds to next step |
| ValidatePayload | Call SchemaValidator. Throw InvalidPayloadException with error array if validation fails. This stops the pipeline before any DB write. | test: invalid payload throws before any DB write, valid payload passes through |
| PersistPaymentRecord | Wrap ONLY the PaymentModel::create() call in DB::transaction(). Do not wrap gateway call. | test: DB record created with correct values, transaction rolls back on DB exception |
| ExecuteGatewayCall | Call driver-\>create(). On Throwable: store in context-\>withException($e). Always call next(). Never rethrow here. | test: successful call stores result, exception stored not thrown, timing recorded |
| PersistAttempt | Wrap in DB::transaction(). Record attempt regardless of success/failure. | test: attempt recorded on success, attempt recorded on failure with error data |
| UpdatePaymentStatus | Wrap in DB::transaction(). Update status and gateway\_reference. On exception in context: set status to Failed. | test: status updated to result status, status set to Failed when exception in context |
| DispatchPaymentEvent | Dispatch PaymentCreated with correct paymentId from context. Dispatch PaymentFailed if exception. After dispatch: rethrow stored exception. | test: PaymentCreated dispatched with correct data, PaymentFailed dispatched on error, exception rethrown after dispatch |

**Prompt for pipeline (use this exact structure):**

"Write the PHP 8.2 PaymentContext class for a Laravel payment pipeline. All properties must be readonly. Mutable state transitions must use named constructors: withPaymentId(string): self, withResult(GatewayResult): self, withException(Throwable): self, withTiming(float): self — each returns a new instance with the updated value. Write Pest tests proving immutability."

**★ Gate 7:** All pipeline step tests pass. Integration test: full payment flow through all steps on SQLite. PHPStan Level 9 clean.

## **Phase 8 — Payment, SubscriptionManager, RefundManager**

| OUTPUT | Three manager classes using HasGatewayContext trait. All bugs from original fixed. Integration tests covering full flows. |
| :---- | :---- |

| Fix | Manager | Prompt Guidance |
| :---- | :---- | :---- |
| verifyFromRequest() passes correct paymentId | Payment | "In verifyFromRequest(), after successful verify(), look up the PaymentModel by gateway\_reference and pass model-\>id as paymentId to PaymentVerified event, not the gateway reference itself." |
| customer\_id stores customer-\>id not email | SubscriptionManager | "In SubscriptionManager::create(), customer\_id should be $payload-\>customer?-\>id, not $payload-\>customer?-\>email. customer\_email is the email field." |
| SubscriptionCreated event receives correct types | SubscriptionManager | "SubscriptionCreated constructor expects string $subscriptionId — always pass (string) $subscription-\>id. amount must be float — cast explicitly." |
| forwardToSubscriptionManager() preserves all context | Payment | "Ensure forwardToSubscriptionManager() and forwardToRefundManager() both clone gateway, profile, context, and oneOffCredentials." |
| RefundManager transaction on DB writes | RefundManager | "Wrap PaymentRefund::create() and subsequent update() in DB::transaction() with rollback on gateway exception." |

**★ Gate 8:** Integration tests: create payment, verify payment, create subscription, cancel, pause, resume, refund. All pass on SQLite. PHPStan clean.

## **Phase 9 — HTTP Controllers & Routes**

| OUTPUT | Fixed WebhookController using handleWebhook() not verify(). ReturnController and CancelController unchanged functionally. Feature tests for all three. |
| :---- | :---- |

| Fix | Detail |
| :---- | :---- |
| WebhookController — use handleWebhook() | When driver implements SupportsWebhookVerification, call driver-\>handleWebhook($request, $creds) to get WebhookData. Store the WebhookData in the webhook event record. Update payment status from webhookData-\>paymentStatus if present. |
| WebhookController — response codes | Signature invalid: 401 not 403\. Already processed: 200 with body "Already processed". Unexpected error: 500 with safe message. Success: 200\. |
| WebhookController — raw body signature | Pass $request-\>getContent() (raw body) to verifyWebhookSignature(), not decoded payload. Real gateway signatures are computed on the raw body. |
| ReturnController — unchanged | Logic was functionally correct. Keep as-is. |
| CancelController — unchanged | Logic was functionally correct. Keep as-is. |

**Prompt for WebhookController:**

"Rewrite WebhookController. When driver instanceof SupportsWebhookVerification: (1) call verifyWebhookSignature($request, $creds) — pass $request-\>getContent() as the raw body to the driver, not $request-\>all(); (2) call handleWebhook($request, $creds) to get WebhookData DTO; (3) use WebhookData to update payment status. Do NOT call driver-\>verify() in the webhook controller."

**★ Gate 9:** Feature tests for webhook receipt, return callback, cancel callback all pass. PHPStan clean.

## **Phase 10 — Logging, Services, Commands**

| OUTPUT | PaymentLogger, SchemaValidator, WebhookRouter, WebhookRetryPolicy unchanged functionally. Commands fixed. CLI tests. |
| :---- | :---- |

| Component | Fix Required | Prompt |
| :---- | :---- | :---- |
| PaymentLogger | No logic change. Add proper PHPDoc and type hints for PHPStan 9\. | Copy and add types. |
| SchemaValidator | No logic change | Copy directly. |
| WebhookRetryPolicy | No logic change | Copy directly. |
| WebhookRouter | No logic change | Copy directly. |
| MakeGatewayCommand — use block bug | When capabilities include refund: add RefundPayload to use block. When capabilities include status: add StatusPayload to use block. When capabilities include recurring: add SubscriptionPayload to use block. | "Fix MakeGatewayCommand::generateDriverClass() to add the correct use statements for RefundPayload, StatusPayload, SubscriptionPayload based on which capabilities are selected." |
| ReplayWebhookCommand — synthetic request | Use Request::create() with content parameter for JSON body: Request::create(uri, 'POST', \[\], \[\], \[\], $serverHeaders, json\_encode($event-\>payload)). This puts the payload in the raw body, not $\_POST. | "Fix ReplayWebhookCommand to put stored webhook payload in the raw request body using json\_encode($event-\>payload) as the content parameter, not the parameters array." |
| ListGatewaysCommand | No logic change | Copy directly. |
| ValidateCredentialsCommand | No logic change | Copy directly. |

**★ Gate 10:** All command tests pass. Generated gateway driver from MakeGatewayCommand passes php \-l. PHPStan Level 9 clean on all new files.

## **Phase 11 — Testing Infrastructure & Fakes**

| OUTPUT | PaymentFake with instance-level state. FakeDriver implementing SupportsWebhookVerification fully. PaymentFake test proving no cross-test leakage. |
| :---- | :---- |

| Fix | Detail |
| :---- | :---- |
| PaymentFake::$recorded | Change from static array to instance array. Add reset(): void method for test teardown. The static pattern caused cross-test contamination. |
| PaymentFake test isolation | Write explicit test: two separate PaymentFake instances have independent $recorded. Original static field would fail this. |
| FakeDriver \+ SupportsWebhookVerification | Implement all four methods of SupportsWebhookVerification including parseWebhookData(). Return a default WebhookData from parseWebhookData() in the fake. |
| FakeDriver \+ capabilities | FakeDriver implements ALL capability interfaces so it can substitute any real driver in tests. |
| Payment::fake() static | Update to return new instance — since $recorded is now instance-level, the fake returned must be the same instance used. |

**Prompt:**

"Rewrite PaymentFake. Change protected static array $recorded to protected array $recorded \= \[\]. Add a reset(): void method that sets $recorded \= \[\]. Add a test that creates two PaymentFake instances, calls charge() on each, and asserts their recorded() arrays are independent."

**★ Gate 11:** All fake/testing tests pass. Cross-test isolation test passes. Full test suite: 0 failures.

## **Phase 12 — Discovery, ServiceProvider & Config**

| OUTPUT | PaymentServiceProvider, GatewayAddonServiceProvider, PaymentConfig all wired correctly. Package auto-discovery works. Config file complete with all missing keys added. |
| :---- | :---- |

| Fix | Detail |
| :---- | :---- |
| config/payments.php — add missing keys | Add models.plan (default App\\Models\\Plan). Add tables.methods explicitly. Verify all table keys match model getTable() calls exactly. |
| PaymentConfig | Add models.plan accessor. Verify all config keys exist. |
| PaymentServiceProvider | No structural change. Verify all singletons registered correctly. |
| GatewayAddonServiceProvider | No change — logic was correct. |
| DummyDriver (built-in) | Add a DummyDriver that implements GatewayDriverContract for testing. Register it as the default gateway. This eliminates the "no gateway registered" error in fresh installs. |

**★ Gate 12:** vendor:publish publishes config and migrations. Fresh migrate \--run on SQLite creates all tables. Package loads in a blank Laravel app.

## **Phase 13 — Architecture Tests & Final Quality Gate**

| OUTPUT | Full Pest architecture test suite enforcing layer boundaries. Final PHPStan run. Final test count. README updated. |
| :---- | :---- |

| Arch Test | Rule |
| :---- | :---- |
| DTO layer is pure | Classes in Data\\ must not use Illuminate\\Database or Illuminate\\Http |
| Enums have no side effects | Classes in Enums\\ must not use any non-PHP-native dependency |
| Contracts are interfaces only | Everything in Contracts\\ must be an interface |
| Pipeline steps have single dependency | Steps\\ classes must not inject more than 2 dependencies |
| Models are not in business logic | Models\\ must not be imported in Data\\, Enums\\, Contracts\\ |
| No magic strings for payment status | No string literal matching any PaymentStatus value outside of enum files and migrations |
| No static mutable state in production code | No static property with default non-null value in src\\ (excluding ServiceProvider) |
| All exceptions extend RuntimeException | Exceptions\\ classes must extend \\RuntimeException |

**★ Gate 13 (Final):** PHPStan Level 9 — 0 errors, 0 suppressions. All arch tests pass. composer test green. composer analyse green. pint \--test clean.

| SECTION 5 — HOW TO PROMPT CLAUDE EFFECTIVELY FOR THIS BUILD |
| :---- |

## **5.1  The Golden Prompting Rules for This Project**

| Rule | Do This | Not This |
| :---- | :---- | :---- |
| One class per prompt | Prompt: "Write ONLY the PaymentContext class. No other files." | "Write the pipeline" — Claude writes 8 files at once, half with bugs |
| Always specify the fix | "Fix: Remove all Crypt:: calls. The cast handles decryption." | "Rewrite DatabaseCredentialsRepository" — Claude may reintroduce the bug |
| Demand tests in the same prompt | "Write X class AND its Pest tests in the same response." | Asking for class then tests separately — Claude forgets constraints |
| State PHPStan level explicitly | "This must pass PHPStan Level 9\. No mixed types." | Implicit — Claude defaults to level 5 patterns |
| Give the wrong code to fix | Paste the buggy original \+ "This has bug X. Fix it by doing Y." | "Rewrite this" without context — Claude reproduces the same bugs |
| Lock readonly early | "All DTO properties must be readonly. Verify before finishing." | Assume Claude knows the design intention |
| Ask for a type-check pass | "Review your response and list any place PHPStan Level 9 would complain." | Assume the output is clean |

## **5.2  Prompt Templates by Phase**

**Phase 0-1 (Setup & Enums) — Use Opus for architecture decisions, Sonnet for file generation:**

| TEMPLATE | "You are building frolaxhq/laravel-payments from scratch. PHP 8.2+, PHPStan Level 9, Pest 3, Laravel 11/12. Write \[FILE\] in namespace Frolax\\Payment\\\[NAMESPACE\]. Requirements: \[BULLET LIST OF SPECIFIC REQUIREMENTS\]. This must produce zero PHPStan Level 9 errors. After writing the class, review it yourself and flag any type issues." |
| :---- | :---- |

**Phase 2-3 (DTOs & Contracts) — Use Sonnet:**

| TEMPLATE | "Write the \[Name\] readonly DTO in namespace Frolax\\Payment\\Data. Properties: \[list with types\]. Rules: (1) all properties readonly, (2) static fromArray(array $data): self validates required fields and throws \\InvalidArgumentException, (3) toArray(): array uses array\_filter to omit nulls. Write the class, then immediately write the Pest tests file testing: valid construction, each invalid input case, toArray output shape, fromArray round-trip." |
| :---- | :---- |

**Phase 7 (Pipeline) — Use Opus:**

| TEMPLATE | "Write pipeline step \[ClassName\] implementing the handle(PaymentContext $context, Closure $next): mixed contract. This step must: \[SPECIFIC RESPONSIBILITY\]. Wrap DB writes in DB::transaction(). Return $next($newContext) where $newContext is the result of $context-\>with\[X\](). Do NOT mutate the context object. Write Pest tests that verify: (1) the step passes context to $next, (2) DB::transaction is used for writes, (3) the specific business rule \[X\]." |
| :---- | :---- |

**Verification prompt (run after every phase):**

| VERIFY | "Review all code written in this conversation. List any location where: (1) a property is not readonly on a DTO, (2) Crypt:: is called manually, (3) a static property holds mutable state, (4) $request-\>all() is passed to a method that should receive raw body, (5) customer\_id is set from an email field, (6) PHPStan Level 9 would report a mixed type or undefined property. For each finding, show the fix." |
| :---- | :---- |

## **5.3  Which Model to Use for Which Phase**

| Phase | Recommended Model | Reason |
| :---- | :---- | :---- |
| 0 — Toolchain setup | Either (deterministic config files) | No reasoning required — just config syntax |
| 1 — Enums & Exceptions | Sonnet | Simple, repetitive — Sonnet is faster and cheaper |
| 2 — DTOs | Sonnet | Structured, mechanical — good for Sonnet |
| 3 — Contracts | Sonnet | Interfaces are pure declarations |
| 4 — Credentials | Opus | The double-decrypt bug requires careful reasoning to avoid reintroducing |
| 5 — Registry | Sonnet | Moderately complex |
| 6 — Models & Migrations | Sonnet | Mechanical — PHPDoc and casts |
| 7 — Pipeline (CRITICAL) | Opus | Most complex phase — immutability, transactions, idempotency logic |
| 8 — Managers | Opus | Multiple interacting bugs to fix — needs strong reasoning |
| 9 — HTTP Controllers | Opus | Webhook flow requires careful separation of verify() vs handleWebhook() |
| 10 — Commands | Sonnet | Mechanical — code generation fixes |
| 11 — Testing Fakes | Sonnet | Mechanical once the static→instance fix is specified |
| 12 — ServiceProvider | Sonnet | Wire-up — mechanical |
| 13 — Arch Tests | Opus | Architecture test rules require precise reasoning |

| SECTION 6 — QUALITY TARGETS & DEFINITION OF DONE |
| :---- |

## **6.1  Mandatory Quality Gates (All Must Pass)**

| Gate | Command | Required Result |
| :---- | :---- | :---- |
| PHPStan | composer analyse | Level 9, 0 errors, 0 suppressions, phpstan-baseline.neon empty |
| Tests | composer test | All pass, minimum 150 tests / 400 assertions |
| Architecture | pest \--group=arch | All architecture tests pass |
| Code Style | pint \--test | Zero violations |
| Static types | PHPStan checkModelProperties=true | All model property accesses type-safe |
| Fresh install | php artisan migrate \--run on blank Laravel app | All tables created, no errors |
| Generated code | php \-l on MakeGatewayCommand output | No parse errors for any capability combination |

## **6.2  Test Coverage Targets by Layer**

| Layer | Target Tests | Key Scenarios |
| :---- | :---- | :---- |
| DTOs | 13+ test files, 3+ tests each | Valid construction, invalid inputs, toArray, fromArray, immutability |
| Credentials | 3 repository classes × 5+ tests | Null return, correct return, priority, time-windows, round-trip encryption |
| GatewayRegistry | 10+ tests | Register, resolve, not-found throws, capability filter, addon registration |
| Pipeline steps | 8 steps × 4+ tests | Happy path, failure path, transaction rollback, idempotency short-circuit |
| Managers (Payment, Sub, Refund) | 3 × 8+ tests | Create, verify, status, subscribe, cancel, pause, resume, refund |
| Controllers | 3 × 4+ tests | Happy path, invalid signature, already processed, missing credentials |
| Fakes | 10+ tests | PaymentFake isolation, FakeDriver all methods, assertion helpers |
| Commands | 4 × 3+ tests | Successful run, no gateways, missing credentials, generated file validity |
| Arch tests | 8+ rules | Layer boundaries, no magic strings, no static mutable state |

## **6.3  The Six Bugs Must Be Regression-Tested**

Each original bug must have an explicit regression test that would have caught it:

| Bug | Regression Test |
| :---- | :---- |
| PaymentFake static state leak | test: two PaymentFake instances have independent recorded() arrays |
| Idempotency allows duplicate inserts | test: calling charge() twice with same idempotency\_key returns same result, only one DB record exists |
| Double credential decryption | test: credential stored via DB round-trips correctly and is returned as array (not empty array) |
| customer\_id \= email | test: created Subscription has customer\_id equal to customer-\>id, customer\_email equal to customer-\>email |
| verifyFromRequest wrong paymentId | test: PaymentVerified event paymentId equals the internal payment model ID, not the gateway reference |
| WebhookController calls verify() | test: WebhookController never calls driver-\>verify() — uses handleWebhook() instead (assert via mock) |

| SECTION 7 — COMPLETE FILE MAP |
| :---- |

Files marked ✓ can be copied from original with minor type fixes. Files marked ★ require significant rewriting. Files marked \+ are new.

| File | Status | Phase |
| :---- | :---- | :---- |
| src/Enums/\*.php (all 7\) | ✓ Copy \+ PHPDoc types | 1 |
| src/Exceptions/\*.php (all 7\) | ✓ Copy | 1 |
| src/Data/Money.php | ★ Make readonly | 2 |
| src/Data/Address.php | ★ Make readonly | 2 |
| src/Data/Customer.php | ★ Make readonly | 2 |
| src/Data/Order.php \+ OrderItem.php | ★ Make readonly | 2 |
| src/Data/Urls.php \+ Context.php | ★ Make readonly | 2 |
| src/Data/Payload.php | ★ Make readonly, fix idempotency null check | 2 |
| src/Data/Credentials.php | ★ Make readonly | 2 |
| src/Data/GatewayResult.php | ★ Make readonly | 2 |
| src/Data/RefundPayload.php \+ StatusPayload.php | ★ Make readonly | 2 |
| src/Data/SubscriptionPayload.php \+ Plan.php \+ SubscriptionItem.php | ★ Make readonly | 2 |
| src/Data/WebhookData.php | ★ Make readonly | 2 |
| src/Contracts/GatewayDriverContract.php | ✓ Copy | 3 |
| src/Contracts/SupportsWebhookVerification.php | ★ Add handleWebhook() method | 3 |
| src/Contracts/Supports\*.php (all others) | ✓ Copy | 3 |
| src/Contracts/CredentialsRepositoryContract.php | ✓ Copy | 3 |
| src/Contracts/PaymentLoggerContract.php | ✓ Copy | 3 |
| src/Contracts/GatewayAddonContract.php | ✓ Copy | 3 |
| src/Credentials/EnvCredentialsRepository.php | ✓ Copy | 4 |
| src/Credentials/DatabaseCredentialsRepository.php | ★ Remove ALL Crypt:: calls | 4 |
| src/Credentials/CompositeCredentialsRepository.php | ✓ Copy | 4 |
| src/GatewayRegistry.php | ★ Add capability validation at register() | 5 |
| database/migrations/create\_payments\_tables.php.stub | ★ Verify all config keys match models | 6 |
| config/payments.php | ★ Add models.plan, verify all tables keys | 6 |
| src/Models/PaymentModel.php | ★ Add @property PHPDoc for all columns | 6 |
| src/Models/Subscription.php | ★ Fix plan() relationship config key, add @property PHPDoc | 6 |
| src/Models/PaymentMethod.php | ★ Fix getTable() config key | 6 |
| src/Models/PaymentAttempt.php | ✓ Copy \+ PHPDoc | 6 |
| src/Models/PaymentRefund.php | ✓ Copy \+ PHPDoc | 6 |
| src/Models/PaymentWebhookEvent.php | ✓ Copy \+ PHPDoc | 6 |
| src/Models/PaymentGateway.php | ✓ Copy \+ PHPDoc | 6 |
| src/Models/PaymentGatewayCredential.php | ✓ Copy \+ PHPDoc | 6 |
| src/Models/PaymentLog.php | ✓ Copy \+ PHPDoc | 6 |
| src/Models/SubscriptionItem.php | ✓ Copy \+ PHPDoc | 6 |
| src/Models/SubscriptionUsage.php | ✓ Copy \+ PHPDoc | 6 |
| src/Pipeline/PaymentContext.php | ★ All readonly, named constructors for mutations | 7 |
| src/Pipeline/Steps/ResolveIdempotency.php | ★ Renamed \+ logic fixed | 7 |
| src/Pipeline/Steps/ValidatePayload.php | \+ New step | 7 |
| src/Pipeline/Steps/PersistPaymentRecord.php | ★ Add DB::transaction() | 7 |
| src/Pipeline/Steps/ExecuteGatewayCall.php | ✓ Minor type fix | 7 |
| src/Pipeline/Steps/PersistAttempt.php | ★ Add DB::transaction() | 7 |
| src/Pipeline/Steps/UpdatePaymentStatus.php | ★ Add DB::transaction() | 7 |
| src/Pipeline/Steps/DispatchPaymentEvent.php | ✓ Minor | 7 |
| src/Payment.php | ★ Fix verifyFromRequest() paymentId | 8 |
| src/SubscriptionManager.php | ★ Fix customer\_id, fix event dispatch types | 8 |
| src/RefundManager.php | ★ Add DB::transaction() | 8 |
| src/Concerns/HasGatewayContext.php | ✓ Copy \+ PHPDoc | 8 |
| src/Http/Controllers/WebhookController.php | ★ Use handleWebhook(), not verify() | 9 |
| src/Http/Controllers/ReturnController.php | ✓ Copy | 9 |
| src/Http/Controllers/CancelController.php | ✓ Copy | 9 |
| src/Logging/PaymentLogger.php | ✓ Copy \+ type hints | 10 |
| src/Services/SchemaValidator.php | ✓ Copy | 10 |
| src/Services/WebhookRetryPolicy.php | ✓ Copy | 10 |
| src/Services/WebhookRouter.php | ✓ Copy | 10 |
| src/Commands/MakeGatewayCommand.php | ★ Fix use block generation | 10 |
| src/Commands/ReplayWebhookCommand.php | ★ Fix synthetic request body | 10 |
| src/Commands/ListGatewaysCommand.php | ✓ Copy | 10 |
| src/Commands/ValidateCredentialsCommand.php | ✓ Copy | 10 |
| src/Testing/FakeDriver.php | ★ Implement parseWebhookData() | 11 |
| src/Testing/PaymentFake.php | ★ Instance $recorded, add reset() | 11 |
| src/Discovery/GatewayAddonServiceProvider.php | ✓ Copy | 12 |
| src/PaymentConfig.php | ★ Add models.plan | 12 |
| src/PaymentServiceProvider.php | ★ Wire DummyDriver registration | 12 |
| src/Drivers/DummyDriver.php | \+ New — built-in test driver | 12 |
| src/Facades/Payment.php | ✓ Copy | 12 |
| src/Events/\*.php (all 13\) | ✓ Copy | All phases |
| routes/web.php | ✓ Copy | 9 |
| tests/Arch/\*.php | \+ New architecture tests | 13 |

| SECTION 8 — ESTIMATED TIMELINE |
| :---- |

| Phase | Estimated Prompts | Estimated Hours | Complexity |
| :---- | :---- | :---- | :---- |
| 0 — Toolchain | 5–8 | 2–3h | Low |
| 1 — Enums & Exceptions | 10–15 | 1–2h | Low |
| 2 — DTOs | 13 classes × 1–2 prompts | 3–4h | Low-Medium |
| 3 — Contracts | 5–8 | 1h | Low |
| 4 — Credentials | 8–12 | 2–3h | Medium (encryption bug) |
| 5 — Registry | 5–8 | 1–2h | Low-Medium |
| 6 — Models & Migrations | 12–16 | 3–4h | Medium |
| 7 — Pipeline | 10–15 | 4–6h | High |
| 8 — Managers | 8–12 | 3–4h | High |
| 9 — HTTP Controllers | 6–10 | 2–3h | Medium |
| 10 — Logging/Services/Commands | 10–14 | 2–3h | Medium |
| 11 — Testing Fakes | 6–8 | 2–3h | Medium |
| 12 — ServiceProvider & Config | 5–8 | 1–2h | Low |
| 13 — Arch Tests & Final Gate | 8–12 | 2–3h | Medium |
| TOTAL | \~115–155 prompts | \~30–45 hours | — |

| NOTE | "Hours" refers to active human review time, not Claude generation time. Most of this work is reviewing Claude's output, running the quality gates, and writing the precise follow-up prompts when gates fail. Plan for \~2-3 revision rounds per phase for the high-complexity phases (7, 8, 9). |
| :---- | :---- |

This document defines the complete rebuild plan for **frolaxhq/laravel-payments**. Follow the phases in order. Do not skip quality gates. The goal is not to finish quickly — it is to finish correctly.

