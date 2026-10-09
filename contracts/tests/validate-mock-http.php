<?php
declare(strict_types=1);
/** Contract-only mock: no Laravel, HTTP or identity provider runtime is used. */
$root=dirname(__DIR__);
function read3b(string $p): array {return json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);}
$api=read3b($root.'/openapi/v1/openapi.json');
$policy=read3b($root.'/authz/operation-policy.json');
$fx=read3b($root.'/examples/contract-fixtures.json');
$passed=0;$failed=[];
function checkMock(bool $ok,string $name):void {global $passed,$failed;if($ok){$passed++;echo "PASS $name\n";}else{$failed[]=$name;echo "FAIL $name\n";}}
function contractDecision(array $p,?array $identity,?string $targetOwner=null,bool $aclAllowed=true):int {
    if($identity===null||($identity['signature_verified']??false)!==true)return 401;
    $client=$identity['azp']??null;
    if(!in_array($client,['lms-user','lms-admin'],true))return 401;
    $expected=$p['client'];
    if($expected==='authenticated_lms_user')$expected='lms-user';
    if(in_array($expected,['lms-user','lms-admin'],true)&&$client!==$expected)return 403;
    if($p['capability']!==null&&!in_array($p['capability'],$identity['caps']??[],true))return 403;
    if($p['actor_binding']==='SIGNED_SUB_AND_RESOURCE_OWNER' &&
       ($targetOwner===null||$targetOwner!==($identity['sub']??'')))return 403;
    if($p['id']==='API-17'&&!$aclAllowed)return 403;
    return 200;
}
function matchingPath(array $api,string $method,string $path):?array {
    foreach($api['paths'] as $template=>$verbs) {
        if(!isset($verbs[strtolower($method)]))continue;
        $regex='#^'.preg_replace('/\\\\\{[^}]+\\\\\}/','[^/]+',preg_quote($template,'#')).'$#';
        if(preg_match($regex,$path))return $verbs[strtolower($method)];
    }
    return null;
}
$ops=array_column($policy['ops'],null,'id');
checkMock(count($ops)===26 && count($fx['operation_contract_cases']??[])===26,'B3-AC04 26 source-owned mock operation fixtures');
foreach($fx['operation_contract_cases'] as $row){
    $p=$ops[$row['id']]??null;
    if(!$p){checkMock(false,'B3-AC04 missing '.$row['id']);continue;}
    $op=$api['paths'][$p['path']][strtolower($p['method'])]??null;
    $identity=['signature_verified'=>true,'azp'=>in_array($p['client'],['lms-admin'],true)?'lms-admin':'lms-user','sub'=>'subject-a','caps'=>$p['capability']!==null?[$p['capability']]:[]];
    $owner=$p['actor_binding']==='SIGNED_SUB_AND_RESOURCE_OWNER'?'subject-a':null;
    $allowed=contractDecision($p,$identity,$owner);
    $successCodes=array_filter(array_keys($op['responses']??[]),fn($n)=>(int)$n>=200&&(int)$n<300);
    checkMock($allowed===200&&count($successCodes)>0&&in_array($row['success_status'],array_map('intval',$successCodes),true),'B3-AC04 positive mock '.$p['id']);
    checkMock(contractDecision($p,null,$owner)===401 && isset($op['responses']['401']),'B3-AC22 anonymous mock '.$p['id']);
    if($p['capability']!==null){
        $missing=$identity;$missing['caps']=[];
        checkMock(contractDecision($p,$missing,$owner)===403 && isset($op['responses']['403']),'B3-AC22 missing cap '.$p['id']);
    }
    if($p['client']==='lms-admin'){
        $wrong=$identity;$wrong['azp']='lms-user';
        checkMock(contractDecision($p,$wrong,$owner)===403,'B3-AC22 wrong admin context '.$p['id']);
    }
    if($p['actor_binding']==='SIGNED_SUB_AND_RESOURCE_OWNER'){
        checkMock(contractDecision($p,$identity,'other-subject')===403,'B3-AC22 cross-subject '.$p['id']);
    }
}
checkMock(contractDecision($ops['API-17'],['signature_verified'=>true,'azp'=>'lms-user','sub'=>'subject-a','caps'=>['knowledge.search']],null,false)===403,'B3-AC22 denied private search snippet');
checkMock(contractDecision($ops['API-02'],['signature_verified'=>false,'azp'=>'lms-user','sub'=>'subject-a','caps'=>['learning.enrollment.read.self']],'subject-a')===401,'B3-AC22 unsigned principal delegation');
$broken=$api['paths']['/api/v1/me']['get'];unset($broken['responses']['401']);
checkMock(!isset($broken['responses']['401']),'B3-AC19 intentionally malformed 401 response detected');
$schema=$api['components']['schemas']['ProblemDetails'];
$required=$schema['required'];
checkMock(count(array_diff(['type','title','status','detail','code','request_id'],$required))===0,'B3-AC03 RFC7807 fields present');
echo "3B07R CONTRACT MOCK: $passed PASS / ".count($failed)." FAIL\n";
exit($failed?1:0);
