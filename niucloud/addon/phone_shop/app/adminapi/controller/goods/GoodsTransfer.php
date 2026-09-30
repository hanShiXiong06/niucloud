<?php
declare(strict_types=1);

namespace addon\phone_shop\app\adminapi\controller\goods;

use addon\phone_shop\app\service\admin\goods\GoodsTransferService;
use core\base\BaseAdminController;

/** 商品批量导入导出 */
class GoodsTransfer extends BaseAdminController
{
    public function noticePreview(int $id)
    {
        return success((new \addon\phone_shop\app\service\admin\goods\GoodsArrivalService())->preview($id));
    }

    public function notice(int $id)
    {
        return success('请查看通知任务状态及微信处理结果', (new \addon\phone_shop\app\service\admin\goods\GoodsArrivalService())->send($id));
    }

    public function noticeRetry(int $id)
    {
        $service = new \addon\phone_shop\app\service\admin\goods\GoodsArrivalService();
        $mode = (string)$this->request->param('mode', 'retry');
        if ($mode === 'supplement') {
            return success('请查看补发进度；原名单回执保留', $service->supplement($id, (string)$this->request->param('audience_token', '')));
        }
        if ($mode !== 'retry') throw new \core\exception\CommonException('不支持的通知操作');
        return success('请查看处理状态；已受理或待核实的通知不会重复发送', $service->retry($id));
    }

    public function noticeResults(int $id)
    {
        return success((new \addon\phone_shop\app\service\admin\goods\GoodsArrivalService())->results($id, (int)$this->request->param('page', 1), (int)$this->request->param('limit', 15)));
    }

    public function template()
    {
        return (new GoodsTransferService())->downloadTemplate();
    }

    public function import()
    {
        $data = $this->request->params([
            ['image_mode', 'direct'], ['default_status', 0], ['images_to_desc', 1]
        ]);
        return success('导入任务已创建', (new GoodsTransferService())->createImportTask(
            $this->request->file('file'),
            (string)($data['image_mode'] ?? 'direct'),
            (int)($data['default_status'] ?? 0),
            (int)($data['images_to_desc'] ?? 1) === 1
        ));
    }

    public function export()
    {
        $data = $this->request->params([
            ['scope', 'filter'], ['is_all', 1], ['ids', []], ['where', []]
        ]);
        return success('导出任务已创建', (new GoodsTransferService())->createExportTask($data));
    }

    public function tasks()
    {
        $data = $this->request->params([['task_type', ''], ['status', '']]);
        return success((new GoodsTransferService())->getPage($data));
    }

    public function task(int $id)
    {
        return success((new GoodsTransferService())->getInfo($id));
    }

    public function retry(int $id)
    {
        return success('任务已重新提交', (new GoodsTransferService())->retry($id));
    }

    public function download(int $id)
    {
        return (new GoodsTransferService())->downloadResult($id);
    }
}
