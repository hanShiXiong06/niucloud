<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\support;

use think\facade\Db;

/** Physical read-only master archives. No table name or projection is supplied by an API caller. */
final class CatalogStore
{
    private const TABLES=[
        'companies'=>'company','warehouses'=>'warehouse','staff'=>'staff','parties'=>'party','members'=>'member',
        'products'=>'product','categories'=>'category','brands'=>'brand','payment_methods'=>'payment_method','capital_accounts'=>'capital_account',
    ];
    // Stored, typed SQL columns derived from the read-only provider record: a single truth at write time.
    // Type: label = descriptive text, id = stable key, flag = 0/1/NULL, phone = source-field provenance.
    private const COLUMNS=[
        'companies'=>['gsdm'=>'id','parent_id'=>'id','parent_name'=>'label','is_store'=>'flag','city_name'=>'label'],
        'warehouses'=>['company_id'=>'id','company_name'=>'label','warehouse_type_name'=>'label','is_allow_sales'=>'flag','is_general'=>'flag'],
        'staff'=>['company_id'=>'id','company_name'=>'label','is_buyer'=>'flag','is_sales'=>'flag','is_engineer'=>'flag'],
        'parties'=>['is_cust'=>'flag','is_supplier'=>'flag','party_phone_field'=>'phone','sales_name'=>'label','city_name'=>'label','cust_grade'=>'label'],
        'members'=>['company_id'=>'id','company_name'=>'label','card_type_name'=>'label','register_date'=>'label'],
        'products'=>['model_name'=>'label','category_id'=>'id','category_name'=>'label','brand_id'=>'id','brand_name'=>'label','memory'=>'label','color'=>'label','version_type_name'=>'label'],
        'categories'=>['parent_id'=>'id','parent_name'=>'label','category_code'=>'id'],
        'brands'=>['spell_code'=>'label','help_code'=>'label'],
        'payment_methods'=>['cash_type'=>'label','inout_tag'=>'label','account_id'=>'id','account_name'=>'label'],
        'capital_accounts'=>['gsdm'=>'id','company_id'=>'id','company_name'=>'label','account_type'=>'label'],
    ];
    private const INDEXES=[
        'companies'=>['gsdm'],'warehouses'=>['company_id'],'staff'=>['company_id','is_buyer','phone_hash'],
        'parties'=>['is_cust','is_supplier','phone_hash'],'members'=>['company_id','phone_hash'],
        'products'=>['model_name','brand_id','category_id'],'categories'=>['parent_id'],'brands'=>[],'payment_methods'=>['account_id'],'capital_accounts'=>['company_id'],
    ];
    public static function kinds():array {return array_keys(self::TABLES);}
    public static function table(string $kind):string {
        if(!isset(self::TABLES[$kind]))throw new Problem('RESOURCE_INVALID','不支持的博远资料类别');
        return 'hsx_boyuan_md_'.self::TABLES[$kind];
    }
    public static function query(string $kind) {return Db::name(self::table($kind));}
    private static function sourceKey(string $key):string {return $key==='model_name'?'name':$key;}
    public static function sourceKeys(string $kind):array {
        self::table($kind);
        return array_diff(array_map([self::class,'sourceKey'],array_keys(self::COLUMNS[$kind])),['party_phone_field']);
    }
    public static function schema(string $kind,string $name):string {
        self::table($kind);
        if(!preg_match('/^[A-Za-z0-9_]{1,64}$/D',$name))throw new Problem('CATALOG_SCHEMA','档案表名称无效');
        $cols=['id bigint unsigned NOT NULL AUTO_INCREMENT','site_id int unsigned NOT NULL','connection_key varchar(32) NOT NULL',
            'external_id varchar(128) NOT NULL',"code varchar(128) NOT NULL DEFAULT ''",'name varchar(255) NOT NULL',
            'enabled tinyint unsigned NOT NULL DEFAULT 0',"phone_hash varchar(64) NOT NULL DEFAULT ''","phone_masked varchar(64) NOT NULL DEFAULT ''",
            'extra_json text NOT NULL','seen_run varchar(32) NOT NULL','update_at int unsigned NOT NULL DEFAULT 0'];
        foreach(self::COLUMNS[$kind]+['status_known'=>'flag'] as $key=>$type){
            $src=self::sourceKey($key);$json="JSON_EXTRACT(extra_json,'$.".$src."')";$val="JSON_UNQUOTE(".$json.")";
            $expr="CASE WHEN JSON_TYPE(".$json.") IN ('STRING','INTEGER','DOUBLE','BOOLEAN') THEN ".$val." ELSE NULL END";
            $sqlType=$type==='id'?'varchar(128)':'varchar(255)';
            if($type==='flag'){$sqlType='tinyint unsigned';$expr="CASE WHEN ".$val." IN ('1','true') THEN 1 WHEN ".$val." IN ('0','false') THEN 0 ELSE NULL END";}
            if($type==='phone'){$sqlType='varchar(32)';$expr="COALESCE(".$expr.",'contact_phone')";}
            $cols[]='`'.$key.'` '.$sqlType.' GENERATED ALWAYS AS ('.$expr.') STORED';
        }
        $cols[]='PRIMARY KEY (id)';$cols[]='UNIQUE KEY record_identity (site_id,connection_key,external_id)';
        $cols[]='KEY batch (site_id,connection_key,seen_run,enabled,id)';$cols[]='KEY browse (site_id,connection_key,id)';
        foreach(self::INDEXES[$kind] as $c)$cols[]='KEY `by_'.$c.'` (site_id,connection_key,`'.$c.'`'.($c==='model_name'?'(120)':'').',id)';
        return 'CREATE TABLE IF NOT EXISTS `'.$name.'` ('.implode(',',$cols).') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    }
}
