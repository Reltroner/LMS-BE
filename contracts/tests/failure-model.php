<?php
declare(strict_types=1);
/** Pure deterministic fault model; no database, Redis, HTTP or live production I/O. */
function phase3bFailureDecision(array $c): string {
    if (($c['booking_same_key_different_payload'] ?? false) === true && ($c['slot_unique'] ?? false)) {
        return '409';
    }
    if (($c['booking_same_key_same_payload'] ?? false) === true && ($c['slot_unique'] ?? false)) {
        return 'REPLAY_ORIGINAL_RESULT';
    }
    if (($c['different_key_same_occupied_slot'] ?? false) === true && ($c['slot_unique'] ?? false)) {
        return 'SLOT_CONFLICT';
    }
    if (($c['keycloak_mutation_success_audit_confirmation_failed'] ?? false) === true && ($c['intent_committed'] ?? false)) {
        return 'RECONCILE_PENDING';
    }
    if (($c['broker_duplicate'] ?? false) === true && ($c['receipt_already_present'] ?? false)) {
        return ($c['domain_side_effects'] ?? 0) === 1 ? 'ONE_SIDE_EFFECT' : 'DUPLICATE_SIDE_EFFECT_BUG';
    }
    if (($c['out_of_order'] ?? false) === true &&
        isset($c['aggregate_version_incoming'], $c['checkpoint_version']) &&
        $c['aggregate_version_incoming'] < $c['checkpoint_version']) {
        return 'REJECT_STALE';
    }
    if (($c['redis_lost'] ?? false) === true && ($c['outbox_committed'] ?? false) === true) {
        return 'REPLAY_AFTER_RECOVERY';
    }
    if (($c['crash_after_commit_before_publish'] ?? false) === true &&
        ($c['outbox_committed'] ?? false) === true &&
        ($c['replay'] ?? false) === true &&
        ($c['broker_available'] ?? true) === false) {
        return 'EVENT_RECOVERABLE';
    }
    return 'UNSUPPORTED_OR_UNRECOVERABLE';
}
function phase3bOwnerGrantDecision(array $databases, string $caller, string $database, string $action): string {
    if (!in_array(strtolower($action), ['select','insert','update','delete'], true)) return 'DENY';
    foreach ($databases as $d) {
        if ($d['id'] !== $database) continue;
        $policy = $d['application_grants'];
        return $d['owner'] === $caller &&
            $policy['read_db'] === $d['name'] &&
            $policy['write_db'] === $d['name'] &&
            $policy['other_lms_db_access'] === 'DENY_SELECT_INSERT_UPDATE_DELETE'
            ? 'ALLOW' : 'DENY';
    }
    return 'DENY';
}
function phase3bMigrationCompatible(array $databases, array $candidate): bool {
    $owners = array_column($databases, 'name');
    return in_array($candidate['database'] ?? null, $owners, true) &&
       ($candidate['owner_db'] ?? null) === ($candidate['database'] ?? null) &&
       ($candidate['additive_only'] ?? false) === true &&
       ($candidate['drops_existing_column'] ?? true) === false &&
       ($candidate['cross_owner_write'] ?? true) === false &&
       ($candidate['rollback_defined'] ?? false) === true;
}
