<?php
declare(strict_types=1);
require_once __DIR__.'/../lib/comparison_model.php';

function asset(string $id,float $ret=.05,float $monthly=5000,float $initial=100000):array{return ['id'=>$id,'initial_contribution'=>$initial,'monthly_contribution'=>$monthly,'annual_return'=>$ret,'entry_fee_initial_pct'=>0,'entry_fee_monthly_pct'=>0,'ongoing_fee_pct_pa'=>0,'fixed_fee_monthly'=>0];}
function baseReq(string $id):array{return ['schema_version'=>'comparison-1.0.0','comparison_id'=>$id,'name'=>$id,'common'=>['horizon_years'=>10,'inflation_rate'=>.025,'snapshot_years'=>[1,5,10]],'baseline_variant_id'=>'variant_a','variants'=>[['id'=>'variant_a','name'=>'A','assets'=>[asset('asset_a1')]],['id'=>'variant_b','name'=>'B','assets'=>[asset('asset_b1',.07)]]]];}
function fixtures():array{$c1=baseReq('cmp_c01');$c2=baseReq('cmp_c02');$c2['common']=['horizon_years'=>15,'inflation_rate'=>.02,'snapshot_years'=>[1,5,10,15]];$c2['variants']=[['id'=>'variant_a','name'=>'A','assets'=>[asset('asset_a1',.03,3000)]],['id'=>'variant_b','name'=>'B','assets'=>[asset('asset_b1',.05,3000)]],['id'=>'variant_c','name'=>'C','assets'=>[asset('asset_c1',.07,3000)]],['id'=>'variant_d','name'=>'D','assets'=>[asset('asset_d1',.09,3000)]]];$c3=baseReq('cmp_c03');$c3['common']=['horizon_years'=>10,'inflation_rate'=>0,'snapshot_years'=>[1,10]];$c3['variants'][0]=['id'=>'variant_a','name'=>'Zero baseline','assets'=>[asset('asset_a1',0,0,0)]];$c3['variants'][1]=['id'=>'variant_b','name'=>'Funded alternative','assets'=>[asset('asset_b1',.05,1000,100000)]];return ['C01'=>$c1,'C02'=>$c2,'C03'=>$c3];}
function canonical(mixed $v):mixed{if(!is_array($v))return $v;if(array_is_list($v))return array_map('canonical',$v);ksort($v,SORT_STRING);foreach($v as $k=>$x)$v[$k]=canonical($x);return $v;}
function jsonCanonical(array $v):string{return json_encode(canonical($v),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION)."\n";}
function finite(mixed $v):bool{if(is_float($v)&&!is_finite($v))return false;if(is_array($v))foreach($v as $x)if(!finite($x))return false;return true;}
function failGolden(string $m):never{fwrite(STDERR,"FAIL $m\n");exit(1);}

$root=__DIR__.'/fixtures/comparison-v1';
$manifest=json_decode((string)file_get_contents($root.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
if(($manifest['status']??null)!=='GOLDEN_LOCKED')failGolden('manifest not GOLDEN_LOCKED');
if(($manifest['engine_version']??null)!==CalculationEngineV100::VERSION)failGolden('manifest CE version mismatch');
$fx=fixtures();$runs=[];
foreach($fx as $id=>$request){$entry=$manifest['fixtures'][$id]??null;if(!is_array($entry)||($entry['status']??null)!=='GOLDEN_LOCKED')failGolden("$id not GOLDEN_LOCKED");$path=$root.'/'.$entry['expected'];if(!is_file($path))failGolden("$id fixture missing");$expected=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);$fixtureJson=jsonCanonical($expected);$fixtureSha=hash('sha256',$fixtureJson);if(!hash_equals((string)$entry['sha256'],$fixtureSha))failGolden("$id fixture SHA256 differs from manifest");}
for($run=1;$run<=2;$run++){foreach($fx as $id=>$request){$result=ComparisonModelV100::calculate($request);if(!finite($result))failGolden("$id non-finite");$json=jsonCanonical($result);if(preg_match('/NaN|Infinity|-Infinity/',$json))failGolden("$id non-finite token");$runs[$run][$id]=['json'=>$json,'sha256'=>hash('sha256',$json)];echo "RUN$run $id SHA256 ".$runs[$run][$id]['sha256']."\n";}}
foreach(array_keys($fx) as $id){$entry=$manifest['fixtures'][$id];$expected=json_decode((string)file_get_contents($root.'/'.$entry['expected']),true,512,JSON_THROW_ON_ERROR);$golden=jsonCanonical($expected);if($runs[1][$id]['json']!==$runs[2][$id]['json'])failGolden("$id run JSON differs");if($runs[2][$id]['json']!==$golden)failGolden("$id result differs from GOLDEN_LOCKED fixture");if(!hash_equals((string)$entry['sha256'],$runs[2][$id]['sha256']))failGolden("$id result SHA256 differs from manifest");echo "$id GOLDEN_LOCKED MATCH; SHA256 ".$runs[2][$id]['sha256']."\n";}
echo "C01-C03 GOLDEN_LOCKED fixture verification PASS\n";
