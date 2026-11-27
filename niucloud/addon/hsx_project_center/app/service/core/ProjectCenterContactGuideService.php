<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\service\core;

use addon\hsx_project_center\app\model\ProjectCenterProject;
use app\model\addon\Addon;
use think\facade\Cache;

/**
 * 陌生客户联系引导。
 *
 * 项目中心只消费 hsx_wecom 的客户联系能力；企微未安装、未授权或权限不足时，
 * 必须回退到项目单独上传的二维码，不能阻塞客户办理。
 */
final class ProjectCenterContactGuideService
{
    private const CONTEXT_SERVICE = 'addon\\hsx_wecom\\app\\service\\core\\WecomDeliveryContextService';
    private const CLIENT = 'addon\\hsx_wecom\\app\\service\\core\\WecomClient';
    private const BINDING_MODEL = 'addon\\hsx_wecom\\app\\model\\WecomStaffBinding';

    public function normalize(array $config): array
    {
        $mode = trim((string)($config['mode'] ?? 'wecom_first'));
        if (!in_array($mode, ['wecom_first', 'qrcode'], true)) $mode = 'wecom_first';
        $staffUids = array_values(array_unique(array_filter(array_map('intval', (array)($config['staff_uids'] ?? [])))));
        return [
            'enabled' => !empty($config['enabled']) ? 1 : 0,
            'mode' => $mode,
            'staff_uids' => array_slice($staffUids, 0, 100),
            'fallback_qrcode' => trim((string)($config['fallback_qrcode'] ?? '')),
            'title' => mb_substr(trim((string)($config['title'] ?? '先添加项目顾问')), 0, 50),
            'tips' => mb_substr(trim((string)($config['tips'] ?? '添加后请发送“我要参与项目”，工作人员会协助建群、付款与后续办理。')), 0, 500),
            'greeting' => mb_substr(trim((string)($config['greeting'] ?? '你好，我想咨询并参与这个项目。')), 0, 200),
            'button_text' => mb_substr(trim((string)($config['button_text'] ?? '我已添加，继续付款')), 0, 30),
            'generated_hash' => trim((string)($config['generated_hash'] ?? '')),
            'generated_config_id' => trim((string)($config['generated_config_id'] ?? '')),
            'generated_qrcode' => trim((string)($config['generated_qrcode'] ?? '')),
        ];
    }

    /** 返回给客户的最小公开信息；企微异常时静默降级并给出明确状态。 */
    public function publicGuide(array $project): array
    {
        $config = $this->normalize((array)($project['config_json']['contact_guide'] ?? []));
        if (empty($config['enabled'])) return ['enabled' => 0];

        $public = [
            'enabled' => 1,
            'title' => $config['title'],
            'tips' => $config['tips'],
            'greeting' => $config['greeting'],
            'button_text' => $config['button_text'],
            'qrcode' => $config['fallback_qrcode'],
            'source' => 'uploaded_qrcode',
            'source_name' => '工作人员二维码',
            'wecom_ready' => 0,
        ];
        if ($config['mode'] !== 'wecom_first') return $public;

        try {
            $generated = $this->resolveWecomQrcode($project, $config);
            if (trim((string)($generated['qrcode'] ?? '')) !== '') {
                $public['qrcode'] = (string)$generated['qrcode'];
                $public['source'] = 'wecom';
                $public['source_name'] = '企业微信项目顾问';
                $public['wecom_ready'] = 1;
            }
        } catch (\Throwable $e) {
            // 公共页面不能泄露企微接口错误详情，也不能因外部服务故障阻塞付款流程。
            Cache::set($this->failureKey((int)($project['site_id'] ?? 0), (int)($project['id'] ?? 0)), 1, 600);
            $public['fallback_active'] = 1;
        }
        return $public;
    }

    private function resolveWecomQrcode(array $project, array $config): array
    {
        $siteId = (int)($project['site_id'] ?? 0);
        $projectId = (int)($project['id'] ?? 0);
        if ($siteId <= 0 || $projectId <= 0) throw new \RuntimeException('项目上下文不完整');
        if (Cache::get($this->failureKey($siteId, $projectId))) throw new \RuntimeException('企微能力暂不可用');

        $addon = Addon::where([['key', '=', 'hsx_wecom'], ['status', '=', 1]])->findOrEmpty();
        if ($addon->isEmpty() || !class_exists(self::CONTEXT_SERVICE) || !class_exists(self::CLIENT) || !class_exists(self::BINDING_MODEL)) {
            throw new \RuntimeException('企微插件未启用');
        }

        $staffUids = $config['staff_uids'];
        if ($staffUids === []) {
            $staffUids = array_values(array_unique(array_filter(array_map('intval', (array)($project['reviewer_uids'] ?? [])))));
        }
        if ($staffUids === []) throw new \RuntimeException('未配置项目顾问');

        $bindingModel = self::BINDING_MODEL;
        $userIds = $bindingModel::where([
            ['site_id', '=', $siteId], ['uid', 'in', $staffUids], ['status', '=', 1],
        ])->column('wecom_userid');
        $userIds = array_values(array_unique(array_filter(array_map(static fn($item): string => trim((string)$item), $userIds))));
        if ($userIds === []) throw new \RuntimeException('项目顾问尚未绑定企微');

        $hash = hash('sha256', $siteId . '|' . $projectId . '|' . implode(',', $userIds));
        if ($config['generated_hash'] === $hash && $config['generated_qrcode'] !== '') {
            return ['qrcode' => $config['generated_qrcode'], 'config_id' => $config['generated_config_id']];
        }

        $contextService = self::CONTEXT_SERVICE;
        $clientClass = self::CLIENT;
        // “联系我”只依赖企业授权凭据和客户联系权限，不应被后台管理小程序
        // 是否配置所阻塞；具体授权缺失会由 WecomClient 给出真实接口错误并降级。
        $context = (new $contextService())->resolve($siteId, false);
        if (empty($context['enabled'])) throw new \RuntimeException('企业微信能力尚未启用');
        $result = (new $clientClass())->addContactWay(
            $context,
            $userIds,
            'project_center_' . $projectId,
            mb_substr((string)($project['title'] ?? '项目咨询'), 0, 30)
        );
        $qrcode = trim((string)($result['qr_code'] ?? ''));
        if ($qrcode === '') throw new \RuntimeException('企微未返回联系二维码');

        $row = ProjectCenterProject::where([['site_id', '=', $siteId], ['id', '=', $projectId]])->findOrEmpty();
        if (!$row->isEmpty()) {
            $projectConfig = (array)$row->config_json;
            $contact = $this->normalize((array)($projectConfig['contact_guide'] ?? []));
            $contact['generated_hash'] = $hash;
            $contact['generated_config_id'] = trim((string)($result['config_id'] ?? ''));
            $contact['generated_qrcode'] = $qrcode;
            $projectConfig['contact_guide'] = $contact;
            $row->save(['config_json' => $projectConfig, 'update_at' => time()]);
        }
        return ['qrcode' => $qrcode, 'config_id' => trim((string)($result['config_id'] ?? ''))];
    }

    private function failureKey(int $siteId, int $projectId): string
    {
        return 'hsx_project_center_contact_failure_' . $siteId . '_' . $projectId;
    }
}
