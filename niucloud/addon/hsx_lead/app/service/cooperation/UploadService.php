<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;
use addon\hsx_lead\app\service\Context;
use app\service\core\upload\CoreUploadService;
use core\exception\CommonException;
use think\facade\Db;

/** Framework storage driver plus plugin ownership evidence; no new attachment table. */
final class UploadService
{
    public function __construct(private Context $context) {}
    public function image(): array
    {
        if ($this->context->uid>0) Store::permission($this->context,'review');else (new PartnerService($this->context))->own(true);
        $owner=$this->context->uid>0?'u'.$this->context->uid:'m'.$this->context->memberId;
        $upload=new CoreUploadService(true);$upload->setValidate(['ext'=>['jpg','jpeg','png','webp'],'mime'=>['image/jpeg','image/png','image/webp'],'size'=>5*1024*1024]);
        $result=$upload->image('file',$this->context->site,'hsx_lead/'.$this->context->site.'/'.$owner.'/'.date('Ym/d'));
        $row=Db::name('sys_attachment')->where('site_id',$this->context->site)->where('att_id',(int)($result['att_id']??0))->find();
        if (!$row) throw new CommonException('图片已上传但素材登记未完成，请重新上传后提交');
        $this->context->audit('cooperation_attachment',(int)$row['att_id'],'uploaded',[]);
        return ['url'=>$result['url'],'path'=>$row['path'],'att_id'=>(int)$row['att_id']];
    }
}
