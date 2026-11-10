<?php

namespace app\listener\notice;

use app\dict\notice\NoticeTypeDict;
use app\service\core\member\CoreMemberService;
use app\service\core\notice\CoreNoticeBindMerchantService;
use app\service\core\notice\CoreNoticeLogService;
use app\service\core\notice\CoreSmsService;
use core\exception\NoticeDeliveryException;
use core\exception\NoticeException;

class Sms
{

    public function handle(array $data)
    {
        // Optional generic receipt contract. Existing callers retain the original
        // bool / async-error behavior unless they explicitly opt in.
        if (($data['return_receipt'] ?? false) === true) return $this->handleWithReceipt($data);
        $site_id = $data[ 'site_id' ];
        $template = $data[ 'template' ];//模板
        $vars = $data[ 'vars' ];//模板变量
        $key = $data[ 'key' ];
        $to = $data[ 'to' ];//发送对象主题
        $mobile = $to[ 'mobile' ] ?? '';
        //完全信任消息的设置, 不再依赖support_type
        if ($template[ 'is_sms' ]) {
            $sms_id = $template[ 'sms_id' ];//发送模板id
            $content = $template[ 'sms' ][ 'content' ];
            $url_params = $template[ 'sms' ][ 'url_params' ] ?? [];
            $member_id = $to[ 'member_id' ] ?? 0;
            $uid = $to[ 'uid' ] ?? 0;
            if (!$mobile) {
                if ($template['receiver_type'] ==1){
                    $member_id = $to[ 'member_id' ] ?? 0;
                    //会员的
                    if ($member_id > 0) {//查询openid
                        $info = ( new CoreMemberService() )->getInfoByMemberId($site_id, $member_id);
                        $mobile = $info[ 'mobile' ] ?? '';
                        $nickname = $info[ 'nickname' ] ?? '';
                    }
                }else{
                    $merchant_id = $to[ 'merchant_id' ] ?? 0;
                    if ($merchant_id > 0) {
                        $info = (new CoreNoticeBindMerchantService())->getInfo($merchant_id);
                        $mobile = $info[ 'mobile' ] ?? '';
                        $nickname = '商户通知';
                    }
                }
            }

            // Initialize before the empty-recipient failure so the original
            // log-only async path never writes an undefined log_data variable.
            $log_data = array(
                    'key' => $key,
                    'notice_type' => NoticeTypeDict::SMS,
                    'uid' => $uid ?? 0,
                    'member_id' => $member_id ?? 0,
                    'nickname' => $nickname ?? '',
                    'receiver' => $mobile,
                    'params' => $data,
                    'content' => $content,
                    'result' => ''
            );

            try {
                if (empty($mobile)) throw new NoticeException('NOTICE_SMS_EMPTY');//没有手机号不能发送短信
                $core_sms_service = new CoreSmsService();

                $core_sms_service->send($site_id, $mobile, $vars, $key, $sms_id, $content, $url_params);
                ( new CoreNoticeLogService() )->add($site_id, $log_data);
            } catch (NoticeException $e) {
                $log_data[ 'result' ] = $e->getMessage();
                ( new CoreNoticeLogService() )->add($site_id, $log_data);
                //这儿决定要不要抛出
                if (!$template[ 'async' ]) {
                    throw new NoticeException($e->getMessage());
                }
            }
        } else {
            if (!$template[ 'async' ]) {
                throw new NoticeException('NOTICE_NOT_OPEN_SMS');
            }
        }
        return true;
    }

    /** Provider acceptance only, never handset delivery or user acknowledgement. */
    private function handleWithReceipt(array $data): array
    {
        $site = (int)($data['site_id'] ?? 0);
        $template = $data['template'] ?? [];
        $to = $data['to'] ?? [];
        $mobile = (string)($to['mobile'] ?? '');
        $log = [
            'key' => (string)($data['key'] ?? ''), 'notice_type' => NoticeTypeDict::SMS,
            'uid' => (int)($to['uid'] ?? 0), 'member_id' => (int)($to['member_id'] ?? 0),
            'nickname' => '', 'receiver' => $mobile, 'params' => $data,
            'content' => $template['sms']['content'] ?? '', 'result' => '',
        ];
        $attempted = false;
        $failure = null;
        try {
            if (empty($template['is_sms'])) throw new NoticeDeliveryException('sms', 'not_submitted', '', '短信通知未启用，本次未提交发送');
            if ($mobile === '') {
                if ((int)($template['receiver_type'] ?? 0) === 1) {
                    if ($log['member_id'] > 0) {
                        $member = (new CoreMemberService())->getInfoByMemberId($site, $log['member_id']);
                        $mobile = (string)($member['mobile'] ?? '');
                        $log['nickname'] = (string)($member['nickname'] ?? '');
                    }
                } elseif ((int)($to['merchant_id'] ?? 0) > 0) {
                    $merchant = (new CoreNoticeBindMerchantService())->getInfo((int)$to['merchant_id']);
                    $mobile = (string)($merchant['mobile'] ?? '');
                    $log['nickname'] = '商户通知';
                }
            }
            $log['receiver'] = $mobile;
            if ($mobile === '') throw new NoticeDeliveryException('sms', 'not_submitted', '', '短信接收手机号未就绪，本次未提交发送');
            if (empty($template['sms_id']) || !is_string($log['content']) || $log['content'] === '') {
                throw new NoticeDeliveryException('sms', 'not_submitted', '', '短信模板尚未配置，本次未提交发送');
            }
            $service = new CoreSmsService();
            // This method only reads configuration. A failure here proves that
            // CoreSmsService::send() and its provider driver have not been called.
            $configuration = $service->getDefaultSmsConfig($site);
            if (empty($configuration['sms_type'])) throw new NoticeDeliveryException('sms', 'not_submitted', '', '短信账号尚未配置，本次未提交发送');
            $attempted = true;
            $result = $service->send($site, $mobile, $data['vars'] ?? [], $log['key'], $template['sms_id'], $log['content'], $template['sms']['url_params'] ?? []);
            // The core service returns true only after a driver has accepted the
            // request (Aliyun OK, Tencent Ok, etc.) and persisted its SMS log.
            if ($result !== true) throw new NoticeDeliveryException('sms', 'unknown', '', '短信受理回执未能核实，请先核对记录，勿重复发送');
        } catch (\Throwable $error) {
            // A generic NoticeException may wrap a timeout or a post-send log
            // error. Never interpret its text as a safe provider rejection.
            $failure = !$attempted && $error instanceof NoticeDeliveryException
                ? $error
                : new NoticeDeliveryException('sms', $attempted ? 'unknown' : 'not_submitted', '',
                    $attempted ? '短信发送结果未能核实，请先核对记录，勿重复发送' : '短信账号或接收资料尚未就绪，本次未提交发送', $error);
            $log['result'] = $failure->getMessage();
        }
        try {
            (new CoreNoticeLogService())->add($site, $log);
        } catch (\Throwable $error) {
            // Do not report accepted if its framework notice log could not be
            // stored. The underlying core SMS log may already record acceptance.
            throw new NoticeDeliveryException('sms', $attempted ? 'unknown' : 'not_submitted', '',
                '短信通知记录未能保存，请核对框架短信记录，勿重复发送', $error);
        }
        if ($failure) throw $failure;
        return ['accepted' => true, 'channel' => 'sms', 'provider_code' => '0', 'provider_id' => ''];
    }

}
