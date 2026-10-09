<?php
declare(strict_types=1);
$root=dirname(__DIR__);
function doc(string $file):array{return json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);}
$g=doc($root.'/compatibility/golden-v1.json');
$cases=doc($root.'/compatibility/negative-cases.json');
$api=doc($root.'/openapi/v1/openapi.json');
$policy=doc($root.'/authz/operation-policy.json');
$persist=doc($root.'/persistence/owner-event-contract.json');
$id=doc($root.'/identity/trust-contract.json');
$passes=0;$fails=[];
function t(bool $ok,string $name):void{global $passes,$fails;if($ok){$passes++;echo "PASS $name\n";}else{$fails[]=$name;echo "FAIL $name\n";}}
function normalized(array $v):array{sort($v);return $v;}
function actualRoutes(array $api):array{$o=[];foreach($api['paths'] as $p=>$methods)foreach($methods as $m=>$details)$o[]=strtoupper($m).' '.$p;return normalized($o);}
function compatible(array $g,array $a,array $caps,array $events):bool{return normalized($g['operations'])===normalized($a)&&normalized($g['capabilities'])===normalized($caps)&&normalized($g['events'])===normalized($events);}
$realRoutes=actualRoutes($api);$caps=$policy['all_capabilities'];$events=array_column($persist['events'],'name');
t(compatible($g,$realRoutes,$caps,$events),'B3-AC21 full current v1 route/role/event golden match');
$missing=$realRoutes;array_shift($missing);
t(!compatible($g,$missing,$caps,$events),'B3-AC21 removed API operation rejected');
$extra=$realRoutes;$extra[]='POST /api/v1/admin/learning/override';
t(!compatible($g,$extra,$caps,$events),'B3-AC21 extra unfrozen public route rejected');
$wrongCaps=$caps;array_shift($wrongCaps);
t(!compatible($g,$realRoutes,$wrongCaps,$events),'B3-AC21 missing capability rejected');
$wrongEvents=$events;$wrongEvents[0]='learning.enrollment.deleted';
t(!compatible($g,$realRoutes,$caps,$wrongEvents),'B3-AC21 unapproved event rejected');
function denyByPolicy(array $policy,string $operation,?string $client,?string $capability,?string $owner,?string $requestedOwner,bool $delegationSigned=true,bool $aclAllowed=true):bool {
    if($client===null||$delegationSigned===false)return true;
    $entry=null;foreach($policy['ops'] as $one)if($one['id']===$operation)$entry=$one;
    if($entry===null)return true;
    if(!in_array($client,['lms-user','lms-admin'],true))return true;
    if(in_array($entry['client'],['lms-user','lms-admin'],true)&&$client!==$entry['client'])return true;
    if($entry['capability']!==null&&$capability!==$entry['capability'])return true;
    if($entry['actor_binding']==='SIGNED_SUB_AND_RESOURCE_OWNER'&&$owner!==$requestedOwner)return true;
    if($operation==='API-17'&&!$aclAllowed)return true;
    return false;
}
t(denyByPolicy($policy,'API-19','lms-user','admin.principal.read','alice','alice'),'B3-AC22 C05 wrong admin client denied');
t(denyByPolicy($policy,'API-04','lms-user','learning.enrollment.read.self','alice','bob'),'B3-AC22 C06 cross-owner denied');
t(denyByPolicy($policy,'API-10',null,null,null,null),'B3-AC22 C07 guest offering denied');
t(denyByPolicy($policy,'API-18',null,null,null,null),'B3-AC22 C08 guest AI denied');
t(denyByPolicy($policy,'API-17','lms-user','knowledge.search','alice','alice',true,false),'B3-AC22 C09 private Knowledge snippet denied');
t(denyByPolicy($policy,'API-02','lms-user','learning.enrollment.read.self','alice','alice',false),'B3-AC22 C15 unsigned delegation denied');
t(!denyByPolicy($policy,'API-02','lms-user','learning.enrollment.read.self','alice','alice'),'B3-AC22 legitimate read allowed');
function eventDecision(bool $receiptPresent,int $version,int $checkpoint):string{
 if($receiptPresent)return 'SKIP_REPLAY';
 if($version<$checkpoint)return 'REJECT_OUT_OF_ORDER';
 return 'ACCEPT';
}
t(eventDecision(true,3,2)==='SKIP_REPLAY','B3-AC23 duplicate event receipt');
t(eventDecision(false,1,2)==='REJECT_OUT_OF_ORDER','B3-AC23 stale event version');
function swapIndex(bool $valid):string{return $valid?'RELEASE_NEW_INDEX':'KEEP_PREVIOUS_RELEASE';}
t(swapIndex(false)==='KEEP_PREVIOUS_RELEASE','B3-AC23 index rollback model');
/** Static trust-reference comparison, NOT a live Studio signature or rights verification. */
function knowledgeRelease(?array $attestation,?array $independentExpected):bool{
 if($attestation===null||$independentExpected===null)return false;
 if(($attestation['published']??false)!==true||($attestation['rights']??null)!=='public-redistribution-allowed')return false;
 foreach(['source_commit_sha'=>40,'digest_sha256'=>64] as $key=>$length){
  $a=$attestation[$key]??'';
  $b=$independentExpected[$key]??'';
  if(!is_string($a)||!is_string($b)||strlen($a)!==$length||strlen($b)!==$length||
     !ctype_xdigit($a)||!ctype_xdigit($b)||!hash_equals($b,$a))return false;
 }
 return true;
}
$trustedSource=['source_commit_sha'=>str_repeat('a',40),'digest_sha256'=>hash('sha256','synthetic-known-published-Studio-source')];
$legit=['published'=>true,'rights'=>'public-redistribution-allowed']+$trustedSource;
t(knowledgeRelease($legit,$trustedSource),'B3-AC24 approved synthetic pinned source accepted');
t(!knowledgeRelease(null,$trustedSource),'B3-AC24 missing source attestation rejected');
t(!knowledgeRelease($legit,null),'B3-AC24 untrusted no independent source pinned rejected');
t(!knowledgeRelease(array_replace($legit,['rights'=>'private']),$trustedSource),'B3-AC24 private rights rejected');
t(!knowledgeRelease(array_replace($legit,['source_commit_sha'=>str_repeat('b',40)]),$trustedSource),'B3-AC24 well-shaped wrong commit rejected');
t(!knowledgeRelease(array_replace($legit,['digest_sha256'=>str_repeat('b',64)]),$trustedSource),'B3-AC24 well-shaped wrong digest rejected');
t(!knowledgeRelease(array_replace($legit,['source_commit_sha'=>'fake']),$trustedSource),'B3-AC24 invalid SHA rejected');
t(count($cases['negative_cases'])===15&&count($persist['databases'])===4&&$id['internal_workload']['delegation']==='SIGNED_SCOPED_PRINCIPAL_DELEGATION_REQUIRED','B3-AC21..24 fixture and dependency inventory');
echo "3B06 MODEL CONTRACT: $passes PASS / ".count($fails)." FAIL\n";exit($fails?1:0);
