# Changelog

## 1.0.0 — 2026-10-16

First release.

- Products API (`GET {prefix}/products`, `GET {prefix}/products/{id}`) served
  from an `EloquentProductSource` in OmniConva's standard format
- Order API (`POST {prefix}/orders`) with Idempotency-Key handling, test-order
  filtering and refusals reported back to OmniConva
- `X-OmniConva-Signature` verification on every route
- `SyncsWithOmniConva` / `SyncsParentWithOmniConva` model traits: product
  webhooks queued after commit, signed and retried
- `OmniConva::orderUpdated()` for order status and tracking
- `<x-omniconva-chat />` Blade component, with signed customer identity
- `omniconva:install`, `omniconva:test` and `omniconva:ping` commands
- Laravel 12 and 13; PHP 8.2+
