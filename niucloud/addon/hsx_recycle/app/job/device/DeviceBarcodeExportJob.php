<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\job\device;

use addon\hsx_recycle\app\listener\export\RecycleDeviceExportListener;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\service\admin\device_export\DeviceBarcodeWorkbook;
use app\dict\sys\ExportDict;
use app\model\sys\SysExport;
use core\base\BaseJob;
use think\facade\Db;

class DeviceBarcodeExportJob extends BaseJob
{
    public function doJob(int $site_id, int $export_id, array $where): bool
    {
        $recordWhere = [['id', '=', $export_id], ['site_id', '=', $site_id], ['export_key', '=', 'recycle_device']];
        $record = SysExport::where($recordWhere)->findOrEmpty();
        if ($site_id <= 0 || $record->isEmpty() || (string)$record->export_status === ExportDict::SUCCESS) return true;

        $path = '';
        $partial = '';
        try {
            $rows = (new RecycleDeviceExportListener())->handle([
                'site_id' => $site_id, 'type' => 'recycle_device', 'where' => $where,
                'mark_exported' => false, 'keep_device_id' => true,
            ]);
            if (!$rows) throw new \RuntimeException('没有符合条件的设备，请刷新列表后重新导出');
            if (!empty($where['device_ids']) && count($rows) !== count(array_unique($where['device_ids']))) {
                throw new \RuntimeException('部分所选设备已不在可导出范围，请刷新列表后重新选择');
            }
            $directory = public_path() . 'upload/export';
            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new \RuntimeException('无法创建导出目录，请检查服务器磁盘和权限');
            }
            $relative = 'upload/export/recycle_device_' . $site_id . '_' . $export_id . '_' . bin2hex(random_bytes(12)) . '.xlsx';
            $path = public_path() . $relative;
            $partial = $path . '.part';
            (new DeviceBarcodeWorkbook())->save($rows, $partial);
            if (!rename($partial, $path)) throw new \RuntimeException('Excel 文件保存失败，请重试');
            $size = filesize($path);
            if (!$size) throw new \RuntimeException('Excel 文件为空，请重试');

            // 文件完整生成后再标记，失败时不提前改变“未导出”筛选范围。
            $published = Db::transaction(function () use ($site_id, $recordWhere, $rows, $relative, $size) {
                $current = SysExport::where($recordWhere)->lock(true)->findOrEmpty();
                if ($current->isEmpty()) throw new \RuntimeException('导出任务已删除');
                // 队列重复投递时只保留先完成的文件，不覆盖成功记录。
                if ((string)$current->export_status === ExportDict::SUCCESS) return false;
                RecycleDevice::where('site_id', $site_id)->whereIn('id', array_column($rows, '_device_id'))->update(['export_time' => time()]);
                $current->save([
                    'export_num' => count($rows), 'file_path' => $relative, 'file_size' => $size,
                    'export_status' => ExportDict::SUCCESS, 'fail_reason' => '',
                ]);
                return true;
            });
            if (!$published && is_file($path)) unlink($path);
            return true;
        } catch (\Throwable $e) {
            foreach ([$partial, $path] as $file) if ($file !== '' && is_file($file)) unlink($file);
            SysExport::where($recordWhere)->where('export_status', '<>', ExportDict::SUCCESS)->update([
                'export_status' => ExportDict::FAIL, 'fail_reason' => mb_substr($e->getMessage(), 0, 250),
                'file_path' => '', 'file_size' => 0,
            ]);
            throw $e;
        }
    }
}
