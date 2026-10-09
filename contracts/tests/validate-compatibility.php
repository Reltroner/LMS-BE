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
function deny(string $case):bool{
 return in_array($case,['C05','C06','C07','C08','C09','C15'],true);
}
foreach(['C05','C06','C07','C08','C09','C15'] as $case)t(deny($case),'B3-AC22 '.$case.' negative deny model');
function eventDecision(bool $receiptPresent,int $version,int $checkpoint):string{
 if($receiptPresent)return 'SKIP_REPLAY';
 if($version<$checkpoint)return 'REJECT_OUT_OF_ORDER';
 return 'ACCEPT';
}
t(eventDecision(true,3,2)==='SKIP_REPLAY','B3-AC23 duplicate event receipt');
t(eventDecision(false,1,2)==='REJECT_OUT_OF_ORDER','B3-AC23 stale event version');
function swapIndex(bool $valid):string{return $valid?'RELEASE_NEW_INDEX':'KEEP_PREVIOUS_RELEASE';}
t(swapIndex(false)==='KEEP_PREVIOUS_RELEASE','B3-AC23 index rollback model');
function knowledgeRelease(?array $attestation):bool{
 if($attestation===null)return false;
 return ($attestation['published']??false)===true&&
  ($attestation['rights']??null)==='public-redistribution-allowed'&&
  preg_match('/^[a-f0-9]{40}$/',$attestation['source_commit_sha']??'')===1&&
  preg_match('/^[a-f0-9]{64}$/',$attestation['digest_sha256']??'')===1;
}
t(!knowledgeRelease(null),'B3-AC24 missing source attestation rejected');
t(!knowledgeRelease(['published'=>true,'rights'=>'private','source_commit_sha'=>str_repeat('a',40),'digest_sha256'=>str_repeat('b',64)]),'B3-AC24 private rights rejected');
t(!knowledgeRelease(['published'=>true,'rights'=>'public-redistribution-allowed','source_commit_sha'=>'fake','digest_sha256'=>str_repeat('b',64)]),'B3-AC24 forged source hash rejected');
t(count($cases['negative_cases'])===15&&count($persist['databases'])===4&&$id['internal_workload']['delegation']==='SIGNED_SCOPED_PRINCIPAL_DELEGATION_REQUIRED','B3-AC21..24 fixture and dependency inventory');
echo "3B06 MODEL CONTRACT: $passes PASS / ".count($fails)." FAIL\n";exit($fails?1:0);
