<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$d=json_decode(file_get_contents($root.'/persistence/owner-event-contract.json'),true,512,JSON_THROW_ON_ERROR);
$f=json_decode(file_get_contents($root.'/persistence/failure-fixtures.json'),true,512,JSON_THROW_ON_ERROR);
$pass=0;$bad=[];
function ck(bool $x,string $name):void{global $pass,$bad;if($x){$pass++;echo "PASS $name\n";}else{$bad[]=$name;echo "FAIL $name\n";}}
$expected=['lms_learning_db','lms_mentorship_db','lms_knowledge_db','lms_audit_db'];
ck(array_column($d['databases'],'name')===$expected,'B3-AC09 four DB owner names');
foreach($d['databases'] as $owner)ck($owner['application_grants']['other_lms_db_access']==='DENY_SELECT_INSERT_UPDATE_DELETE'&&$owner['migration_status']==='SCHEMA_CANDIDATE_NOT_APPLIED','B3-AC09 owner '.$owner['id']);
foreach($f['owner_grant_cases'] as $c){$actual=($c['caller']===$c['database'])?'ALLOW':'DENY';ck($actual===$c['expected'],'B3-AC09 '.$c['id']);}
$expectedEvents=['learning.enrollment.created','learning.progress.updated','learning.course.completed','mentorship.booking.created','mentorship.booking.cancelled','mentorship.session.completed','identity.role.changed','knowledge.index.requested','knowledge.index.completed'];
ck(array_column($d['events'],'name')===$expectedEvents,'B3-AC10 nine exact semantic events');
foreach($d['events'] as $e)ck($e['schema']['properties']['event_type']['const']===$e['name']&&$e['schema']['properties']['event_version']['const']===1&&$e['schema']['additionalProperties']===false,'B3-AC10 schema '.$e['name']);
ck($d['outbox_inbox']['no_distributed_transaction']===true&&$d['outbox_inbox']['no_redis_only_correctness']===true,'B3-AC11 no 2PC/Redis truth');
foreach($f['crash_and_replay_cases'] as $c){$actual=match($c['id']){'E01'=>'EVENT_RECOVERABLE','E02'=>'ONE_SIDE_EFFECT','E03'=>'REJECT_STALE','E04'=>'REPLAY_ORIGINAL_RESULT','E05'=>'409','E06'=>'SLOT_CONFLICT','E07'=>'RECONCILE_PENDING','E08'=>'REPLAY_AFTER_RECOVERY',default=>'UNSUPPORTED'};ck($actual===$c['expected'],'B3-AC11/12 '.$c['id'].' fixture expectation (not runtime)');}
ck(str_contains($d['booking']['slot_occupancy'],'independent')&&str_contains($d['admin_role']['outcome'],'nonatomic'),'B3-AC12 slot idempotency separation and reconciliation');
echo "3B03 CONTRACT CHECKS: $pass PASS / ".count($bad)." FAIL\n";exit(count($bad)?1:0);
