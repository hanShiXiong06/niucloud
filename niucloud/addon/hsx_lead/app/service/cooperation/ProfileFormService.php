<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\support\Data;
use app\model\diy_form\DiyForm;
use app\service\core\diy_form\CoreDiyFormRecordsService;
use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;
use think\facade\Db;

/** Configurable supplemental material; never an alternate identity/entitlement writer. */
final class ProfileFormService
{
    public const KEY='HSX_LEAD_PROFILE_FORM';
    private const ENTITY='merchant_profile_form';
    private const TYPES=['FormInput','FormTextarea','FormMobile','FormEmail','FormNumber','FormRadio','FormCheckbox','FormDate'];
    public function __construct(private Context $context) {}
    public static function defaults(): array
    {
        return ['fields'=>['storefront_image'=>['visible'=>true,'required'=>false,'sort'=>10],
            'business_license'=>['visible'=>true,'required'=>false,'sort'=>20]],'extra_form'=>['enabled'=>false,'form_id'=>0]];
    }
    private function settings(): array
    {
        // Do not let a permanent framework config cache hide a recent admin edit.
        $raw=Db::name('sys_config')->where('site_id',$this->context->site)->where('config_key',self::KEY)->value('value');
        if($raw===null||$raw==='')return self::defaults();
        $data=is_array($raw)?$raw:json_decode((string)$raw,true);
        if(!is_array($data))throw new CommonException('门店资料配置无法读取，请管理员核对；不会忽略必填规则');
        return self::normalize($data);
    }
    private static function flag($value,string $label): bool
    {
        if(!in_array($value,[true,false,1,0,'1','0'],true))throw new CommonException('请明确设置'.$label);
        return in_array($value,[true,1,'1'],true);
    }
    private static function normalize(array $input): array
    {
        $next=self::defaults();$fields=$input['fields']??null;$extra=$input['extra_form']??null;
        if(!is_array($fields)||array_diff(array_keys($fields),array_keys($next['fields']))||!is_array($extra))throw new CommonException('门店资料配置结构不完整');
        foreach($next['fields'] as$key=>$default){
            $row=$fields[$key]??null;if(!is_array($row)||array_diff(array_keys($row),['visible','required','sort']))throw new CommonException('门店资料字段配置不完整');
            $visible=self::flag($row['visible']??null,'字段显示');$required=self::flag($row['required']??null,'字段必填');$sort=$row['sort']??null;
            if((!is_int($sort)&&!is_string($sort))||!ctype_digit((string)$sort)||(int)$sort>1000)throw new CommonException('字段排序须为0至1000的整数');
            if(!$visible&&$required)throw new CommonException('隐藏的资料项不能同时设为必填');
            $next['fields'][$key]=['visible'=>$visible,'required'=>$required,'sort'=>(int)$sort];
        }
        if(array_diff(array_keys($extra),['enabled','form_id']))throw new CommonException('补充表单配置字段无效');
        $id=$extra['form_id']??0;if((!is_int($id)&&!is_string($id))||!ctype_digit((string)$id)||(int)$id>2147483647)throw new CommonException('请选择有效的万能表单');
        $next['extra_form']=['enabled'=>self::flag($extra['enabled']??null,'补充表单开关'),'form_id'=>(int)$id];
        if($next['extra_form']['enabled']&&$next['extra_form']['form_id']<=0)throw new CommonException('请先选择补充资料表单');
        return $next;
    }
    private function definition(array $settings,bool $lock=false): array
    {
        if(!$settings['extra_form']['enabled'])return [];
        $query=(new DiyForm())->where('site_id',$this->context->site)->where('form_id',$settings['extra_form']['form_id']);if($lock)$query->lock(true);
        $row=$query->field('form_id,title,status,type,value')->findOrEmpty()->toArray();
        if(!$row||(int)$row['status']!==1)throw new CommonException('本站补充资料表单不存在或已关闭，请管理员重新配置');
        // This is a business supplement, not an order/product/private identity form.
        if($row['type']!=='DIY_FORM')throw new CommonException('门店补充资料请选择“自定义表单”，不能复用其他业务专用表单');
        $value=$row['value'];if(is_string($value))$value=json_decode($value,true);
        if(!is_array($value)||!is_array($value['value']??null)||count($value['value'])>40||strlen(Data::json($value))>524288)throw new CommonException('补充资料表单结构无效或超过40项，请管理员核对');
        $components=[];$seen=[];
        foreach($value['value'] as$component){
            if(!is_array($component))throw new CommonException('补充资料存在无法识别的组件');
            if(($component['componentName']??'')==='FormSubmit')continue;
            if(($component['componentType']??'')!=='diy_form'||!in_array($component['componentName']??'',self::TYPES,true))throw new CommonException('补充资料暂不支持组件：'.substr((string)($component['componentName']??'未知'),0,60).'；请使用文本、电话、邮箱、数字、单多选或日期');
            $id=$component['id']??'';if(!is_string($id)||!preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$id)||in_array($id,['__proto__','prototype','constructor'],true)||isset($seen[$id])||!is_array($component['field']??null))throw new CommonException('补充资料字段标识缺失、保留或重复，请管理员重新保存表单');$seen[$id]=true;
            $name=$component['field']['name']??null;if(!is_string($name)||trim($name)===''||mb_strlen($name)>100)throw new CommonException('补充资料字段名称无效，请管理员核对');
            if(!empty($component['field']['unique']))throw new CommonException('门店资料会反复修改，补充表单暂不支持“内容不可重复”');
            foreach((array)($component['logicalRule']??[])as$rule)if(!empty($rule['triggerOptionId']))throw new CommonException('门店补充资料暂不支持条件显隐，请使用独立可选字段');
            if(in_array($component['componentName'],['FormRadio','FormCheckbox'],true)){
                $options=$component['options']??null;$ids=[];if(!is_array($options)||!$options||count($options)>100)throw new CommonException('补充资料选择项配置无效');
                foreach($options as$option){$optionId=$option['id']??null;$optionText=$option['text']??null;if(!is_array($option)||!is_string($optionId)||!preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$optionId)||in_array($optionId,['__proto__','prototype','constructor'],true)||isset($ids[$optionId])||!is_string($optionText)||trim($optionText)===''||mb_strlen($optionText)>200)throw new CommonException('补充资料选择项标识或名称无效');$ids[$optionId]=true;}
                $range=$component['selectableNum']??['min'=>0,'max'=>0];foreach(['min','max']as$bound)if(!isset($range[$bound])||!is_numeric($range[$bound])||(int)$range[$bound]!=(float)$range[$bound]||(int)$range[$bound]<0||(int)$range[$bound]>100)throw new CommonException('多选数量范围无效');
                if((int)$range['max']>0&&(int)$range['min']>(int)$range['max'])throw new CommonException('多选最少数量不能超过最多数量');
            }
            if($component['componentName']==='FormDate'&&!in_array($component['dateFormat']??'YYYY年M月D日',['YYYY年M月D日','YYYY-MM-DD','YYYY/MM/DD','YYYY-MM-DD HH:mm'],true))throw new CommonException('补充资料请选择日期或日期时间格式，暂不支持纯时间');
            if($component['componentName']==='FormNumber'){
                if(!is_array($component['formatLimit']??[])||array_diff($component['formatLimit']??[],['support_negative_number','support_decimal','number_scope']))throw new CommonException('补充资料数字限制暂不支持');
                if(in_array('number_scope',$component['formatLimit']??[],true)&&(!is_numeric($component['numberScope']['min']??null)||!is_numeric($component['numberScope']['max']??null)||(float)$component['numberScope']['min']>(float)$component['numberScope']['max']))throw new CommonException('补充资料数字范围无效');
            }
            if(!empty($component['isHidden']))continue;
            $component['field']['cache']=false;$component['field']['autofill']=false;$component['field']['value']=$component['componentName']==='FormDate'?['date'=>'','timestamp'=>'']:(in_array($component['componentName'],['FormRadio','FormCheckbox'],true)?[]:'');
            $component['field']['required']=!empty($component['field']['required']);$component['field']['unique']=false;$component['field']['privacyProtection']=!empty($component['field']['privacyProtection']);
            $component['isHidden']=false;$component['componentIsShow']=true;$components[]=$component;
        }
        if(!$components)throw new CommonException('补充资料表单没有可填写字段，请管理员核对');
        return ['form_id'=>(int)$row['form_id'],'status'=>1,'title'=>(string)$row['title'],'type'=>'DIY_FORM','global'=>is_array($value['global']??null)?$value['global']:[],'value'=>$components];
    }
    private function state(bool $strict=true,bool $lock=false): array
    {
        $settings=$this->settings();$error='';$definition=[];
        try{$definition=$this->definition($settings,$lock);}catch(CommonException$e){if($strict)throw$e;$error=$e->getMessage();}
        $version=hash('sha256',Data::json([$settings,$definition,$error]));
        return $settings+['version'=>$version,'definition'=>$definition,'configuration_error'=>$error];
    }
    public function admin(): array
    {
        Store::permission($this->context,'profile_form');$state=$this->state(false);$forms=(new DiyForm())->where('site_id',$this->context->site)->where('type','DIY_FORM')->where('status',1)->field('form_id,title,status,type')->order('form_id desc')->limit(200)->select()->toArray();
        $state['forms']=$forms;$state['supported_types']=self::TYPES;$state['extra_form']['definition']=$state['definition'];unset($state['definition']);return$state;
    }
    public function save(array $input): array
    {
        Store::permission($this->context,'profile_form_save');
        if(array_diff(array_keys($input),['fields','extra_form','expected_version']))throw new CommonException('门店资料配置包含无效字段');
        $next=self::normalize($input);
        Db::transaction(function()use($input,$next):void{
            Db::name('site')->where('site_id',$this->context->site)->lock(true)->find();$current=$this->state(false,true);
            if(!is_string($input['expected_version']??null)||!hash_equals($current['version'],$input['expected_version']))throw new CommonException('门店资料配置已变化，请刷新后重新核对');
            $this->definition($next,true);(new CoreConfigService())->setConfig($this->context->site,self::KEY,$next);
            $this->context->audit('profile_form_config',0,'updated',['form_id'=>$next['extra_form']['form_id'],'enabled'=>$next['extra_form']['enabled']]);
        });
        // Return the authoritative write result, not a client-side optimistic copy.
        $result=$this->state(false);$result['extra_form']['definition']=$result['definition'];unset($result['definition']);return$result+['forms'=>(new DiyForm())->where('site_id',$this->context->site)->where('type','DIY_FORM')->where('status',1)->field('form_id,title,status,type')->order('form_id desc')->limit(200)->select()->toArray(),'supported_types'=>self::TYPES];
    }
    public function member(): array
    {
        if($this->context->memberId<=0)throw new CommonException('请先登录后完善门店资料');
        $merchant=TeamService::resolve($this->context,false);if($merchant&&!$merchant['is_owner'])throw new CommonException('门店资料由负责人维护');
        $state=$this->state();$record=$merchant?$this->record((int)$merchant['id']):[];
        $ownerChanged=$record&&(int)($record['_submitted_member_id']??0)!==$this->context->memberId;
        $values=[];if(!$ownerChanged)foreach($record['fields']??[]as$field)$values[$field['id']]=$field['value'];
        $values=array_intersect_key($values,array_column($state['definition']['value']??[],null,'id'));
        $state['extra_form']+=['title'=>$state['definition']['title']??'','definition'=>$state['definition'],'values'=>(int)($record['form_id']??0)===$state['extra_form']['form_id']?$values:[],
            'values_notice'=>$ownerChanged&&$state['extra_form']['enabled']?'负责人已变更，请重新核对补充资料':''];unset($state['definition']);return$state;
    }
    /** Called after owner checks and under the same site/profile transaction. */
    public function prepare(array $input,?array $merchant): array
    {
        $state=$this->state(true,true);$payload=$input['profile_form']??null;
        $required=$state['extra_form']['enabled']||$state['fields']['storefront_image']['required']||$state['fields']['business_license']['required'];
        if($payload!==null){
            if(!is_array($payload)||array_diff(array_keys($payload),['version','form_id','values'])||!is_string($payload['version']??null)||!hash_equals($state['version'],$payload['version']))throw new CommonException('门店资料表单已更新，请刷新后重新填写');
            $formId=$payload['form_id']??null;if((!is_int($formId)&&!is_string($formId))||!ctype_digit((string)$formId))throw new CommonException('补充资料表单标识无效');
        }elseif($required)throw new CommonException('请刷新页面加载当前门店资料要求，再保存');
        foreach(['storefront_image'=>'storefront_image','business_license'=>'business_license_token']as$key=>$field){
            if(!$state['fields'][$key]['visible']){unset($input[$field]);continue;}
            if(!$state['fields'][$key]['required'])continue;
            $current=$merchant?($key==='storefront_image'?(new StorefrontService($this->context))->image((int)$merchant['id']):((new BusinessLicenseService($this->context))->ownSummary($merchant)['business_license_token']??'')):'';
            $value=array_key_exists($field,$input)?$input[$field]:$current;
            if(!is_string($value)||trim($value)==='')throw new CommonException($key==='storefront_image'?'请上传门头照片':'请上传营业执照');
        }
        $components=[];$fields=[];
        if($state['extra_form']['enabled']){
            if((int)($payload['form_id']??0)!==$state['extra_form']['form_id']||!is_array($payload['values']??null))throw new CommonException('补充资料表单不匹配，请刷新后重新填写');
            $allowed=array_column($state['definition']['value'],null,'id');if(array_diff(array_keys($payload['values']),array_keys($allowed)))throw new CommonException('补充资料包含未配置的字段');
            foreach($allowed as$id=>$component){
                $value=$this->normalizeValue($component,$payload['values'][$id]??null);
                if(self::blank($value)&&$component['field']['required'])throw new CommonException(($component['field']['name']??'资料项').'不能为空');
                $component['field']['value']=$value;$components[]=$component;
                $fields[]=['id'=>$id,'label'=>(string)($component['field']['name']??''),'type'=>$component['componentName'],'value'=>$value,'display_value'=>self::display($value)];
            }
        }elseif($payload!==null&&((int)($payload['form_id']??0)!==0||!empty($payload['values'])))throw new CommonException('本站当前未启用补充资料表单，请刷新页面');
        $formId=$state['extra_form']['enabled']?$state['extra_form']['form_id']:0;
        // A plugin validation extension may reject by throwing. Its return
        // value is deliberately ignored: it cannot rewrite identity or rights.
        event('HsxLeadMerchantProfileValidate',['site_id'=>$this->context->site,'member_id'=>$this->context->memberId,'merchant_id'=>(int)($merchant['id']??0),'form_id'=>$formId,'version'=>$state['version'],'fields'=>$fields]);
        return['input'=>$input,'version'=>$state['version'],'form_id'=>$formId,'title'=>$state['definition']['title']??'','components'=>$components,'fields'=>$fields];
    }
    public function persist(int $merchantId,array $prepared): void
    {
        if((int)$prepared['form_id']<=0)return;
        try{
            $id=(new CoreDiyFormRecordsService())->add(['site_id'=>$this->context->site,'member_id'=>$this->context->memberId,'relate_id'=>$merchantId,'form_id'=>$prepared['form_id'],'value'=>$prepared['components']]);
            if(!is_int($id)&&(!is_string($id)||!ctype_digit($id)))throw new \RuntimeException('No verifiable framework record identity');$id=(int)$id;
            $hasAnswers=false;foreach($prepared['components']as$component)if(!self::blank($component['field']['value']??null)){$hasAnswers=true;break;}
            if($id<=0&&$hasAnswers)throw new \RuntimeException('Framework did not persist nonempty answers');
            if($id>0){
                $saved=Db::name('diy_form_records')->where('site_id',$this->context->site)->where('record_id',$id)->where('form_id',(int)$prepared['form_id'])->where('member_id',$this->context->memberId)->where('relate_id',$merchantId)->find();
                if(!$saved||Data::decode($saved['value'])!==$prepared['components'])throw new \RuntimeException('Framework record read-back differs');
            }
            $this->context->audit(self::ENTITY,$merchantId,'saved',['form_id'=>(int)$prepared['form_id'],'record_id'=>$id,'version'=>$prepared['version'],'title'=>$prepared['title']]);
        }catch(\Throwable$error){
            \think\facade\Log::warning('门店补充资料保存未完成：site='.$this->context->site.', merchant='.$merchantId.', type='.get_class($error));
            throw new CommonException('补充资料保存失败，门店资料未更新；请重试或联系管理员核对表单服务');
        }
    }
    private function record(int $merchantId): array
    {
        $row=Data::query('audit',$this->context->site)->where('entity_type',self::ENTITY)->where('entity_id',$merchantId)->where('action','saved')->order('id desc')->find();
        if(!$row)return[];$data=Data::decode($row['detail_json']);
        if((int)($data['record_id']??0)<=0)return array_intersect_key($data,array_flip(['form_id','version','title']))+['fields'=>[],'_submitted_member_id'=>(int)$row['operator_member_id']];
        // The record belongs to the merchant subject. The historical submitter
        // remains immutable if the current owner is legitimately reassigned.
        $record=Db::name('diy_form_records')->where('site_id',$this->context->site)->where('form_id',(int)($data['form_id']??0))->where('record_id',(int)$data['record_id'])->where('relate_id',$merchantId)->where('member_id',(int)$row['operator_member_id'])->find();
        if(!$record)throw new CommonException('门店补充资料记录无法对应，请管理员核对');
        $components=is_array($record['value'])?$record['value']:Data::decode($record['value']);$fields=[];
        foreach($components as$component){if(!is_array($component)||!is_array($component['field']??null))continue;$value=$component['field']['value']??'';
            $fields[]=['id'=>(string)$component['id'],'label'=>(string)($component['field']['name']??''),'type'=>(string)$component['componentName'],'value'=>$value,'display_value'=>self::display($value)];}
        return array_intersect_key($data,array_flip(['form_id','version','title']))+['fields'=>$fields,'_submitted_member_id'=>(int)$row['operator_member_id']];
    }
    public function adminRecord(int $merchantId): array
    {
        Store::permission($this->context,'profile_form_record');Data::row('merchant',$this->context->site,$merchantId);$record=$this->record($merchantId);
        if(!$record)return['form_id'=>0,'title'=>'','version'=>'','fields'=>[]];
        unset($record['_submitted_member_id']);return$record;
    }
    private static function blank($value): bool
    {
        if($value===null||$value===false||is_string($value)&&trim($value)==='')return true;
        if(is_array($value)){foreach($value as$item)if(!self::blank($item))return false;return true;}
        return false;
    }
    private static function display($value): string
    {
        if(is_array($value)){if(array_key_exists('date',$value))return(string)$value['date'];return implode('、',array_map(static fn($row):string=>is_array($row)?(string)($row['text']??''):(string)$row,$value));}
        return(string)($value??'');
    }
    private function normalizeValue(array $component,$value)
    {
        $type=$component['componentName'];$name=(string)($component['field']['name']??'资料项');
        if(self::blank($value)){
            if($type==='FormCheckbox'&&(int)($component['selectableNum']['min']??0)>0)throw new CommonException($name.'至少选择'.(int)$component['selectableNum']['min'].'项');
            return $type==='FormDate'?['date'=>'','timestamp'=>'']:(in_array($type,['FormRadio','FormCheckbox'],true)?[]:'');
        }
        if(in_array($type,['FormRadio','FormCheckbox'],true)){
            if(!is_array($value)||count($value)>100||($type==='FormRadio'&&count($value)>1))throw new CommonException($name.'选项格式不正确');
            $options=array_column($component['options']??[],null,'id');$result=[];$seen=[];
            foreach($value as$selected){$id=is_array($selected)?($selected['id']??null):null;if(!is_string($id)||!isset($options[$id])||isset($seen[$id]))throw new CommonException($name.'包含无效或重复选项');$seen[$id]=true;$result[]=['id'=>$id,'text'=>(string)$options[$id]['text']];}
            if($type==='FormCheckbox'&&(count($result)<(int)($component['selectableNum']['min']??0)||((int)($component['selectableNum']['max']??0)>0&&count($result)>(int)$component['selectableNum']['max'])))throw new CommonException($name.'选择数量不符合设置');return$result;
        }
        if($type==='FormDate'){
            if(!is_array($value)||array_diff(array_keys($value),['date','timestamp'])||!is_string($value['date']??null)||mb_strlen($value['date'])>64||preg_match('/[<>\x00-\x1f]/u',$value['date']))throw new CommonException($name.'日期格式不正确');
            if(trim($value['date'])==='')return['date'=>'','timestamp'=>''];
            $date=trim($value['date']);$format=(string)($component['dateFormat']??'YYYY年M月D日');
            if($format==='HH:mm'){
                if(!preg_match('/^([0-9]{1,2}):([0-9]{2})$/D',$date,$parts)||(int)$parts[1]>23||(int)$parts[2]>59)throw new CommonException($name.'时间不正确');
                $timestamp=mktime((int)$parts[1],(int)$parts[2],0);
            }else{
                if(!preg_match('/^([0-9]{4})(?:年|-|\/)([0-9]{1,2})(?:月|-|\/)([0-9]{1,2})日?(?: ([0-9]{1,2}):([0-9]{2}))?$/Du',$date,$parts)
                    ||!checkdate((int)$parts[2],(int)$parts[3],(int)$parts[1])||(int)($parts[4]??0)>23||(int)($parts[5]??0)>59)throw new CommonException($name.'日期不正确');
                $timestamp=mktime((int)($parts[4]??0),(int)($parts[5]??0),0,(int)$parts[2],(int)$parts[3],(int)$parts[1]);
            }
            return['date'=>$date,'timestamp'=>$timestamp];
        }
        if(!is_string($value)&&!is_int($value)&&!is_float($value))throw new CommonException($name.'格式不正确');$value=trim((string)$value);
        if(!mb_check_encoding($value,'UTF-8')||mb_strlen($value)>($type==='FormTextarea'?4000:1000)||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u',$value))throw new CommonException($name.'内容过长或含无效字符');
        if($type==='FormMobile')return PartnerService::phone($value);
        if($type==='FormEmail'&&!filter_var($value,FILTER_VALIDATE_EMAIL))throw new CommonException($name.'邮箱格式不正确');
        if($type==='FormNumber'){
            if(!preg_match('/^-?[0-9]{1,12}(?:\.[0-9]{1,6})?$/D',$value))throw new CommonException($name.'请输入有效数字');$limits=(array)($component['formatLimit']??[]);
            if((float)$value<0&&!in_array('support_negative_number',$limits,true)||str_contains($value,'.')&&!in_array('support_decimal',$limits,true))throw new CommonException($name.'数字格式不符合设置');
            if(in_array('number_scope',$limits,true)&&((float)$value<(float)($component['numberScope']['min']??0)||(float)$value>(float)($component['numberScope']['max']??0)))throw new CommonException($name.'超出填写范围');
        }
        return$value;
    }
}
