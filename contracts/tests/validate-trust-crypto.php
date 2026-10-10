<?php
declare(strict_types=1);
/** Signed compact Ed25519 *synthetic* assertions. Key seed is PUBLIC and not a credential. */
if(!function_exists('sodium_crypto_sign_seed_keypair')){fwrite(STDERR,"FAIL sodium extension required\n");exit(1);}
$profile=json_decode((string)file_get_contents(dirname(__DIR__).'/identity/crypto-profile-proposal.json'),true,512,JSON_THROW_ON_ERROR);
$passed=0;$failed=[];
function at(bool $ok,string $name):void{global $passed,$failed;if($ok){$passed++;echo "PASS $name\n";}else{$failed[]=$name;echo "FAIL $name\n";}}
function enc64(string $s):string{return rtrim(strtr(base64_encode($s),'+/','-_'),'=');}
function dec64(string $s):string|false{return base64_decode(strtr($s,'-_','+/'),true);}
$keyPair=sodium_crypto_sign_seed_keypair(hash('sha256','PUBLIC TEST VECTOR DO NOT USE IN PRODUCTION',true));
$private=sodium_crypto_sign_secretkey($keyPair);
$public=sodium_crypto_sign_publickey($keyPair);
$kid='phase3b07r-gateway-test';
$now=1800000000;
$claims=['iss'=>'lms-internal-trust','sub'=>'workload-service-principal','aud'=>'lms-internal-services',
 'iat'=>$now,'nbf'=>$now,'exp'=>$now+45,'jti'=>'synthetic-jti-1','kid'=>$kid,
 'caller_service'=>'gateway','recipient_service'=>'learning','operation_id'=>'API-02',
 'principal_sub'=>'synthetic-student','principal_capabilities'=>['learning.enrollment.read.self'],
 'request_id'=>'synthetic-request-1'];
function signed(array $payload,string $secret,string $kid):string{
 $header=enc64(json_encode(['typ'=>'JWT','alg'=>'EdDSA','kid'=>$kid],JSON_THROW_ON_ERROR));
 $body=enc64(json_encode($payload,JSON_THROW_ON_ERROR));
 $input=$header.'.'.$body;
 return $input.'.'.enc64(sodium_crypto_sign_detached($input,$secret));
}
function decision(string $token,array $trusted,string $caller,string $recipient,int $now,array &$seen,array $profile):int{
 $parts=explode('.',$token);if(count($parts)!==3)return 401;
 $h=json_decode(dec64($parts[0])?:'',true);$c=json_decode(dec64($parts[1])?:'',true);
 $sig=dec64($parts[2]);
 if(!is_array($h)||!is_array($c)||!is_string($sig)||($h['alg']??null)!=='EdDSA'||($h['typ']??null)!=='JWT')return 401;
 $kid=$h['kid']??'';if(!isset($trusted[$kid])||($c['kid']??'')!==$kid)return 401;
 if(!sodium_crypto_sign_verify_detached($sig,$parts[0].'.'.$parts[1],$trusted[$kid]))return 401;
 foreach($profile['required_claims'] as $required)if(!array_key_exists($required,$c))return 401;
 if($c['iss']!==$profile['issuer']||$c['aud']!==$profile['audience'])return 401;
 if(!is_int($c['iat'])||!is_int($c['nbf'])||!is_int($c['exp'])||$c['exp']<=$c['iat']||
 $c['exp']-$c['iat']>$profile['max_ttl_seconds']||
 $c['nbf']>$now+$profile['clock_skew_seconds']||$c['iat']>$now+$profile['clock_skew_seconds']||
 $c['exp']<$now-$profile['clock_skew_seconds'])return 401;
 if($c['caller_service']!==$caller||$c['recipient_service']!==$recipient||
 $c['operation_id']!==$profile['operation_id']||!is_string($c['principal_sub'])||$c['principal_sub']===''||
 !is_string($c['request_id'])||$c['request_id']==='')return 403;
 if(!in_array($profile['client_capability'],$c['principal_capabilities'],true))return 403;
 $nonce=$kid.'|'.$caller.'|'.$recipient.'|'.$c['jti'];
 if(isset($seen[$nonce]))return 401;
 $seen[$nonce]=true;return 200;
}
$trusted=[$kid=>$public];
$nonce=[];$valid=signed($claims,$private,$kid);
at(decision($valid,$trusted,'gateway','learning',$now,$nonce,$profile)===200,'B3-AC07 real synthetic Ed25519 signature verified');
at(decision($valid,$trusted,'gateway','learning',$now,$nonce,$profile)===401,'B3-AC07 duplicate jti rejected');
$nonce=[];
$mut=$claims;$mut['aud']='other-api';
at(decision(signed($mut,$private,$kid),$trusted,'gateway','learning',$now,$nonce,$profile)===401,'B3-AC07 wrong audience rejected');
$nonce=[];$mut=$claims;$mut['exp']=$now-60;
at(decision(signed($mut,$private,$kid),$trusted,'gateway','learning',$now,$nonce,$profile)===401,'B3-AC07 stale signed assertion rejected');
$nonce=[];$mut=$claims;$mut['nbf']=$now+30;
at(decision(signed($mut,$private,$kid),$trusted,'gateway','learning',$now,$nonce,$profile)===401,'B3-AC07 future not-before rejected');
$nonce=[];$mut=$claims;$mut['principal_capabilities']=[];
at(decision(signed($mut,$private,$kid),$trusted,'gateway','learning',$now,$nonce,$profile)===403,'B3-AC07 delegated capability elevation denied');
$nonce=[];
at(decision($valid,$trusted,'gateway','audit',$now,$nonce,$profile)===403,'B3-AC07 recipient-bound denial');
$nonce=[];
at(decision($valid,['untrusted-kid'=>$public],'gateway','learning',$now,$nonce,$profile)===401,'B3-AC07 unknown trusted key ID denied');
$nonce=[];$tampered=substr($valid,0,-1).(substr($valid,-1)==='A'?'B':'A');
at(decision($tampered,$trusted,'gateway','learning',$now,$nonce,$profile)===401,'B3-AC07 altered detached signature rejected');
/** Source-status acceptance only; the signed fixtures above are a single combined test assertion. */
function acceptsCryptoDesign(array $candidate):bool{
 return ($candidate['status']??null)==='OWNER_RATIFIED_NONPRODUCTION_DESIGN_RUNTIME_NOT_AUTHORIZED'&&
 ($candidate['ratified_adr']??null)==='ADR-LMS-TRUST-001'&&
 ($candidate['requires_owner_approval_before_runtime']??null)===true&&
 ($candidate['runtime_authorized']??null)===false&&($candidate['not_a_live_keycloak_token']??null)===true;
}
at(acceptsCryptoDesign($profile),'B3-AC07 owner-ratified DESIGN with live runtime still unauthorized');
at($profile['algorithm']==='EdDSA'&&$profile['curve']==='Ed25519'&&$profile['serialization']==='JWS Compact RFC7515 with EdDSA RFC8037','B3-AC07 ratified internal signing profile');
at($profile['max_ttl_seconds']===60&&$profile['clock_skew_seconds']===5&&$profile['rotation_overlap_seconds']===180&&($profile['replay_recovery_quarantine_seconds']??null)===65,'B3-AC07 ratified numeric freshness rotation and recovery bounds');
at($profile['phase']==='3B-07R'&&$profile['key_generation']==='Synthetic deterministic public non-secret test seed ONLY; production private keys never stored in repo','B3-AC07 historical profile and public synthetic seed remain test-only');
at($profile['required_claims']===['iss','sub','aud','iat','nbf','exp','jti','kid','caller_service','recipient_service','operation_id','principal_sub','principal_capabilities','request_id'],'B3-AC07 required signed claims preserved');
$mutant=$profile;$mutant['status']='CANDIDATE_NOT_OWNER_RATIFIED_SECURITY_ADR';
at(!acceptsCryptoDesign($mutant),'B3-AC07 stale unratified source status rejected');
$mutant=$profile;$mutant['requires_owner_approval_before_runtime']=false;
at(!acceptsCryptoDesign($mutant),'B3-AC07 bypassed runtime owner approval rejected');
$mutant=$profile;$mutant['runtime_authorized']=true;
at(!acceptsCryptoDesign($mutant),'B3-AC07 design ratification cannot authorize runtime');
$mutant=$profile;$mutant['not_a_live_keycloak_token']=false;
at(!acceptsCryptoDesign($mutant),'B3-AC07 profile cannot claim a live Keycloak token');
echo "3B07R SIGNED TRUST MODEL: $passed PASS / ".count($failed)." FAIL\n";
exit($failed?1:0);
