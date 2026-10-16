# Changelog

## 1.0.1 — 2026-10-16

Security hardening, from a review of the package.

- Only an `OrderRefused` exception's message is sent to OmniConva when an
  order can't be created; any other exception (a `QueryException` with SQL
  and customer data in its text) is reported to your handler and answered
  with a generic 500. Throw `OmniConva\Laravel\Exceptions\OrderRefused` to
  refuse an order.
- `SyncsWithOmniConva` / `SyncsParentWithOmniConva` never let a mapping or
  encoding problem abort your save; it is reported instead. Invalid UTF-8 in
  a product is substituted rather than fatal.
- `omniconva:install` reads current values from config (not `env()`, which
  is empty with a cached config), never overwrites a credential with an
  empty value, always quotes what it writes, and warns when secrets are
  passed as options.
- **Breaking for http setups:** `OMNICONVA_WEBHOOK_URL` must be https;
  `http://` is accepted for localhost and `127.x`, and elsewhere only with
  `OMNICONVA_ALLOW_HTTP=1` (a Docker Compose service name, for instance).
  Webhook deliveries no longer follow redirects.
- Idempotency claims carry an owner token, so a request whose claim was
  taken over after a stall can't release or complete the one that replaced
  it; `key` widened to 255. Run `php artisan migrate` after updating
  (a second migration is published with `--tag=omniconva-migrations`).
- Non-finite prices become null instead of "inf"; big numeric ids in order
  payloads stay strings; an empty route prefix is refused; the chat widget
  ignores a non-scalar customer id.

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
