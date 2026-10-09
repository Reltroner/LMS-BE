# Reltroner LMS — Phase 3B-01 Contract Artifacts

**Status:** Source-only contract candidate; **not** deployed service implementation and **not** Phase 4/production authorization.

## Binding authority

- [Phase 0C FROZEN physical contract](https://github.com/Reltroner/progress-documentation/blob/main/lms/master-infrastructure-placement-contract.md).
- [Phase 1 FROZEN logical/API contract](https://github.com/Reltroner/progress-documentation/blob/main/lms/logical-service-boundary-api-contract.md): exact 26 external operations.
- [FZ-11 design freeze](https://github.com/Reltroner/progress-documentation/blob/main/lms/reltroner-lms-phase3a-fz11-postmerge-activation-20261009.md) anchored at `b9390a06ebc5db5377059a99109d59fea092cccb`.
- [FZ-10 Phase 3B work order](https://github.com/Reltroner/progress-documentation/blob/main/lms/reltroner-lms-phase3a-04-fz10-phase3b-entry-exit-authorization-20261009.md) defining `B3-AC01..04` for 3B-01.

## Contents

| File | Authority / purpose |
|---|---|
| `openapi/v1/openapi.json` | OpenAPI **3.1** contract for exactly **26** frozen method/path public operations; explicit OIDC bearer requirement, RFC 7807 compatible errors, cursor parameters and booking Idempotency-Key |
| `authz/operation-policy.json` | Contract-only operation → domain owner/authorized client/capability/object ownership matrix and exact list of **19** frozen capability values |
| `schemas/implementation-gates.json` | Explicit provisional DTO fields and future provider/runtime hard gates; no silent Phase 3A contract amendment |
| `examples/contract-fixtures.json` | Synthetic sample payloads, 9 positive and 16 negative **model authorization** cases; no secrets or real access tokens |
| `tests/validate.php` | Zero-dependency static assertions and simulated claim-gate tests; **not** Laravel/Keycloak/HTTP execution |

## Execute checks locally in non-production

From root of `Reltroner/LMS-BE` feature branch with **PHP 8.3+**:

```powershell
php -l contracts/tests/validate.php
php contracts/tests/validate.php
```

The validator uses only PHP's native JSON API and reads files beneath `contracts/`. It does **not** require Composer install, network access, local database, credentials, or a VPS connection. To preserve historical evidence, capture the exact branch/HEAD, output and exit code; do not claim a PASS until the commands actually run.

## Strict security and compatibility choices

1. **Every one of 26 initial API operations is authenticated** in this conservative 3B-01 candidate. The Phase 1 contract allows guest-readable offerings *only if policy permits*. Guest publishing is **not authorized yet**, so both offering endpoints require valid `lms-user` access token plus `mentorship.offering.read`. Opening them to guests needs a separate owner-approved sanitized public projection and privacy tests.
2. The general `lms-user`/ `lms-admin` browser contexts are distinct. Wrong `aud`, `iss`, `azp`, expired token, unrecognized capability, ownership mismatch or unapproved client must fail closed. The frontend's historical `student` fallback is *never* a backend authorization fallback.
3. `GET /api/v1/me` is authenticated but has **no invented new capability**; both recognized LMS clients may read only the safe principal projection.
4. `GET /api/v1/mentorship/availability` is *authenticated booking-oriented*, requiring `mentorship.booking.create.self`; it is not an invented public availability API.
5. `admin.learning.read` and `admin.learning.override` remain **reserved but unrouted**: their appearance in the 19-capability inventory does not authorize fabricated public endpoints.
6. Domain response fields and pagination **limit=100** are deliberately candidate schema values (not Phase 1 freeze). Booking idempotency key format/retention/replay and Keycloak admin adapter lifecycle must be approved in later technical hard gates. A complete provider integration test is required before promotion to a released API.

## B3-AC01..04 acceptance interpretation

- `B3-AC01`: 26 exact method+path API operations, 22 path keys, no new business route family.
- `B3-AC02`: 19 exact capability names; admin.learning reserved; guest offering default denial is explicit (future public policy pending).
- `B3-AC03`: 401/403 `application/problem+json`, Problem Details fields including `code` and `request_id`; pagination `data/page.next_cursor`; booking header `Idempotency-Key`.
- `B3-AC04`: owner/client/capability/ownership policy mapped per operation with positive/negative *model* fixtures and deterministic static test harness. **Not** production-compatible provider/consumer runtime proof.

**Exit report must distinguish** the static validator's actual result from the later **six-service PHPUnit/CI**, FE negative build, provider/consumer handshake and deployed Keycloak security. Nothing here starts or modifies real business handlers.

## Rollback and file scope

All writes in this package are limited to **new files inside `contracts/`**. No `services/**`, `LMS-FE`, `.github/workflows/**`, `.env`, Keycloak, database or production changes. Keep the branch separate from `main`; if rejected, use a reviewed revert of the feature PR rather than destructive reset/cleanup of another workspace.
