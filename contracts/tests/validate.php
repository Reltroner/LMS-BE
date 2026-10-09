<?php
declare(strict_types=1);

/**
 * Reltroner LMS 3B-01 standalone contract and policy-fixture verifier.
 * php contracts/tests/validate.php
 *
 * Native PHP JSON only: no Composer/vendor, no network, no real JWT,
 * no live HTTP/Keycloak/DB, and no production mutation.
 */
$root = dirname(__DIR__);
$errors = [];
$passed = 0;
function assertContract(bool $ok, string $label): void {
    global $errors, $passed;
    if ($ok) { ++$passed; echo "PASS  " . $label . PHP_EOL; }
    else { $errors[] = $label; echo "FAIL  " . $label . PHP_EOL; }
}
function loadFixture(string $file): array {
    if (!is_file($file)) { throw new RuntimeException("Missing fixture: " . $file); }
    $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) { throw new RuntimeException("Fixture not object: " . $file); }
    return $data;
}
function resolvePointer(array $root, string $pointer): mixed {
    if (!str_starts_with($pointer, "#/")) { throw new RuntimeException("Unsupported reference: " . $pointer); }
    $current = $root;
    foreach (explode("/", substr($pointer, 2)) as $segment) {
        $key = str_replace(["~1","~0"], ["/","~"], $segment);
        if (!is_array($current) || !array_key_exists($key, $current)) {
            throw new RuntimeException("Dangling JSON reference: " . $pointer);
        }
        $current = $current[$key];
    }
    return $current;
}
function inspectReferences(mixed $value, array $doc, array &$bad): void {
    if (!is_array($value)) { return; }
    foreach ($value as $k => $v) {
        if ($k === '$ref' && is_string($v)) {
            try { resolvePointer($doc, $v); }
            catch (Throwable $e) { $bad[] = $e->getMessage(); }
        } else { inspectReferences($v, $doc, $bad); }
    }
}
function mockDecision(array $op, ?array $claims, array $auth): int {
    if ($claims === null) { return 401; }
    if (($claims["token_use"] ?? null) !== "access" ||
        ($claims["signature_valid"] ?? false) !== true ||
        ($claims["exp_valid"] ?? false) !== true ||
        ($claims["nbf_valid"] ?? false) !== true ||
        ($claims["iss"] ?? null) !== $auth["issuer"] ||
        ($claims["aud"] ?? null) !== $auth["resource_audience"] ||
        !in_array(($claims["azp"] ?? null), $auth["browser_clients"], true) ||
        !is_string(($claims["sub"] ?? null)) ||
        $claims["sub"] === "") { return 401; }

    $expectedClient = $op["client"];
    if ($expectedClient === "PENDING_PUBLIC_PROJECTION_POLICY" ||
        $expectedClient === "authenticated_lms_user") { $expectedClient = "lms-user"; }
    if (in_array($expectedClient, ["lms-user","lms-admin"], true) &&
        ($claims["azp"] !== $expectedClient)) { return 403; }

    $needed = $op["capability"];
    if (is_string($needed) && !in_array($needed, $claims["capabilities"] ?? [], true)) {
        return 403;
    }
    if ($op["actor_binding"] === "SIGNED_SUB_AND_RESOURCE_OWNER" &&
        array_key_exists("resource_principal_id", $claims) &&
        $claims["resource_principal_id"] !== $claims["sub"]) { return 403; }
    return 200;
}

try {
    $spec = loadFixture($root . "/openapi/v1/openapi.json");
    $policy = loadFixture($root . "/authz/operation-policy.json");
    $fx = loadFixture($root . "/examples/contract-fixtures.json");
    $expectedRoutes = json_decode('["GET /api/v1/me","GET /api/v1/learning/enrollments","POST /api/v1/learning/enrollments","GET /api/v1/learning/enrollments/{enrollment_id}","GET /api/v1/learning/courses/{course_id}/progress","PUT /api/v1/learning/courses/{course_id}/lessons/{lesson_id}/progress","GET /api/v1/learning/bookmarks","PUT /api/v1/learning/bookmarks/{content_id}","DELETE /api/v1/learning/bookmarks/{content_id}","GET /api/v1/mentorship/offerings","GET /api/v1/mentorship/offerings/{offering_id}","GET /api/v1/mentorship/availability","GET /api/v1/mentorship/bookings","POST /api/v1/mentorship/bookings","GET /api/v1/mentorship/bookings/{booking_id}","POST /api/v1/mentorship/bookings/{booking_id}/cancel","GET /api/v1/knowledge/search","POST /api/v1/assistant/query","GET /api/v1/admin/principals","GET /api/v1/admin/principals/{principal_id}","PATCH /api/v1/admin/principals/{principal_id}/roles","GET /api/v1/admin/mentorship/bookings","GET /api/v1/admin/mentorship/bookings/{booking_id}","PATCH /api/v1/admin/mentorship/bookings/{booking_id}","GET /api/v1/admin/audit-events","GET /api/v1/admin/audit-events/{audit_event_id}"]', true, 512, JSON_THROW_ON_ERROR);
    $expectedCapabilities = json_decode('["learning.enrollment.read.self","learning.enrollment.create.self","learning.progress.read.self","learning.progress.write.self","learning.bookmark.read.self","learning.bookmark.write.self","mentorship.offering.read","mentorship.booking.read.self","mentorship.booking.create.self","mentorship.booking.cancel.self","knowledge.search","assistant.use","admin.principal.read","admin.principal.role.manage","admin.mentorship.read","admin.mentorship.manage","admin.learning.read","admin.learning.override","admin.audit.read"]', true, 512, JSON_THROW_ON_ERROR);
    assertContract(($spec["openapi"] ?? null) === "3.1.0", "B3-AC01: OpenAPI 3.1.0");
    assertContract(($spec["servers"][0]["url"] ?? null) === "https://lms-api.reltroner.com", "B3-AC01: one public Gateway host");
    assertContract(($policy["authority"]["logical_blob"] ?? null) === "cf089b8df4b5ccb1761b504ffae662a0053bf03e", "B3-AC01: frozen Phase 1 SHA binding");

    $opIndex = [];
    $actualRoutes = [];
    $requiredMethods = ["get","post","put","patch","delete"];
    foreach ($spec["paths"] ?? [] as $path => $methods) {
        foreach ($methods as $method => $op) {
            if (!in_array($method, $requiredMethods, true)) {
                throw new RuntimeException("Unexpected path method: " . $method);
            }
            $route = strtoupper($method) . " " . $path;
            $actualRoutes[] = $route;
            $id = $op["x-lms-operation-id"] ?? null;
            if (isset($opIndex[$id])) { throw new RuntimeException("Duplicate operation ID: " . $id); }
            $opIndex[$id] = $op;
        }
    }
    sort($actualRoutes);
    sort($expectedRoutes);
    assertContract(count($actualRoutes) === 26 && $actualRoutes === $expectedRoutes,
        "B3-AC01: exact frozen 26 method+path inventory, no extras");
    assertContract(count($opIndex) === 26 && count($policy["ops"] ?? []) === 26,
        "B3-AC04: 26 unique operation IDs and mapped policy records");

    $actualCaps = $policy["all_capabilities"] ?? [];
    sort($actualCaps);
    sort($expectedCapabilities);
    assertContract(count($actualCaps) === 19 && $actualCaps === $expectedCapabilities,
        "B3-AC02: exact frozen 19 capability namespace");
    $usedCaps = [];
    $allMetadata = true;
    $responsesValid = true;
    $allAuth = true;
    $allClientsValid = true;
    foreach ($policy["ops"] as $p) {
        $op = $opIndex[$p["id"]] ?? null;
        if (!is_array($op)) { $allMetadata = false; continue; }
        $route = strtolower($p["method"]);
        if (($spec["paths"][$p["path"]][$route]["x-lms-operation-id"] ?? null) !== $p["id"] ||
            ($op["x-lms-domain-owner"] ?? null) !== $p["owner"] ||
            ($op["x-lms-capability"] ?? null) !== $p["capability"] ||
            ($op["x-lms-browser-client"] ?? null) !== $p["client"] ||
            ($op["x-lms-owner-binding"] ?? null) !== $p["actor_binding"] ||
            ($op["x-lms-guest-policy"] ?? null) !== $p["guest_policy"]) {
            $allMetadata = false;
        }
        if (!is_array($op["responses"] ?? null) ||
            !isset($op["responses"]["401"], $op["responses"]["403"])) {
            $responsesValid = false;
        }
        if (($op["security"] ?? null) !== [["LmsBearer" => []]]) { $allAuth = false; }
        if (!in_array($p["client"], [
            "lms-user","lms-admin","any_valid_lms_client",
            "authenticated_lms_client","authenticated_lms_user",
            "PENDING_PUBLIC_PROJECTION_POLICY"
        ], true)) { $allClientsValid = false; }
        if (is_string($p["capability"])) { $usedCaps[] = $p["capability"]; }
    }
    assertContract($allMetadata && $allClientsValid, "B3-AC04: per-route owner/client/capability/object-binding matrix matches OpenAPI");
    assertContract($responsesValid, "B3-AC03: 401 and 403 Problem Details declared by every operation");
    assertContract($allAuth && ($spec["security"] ?? null) === [["LmsBearer" => []]],
        "B3-AC02: no anonymous default in 26-operation contract");

    $reserved = $policy["reserved_unrouted"] ?? [];
    assertContract($reserved === ["admin.learning.read","admin.learning.override"] &&
        count(array_intersect($reserved, $usedCaps)) === 0,
        "B3-AC02: reserved admin.learning capabilities have no invented public endpoints");
    $offers = array_filter($policy["ops"], fn($op) => in_array($op["id"], ["API-10","API-11"], true));
    $guestDenied = count($offers) === 2;
    foreach ($offers as $offer) {
        if ($offer["authentication"] !== "REQUIRED_BEARER_ACCESS_TOKEN" ||
            $offer["capability"] !== "mentorship.offering.read" ||
            $offer["guest_policy"] !== "GUEST_DENIED_PENDING_OWNER_PUBLIC_PROJECTION_POLICY") {
            $guestDenied = false;
        }
    }
    assertContract($guestDenied && ($policy["authorization_model"]["guest_offerings"]["required_auth"] ?? false),
        "B3-AC02: offering guest access stays denied pending separate owner release decision");
    $availability = $opIndex["API-12"] ?? [];
    assertContract(($availability["x-lms-capability"] ?? null) === "mentorship.booking.create.self",
        "B3-AC02: mentorship availability requires authenticated booking capability");
    $booking = $opIndex["API-14"] ?? [];
    $idempotency = false;
    foreach ($booking["parameters"] ?? [] as $param) {
        if (($param['$ref'] ?? null) === "#/components/parameters/IdempotencyKey") {
            $idempotency = true;
        }
    }
    $keySchema = $spec["components"]["parameters"]["IdempotencyKey"] ?? [];
    assertContract($idempotency && $keySchema["name"] === "Idempotency-Key" &&
        $keySchema["required"] === true && $keySchema["in"] === "header",
        "B3-AC03: durable booking creation idempotency key required");

    $schemas = $spec["components"]["schemas"];
    $problem = $schemas["ProblemDetails"] ?? [];
    $required = ["type","title","status","detail","code","request_id"];
    assertContract(count(array_diff($required, $problem["required"] ?? [])) === 0 &&
        $problem["additionalProperties"] === false,
        "B3-AC03: RFC7807-compatible error schema requires code/request_id and rejects stack fields");
    $collection = $schemas["CursorCollection"] ?? [];
    assertContract(($collection["properties"]["page"]['$ref'] ?? null) === "#/components/schemas/PageInfo" &&
        ($schemas["PageInfo"]["properties"]["next_cursor"]["type"] ?? []) === ["string","null"],
        "B3-AC03: bounded cursor collection plus nullable next_cursor");
    $cursorOps = array_filter($policy["ops"], fn($p) =>
        in_array($p["id"], ["API-02","API-07","API-10","API-12","API-13","API-19","API-22","API-25"], true));
    $bounded = count($cursorOps) === 8;
    foreach ($cursorOps as $p) {
        $params = $opIndex[$p["id"]]["parameters"];
        $names = array_column($params, '$ref');
        if (!in_array("#/components/parameters/Cursor", $names, true) ||
            !in_array("#/components/parameters/Limit", $names, true)) { $bounded = false; }
    }
    assertContract($bounded, "B3-AC03: all eight unbounded-list-risk routes have cursor+limit contract parameters");

    $badRefs = [];
    inspectReferences($spec, $spec, $badRefs);
    assertContract($badRefs === [], "B3-AC04: all internal OpenAPI JSON references resolve");

    $samples = $fx["http_samples"] ?? [];
    $sampleOK = true;
    foreach ([401,403,409] as $status) {
        $one = $samples["problem_" . $status] ?? [];
        if (($one["status"] ?? null) !== $status ||
            ($one["media_type"] ?? null) !== "application/problem+json" ||
            ($one["body"]["status"] ?? null) !== $status ||
            !isset($one["body"]["code"],$one["body"]["request_id"])) {
            $sampleOK = false;
        }
    }
    assertContract($sampleOK && array_key_exists("next_cursor", $samples["collection"]["page"] ?? []),
        "B3-AC03: Problem 401/403/409 and cursor example fixtures");
    $positiveOK = true;
    foreach ($fx["positive_authorization"] ?? [] as $f) {
        $p = $policy["ops"][(int) substr($f["operation_id"],4) - 1] ?? [];
        if (!isset($p["id"]) || $p["id"] !== $f["operation_id"] ||
            mockDecision($p,$f["claims"],$policy["authorization_model"]) !== $f["expected"]) {
            $positiveOK = false;
        }
    }
    assertContract($positiveOK && count($fx["positive_authorization"] ?? []) >= 8,
        "B3-AC04: positive capability/context MODEL simulations (NOT live provider)");
    $negativeOK = true;
    foreach ($fx["negative_authorization"] ?? [] as $f) {
        $p = $policy["ops"][(int) substr($f["operation_id"],4) - 1] ?? [];
        if (!isset($p["id"]) || $p["id"] !== $f["operation_id"] ||
            mockDecision($p,$f["claims"],$policy["authorization_model"]) !== $f["expected"]) {
            $negativeOK = false;
        }
    }
    assertContract($negativeOK && count($fx["negative_authorization"] ?? []) >= 12,
        "B3-AC04: negative capability/context MODEL simulations (NOT live provider)");

    echo PHP_EOL . "CONTRACT STATIC TESTS: " . $passed . " PASS; " . count($errors) . " FAIL" . PHP_EOL;
    if ($errors !== []) { exit(1); }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "CONTRACT CHECK ERROR: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
