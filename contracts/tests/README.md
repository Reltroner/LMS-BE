# Phase 3B-01 Contract Validation

Run from repository root using PHP 8.3+:

```powershell
php -l contracts/tests/validate.php
php contracts/tests/validate.php
```

The harness is a **source-only, deterministic** test, using PHP core `json_decode` and the static files under `contracts/`. It checks 26 exact frozen routes, 19 exact capability strings, denied guest offerings, reserved permissions, schema links, Problem Details error samples, booking Idempotency-Key, cursor pagination, operation owner/client/authorization metadata, and synthetic positive/negative policy simulations.

**It does not call a Laravel application, Keycloak, HTTP server, Redis, PostgreSQL or external LLM.** Model authorization fixture PASS must not be claimed as actual middleware/production denial. Record command output and Git HEAD SHA to make an acceptance claim. CI integration is scheduled separately for Phase 3B-05; provider/consumer runtime compatibility requires later verification.
