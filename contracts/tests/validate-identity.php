<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$policy=json_decode(file_get_contents($root.'/identity/trust-contract.json'),true,512,JSON_THROW_ON_ERROR);
$fx=json_decode(file_get_contents($root.'/identity/trust-fixtures.json'),true,512,JSON_THROW_ON_ERROR);
$errors=[];$pass=0;
function result(bool $ok,string $id):void{global $errors,$pass;if($ok){$pass++;echo "PASS $id\n";}else{$errors[]=$id;echo "FAIL $id\n";}}
result($policy['oidc']['issuer']==='https://auth.reltroner.com/realms/reltroner'&&$policy['oidc']['audience']==='lms-api','B3-AC05 issuer audience');
result(count($policy['oidc']['browser_clients'])===2&&array_keys($policy['oidc']['browser_clients'])===['lms-user','lms-admin']&&$policy['oidc']['browser_clients']['lms-user']['pkce']==='S256'&&$policy['oidc']['browser_clients']['lms-admin']['pkce']==='S256','B3-AC05 clients PKCE S256');
result($policy['owner_boundary']['HRM_realm_mappers_touched']===false&&$policy['owner_boundary']['production_keycloak_clients_created']===false,'B3-AC08 HRM and provisioning not mutated');
/** Metadata acceptance only: no HTTP verifier, key provisioning or Redis connection. */
$expectedCrypto=[
 'design_status'=>'OWNER_RATIFIED_NONPRODUCTION_DESIGN_RUNTIME_NOT_AUTHORIZED',
 'ratified_adr'=>'ADR-LMS-TRUST-001',
 'runtime_authorized'=>false,
 'requires_owner_approval_before_runtime'=>true,
 'algorithm'=>'EdDSA',
 'curve'=>'Ed25519',
 'serialization'=>'JWS_COMPACT_RFC7515_RFC8037',
 'protected_header'=>['alg'=>'EdDSA','kid_lookup'=>'TRUSTED_PINNED_REGISTRY_ONLY','kid_matches_payload'=>true],
 'JWKS_or_key_distribution'=>[
  'mode'=>'PINNED_PER_SERVICE_PUBLIC_KEYS','public_key_type'=>'OKP','public_key_curve'=>'Ed25519',
  'caller_to_kid_binding'=>true,'fingerprint_review_required'=>true,
  'token_header_key_urls_allowed'=>false,'private_keys_in_source'=>false],
 'rotation_window'=>['overlap_seconds'=>180,'allowed_keys'=>'CURRENT_AND_PREVIOUS_ONLY','compromised_key_revoked_immediately'=>true],
 'clock_skew_seconds'=>5,
 'assertion_max_ttl_seconds'=>60,
 'nonce_storage'=>[
  'mode'=>'ATOMIC_SINGLE_USE','identity_fields'=>['iss','caller_service','recipient_service','jti'],
  'jti_min_entropy_bits'=>128,'reserve_before_side_effects'=>true,'ttl'=>'REMAINING_EXP_PLUS_ALLOWED_SKEW',
  'store'=>'REDIS_EPHEMERAL_SET_NX_PX_EQUIVALENT_DESIGN_ONLY','on_store_unavailable'=>'DENY',
  'on_indeterminate_result'=>'DENY','keyspace_loss_detection_required'=>true,
  'recovery_quarantine_seconds'=>65,'quarantine_starts_after'=>'VERIFIED_RECOVERY'],
 'authorization_binding'=>['issuer','audience','caller_service','recipient_service','operation_id','principal_sub','principal_capabilities_intersection','request_id'],
 'fail_closed'=>true,
];
$runtimeGate='Future isolated real-signature HTTP integration with independently validated dual assertions and Redis-loss detection/quarantine; signing-key custody and pinned public-key registry review; independent owner approval before live runtime. Synthetic fixtures do not authorize runtime.';
foreach($expectedCrypto as $field=>$expected)result(($policy['internal_workload']['cryptographic_parameters'][$field]??null)===$expected,'B3-AC07 ratified DESIGN '.$field);
result($policy['oidc']['access_token']['allowed_signing_algorithms']==='BLOCKED_PENDING_SIGNED_3B02_CRYPTO_PARAMETER_ADR','B3-AC05 OIDC algorithm decision remains independent and blocked');
result($policy['internal_workload']['not_approved_until']===$runtimeGate&&$policy['internal_workload']['mode']==='dual_assertion'&&$policy['internal_workload']['mTLS_or_service_identity']==='SIGNED_WORKLOAD_ASSERTION_REQUIRED_NOT_YET_IMPLEMENTED'&&$policy['owner_boundary']['implementation']==='SCHEMA_AND_SANITIZED_MODEL_TEST_ONLY','B3-AC07 live runtime gates remain open');
function acceptsIdentityDesign(array $candidate,array $expectedCrypto,string $runtimeGate):bool{
 if(($candidate['oidc']['access_token']['allowed_signing_algorithms']??null)!=='BLOCKED_PENDING_SIGNED_3B02_CRYPTO_PARAMETER_ADR'||($candidate['internal_workload']['not_approved_until']??null)!==$runtimeGate)return false;
 foreach($expectedCrypto as $field=>$expected)if(($candidate['internal_workload']['cryptographic_parameters'][$field]??null)!==$expected)return false;
 return true;
}
result(acceptsIdentityDesign($policy,$expectedCrypto,$runtimeGate),'B3-AC07 ratified metadata accepted without runtime authorization');
$mutant=$policy;$mutant['oidc']['access_token']['allowed_signing_algorithms']=['EdDSA'];
result(!acceptsIdentityDesign($mutant,$expectedCrypto,$runtimeGate),'B3-AC07 internal algorithm copied into OIDC rejected');
$mutant=$policy;$mutant['internal_workload']['cryptographic_parameters']['runtime_authorized']=true;
result(!acceptsIdentityDesign($mutant,$expectedCrypto,$runtimeGate),'B3-AC07 premature runtime authorization rejected');
$mutant=$policy;$mutant['internal_workload']['cryptographic_parameters']['requires_owner_approval_before_runtime']=false;
result(!acceptsIdentityDesign($mutant,$expectedCrypto,$runtimeGate),'B3-AC07 removed independent owner approval rejected');
$mutant=$policy;unset($mutant['internal_workload']['cryptographic_parameters']['nonce_storage']);
result(!acceptsIdentityDesign($mutant,$expectedCrypto,$runtimeGate),'B3-AC07 missing replay controls rejected');
$mutant=$policy;$mutant['internal_workload']['cryptographic_parameters']['nonce_storage']=$expectedCrypto['nonce_storage'];$mutant['internal_workload']['cryptographic_parameters']['nonce_storage']['on_store_unavailable']='ALLOW';
result(!acceptsIdentityDesign($mutant,$expectedCrypto,$runtimeGate),'B3-AC07 fail-open replay metadata rejected');
$mutant=$policy;$mutant['internal_workload']['cryptographic_parameters']['nonce_storage']=$expectedCrypto['nonce_storage'];$mutant['internal_workload']['cryptographic_parameters']['nonce_storage']['recovery_quarantine_seconds']=64;
result(!acceptsIdentityDesign($mutant,$expectedCrypto,$runtimeGate),'B3-AC07 insufficient recovery quarantine rejected');
$mutant=$policy;$mutant['internal_workload']['not_approved_until']='OWNER_RATIFIED_RUNTIME_READY';
result(!acceptsIdentityDesign($mutant,$expectedCrypto,$runtimeGate),'B3-AC07 erased downstream runtime gates rejected');
function oidc(array $c,array $p):int{
 if(!$c['signature_verified']||$c['issuer']!==$p['oidc']['issuer']||$c['aud']!==$p['oidc']['audience']||$c['type']!=='access'||!isset($p['oidc']['browser_clients'][$c['azp']]))return 401;
 return in_array($c['needed'],$c['caps'],true)?200:403;
}
foreach($fx['oidc_cases'] as $c)result(oidc($c,$policy)===$c['expected'],'B3-AC06 '.$c['id']);
function workload(array $c):int{
 if(!$c['signature_verified']||!$c['key_authorized']||!$c['fresh']||$c['replayed']||$c['request_id']==='')return 401;
 if($c['caller']!=='gateway'||$c['recipient']!=='learning'||$c['operation_id']!=='API-02'||!in_array('learning.enrollment.read.self',$c['delegated_capabilities'],true))return 403;
 return 200;
}
foreach($fx['workload_cases'] as $c)result(workload($c)===$c['expected'],'B3-AC07 '.$c['id']);
result($fx['no_real_jwt']&&$fx['no_production_secrets'],'B3-AC08 synthetic secret-free fixtures');
echo "3B02 STATIC MODEL: $pass PASS / ".count($errors)." FAIL\n";exit(count($errors)>0?1:0);
