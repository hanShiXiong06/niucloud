<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service;

use addon\hsx_lead\app\support\Data;
use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;
use think\facade\Db;
use addon\hsx_lead\app\service\cooperation\{BusinessService,ConfigService};

/** A curated demand directory uses framework config, not another device-catalog table. */
final class CatalogService
{
    public const KEY='HSX_LEAD_DEMAND_CATALOG';
    public function __construct(private Context $context) {}

    public function get(array $params=[]): array
    {
        $this->context->manager();
        if (!empty($params['business_type'])) {
            $business=(new BusinessService($this->context))->definition(Data::text($params,'business_type',16,true),false);
            return ['items'=>(new BusinessService($this->context))->options($business),'business_type'=>$business['key'],'source'=>'business_config','supports_preview'=>true];
        }
        return $this->directory()+['supports_preview'=>true];
    }

    /** Read-only demand labels; contains no customer data, stock prices or credentials. */
    public function directory(): array
    {
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
        $content=Data::text($input,'content',3000000);
        if (strlen($content)>3000000) throw new CommonException('目录文件内容过大，请将原文件控制在2MB以内');
        $format=Data::text($input,'format',10);
        if (!mb_check_encoding($content,'UTF-8')) throw new CommonException('请使用UTF-8编码的CSV或JSON文件');
        $content=preg_replace('/^\xEF\xBB\xBF/','',$content)??$content;
        if ($format==='json') {
            try { $rows=json_decode($content,true,64,JSON_THROW_ON_ERROR|JSON_BIGINT_AS_STRING); }
            catch(\Throwable $error) { throw new CommonException('JSON格式不正确，请上传设备数组或含items的对象'); }
            if (is_array($rows) && isset($rows['items'])) $rows=$rows['items'];
        } elseif ($format==='csv') $rows=$this->csv($content);
        elseif ($format==='xlsx') $rows=$this->xlsx($content);
        else throw new CommonException('请上传XLSX、UTF-8 CSV或JSON');
        if (!is_array($rows) || !\addon\hsx_lead\app\support\PhpRuntime::isList($rows) || count($rows)>5000) throw new CommonException('清单应为数组，最多5000项');
        if (!$rows) throw new CommonException('设备清单为空，未覆盖已有配置');
        $businessKey=Data::text($input,'business_type',16)?:'purchase';
        $business=(new BusinessService($this->context))->definition($businessKey,false);
        $items=[];$lastHierarchy=['',''];
        foreach($rows as $index=>$row) {
            if (!is_array($row)) throw new CommonException('第'.($index+1).'项不是有效设备');
            if (array_key_exists('一级分类',$row)) {
                if (($row['一级分类']??'')!==''&&$row['一级分类']!==$lastHierarchy[0]) $lastHierarchy[1]='';
                foreach(['一级分类','二级分类'] as $level=>$column) if (array_key_exists($column,$row)) {if ($row[$column]==='') $row[$column]=$lastHierarchy[$level];else $lastHierarchy[$level]=$row[$column];}
            }
            try {$item=$this->item($row);} catch(CommonException $error) {throw new CommonException('第'.($index+2).'行：'.$error->getMessage());}
            if ($business['dimension']!=='model'&&(!isset($row['id'])||$row['id']==='')) $item['id']='opt_'.substr(hash('sha256',$businessKey.'|'.implode('|',$item['path']).'|'.$item['title']),0,24);
            if (isset($items[$item['id']])) throw new CommonException('目录存在重复选项ID或重复路径，请核对后重新导入');
            $items[$item['id']]=$item;
        }
        $items=array_values($items);
        $candidate=$business;$candidate['options']=$items;BusinessService::validate([$candidate]);
        $preview=$input['preview']??false;
        if (!in_array($preview,[true,false,0,1,'0','1'],true)) throw new CommonException('预览参数无效');
        if (in_array($preview,[true,1,'1'],true)) return ['items'=>$items,'count'=>count($items),'preview'=>true,'business_type'=>$businessKey,'source'=>'preview'];
        Db::transaction(function()use($items,$businessKey){
            Db::name('site')->where('site_id',$this->context->site)->lock(true)->field('site_id')->find();
            $config=(new ConfigService($this->context))->get();$found=false;
            foreach($config['businesses'] as &$entry) {
                // Preserve sibling model directories before replacing their old shared fallback.
                if ($businessKey==='purchase'&&$entry['key']!=='purchase'&&$entry['dimension']==='model'&&!$entry['options']) $entry['options']=(new BusinessService($this->context))->options($entry);
                if ($entry['key']===$businessKey) {$found=true;$entry['options']=$businessKey==='purchase'?[]:$items;}
            }unset($entry);
            if (!$found) throw new CommonException('业务已变化，请重新选择导入业务');
            $config['businesses']=BusinessService::validate($config['businesses']);
            (new CoreConfigService())->setConfig($this->context->site,ConfigService::KEY,$config);
            if ($businessKey==='purchase') (new CoreConfigService())->setConfig($this->context->site,self::KEY,['items'=>$items,'updated_at'=>time()]);
            $this->context->audit('catalog',0,'replaced',['business_type'=>$businessKey,'items_count'=>count($items)]);
        });
        return $this->get(['business_type'=>$businessKey])+['count'=>count($items),'message'=>'当前业务目录已保存；其他业务和既有工单需求快照未改动'];
    }

    private function item(array $row): array
    {
        $title=Data::text(['title'=>$row['title']??$row['model']??$row['名称']??$row['型号']??$row['三级分类']??''],'title',200,true);
        $brand=Data::text(['brand'=>$row['brand']??$row['品牌']??''],'brand',100);
        $category=Data::text(['category'=>$row['category_name']??$row['category']??$row['分类']??$row['一级分类']??''],'category',100);
        $path=$row['path']??array_values(array_filter([$row['一级分类']??$category,$row['二级分类']??$brand,$row['三级分类']??$title],static fn($v)=>$v!==''));
        if (!is_array($path)||!\addon\hsx_lead\app\support\PhpRuntime::isList($path)||count($path)>3) throw new CommonException('目录分类最多三级');
        $path=array_map(static fn($part)=>BusinessService::label($part),$path);BusinessService::label($title,200);
        if ($brand!=='') BusinessService::label($brand);if ($category!=='') BusinessService::label($category);
        $aliases=$row['aliases']??$row['别名']??[];
        if (is_string($aliases)) $aliases=preg_split('/[|；;\r\n]+/u',$aliases,-1,PREG_SPLIT_NO_EMPTY);
        if (!is_array($aliases) || count($aliases)>20) throw new CommonException('每个型号最多20个别名，使用分号或竖线分隔');
        $clean=[];
        foreach($aliases as $alias) $clean[]=Data::text(['alias'=>$alias],'alias',200,true);
        $id=isset($row['id'])&&$row['id']!==''?$row['id']:'cat_'.substr(hash('sha256',$brand.'|'.$title.'|'.$category.(isset($row['一级分类'])||isset($row['path'])?'|'.implode('|',$path):'')),0,24);
        if (!is_string($id)||!preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$id)) throw new CommonException('目录ID格式错误');
        return ['id'=>$id,'title'=>$title,'brand'=>$brand,
            'category_name'=>$category,'path'=>$path,'aliases'=>array_values(array_unique($clean))];
    }

    private function xlsx(string $content): array
    {
        $binary=base64_decode($content,true);
        if ($binary===false||strlen($binary)>2097152||!str_starts_with($binary,'PK')) throw new CommonException('XLSX内容须为有效的base64，原文件不超过2MB');
        $file=tempnam(sys_get_temp_dir(),'hsx_lead_catalog_');if ($file===false) throw new CommonException('无法准备目录导入');
        $book=null;$zip=new \ZipArchive();$zipOpen=false;
        try {
            if (file_put_contents($file,$binary)!==strlen($binary)||$zip->open($file)!==true) throw new CommonException('无法读取XLSX文件');
            $zipOpen=true;
            $size=0;if ($zip->numFiles>150) throw new CommonException('XLSX工作簿结构过于复杂，请使用简单目录模板');
            for($i=0;$i<$zip->numFiles;$i++) {
                $stat=$zip->statIndex($i);$size+=(int)$stat['size'];
                if ($size>8388608) throw new CommonException('XLSX解压后过大，请减少格式、工作表和内容');
                if (preg_match('/vbaProject|activeX|embeddings|externalLinks/i',$stat['name'])) throw new CommonException('目录XLSX不得包含宏、嵌入对象或外部工作簿');
                if (preg_match('/\.(?:xml|rels)$/i',$stat['name'])) {
                    $xml=$zip->getFromIndex($i);
                    if ($xml===false||preg_match('/<!DOCTYPE|<!ENTITY|TargetMode\s*=\s*["\x27]External/i',$xml)) throw new CommonException('目录XLSX不得包含外部链接或XML实体');
                }
            }
            $zip->close();$zipOpen=false;
            $reader=new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();$reader->setReadDataOnly(true);$reader->setIncludeCharts(false);
            $info=$reader->listWorksheetInfo($file);
            if (count($info)!==1||(int)$info[0]['totalRows']>5001||(int)$info[0]['totalColumns']>12) throw new CommonException('请使用单工作表，最多5000条目录及12列');
            $book=$reader->load($file);$sheet=$book->getSheet(0);$header=[];$rows=[];
            foreach($sheet->getRowIterator() as $row) {
                $values=[];
                foreach($row->getCellIterator('A',\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(max(1,(int)$info[0]['totalColumns']))) as $cell) {
                    if ($cell->getDataType()===\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA) throw new CommonException('目录XLSX不支持公式，请先粘贴为值');
                    $value=$cell->getValue();if (is_object($value)) $value=(string)$value;$values[]=trim((string)($value??''));
                }
                if (!$header) { $header=$values;if (count(array_unique($header))!==count($header)||in_array('',$header,true)) throw new CommonException('XLSX表头不得为空或重复');continue; }
                if (!array_filter($values,static fn($v)=>$v!=='')) continue;
                $rows[]=array_combine($header,$values);
            }
            return $rows;
        } catch(CommonException $error) {throw $error;}
        catch(\Throwable $error) {throw new CommonException('XLSX格式不正确，请使用纯文本目录模板');}
        finally {if ($book) $book->disconnectWorksheets();if ($zipOpen) $zip->close();if (is_file($file)) unlink($file);}
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
        if (!$requested || count($requested)>20 || !\addon\hsx_lead\app\support\PhpRuntime::isList($requested)) throw new CommonException('请勾选1—20项意向设备');
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
