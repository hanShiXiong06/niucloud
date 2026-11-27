<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\support\Data;
use think\facade\{Cache,Db};

/** A read-only projection of Niucloud's dictionary, not an ERP or paid map service. */
final class RegionSearchService
{
    public function search(array $params): array
    {
        $keyword=Data::text($params,'keyword',80);
        if ($keyword==='') return ['data'=>[],'total'=>0];
        $rows=Cache::remember('hsx_lead_area_search_v1',static function():array {
            return Db::name('sys_area')->where('status',1)->whereBetween('level',[1,3])->field('id,pid,name,shortname,level')->order('id')->select()->toArray();
        },600);
        return self::find($rows,$keyword);
    }

    public static function find(array $rows,string $keyword): array
    {
        $needle=self::normalize($keyword);if ($needle==='') return ['data'=>[],'total'=>0];
        $map=array_column($rows,null,'id');$matches=[];
        foreach($rows as $row) {
            $level=(int)$row['level'];if ($level<1||$level>3) continue;
            $chain=[$row];$parent=$row;$valid=true;
            for($n=$level-1;$n>=1;$n--) {
                $parent=$map[(int)$parent['pid']]??null;
                if (!$parent||(int)$parent['level']!==$n) {$valid=false;break;}
                array_unshift($chain,$parent);
            }
            if (!$valid) continue;
            $text=implode(' / ',array_column($chain,'name'));$name=self::normalize((string)$row['name']);
            if (!str_contains(self::normalize($text),$needle)&&!str_contains(self::normalize((string)($row['shortname']??'')),$needle)) continue;
            $result=['id'=>(int)$row['id'],'name'=>(string)$row['name'],'level'=>$level,'province_id'=>(int)$chain[0]['id'],
                'city_id'=>$level>=2?(int)$chain[1]['id']:0,'district_id'=>$level===3?(int)$row['id']:0,'region_text'=>$text];
            $matches[]=['rank'=>$name===$needle?0:(str_starts_with($name,$needle)?1:2),'item'=>$result];
        }
        usort($matches,static fn(array $a,array $b):int=>[$a['rank'],$a['item']['level'],$a['item']['id']]<=>[$b['rank'],$b['item']['level'],$b['item']['id']]);
        return ['data'=>array_column(array_slice($matches,0,30),'item'),'total'=>count($matches)];
    }

    private static function normalize(string $value): string
    {
        return mb_strtolower(preg_replace('/[\s\/·,，]+/u','',$value)??'');
    }
}
