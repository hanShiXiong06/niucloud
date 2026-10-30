<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service;

use addon\hsx_lead\app\support\Data;
use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;
use think\facade\Db;

/** A curated demand directory uses framework config, not another device-catalog table. */
final class CatalogService
{
    public const KEY='HSX_LEAD_DEMAND_CATALOG';
    public function __construct(private readonly Context $context) {}

    public function get(): array
    {
        $this->context->manager();
        $value=(new CoreConfigService())->getConfigValue($this->context->site,self::KEY);
        if (!empty($value['items'])) return ['items'=>$value['items'],'source'=>'configured','updated_at'=>(int)($value['updated_at']??0)];
        $rows=Db::name('hsx_lead_inventory')->alias('i')
            ->join('hsx_lead_connection c','c.id=i.connection_id AND c.site_id=i.site_id AND c.merchant_id=i.merchant_id AND c.published_run_id=i.sync_run_id')
            ->join('hsx_lead_merchant m','m.id=i.merchant_id AND m.site_id=i.site_id')
            ->where('i.site_id',$this->context->site)->where('m.status',1)->where('i.model','<>','')
            ->field('i.brand,i.model')->group('i.brand,i.model')->limit(5000)->select()->toArray();
        $items=[]; $seen=[];
        foreach(require dirname(__DIR__).'/dict/catalog/default.php' as $row) {
            $item=$this->item($row); $items[$item['id']]=$item; $seen[$item['brand'].'|'.$item['title']]=true;
        }
        foreach($rows as $row) {
            if (preg_match('/报废|内爆|故障|损坏|不开机/u',(string)$row['model'])) continue;
            $item=$this->item($row);
            // A stock-derived duplicate must not erase the curated aliases/category.
            $key=$item['brand'].'|'.$item['title'];
            if (!isset($seen[$key]) && count($items)<5000) { $items[$item['id']]=$item; $seen[$key]=true; }
        }
        return ['items'=>array_values($items),'source'=>'default_and_inventory','updated_at'=>0,
            'notice'=>'内置常用需求选项，补充已同步库存型号；清单本身不代表商户有货'];
    }

    public function save(array $input): array
    {
        $this->context->manager();
        $content=Data::text($input,'content',1048576);
        if (strlen($content)>1048576) throw new CommonException('设备清单文件请控制在1MB以内');
        $format=Data::text($input,'format',10);
        if (!mb_check_encoding($content,'UTF-8')) throw new CommonException('请使用UTF-8编码的CSV或JSON文件');
        $content=preg_replace('/^\xEF\xBB\xBF/','',$content)??$content;
        if ($format==='json') {
            try { $rows=json_decode($content,true,64,JSON_THROW_ON_ERROR|JSON_BIGINT_AS_STRING); }
            catch(\Throwable $error) { throw new CommonException('JSON格式不正确，请上传设备数组或含items的对象'); }
            if (is_array($rows) && isset($rows['items'])) $rows=$rows['items'];
        } elseif ($format==='csv') $rows=$this->csv($content);
        else throw new CommonException('请上传CSV或JSON；Excel请先另存为UTF-8 CSV');
        if (!is_array($rows) || !array_is_list($rows) || count($rows)>5000) throw new CommonException('清单应为数组，最多5000项');
        if (!$rows) throw new CommonException('设备清单为空，未覆盖已有配置');
        $items=[];
        foreach($rows as $index=>$row) {
            if (!is_array($row)) throw new CommonException('第'.($index+1).'项不是有效设备');
            $item=$this->item($row); $items[$item['id']]=$item;
        }
        $items=array_values($items);
        Db::transaction(function()use($items){
            Db::name('site')->where('site_id',$this->context->site)->lock(true)->field('site_id')->find();
            (new CoreConfigService())->setConfig($this->context->site,self::KEY,['items'=>$items,'updated_at'=>time()]);
            $this->context->audit('catalog',0,'replaced',['items_count'=>count($items)]);
        });
        return $this->get()+['message'=>'设备清单已保存；既有工单继续保留录入时的需求信息'];
    }

    private function item(array $row): array
    {
        $title=Data::text(['title'=>$row['title']??$row['model']??$row['型号']??''],'title',200,true);
        $brand=Data::text(['brand'=>$row['brand']??$row['品牌']??''],'brand',100);
        $category=Data::text(['category'=>$row['category_name']??$row['category']??$row['分类']??''],'category',100);
        $aliases=$row['aliases']??$row['别名']??[];
        if (is_string($aliases)) $aliases=preg_split('/[|；;\r\n]+/u',$aliases,-1,PREG_SPLIT_NO_EMPTY);
        if (!is_array($aliases) || count($aliases)>20) throw new CommonException('每个型号最多20个别名，使用分号或竖线分隔');
        $clean=[];
        foreach($aliases as $alias) $clean[]=Data::text(['alias'=>$alias],'alias',200,true);
        return ['id'=>'cat_'.substr(hash('sha256',$brand.'|'.$title.'|'.$category),0,24),'title'=>$title,'brand'=>$brand,
            'category_name'=>$category,'aliases'=>array_values(array_unique($clean))];
    }

    private function csv(string $content): array
    {
        $stream=fopen('php://temp','r+');
        if (!$stream) throw new CommonException('无法读取CSV文件');
        try {
            fwrite($stream,$content); rewind($stream);
            $header=fgetcsv($stream,0,',','"','');
            if (!$header) return [];
            $header=array_map('trim',$header); $rows=[];
            if (count(array_unique($header))!==count($header)) throw new CommonException('CSV表头有重复列，请核对后重新导入');
            while(($values=fgetcsv($stream,0,',','"',''))!==false) {
                if (count($values)===1 && trim((string)$values[0])==='') continue;
                if (count($values)!==count($header)) throw new CommonException('CSV列数不一致，请核对表头、逗号和引号');
                $rows[]=array_combine($header,$values);
                if (count($rows)>5000) throw new CommonException('设备清单最多5000项');
            }
            return $rows;
        } finally { fclose($stream); }
    }

    public function resolve(array $requested,array $previous=[]): array
    {
        $this->context->manager();
        if (!$requested || count($requested)>20 || !array_is_list($requested)) throw new CommonException('请勾选1—20项意向设备');
        $directory=array_column($this->get()['items'],null,'id');
        foreach($previous as $item) if (isset($item['catalog_id']) && !isset($directory[$item['catalog_id']])) {
            $directory[$item['catalog_id']]=['id'=>$item['catalog_id'],'title'=>$item['title'],'brand'=>$item['brand']??'',
                'category_name'=>$item['category_name']??'','aliases'=>$item['aliases']??[]];
        }
        $result=[]; $seen=[];
        foreach($requested as $entry) {
            if (!is_array($entry)) throw new CommonException('需求设备格式不正确');
            $id=Data::text($entry,'catalog_id',100,true);
            if (!isset($directory[$id])) throw new CommonException('所选型号已不在当前清单，请刷新并重新勾选');
            if (isset($seen[$id])) throw new CommonException('同一型号请修改数量，不要重复勾选');
            $seen[$id]=true; $quantity=$entry['quantity']??1;
            if ((!is_int($quantity) && !is_string($quantity)) || !preg_match('/^[1-9]\d{0,2}$/D',(string)$quantity)) throw new CommonException('每项设备数量请填写1—999的整数');
            $min=Data::amount($entry['budget_min']??null,true); $max=Data::amount($entry['budget_max']??null,true);
            if ($min!==null && $max!==null && bccomp($min,$max,2)>0) throw new CommonException('每项设备预算下限不能高于上限');
            $item=$directory[$id];
            $result[]=['catalog_id'=>$id,'title'=>$item['title'],'brand'=>$item['brand'],'category_name'=>$item['category_name'],
                'aliases'=>$item['aliases'],'quantity'=>(int)$quantity,'budget_min'=>$min,'budget_max'=>$max,'remark'=>Data::text($entry,'remark',1000)];
        }
        return $result;
    }
}
