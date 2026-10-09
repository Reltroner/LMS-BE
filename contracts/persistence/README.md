# Phase 3B-03 — Four logical owner DBs & nine semantic events

Schema-only and failure-fixture candidate. **No SQL migration, application DB user, production grant or Redis broker is created**. Four service-owned PostgreSQL 18 logical databases; Gateway/Assistant have no default durable domain DB. Local atomic domain+outbox, transient Redis dispatch, idempotent inbox and explicit stale/replay checks. Booking unique occupancy and durable Idempotency-Key are separate requirements. Keycloak role mutations require durable Audit intent and reconciliation; cross-system commit is not atomic.

Run `php -l contracts/tests/validate-persistence.php` and `php contracts/tests/validate-persistence.php` with PHP 8.3+ (synthetic-only fixture checks). **Do not use the hardcoded model expectations as evidence that real DB constraints/outbox/role reconciliation work.** DDL/grant/RBAC specifics, real transaction tests, retry windows and operational retention require later review.
