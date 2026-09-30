<?php
declare(strict_types=1);
require_once __DIR__.'/calculation_engine.php';

final class ComparisonException extends RuntimeException {
    public function __construct(public readonly string $errorCode, public readonly string $path='', public readonly ?string $variantId=null, public readonly ?string $assetId=null, public readonly array $details=[], int $httpStatus=422){ parent::__construct($errorCode,$httpStatus); }
    public function httpStatus(): int { return $this->getCode() ?: 422; }
}

final class ComparisonModelV100 {
    public const REQUEST_VERSION='comparison-1.0.0';
    public const RESULT_VERSION='comparison-result-1.0.0';
    private const METRICS=['nominal_value','real_value','cumulative_contributions','cumulative_entry_fees','cumulative_ongoing_fees','cumulative_fixed_fees','cumulative_total_fees','net_gain'];

    public static function calculate(array $r, ?callable $ce=null): array {
        self::validate($r);
        $ce ??= static fn(array $input): array => CalculationEngineV100::calculate($input);
        $results=[];
        foreach($r['variants'] as $vi=>$v){
            $input=['horizon_years'=>$r['common']['horizon_years'],'inflation_rate'=>$r['common']['inflation_rate'],'snapshot_years'=>$r['common']['snapshot_years'],'assets'=>$v['assets']];
            try{$out=$ce($input);}catch(InvalidArgumentException $e){
                $mapped=self::mapCeError($e->getMessage(),$r,$vi,$v);
                throw new ComparisonException('CE_REQUEST_INVALID',$mapped['path'],$v['id'],$mapped['asset_id'],['ce_error'=>['code'=>$mapped['code'],'field'=>$mapped['field']]],422);
            }catch(Throwable $e){throw new ComparisonException('COMPARISON_INCOMPLETE',"/variants/$vi",$v['id'],null,['reason'=>'CE_RUNTIME_FAILURE'],502);}
            self::validateCeResult($out,$vi,$v['id']);
            $results[$v['id']]=['name'=>$v['name'],'ce'=>$out];
        }
        $baseId=$r['baseline_variant_id']; $base=$results[$baseId]['ce']; $variants=[];
        foreach($r['variants'] as $v){$id=$v['id'];$out=$results[$id]['ce'];$res=[];$delta=[];
            foreach(self::METRICS as $m){$value=(float)$out[$m];$b=(float)$base[$m];$abs=$id===$baseId?0.0:$value-$b;$rel=$id===$baseId?0.0:($b==0.0?null:$abs/abs($b));$res[$m]=$value;$delta[$m]=['absolute'=>$abs,'relative'=>$rel];}
            $variants[]=['id'=>$id,'name'=>$v['name'],'result'=>$res,'delta_vs_baseline'=>$delta];
        }
        $result=['schema_version'=>self::RESULT_VERSION,'comparison_id'=>$r['comparison_id'],'calculation_status'=>'OK','engine_version'=>CalculationEngineV100::VERSION,'baseline_variant_id'=>$baseId,'variants'=>$variants,'failed_variant_ids'=>[]];
        self::assertFinite($result); return $result;
    }

    public static function validate(array $r): void {
        foreach(['schema_version','comparison_id','name','common','baseline_variant_id','variants'] as $k) if(!array_key_exists($k,$r)) throw new ComparisonException('SCHEMA_VALIDATION_FAILED',"/$k");
        if($r['schema_version']!==self::REQUEST_VERSION) throw new ComparisonException('SCHEMA_VALIDATION_FAILED','/schema_version');
        if(!is_string($r['comparison_id'])||!preg_match('/^[A-Za-z0-9_-]{1,120}$/',$r['comparison_id'])) throw new ComparisonException('SCHEMA_VALIDATION_FAILED','/comparison_id');
        if(!is_string($r['name'])||trim($r['name'])===''||strlen($r['name'])>120) throw new ComparisonException('SCHEMA_VALIDATION_FAILED','/name');
        if(!is_array($r['common'])||!isset($r['common']['horizon_years'],$r['common']['inflation_rate'],$r['common']['snapshot_years'])) throw new ComparisonException('SCHEMA_VALIDATION_FAILED','/common');
        $c=$r['common']; if(!is_int($c['horizon_years'])||$c['horizon_years']<1||$c['horizon_years']>100) throw new ComparisonException('SCHEMA_VALIDATION_FAILED','/common/horizon_years');
        if(!is_numeric($c['inflation_rate'])||!is_finite((float)$c['inflation_rate'])||(float)$c['inflation_rate']<=-1) throw new ComparisonException('SCHEMA_VALIDATION_FAILED','/common/inflation_rate');
        if(!is_array($c['snapshot_years'])) throw new ComparisonException('SCHEMA_VALIDATION_FAILED','/common/snapshot_years'); $seenS=[];
        foreach($c['snapshot_years'] as $i=>$y){if(!is_int($y)||$y<1) throw new ComparisonException('SCHEMA_VALIDATION_FAILED',"/common/snapshot_years/$i");if(isset($seenS[$y])) throw new ComparisonException('SCHEMA_VALIDATION_FAILED',"/common/snapshot_years/$i");$seenS[$y]=1;if($y>$c['horizon_years']) throw new ComparisonException('SNAPSHOT_OUT_OF_HORIZON',"/common/snapshot_years/$i",null,null,['snapshot_year'=>$y,'horizon_years'=>$c['horizon_years']]);}
        if(!is_array($r['variants'])||count($r['variants'])<2||count($r['variants'])>4) throw new ComparisonException('SCHEMA_VALIDATION_FAILED','/variants');
        $seen=[];
        foreach($r['variants'] as $vi=>$v){$id=$v['id']??null;if(!is_string($id)||!preg_match('/^[A-Za-z0-9_-]{1,120}$/',$id)) throw new ComparisonException('SCHEMA_VALIDATION_FAILED',"/variants/$vi/id");if(isset($seen[$id])) throw new ComparisonException('DUPLICATE_VARIANT_ID',"/variants/$vi/id",$id);$seen[$id]=1;
            if(!isset($v['name'])||!is_string($v['name'])||trim($v['name'])===''||strlen($v['name'])>80) throw new ComparisonException('SCHEMA_VALIDATION_FAILED',"/variants/$vi/name",$id);
            if(!isset($v['assets'])||!is_array($v['assets'])||count($v['assets'])<1) throw new ComparisonException('SCHEMA_VALIDATION_FAILED',"/variants/$vi/assets",$id);$sa=[];
            foreach($v['assets'] as $ai=>$a){$aid=$a['id']??null;if(!is_string($aid)||!preg_match('/^[A-Za-z0-9_-]{1,120}$/',$aid)) throw new ComparisonException('SCHEMA_VALIDATION_FAILED',"/variants/$vi/assets/$ai/id",$id);if(isset($sa[$aid])) throw new ComparisonException('DUPLICATE_ASSET_ID',"/variants/$vi/assets/$ai/id",$id,$aid);$sa[$aid]=1;}
        }
        if(!is_string($r['baseline_variant_id'])||!isset($seen[$r['baseline_variant_id']])) throw new ComparisonException('BASELINE_VARIANT_NOT_FOUND','/baseline_variant_id',is_string($r['baseline_variant_id'])?$r['baseline_variant_id']:null);
    }

    private static function validateCeResult(array $o,int $vi,string $id): void {if(($o['success']??false)!==true||($o['calculation_status']??null)!=='OK'||($o['engine_version']??null)!==CalculationEngineV100::VERSION) throw new ComparisonException('CE_INVALID_RESPONSE',"/variants/$vi",$id,null,[],502);foreach(self::METRICS as $m)if(!array_key_exists($m,$o)||!is_numeric($o[$m])||!is_finite((float)$o[$m]))throw new ComparisonException('CE_INVALID_RESPONSE',"/variants/$vi",$id,null,['metric'=>$m],502);}
    private static function mapCeError(string $code,array $r,int $vi,array $v): array {$fieldMap=['INITIAL_CONTRIBUTION_NEGATIVE'=>'initial_contribution','MONTHLY_CONTRIBUTION_NEGATIVE'=>'monthly_contribution','ANNUAL_RETURN_OUT_OF_RANGE'=>'annual_return','ENTRY_FEE_INITIAL_OUT_OF_RANGE'=>'entry_fee_initial_pct','ENTRY_FEE_MONTHLY_OUT_OF_RANGE'=>'entry_fee_monthly_pct','ONGOING_FEE_OUT_OF_RANGE'=>'ongoing_fee_pct_pa','FIXED_FEE_NEGATIVE'=>'fixed_fee_monthly'];$field=$fieldMap[$code]??null;$ai=0;if($field!==null){foreach($v['assets'] as $i=>$a){try{CalculationEngineV100::calculate(['horizon_years'=>$r['common']['horizon_years'],'inflation_rate'=>$r['common']['inflation_rate'],'snapshot_years'=>$r['common']['snapshot_years'],'assets'=>[$a]]);}catch(InvalidArgumentException $e){if($e->getMessage()===$code){$ai=$i;break;}}}}return ['code'=>$code,'field'=>$field,'asset_id'=>$v['assets'][$ai]['id']??null,'path'=>$field!==null?"/variants/$vi/assets/$ai/$field":"/variants/$vi"];}
    private static function assertFinite(mixed $v): void {if(is_float($v)&&!is_finite($v))throw new ComparisonException('COMPARISON_INVARIANT_FAILED','',null,null,[],500);if(is_array($v))foreach($v as $x)self::assertFinite($x);}
}
