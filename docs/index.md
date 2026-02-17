---
layout: home

hero:
  name: Laravel Payments
  text: Gateway-Agnostic Payment Abstraction
  tagline: One canonical payload for every gateway—forever. Production-grade, extensible, and developer-friendly.
  actions:
    - theme: brand
      text: Get Started
      link: /guide/introduction
    - theme: alt
      text: View on GitHub
      link: https://github.com/frolaxhq/laravel-payments

features:
  - icon: 📦
    title: One Canonical Payload
    details: Every gateway receives the same immutable DTO structure. Write your integration once, switch gateways without changing a single line of application code.
  - icon: 🔌
    title: Capability-Based Drivers
    details: Drivers implement only the capabilities they support—redirect, webhooks, refunds, status queries. Core never branches on gateway name.
  - icon: 🔍
    title: Auto-Discovery
    details: Install a gateway addon package via Composer and it works instantly. Zero manual registration, zero config file changes.
  - icon: 🏢
    title: Multi-Tenant Credentials
    details: ENV, database, or composite credential storage with tenant isolation, time windows, priority-based rotation, and encrypted storage.
  - icon: 🔒
    title: Idempotent & Replay-Safe
    details: Every payment creation is idempotent. Webhooks are deduplicated and can be replayed safely from stored events via CLI.
  - icon: 📊
    title: Structured Logging
    details: Dot-notation logs with automatic redaction of sensitive data. Dual output to Laravel channels and database for queryable audit trails.
---
