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
result($policy['internal_workload']['cryptographic_parameters']['algorithm']==='PENDING_SECURITY_ADR','B3-AC07 crypto choice not fabricated');
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
