<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;
use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\support\Data;
use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;
use think\facade\Db;

final class ConfigService
{
    public const KEY='HSX_LEAD_COOPERATION';
    public function __construct(private Context $context) {}
    public static function defaults(): array
    {
        return ['enabled'=>false,'publishing_required'=>true,'publishing_price'=>'0.00','certification_price'=>'0.00','certification_months'=>12,
            'fee_percent'=>'10.00','fee_minimum'=>'9.90','fee_confirmed'=>false,'commission_minimum'=>'50.00','response_minutes'=>30,
            'contact_hours'=>24,'review_hours'=>48,'inventory_fresh_hours'=>24,
            'grades'=>[['id'=>'S','name'=>'S','rank'=>30,'min_inventory'=>100,'all_models'=>false,'status'=>1],['id'=>'A','name'=>'A','rank'=>20,'min_inventory'=>30,'all_models'=>false,'status'=>1],['id'=>'B','name'=>'B','rank'=>10,'min_inventory'=>0,'all_models'=>false,'status'=>1]]];
    }
    public function get(): array
    {
        $value=(new CoreConfigService())->getConfigValue($this->context->site,self::KEY);
        return array_replace(self::defaults(),is_array($value)?$value:[]);
    }
    public function publicConfig(): array { return $this->get(); }
    public function save(array $input): array
    {
        Store::permission($this->context,'config');$next=$this->get();
        foreach(['enabled','publishing_required','fee_confirmed'] as $key) {
            if (!in_array($input[$key]??null,[true,false,0,1,'0','1'],true)) throw new CommonException('请明确设置开关：'.$key);
            $next[$key]=in_array($input[$key],[true,1,'1'],true);
        }
        foreach(['publishing_price','certification_price','fee_percent','fee_minimum','commission_minimum'] as $key) $next[$key]=Rules::amount(Rules::cents($input[$key]??$next[$key]));
        if (Rules::cents($next['fee_percent'])>=10000 || Rules::cents($next['commission_minimum'])<5000 || Rules::cents($next['fee_minimum'])>=Rules::cents($next['commission_minimum'])) throw new CommonException('平台费须低于最低佣金，且成交佣金不能低于50元');
        if ($next['fee_confirmed']) Rules::split(Rules::cents($next['commission_minimum']),$next);
        foreach(['certification_months'=>[1,120],'response_minutes'=>[0,10080],'contact_hours'=>[0,720],'review_hours'=>[0,2160],'inventory_fresh_hours'=>[1,168]] as $key=>$range) {
            $v=$input[$key]??$next[$key];if ((!is_int($v)&&!is_string($v)) || !ctype_digit((string)$v) || (int)$v<$range[0] || (int)$v>$range[1]) throw new CommonException('请核对时间设置：'.$key);$next[$key]=(int)$v;
        }
        $rows=$input['grades']??null;if (!is_array($rows)||!$rows||count($rows)>20) throw new CommonException('请设置1—20个商户等级');
        $grades=[];$ids=[];$ranks=[];
        foreach($rows as $row) {
            if (!is_array($row)) throw new CommonException('等级格式不正确');$id=Data::text($row,'id',40,true);
            if (!preg_match('/^[A-Za-z0-9_-]{1,40}$/D',$id)||isset($ids[$id])) throw new CommonException('等级标识必须唯一且只含字母、数字、横线');
            foreach(['rank','min_inventory'] as $field) if ((!is_int($row[$field]??null)&&!is_string($row[$field]??null)) || !preg_match('/^(?:0|[1-9][0-9]{0,7})$/D',(string)$row[$field])) throw new CommonException('等级排序与库存门槛必须为非负整数');
            $rank=(int)$row['rank'];$count=(int)$row['min_inventory'];
            if ($rank<0||$rank>10000||$count<0||$count>10000000||isset($ranks[$rank])) throw new CommonException('等级排序须唯一，库存门槛须为非负整数');
            $ids[$id]=true;$ranks[$rank]=true;$grades[]=['id'=>$id,'name'=>Data::text($row,'name',40,true),'rank'=>$rank,'min_inventory'=>$count,'all_models'=>in_array($row['all_models']??false,[true,1,'1'],true),'status'=>(int)($row['status']??1)===1?1:0];
        }
        $active=array_values(array_filter($grades,static fn(array $g):bool=>$g['status']===1));if (!$active) throw new CommonException('至少保留一个启用等级');
        $highest=max(array_column($active,'rank'));foreach($grades as $g) if ($g['all_models']&&($g['status']!==1||$g['rank']!==$highest)) throw new CommonException('只有当前最高启用等级可以授权全型号交付能力');
        $next['grades']=$grades;
        Db::transaction(function()use($next):void { Db::name('site')->where('site_id',$this->context->site)->lock(true)->find();(new CoreConfigService())->setConfig($this->context->site,self::KEY,$next);$this->context->audit('cooperation_config',0,'saved',['enabled'=>$next['enabled'],'fee_confirmed'=>$next['fee_confirmed']]); });
        return $next;
    }
}
