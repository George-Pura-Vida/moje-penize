<?php
declare(strict_types=1);
require_once __DIR__.'/../lib/comparison_model.php';
function a(string $id,float $ret=.05,float $monthly=5000):array{return ['id'=>$id,'initial_contribution'=>100000,'monthly_contribution'=>$monthly,'annual_return'=>$ret,'entry_fee_initial_pct'=>0,'entry_fee_monthly_pct'=>0,'ongoing_fee_pct_pa'=>0,'fixed_fee_monthly'=>0];}
function req(string $id='cmp_test'):array{return ['schema_version'=>'comparison-1.0.0','comparison_id'=>$id,'name'=>'Test','common'=>['horizon_years'=>10,'inflation_rate'=>.025,'snapshot_years'=>[1,5,10]],'baseline_variant_id'=>'variant_a','variants'=>[['id'=>'variant_a','name'=>'A','assets'=>[a('asset_a1')]],['id'=>'variant_b','name'=>'B','assets'=>[a('asset_b1',.07)]]]];}
function ok(bool $x,string $m):void{if(!$x){fwrite(STDERR,"FAIL $m\n");exit(1);}echo "PASS $m\n";}
function err(array $r,string $code,?callable $ce=null):ComparisonException{try{ComparisonModelV100::calculate($r,$ce);}catch(ComparisonException $e){ok($e->errorCode===$code,$code);return $e;}throw new RuntimeException("Expected $code");}
$r=req('cmp_c01');$o=ComparisonModelV100::calculate($r);ok($o['calculation_status']==='OK'&&count($o['variants'])===2,'C01 two variants');ok($o['variants'][0]['delta_vs_baseline']['nominal_value']['absolute']===0.0,'C01 baseline delta');
$r=req('cmp_c02');$r['variants'][]=['id'=>'variant_c','name'=>'C','assets'=>[a('asset_c1',.08)]];$r['variants'][]=['id'=>'variant_d','name'=>'D','assets'=>[a('asset_d1',.09)]];$o=ComparisonModelV100::calculate($r);ok(count($o['variants'])===4,'C02 four variants');
$r=req('cmp_c03');$r['common']['inflation_rate']=0;$r['variants'][0]['assets'][0]=['id'=>'asset_a1','initial_contribution'=>0,'monthly_contribution'=>0,'annual_return'=>0,'entry_fee_initial_pct'=>0,'entry_fee_monthly_pct'=>0,'ongoing_fee_pct_pa'=>0,'fixed_fee_monthly'=>0];$o=ComparisonModelV100::calculate($r);ok($o['variants'][1]['delta_vs_baseline']['nominal_value']['relative']===null,'C03 zero baseline relative null');
$r=req('cmp_c04');$r['variants'][1]['id']='variant_a';err($r,'DUPLICATE_VARIANT_ID');
$r=req('cmp_c05');$r['baseline_variant_id']='variant_x';err($r,'BASELINE_VARIANT_NOT_FOUND');
$r=req('cmp_c06');$r['variants'][1]['assets'][]=a('asset_b1');err($r,'DUPLICATE_ASSET_ID');
$r=req('cmp_c07');$r['common']['snapshot_years']=[1,5,11];$e=err($r,'SNAPSHOT_OUT_OF_HORIZON');ok($e->path==='/common/snapshot_years/2','C07 path');
$r=req('cmp_c08');$r['variants'][1]['assets'][]=a('asset_b2',.05,-1);$e=err($r,'CE_REQUEST_INVALID');ok($e->path==='/variants/1/assets/1/monthly_contribution'&&$e->assetId==='asset_b2','C08 second asset mapping');
$r=req('cmp_c09');$calls=0;$bad=function(array $i)use(&$calls):array{$calls++;$o=CalculationEngineV100::calculate($i);if($calls===2)$o['nominal_value']='bad';return $o;};err($r,'CE_INVALID_RESPONSE',$bad);
$r=req('cmp_c10');$calls=0;$down=function(array $i)use(&$calls):array{$calls++;if($calls===2)throw new RuntimeException('down');return CalculationEngineV100::calculate($i);};err($r,'COMPARISON_INCOMPLETE',$down);
echo "ComparisonModel C01-C10 PASS\n";
