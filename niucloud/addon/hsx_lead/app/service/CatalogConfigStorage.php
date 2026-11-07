<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service;

use core\exception\CommonException;
use think\facade\{Cache,Db};

/** Plugin-only business, directory and merchant-capability JSON beyond TEXT. */
final class CatalogConfigStorage
{
    public const MARKER='_hsx_lead_catalog_storage';
    public const MANIFEST_VERSION='chunks.v1';
    public const PART_VERSION='part.v1';
    public const INLINE_BYTES=49152;
    public const PART_BYTES=24576;
    public const MAX_BYTES=16777216;
    public const MAX_PARTS=683;
    private const KEYS=['HSX_LEAD_DEMAND_CATALOG','HSX_LEAD_COOPERATION'];
    private const ROW_BYTES=61440;

    /** A short shared-lock transaction keeps manifest and fragments coherent. */
    public static function read(int $site,string $key,bool $lock=false): array
    {
        self::identity($site,$key);
        return Db::transaction(static function()use($site,$key,$lock):array {
            $row=self::row($site,$key,$lock?true:'LOCK IN SHARE MODE');
            if (!$row) {
                if ($lock) throw new CommonException('当前事务未能定位后台目录配置，请刷新后重试；没有使用默认收费规则');
                return [];
            }
            $value=self::decode($row['value']??null);
            if (!array_key_exists(self::MARKER,$value)) return $value;
            $manifest=self::manifest($value);
            $keys=self::partKeys($key,$manifest);
            // A manifest obtained by a current locking read may be newer than
            // an outer REPEATABLE READ snapshot. Persisted primary IDs avoid
            // a snapshot-only name lookup, and FORCE PRIMARY keeps the lock
            // range confined even when the table has no site/key index.
            $rows=Db::name('sys_config')->force('PRIMARY')->whereIn('id',$manifest['row_ids'])->where('site_id',$site)
                ->field('id,site_id,config_key,value')->lock($lock?true:'LOCK IN SHARE MODE')->select()->toArray();
            if (count($rows)!==$manifest['parts']) throw new CommonException('后台目录配置分片缺失，请管理员核对或重新导入；没有返回不完整目录');
            $byKey=[];
            foreach($rows as $partRow) {
                $partKey=(string)$partRow['config_key'];
                if (isset($byKey[$partKey]) || !in_array($partKey,$keys,true)) throw new CommonException('后台目录配置存在重复或错配分片，请管理员核对');
                $byKey[$partKey]=$partRow;
            }
            $payload='';
            foreach($keys as $index=>$partKey) {
                if (!isset($byKey[$partKey]) || (int)$byKey[$partKey]['id']!==$manifest['row_ids'][$index]) throw new CommonException('后台目录配置分片缺失或索引变化，请管理员核对或重新导入');
                $part=self::decode($byKey[$partKey]['value']??null);
                if (($part[self::MARKER]??null)!==self::PART_VERSION || ($part['base_key']??null)!==$key
                    || ($part['generation']??null)!==$manifest['generation'] || ($part['index']??null)!==$index
                    || !is_string($part['data']??null) || strlen($part['data'])>32768) {
                    throw new CommonException('后台目录配置分片不匹配，请管理员核对；没有返回不完整目录');
                }
                $bytes=base64_decode($part['data'],true);
                $expectedLength=$index===$manifest['parts']-1?$manifest['bytes']-($index*self::PART_BYTES):self::PART_BYTES;
                if ($bytes===false || strlen($bytes)!==$expectedLength || base64_encode($bytes)!==$part['data']) {
                    throw new CommonException('后台目录配置分片内容不完整，请管理员核对');
                }
                $payload.=$bytes;
            }
            if (strlen($payload)!==$manifest['bytes'] || !hash_equals($manifest['sha256'],hash('sha256',$payload))) {
                throw new CommonException('后台目录配置完整性核对失败，请管理员核对；没有返回截断目录');
            }
            $decoded=self::decode($payload);
            if (array_key_exists(self::MARKER,$decoded)) throw new CommonException('后台目录配置包含保留存储标识，请管理员核对');
            return $decoded;
        });
    }

    /** Caller must hold the site row lock and an encompassing DB transaction. */
    public static function write(int $site,string $key,array $value): void
    {
        self::identity($site,$key);
        $pdo=Db::connect()->getPdo();
        if (!$pdo instanceof \PDO || !$pdo->inTransaction()) throw new CommonException('后台目录保存缺少完整事务保护，未执行保存');
        if (array_key_exists(self::MARKER,$value)) throw new CommonException('目录内容不能使用内部存储标识');
        $payload=self::encode($value);
        if (strlen($payload)>self::MAX_BYTES) throw new CommonException('后台业务与目录配置总量超过16MB，请精简目录后重新保存');
        $old=self::row($site,$key,true);
        $oldManifest=null;
        if ($old && is_string($old['value']??null) && $old['value']!=='') {
            // A deliberate validated replacement may repair a previously
            // truncated inline JSON value. Never guess fragment keys from it.
            try {$oldValue=self::decode($old['value']);}
            catch(CommonException $error) {$oldValue=[];}
            if (array_key_exists(self::MARKER,$oldValue)) $oldManifest=self::manifest($oldValue);
        }
        if (strlen($payload)<=self::INLINE_BYTES) {
            self::writeRow($site,$key,$payload);
        } else {
            do {$generation=bin2hex(random_bytes(8));} while($oldManifest && $generation===$oldManifest['generation']);
            $parts=(int)ceil(strlen($payload)/self::PART_BYTES);
            $manifest=[self::MARKER=>self::MANIFEST_VERSION,'generation'=>$generation,'parts'=>$parts,
                'bytes'=>strlen($payload),'sha256'=>hash('sha256',$payload)];
            $keys=self::partKeys($key,$manifest);$rowIds=[];
            foreach($keys as $index=>$partKey) {
                $part=[self::MARKER=>self::PART_VERSION,'base_key'=>$key,'generation'=>$generation,'index'=>$index,
                    'data'=>base64_encode(substr($payload,$index*self::PART_BYTES,self::PART_BYTES))];
                $rowIds[]=self::writeRow($site,$partKey,self::encode($part));
            }
            $manifest['row_ids']=$rowIds;
            // Switch the manifest only after all new fragments read back exactly.
            self::writeRow($site,$key,self::encode($manifest));
        }
        if ($oldManifest) {
            $obsolete=self::partKeys($key,$oldManifest);
            foreach($obsolete as $index=>$obsoleteKey) {
                // Point DELETEs cannot scan-lock other sites on the framework
                // table, which intentionally need not have a site/key index.
                Db::name('sys_config')->where('id',$oldManifest['row_ids'][$index])->where('site_id',$site)->where('config_key',$obsoleteKey)->delete();
            }
        }
        // Readers here bypass cache; evict only the framework compatibility key.
        self::evict($site,$key);
    }

    /** Call again after the caller's outer transaction has committed. */
    public static function evict(int $site,string $key): void
    {
        self::identity($site,$key);
        Cache::delete('site_config_cache'.$key.'_'.$site);
    }

    private static function identity(int $site,string $key): void
    {
        $capability=(bool)preg_match('/^HSX_LEAD_CAPABILITY_[1-9][0-9]{0,9}$/D',$key);
        if ($site<=0 || (!in_array($key,self::KEYS,true)&&!$capability)) throw new CommonException('后台目录存储范围无效');
    }

    private static function encode(array $value): string
    {
        try {return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR);}
        catch(\JsonException $error) {throw new CommonException('后台目录配置不能完整编码，请核对文件内容');}
    }

    private static function decode($raw): array
    {
        if ($raw===null || $raw==='') return [];
        if (!is_string($raw) || strlen($raw)>self::MAX_BYTES) throw new CommonException('后台目录配置格式或容量异常，请管理员核对');
        try {$value=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}
        catch(\JsonException $error) {throw new CommonException('后台目录配置JSON不完整，可能曾被数据库截断，请管理员核对或重新导入');}
        if (!is_array($value)) throw new CommonException('后台目录配置不是有效对象，请管理员核对');
        return $value;
    }

    private static function manifest(array $value): array
    {
        if (($value[self::MARKER]??null)!==self::MANIFEST_VERSION
            || !is_string($value['generation']??null) || !preg_match('/^[a-f0-9]{16}$/D',$value['generation'])
            || !is_int($value['parts']??null) || $value['parts']<1 || $value['parts']>self::MAX_PARTS
            || !is_int($value['bytes']??null) || $value['bytes']<=self::INLINE_BYTES || $value['bytes']>self::MAX_BYTES
            || $value['parts']!==(int)ceil($value['bytes']/self::PART_BYTES)
            || !is_string($value['sha256']??null) || !preg_match('/^[a-f0-9]{64}$/D',$value['sha256'])
            || !is_array($value['row_ids']??null) || count($value['row_ids'])!==$value['parts']
            || array_keys($value['row_ids'])!==range(0,$value['parts']-1)
            || count(array_unique($value['row_ids'],SORT_REGULAR))!==$value['parts']) {
            throw new CommonException('后台目录存储索引异常，请管理员核对；不会使用默认数据替代');
        }
        foreach($value['row_ids'] as $id) if (!is_int($id) || $id<=0) throw new CommonException('后台目录分片主键索引异常，请管理员核对');
        return $value;
    }

    private static function partKeys(string $key,array $manifest): array
    {
        $prefix='HSX_LEAD_CFG_'.substr(hash('sha256',$key),0,32).'_'.$manifest['generation'].'_';
        $keys=[];
        for($index=0;$index<$manifest['parts'];$index++) $keys[]=$prefix.str_pad((string)$index,4,'0',STR_PAD_LEFT);
        return $keys;
    }

    private static function row(int $site,string $key,$lock): ?array
    {
        // Locate without locks first. A locking site/key table scan would lock
        // unrelated tenants because sys_config has only its primary index.
        $rows=Db::name('sys_config')->where('site_id',$site)->where('config_key',$key)->field('id')->limit(2)->select()->toArray();
        if (count($rows)>1) throw new CommonException('后台目录配置存在重复记录，请管理员核对');
        if (!$rows) return null;
        return self::rowById($site,$key,(int)$rows[0]['id'],$lock);
    }

    private static function rowById(int $site,string $key,int $id,$lock): array
    {
        $rows=Db::name('sys_config')->force('PRIMARY')->where('id',$id)->where('site_id',$site)->where('config_key',$key)
            ->field('id,site_id,config_key,value')->lock($lock)->select()->toArray();
        if (count($rows)!==1) throw new CommonException('后台目录配置定位已变化，请刷新后重试');
        return $rows[0];
    }

    private static function writeRow(int $site,string $key,string $json): int
    {
        if (strlen($json)>self::ROW_BYTES) throw new CommonException('后台目录分片超过安全容量，未保存截断内容');
        $old=self::row($site,$key,true);
        $now=time();
        if ($old) {
            $id=(int)$old['id'];
            Db::name('sys_config')->where('site_id',$site)->where('id',$old['id'])->where('config_key',$key)
                ->update(['value'=>$json,'update_time'=>$now]);
        } else {
            $id=(int)Db::name('sys_config')->insertGetId(['site_id'=>$site,'config_key'=>$key,'value'=>$json,'status'=>1,'create_time'=>$now,'update_time'=>$now]);
        }
        $saved=self::rowById($site,$key,$id,true);
        if (!$saved || !is_string($saved['value']) || !hash_equals($json,$saved['value'])) {
            throw new CommonException('后台目录保存回读不完整，事务已停止；未确认保存截断数据');
        }
        return $id;
    }
}
