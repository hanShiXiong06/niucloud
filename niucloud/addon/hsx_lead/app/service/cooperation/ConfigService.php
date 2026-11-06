<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;
use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\support\Data;
use app\service\core\sys\CoreConfigService;
use app\model\sys\SysConfig;
use core\exception\CommonException;
use think\facade\{Cache,Db};

final class ConfigService
{
    public const KEY='HSX_LEAD_COOPERATION';
    public function __construct(private Context $context) {}
    public static function defaults(): array
    {
        return ['enabled'=>false,'publishing_required'=>true,
            'fee_percent'=>'10.00','fee_minimum'=>'9.90','fee_confirmed'=>false,'commission_minimum'=>'50.00','response_minutes'=>30,
            'contact_hours'=>24,'review_hours'=>48,'inventory_fresh_hours'=>24,'demand_model_limit'=>5,
            'businesses'=>BusinessService::defaults(),'membership_levels'=>[]];
    }
    public function get(bool $lock=false): array
    {
        // Billing rules must be read from the persisted tenant record. The
        // framework's unversioned, permanent cache can be repopulated by a read
        // before a concurrent save transaction commits (or lose its tag index).
        // Reuse its JSON model without caching this small, payment-critical row.
        $query=(new SysConfig())->where('site_id',$this->context->site)->where('config_key',self::KEY)->field('value');if ($lock) $query->lock(true);
        $record=$query->findOrEmpty()->toArray();
        $value=$record['value']??[];
        $saved=is_array($value)?$value:[];
        // General business settings and framework membership definitions are
        // separate sources. Unknown/retired keys are not reintroduced.
        $result=array_replace(self::defaults(),array_intersect_key($saved,self::defaults()));
        // Definitions belong to the framework membership editor, never to this
        // plugin's general configuration or a client-submitted tier array.
        $result['membership_levels']=MembershipCatalogService::levels($this->context->site,$lock);
        $result['membership_configuration_source']='framework_member_level';
        return $result;
    }
    public function publicConfig(): array { return $this->get(); }
    public function save(array $input): array
    {
        Store::permission($this->context,'config');$next=$this->get();
        foreach(['enabled','publishing_required','fee_confirmed'] as $key) {
            if (!in_array($input[$key]??null,[true,false,0,1,'0','1'],true)) throw new CommonException('请明确设置开关：'.$key);
            $next[$key]=in_array($input[$key],[true,1,'1'],true);
        }
        foreach(['fee_percent','fee_minimum','commission_minimum'] as $key) $next[$key]=Rules::amount(Rules::cents($input[$key]??$next[$key]));
        foreach(['fee_minimum','commission_minimum'] as $key) if (Rules::cents($next[$key])>9999999999) throw new CommonException('配置金额超出框架账户精度范围，请核对：'.$key);
        if (Rules::cents($next['fee_percent'])>=10000 || Rules::cents($next['commission_minimum'])<1 || Rules::cents($next['fee_minimum'])>=Rules::cents($next['commission_minimum'])) throw new CommonException('成交最低协作费用须大于0，平台维护费须低于最低协作费用');
        if ($next['fee_confirmed']) Rules::split(Rules::cents($next['commission_minimum']),$next);
        foreach(['response_minutes'=>[0,10080],'contact_hours'=>[0,720],'review_hours'=>[0,2160],'inventory_fresh_hours'=>[1,168]] as $key=>$range) {
            $v=$input[$key]??$next[$key];if ((!is_int($v)&&!is_string($v)) || !ctype_digit((string)$v) || (int)$v<$range[0] || (int)$v>$range[1]) throw new CommonException('请核对时间设置：'.$key);$next[$key]=(int)$v;
        }
        $next['demand_model_limit']=Rules::demandLimit($input['demand_model_limit']??$next['demand_model_limit']);
        $next['businesses']=BusinessService::validate($input['businesses']??$next['businesses']);
        if ($next['fee_confirmed']) foreach($next['businesses'] as $business) if ($business['enabled']) Rules::split(Rules::cents($business['commission_minimum']),BusinessService::feeRules($business,$next));
        Db::transaction(function()use($next):void {
            Db::name('site')->where('site_id',$this->context->site)->lock(true)->find();
            // CoreConfig also uses its cache to decide create versus update.
            // Remove a stale "not found" sentinel under the tenant lock first.
            Cache::delete('site_config_cache'.self::KEY.'_'.$this->context->site);
            $stored=$next;unset($stored['membership_levels'],$stored['membership_configuration_source']);
            (new CoreConfigService())->setConfig($this->context->site,self::KEY,$stored);
            $this->context->audit('cooperation_config',0,'saved',['enabled'=>$next['enabled'],'fee_confirmed'=>$next['fee_confirmed']]);
        });
        // Evict this exact framework entry after commit as well, not every
        // site's configuration. Return read-back, never just echo the request.
        Cache::delete('site_config_cache'.self::KEY.'_'.$this->context->site);
        $saved=$this->get();
        foreach(['enabled','publishing_required','fee_confirmed','fee_percent','fee_minimum','commission_minimum'] as $key) if ((string)$saved[$key] !== (string)$next[$key]) throw new CommonException('配置已提交，但回读结果已变化，请重新读取并核对，不要反复保存');
        return $saved;
    }
}
