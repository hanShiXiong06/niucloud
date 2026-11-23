<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;
use addon\hsx_boyuan\app\support\CatalogStore;

use addon\hsx_boyuan\app\support\Contract;
use think\facade\Db;

/** Identity for an explicitly approved cross-catalog mapping, never a name-based product search. */
final class RecyclePurchaseSku
{
    /** A key with a null value means "recycle row; manual selection only", not an ERP text mapping. */
    public static function identities(int $site,array $head,array $lines): array
    {
        $out=array_fill_keys(array_column($lines,'source_line_id'),null);
        if ((int)($head['site_id']??0)!==$site || ($head['status']??'')!=='completed' || ($head['source_plugin']??'')!=='hsx_recycle'
            || ($head['origin_plugin']??'')!=='hsx_recycle' || ($head['origin_type']??'')!=='hsx_recycle.recycle_purchase' || !function_exists('event')) return $out;
        foreach ($lines as $line) {
            $id=(int)substr($line['source_line_id'],14); $assetId=(int)substr($line['device_ref'],6);
            // Inspect one report at a time: a single USB + inspection snapshot can already exceed 1MB.
            $purchase=Db::name('erp_purchase_item')->where('site_id',$site)->where('purchase_order_id',(int)$head['id'])
                ->where('id',$id)->where('asset_id',$assetId)->field('imei,model,spec,color,spec_json,qc_report')->find();
            $asset=Db::name('erp_asset')->where('site_id',$site)->where('purchase_order_id',(int)$head['id'])->where('purchase_item_id',$id)
                ->where('id',$assetId)->where('source_plugin','hsx_recycle')->field('imei,spec_json,model,spec,color')->find();
            if (!$purchase || !$asset || $line['imei']!==$purchase['imei'] || $line['imei']!==$asset['imei']) continue;
            $variant=array_intersect_key($purchase,array_flip(['model','spec','color']));
            foreach (['model','spec','color'] as $field) if (($asset[$field]??null)!==($variant[$field]??null)) continue 2;
            if ($line['product_ref']!=='model:'.hash('sha256',json_encode(['model'=>$asset['model'],'spec'=>$asset['spec'],'color'=>$asset['color']],JSON_UNESCAPED_UNICODE))) continue;
            $meta=self::json($purchase['spec_json']??null); $current=self::json($asset['spec_json']??null);
            foreach (['source_plugin','source_catalog','source_device_id','capacity','color'] as $f) {
                if (!isset($meta[$f]) || $meta[$f]!==($current[$f]??null)) continue 2;
            }
            $source=$meta['source_catalog']; $device=$meta['source_device_id'];
            if ($meta['source_plugin']!=='hsx_recycle' || !is_int($device) || $device<1 || !is_array($source)) continue;
            $owner=$source['owner_site_id']??null; $leaf=$source['model_id']??null;
            if (!in_array($owner,[0,$site],true) || !is_int($leaf) || $leaf<1 || ($source['source_plugin']??'')!=='hsx_recycle'
                || ($source['namespace']??'')!=='recycle_device_model_dict:'.$owner) continue;
            $capacity=self::memory($meta['capacity']); $color=self::color($meta['color']);
            if ($capacity==='' || $color==='' || self::color($asset['color'])!==$color
                || $asset['spec']!==trim($meta['capacity']).' '.trim($meta['color'])) continue;
            $report=self::json($purchase['qc_report']??null);
            if (($report['source_plugin']??'')!=='hsx_recycle' || ($report['source_device_id']??null)!==$device) continue;
            $version=self::version($report,$line); if (!$version) continue;
            try {
                $responses=event('HsxErpSourceCatalogRequested',['site_id'=>$site,'source_plugin'=>'hsx_recycle','source_device_id'=>$device]);
            } catch (\Throwable $e) {
                // Optional provider: no current confirmation => no automatic mapping.
                continue;
            }
            $matches=array_values(array_filter((array)$responses,static fn($r)=>is_array($r) && ($r['provider']??'')==='hsx_recycle'));
            $live=$matches[0]['catalog']??[];
            if (count($matches)!==1 || !is_array($live)) continue;
            foreach (['source_plugin','owner_site_id','namespace','model_id'] as $f) if (($live[$f]??null)!==$source[$f]) continue 2;
            $path=explode('/',(string)($live['model_name']??'')); $leafName=trim((string)end($path));
            $label=implode(' · ',array_filter([$leafName,$capacity,$color,$version['inspection'],$version['sku']],'strlen'));
            $out[$line['source_line_id']]=['ref'=>'recycle-sku:'.hash('sha256',Contract::json([$source['namespace'],$leaf,$capacity,$color,$version])),
                'label'=>$label,'capacity'=>$capacity,'color'=>$color,'version'=>$version['inspection'],'hardware_sku'=>$version['sku']!==''];
        }
        return $out;
    }

    private static function json($raw): array
    {
        if (is_array($raw)) return $raw;
        $r=is_string($raw)?json_decode($raw,true):null;
        return is_array($r)?$r:[];
    }
    private static function memory($value): string
    {
        if (!is_string($value) || !preg_match('/^([1-9][0-9]{0,3})\s*([GT])B?(?:\s*\+\s*([1-9][0-9]{0,3})\s*([GT])B?)?$/iD',trim($value),$m)) return '';
        return $m[1].strtoupper($m[2]).(isset($m[3])?'+'.$m[3].strtoupper($m[4]):'');
    }
    private static function color($value): string
    {
        if (!is_string($value)) return '';
        $v=trim($value);
        if (preg_match('/^([黑白红蓝绿紫金银灰粉黄橙青])色?$/uD',$v,$m)) return $m[1];
        if (preg_match('/^(?:[\p{Han}]{1,8}[黑白红蓝绿紫金银灰粉黄橙青]|[\p{Han}]{1,8}[黑白红蓝绿紫金银灰粉黄橙青]色)$/uD',$v)) return $v;
        return preg_match('/^(?:black|white|red|blue|green|purple|gold|silver|gray|grey|pink|yellow|orange)$/iD',$v)?strtolower($v):'';
    }
    private static function version(array $report,array $line): array
    {
        $inspection='';
        $items=$report['report']['result_items']??[];
        if (!is_array($items)) return [];
        foreach ($items as $item) {
            // These are explicit operator-defined inspection field titles, never guess from notes or OS version.
            if (!is_array($item) || !in_array($item['field_name']??'',['销售版本','版本类型','销售地区版本'],true)) continue;
            $labels=$item['labels']??null; $v=is_array($labels) && count($labels)===1 && is_string($labels[0]??null)?trim($labels[0]):'';
            if ($v==='' || mb_strlen($v)>40 || preg_match('/[[:cntrl:]]|未知|不详|不明|默认|通用|其他|其它|未选/u',$v) || ctype_digit($v)
                || ($inspection!=='' && $inspection!==$v)) return [];
            $inspection=$v;
        }
        $sku=''; $raw=$report['device_readings']['local']['raw']??[];
        if (is_array($raw) && ($raw['schema_version']??'')==='hsx.device.snapshot.v1' && ($raw['platform']??'')==='ios') {
            $hardware=$raw['hardware']??[]; $actual=$raw['identity']??[];
            if (!is_array($hardware) || !is_array($actual)) return [];
            if (!in_array($line['imei'],[$actual['imei']??'', $actual['imei2']??''],true)
                && (empty($line['sn']) || $line['sn']!==($actual['serial_number']??''))) return [];
            $number=$hardware['model_number']??''; $region=$hardware['region_code']??''; $full=$hardware['model_number_full']??'';
            if (is_string($number) && preg_match('/^[A-Z0-9]{4,8}$/D',$number) && is_string($region) && preg_match('~^[A-Z0-9]{1,3}/A$~D',$region)
                && $full===$number.$region) $sku=$full;
        }
        return $sku!=='' || $inspection!=='' ? ['sku'=>$sku,'inspection'=>$inspection]:[];
    }

    /** Require current archived SKU attributes; a name-only or stale product never becomes reusable. */
    public static function compatible(array $identity,array $extra): bool
    {
        return self::memory($extra['memory']??null)===$identity['capacity'] && self::color($extra['color']??null)===$identity['color']
            && ($identity['version']==='' || ($identity['version']===trim((string)($extra['version_type_name']??'')) && !empty($extra['version_type_id'])));
    }

    /** A market/version label alone is not a manufacturer SKU. Refuse duplicate or incomplete variants within its official model family. */
    public static function unambiguous(int $site,string $key,string $external,array $identity,array $extra,array &$families): bool
    {
        if (!self::compatible($identity,$extra)) return false;
        if ($identity['hardware_sku']) return true; // The operator confirmed the full physical SKU including its market.
        $family=$extra['name']??null;
        if (!is_string($family) || trim($family)==='' || empty($extra['brand_id'])) return false;
        if (!array_key_exists($family,$families)) {
            $run=ReferenceService::latestRun($site,$key,'products');
            $rows=$run===''?[]:CatalogStore::query('products')->where('site_id',$site)->where('connection_key',$key)
                ->where('enabled',1)->where('seen_run',$run)->where('model_name',$family)->field('external_id,extra_json')->page(1,201)->select()->toArray();
            $families[$family]=count($rows)>200?[]:array_column($rows,'extra_json','external_id');
        }
        $rows=$families[$family];
        if (!isset($rows[$external])) return false;
        foreach ($rows as $id=>$raw) {
            if ((string)$id===$external) continue;
            $other=self::json($raw); $cap=self::memory($other['memory']??null); $color=self::color($other['color']??null);
            if (!empty($other['brand_id']) && $extra['brand_id']!==$other['brand_id']
                || $cap!=='' && $cap!==$identity['capacity'] || $color!=='' && $color!==$identity['color']) continue;
            $version=$other['version_type_name']??'';
            if (is_string($version) && trim($version)!=='' && !empty($other['version_type_id']) && trim($version)!==$identity['version']) continue;
            return false; // Equally plausible or incomplete live SKU; always ask this time.
        }
        return true;
    }
    public static function unchanged(array $old,array $current): bool
    {
        foreach (['name','brand_id','category_id','version_type_id','version_type_name','memory','color'] as $f) {
            if (($old[$f]??null)!==($current[$f]??null)) return false;
        }
        return true;
    }
}
