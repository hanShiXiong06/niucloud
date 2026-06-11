<?php
declare(strict_types=1);

namespace addon\hsx_device_asset\app\service\admin;

use addon\hsx_device_asset\app\dict\DeviceAssetDict;
use addon\hsx_device_asset\app\model\DeviceAssetItem;
use addon\hsx_device_asset\app\model\DeviceAssetMedia;
use addon\hsx_device_asset\app\model\DeviceAssetOperationLog;
use addon\hsx_device_asset\app\model\DeviceAssetPhotoTask;
use addon\hsx_device_asset\app\model\DeviceAssetPriceOrder;
use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use core\base\BaseAdminService;
use core\exception\CommonException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Db;

/**
 * 设备资产中台服务
 */
class DeviceAssetService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new DeviceAssetItem();
    }

    /**
     * 回收完成后可进入资产中台的设备池。
     */
    public function getImportableDevices(array $where = []): array
    {
        $query = (new RecycleDevice())
            ->where([['site_id', '=', $this->site_id]])
            ->whereIn('status', $this->allowedRecycleStatuses($where))
            ->with([
                'order' => function ($query) {
                    $query->field('id,order_no,member_id,status,create_at');
                },
                'priceUser' => function ($query) {
                    $query->field('uid,username,real_name');
                },
            ])
            ->field('id,site_id,order_id,imei,imei2,sn,model,category_id,capacity,color,status,initial_price,final_price,sell_price,check_result,check_result_seller,check_result_buyer,check_images,check_images_seller,check_images_buyer,check_at,price_uid,create_at,update_at')
            ->order('update_at desc,id desc')
            ->append(['status_name', 'category_name']);

        if (!empty($where['keyword'])) {
            $keyword = trim((string)$where['keyword']);
            $query->where(function ($query) use ($keyword) {
                $query->whereLike('imei|imei2|sn|model', '%' . $keyword . '%')
                    ->whereOr('id', '=', is_numeric($keyword) ? (int)$keyword : 0);
            });
        }
        if (!empty($where['imei'])) {
            $query->whereLike('imei|imei2|sn', '%' . trim((string)$where['imei']) . '%');
        }
        if (!empty($where['model'])) {
            $query->whereLike('model', '%' . trim((string)$where['model']) . '%');
        }
        if (!empty($where['category_id'])) {
            $query->where('category_id', '=', (int)$where['category_id']);
        }
        if (!empty($where['update_at']) && is_array($where['update_at']) && count($where['update_at']) === 2) {
            $query->whereBetweenTime('update_at', $where['update_at'][0], $where['update_at'][1]);
        }

        $importedDeviceIds = (new DeviceAssetItem())
            ->where([['site_id', '=', $this->site_id]])
            ->column('device_id');
        if (!empty($importedDeviceIds)) {
            $query->whereNotIn('id', $importedDeviceIds);
        }

        return $this->pageQuery($query);
    }

    /**
     * 资产列表。
     */
    public function getPage(array $where = []): array
    {
        $query = (new DeviceAssetItem())
            ->where([['site_id', '=', $this->site_id]])
            ->with([
                'recycleDevice' => function ($query) {
                    $query->field('id,site_id,order_id,imei,imei2,sn,model,capacity,color,initial_price,final_price,sell_price,check_result,check_result_seller,check_result_buyer,check_images,check_images_seller,check_images_buyer,check_at');
                },
            ])
            ->field('*')
            ->order('id desc');

        if (!empty($where['keyword'])) {
            $keyword = trim((string)$where['keyword']);
            $query->where(function ($query) use ($keyword) {
                $query->whereLike('asset_no|imei|imei2|sn|model', '%' . $keyword . '%')
                    ->whereOr('device_id', '=', is_numeric($keyword) ? (int)$keyword : 0);
            });
        }
        foreach (['status', 'photo_status', 'price_status', 'export_status'] as $field) {
            if (!empty($where[$field])) {
                $query->where($field, '=', (string)$where[$field]);
            }
        }
        $this->applyTaskType($query, (string)($where['task_type'] ?? ''));
        if (!empty($where['category_id'])) {
            $query->where('category_id', '=', (int)$where['category_id']);
        }
        if (!empty($where['create_at']) && is_array($where['create_at']) && count($where['create_at']) === 2) {
            $query->whereBetweenTime('create_at', $where['create_at'][0], $where['create_at'][1]);
        }

        return $this->pageQuery($query);
    }

    public function getTaskStats(): array
    {
        $assetQuery = (new DeviceAssetItem())->where([["site_id", "=", $this->site_id]]);
        $importedDeviceIds = (clone $assetQuery)->column("device_id");
        $poolQuery = (new RecycleDevice())
            ->where([["site_id", "=", $this->site_id]])
            ->whereIn("status", $this->allowedRecycleStatuses([]));
        if (!empty($importedDeviceIds)) {
            $poolQuery->whereNotIn("id", $importedDeviceIds);
        }

        return [
            "pending" => $this->taskTypeCount(clone $assetQuery, "pending"),
            "pool" => (int)$poolQuery->count(),
            "photo" => $this->taskTypeCount(clone $assetQuery, "photo"),
            "price" => $this->taskTypeCount(clone $assetQuery, "price"),
            "completed" => $this->taskTypeCount(clone $assetQuery, "completed"),
            "total" => (int)(clone $assetQuery)->count(),
        ];
    }

    public function getInfo(int $id): array
    {
        $asset = (new DeviceAssetItem())
            ->where([
                ['site_id', '=', $this->site_id],
                ['id', '=', $id],
            ])
            ->with(['media', 'photoTasks', 'priceOrders', 'logs', 'recycleDevice'])
            ->find();
        if (empty($asset)) {
            throw new CommonException('资产不存在');
        }
        return $asset->toArray();
    }

    /**
     * 从回收设备批量导入资产。
     */
    public function importDevices(array $deviceIds): array
    {
        $deviceIds = array_values(array_unique(array_filter(array_map('intval', $deviceIds))));
        if (empty($deviceIds)) {
            throw new CommonException('请选择需要入库的设备');
        }

        $devices = (new RecycleDevice())
            ->where([['site_id', '=', $this->site_id]])
            ->whereIn('id', $deviceIds)
            ->whereIn('status', [RecycleOrderDict::DEVICE_STATUS_RECYCLED, RecycleOrderDict::DEVICE_STATUS_CONSIGNED])
            ->select()
            ->toArray();

        if (count($devices) !== count($deviceIds)) {
            throw new CommonException('部分设备不存在或状态不允许入库');
        }

        $exists = (new DeviceAssetItem())
            ->where([['site_id', '=', $this->site_id]])
            ->whereIn('device_id', $deviceIds)
            ->column('device_id');
        if (!empty($exists)) {
            throw new CommonException('存在已入库设备，请勿重复导入：' . implode(',', $exists));
        }

        $completedEvent = [];
        Db::startTrans();
        try {
            $items = [];
            foreach ($devices as $device) {
                $asset = DeviceAssetItem::create($this->buildAssetData($device));
                $asset->save(['asset_no' => $this->makeAssetNo((int)$asset->id)]);
                $this->writeLog((int)$asset->id, (int)$device['id'], DeviceAssetDict::ACTION_IMPORT, [
                    'source_status' => $device['status'] ?? '',
                    'recycle_order_id' => $device['order_id'] ?? 0,
                ]);
                $items[] = $asset->refresh()->toArray();
            }
            Db::commit();
            return ['count' => count($items), 'items' => $items];
        } catch (\Throwable $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 扫码导入。二维码优先按 device_id，其次按 IMEI/SN。
     */
    public function scanImport(string $keyword): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            throw new CommonException('请输入设备码、IMEI 或 SN');
        }

        $device = (new RecycleDevice())
            ->where([['site_id', '=', $this->site_id]])
            ->whereIn('status', [RecycleOrderDict::DEVICE_STATUS_RECYCLED, RecycleOrderDict::DEVICE_STATUS_CONSIGNED])
            ->where(function ($query) use ($keyword) {
                if (is_numeric($keyword)) {
                    $query->where('id', '=', (int)$keyword)
                        ->whereOr('imei', '=', $keyword)
                        ->whereOr('imei2', '=', $keyword)
                        ->whereOr('sn', '=', $keyword);
                    return;
                }
                $query->where('imei', '=', $keyword)
                    ->whereOr('imei2', '=', $keyword)
                    ->whereOr('sn', '=', $keyword);
            })
            ->find();

        if (empty($device)) {
            throw new CommonException('未找到可入库设备');
        }

        $asset = (new DeviceAssetItem())->where([
            ['site_id', '=', $this->site_id],
            ['device_id', '=', (int)$device->id],
        ])->find();

        if (!empty($asset)) {
            return ['created' => false, 'asset' => $asset->toArray()];
        }

        $result = $this->importDevices([(int)$device->id]);
        return ['created' => true, 'asset' => $result['items'][0] ?? []];
    }

    public function createPhotoTask(int $assetId, array $data = []): array
    {
        $asset = $this->getAsset($assetId);
        $task = DeviceAssetPhotoTask::create([
            'site_id' => $this->site_id,
            'task_no' => $this->makeBizNo('PT'),
            'asset_id' => $assetId,
            'device_id' => (int)$asset->device_id,
            'status' => DeviceAssetDict::TASK_STATUS_PROCESSING,
            'source' => (string)($data['source'] ?? 'pc'),
            'station_id' => (string)($data['station_id'] ?? ''),
            'camera_job_id' => (string)($data['camera_job_id'] ?? ''),
            'operator_uid' => $this->uid,
            'started_at' => time(),
            'remark' => (string)($data['remark'] ?? ''),
        ]);

        $asset->save([
            'status' => DeviceAssetDict::STATUS_PHOTOING,
            'photo_status' => DeviceAssetDict::PHOTO_STATUS_PHOTOING,
        ]);

        $this->writeLog($assetId, (int)$asset->device_id, DeviceAssetDict::ACTION_PHOTO_TASK_CREATE, [
            'task_id' => (int)$task->id,
            'source' => $task->source,
            'station_id' => $task->station_id,
        ]);

        return $task->toArray();
    }

    public function saveMedia(int $assetId, array $data): array
    {
        $asset = $this->getAsset($assetId);
        $mediaList = $data['media'] ?? [];
        if (!is_array($mediaList) || empty($mediaList)) {
            $mediaList = [[
                'url' => (string)($data['url'] ?? ''),
                'oss_key' => (string)($data['oss_key'] ?? ''),
                'media_type' => (string)($data['media_type'] ?? 'image'),
                'scene' => (string)($data['scene'] ?? 'common'),
                'source' => (string)($data['source'] ?? 'manual'),
                'sort' => (int)($data['sort'] ?? 0),
            ]];
        }

        $rows = [];
        foreach ($mediaList as $item) {
            $url = trim((string)($item['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $rows[] = [
                'site_id' => $this->site_id,
                'asset_id' => $assetId,
                'device_id' => (int)$asset->device_id,
                'task_id' => (int)($item['task_id'] ?? $data['task_id'] ?? 0),
                'media_type' => in_array(($item['media_type'] ?? 'image'), ['image', 'video'], true) ? (string)$item['media_type'] : 'image',
                'scene' => (string)($item['scene'] ?? 'common'),
                'url' => $url,
                'oss_key' => (string)($item['oss_key'] ?? ''),
                'source' => (string)($item['source'] ?? $data['source'] ?? 'manual'),
                'status' => DeviceAssetDict::MEDIA_STATUS_PENDING,
                'sort' => (int)($item['sort'] ?? 0),
                'operator_uid' => $this->uid,
            ];
        }

        if (empty($rows)) {
            throw new CommonException('请上传有效的图片或视频');
        }

        (new DeviceAssetMedia())->saveAll($rows);
        if (!empty($data['task_id'])) {
            (new DeviceAssetPhotoTask())->where([
                ['site_id', '=', $this->site_id],
                ['id', '=', (int)$data['task_id']],
            ])->update(['status' => DeviceAssetDict::TASK_STATUS_REVIEW]);
        }

        $this->refreshMediaCount($assetId);
        $asset->save([
            'status' => DeviceAssetDict::STATUS_PHOTO_REVIEW,
            'photo_status' => DeviceAssetDict::PHOTO_STATUS_REVIEW,
        ]);

        $this->writeLog($assetId, (int)$asset->device_id, DeviceAssetDict::ACTION_MEDIA_SAVE, [
            'count' => count($rows),
            'task_id' => (int)($data['task_id'] ?? 0),
        ]);

        return $this->getInfo($assetId);
    }

    public function reviewMedia(int $mediaId, string $status, string $reason = ''): array
    {
        if (!in_array($status, [DeviceAssetDict::MEDIA_STATUS_APPROVED, DeviceAssetDict::MEDIA_STATUS_REJECTED], true)) {
            throw new CommonException('媒体复检状态不正确');
        }

        $media = (new DeviceAssetMedia())->where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $mediaId],
        ])->find();
        if (empty($media)) {
            throw new CommonException('图片或视频不存在');
        }

        $media->save([
            'status' => $status,
            'reject_reason' => $status === DeviceAssetDict::MEDIA_STATUS_REJECTED ? $reason : '',
            'operator_uid' => $this->uid,
        ]);

        $this->refreshMediaCount((int)$media->asset_id);
        $rejectedCount = (new DeviceAssetMedia())->where([
            ['site_id', '=', $this->site_id],
            ['asset_id', '=', (int)$media->asset_id],
            ['status', '=', DeviceAssetDict::MEDIA_STATUS_REJECTED],
        ])->count();
        if ($rejectedCount > 0) {
            (new DeviceAssetItem())->where([
                ['site_id', '=', $this->site_id],
                ['id', '=', (int)$media->asset_id],
            ])->update([
                'status' => DeviceAssetDict::STATUS_PHOTO_REJECTED,
                'photo_status' => DeviceAssetDict::PHOTO_STATUS_REJECTED,
            ]);
        }

        $this->writeLog((int)$media->asset_id, (int)$media->device_id, DeviceAssetDict::ACTION_MEDIA_REVIEW, [
            'media_id' => $mediaId,
            'status' => $status,
            'reason' => $reason,
        ]);

        return $this->getInfo((int)$media->asset_id);
    }

    public function reviewMediaBatch(int $assetId, array $mediaIds, string $status, string $reason = ''): array
    {
        if (!in_array($status, [DeviceAssetDict::MEDIA_STATUS_APPROVED, DeviceAssetDict::MEDIA_STATUS_REJECTED], true)) {
            throw new CommonException('媒体复检状态不正确');
        }
        $mediaIds = array_values(array_unique(array_filter(array_map('intval', $mediaIds))));
        if (empty($mediaIds)) {
            throw new CommonException('请选择需要处理的图片');
        }

        $asset = $this->getAsset($assetId);
        $query = (new DeviceAssetMedia())->where([
            ['site_id', '=', $this->site_id],
            ['asset_id', '=', $assetId],
        ])->whereIn('id', $mediaIds);
        $count = (int)(clone $query)->count();
        if ($count <= 0) {
            throw new CommonException('未找到可处理图片');
        }

        Db::startTrans();
        try {
            $query->update([
                'status' => $status,
                'reject_reason' => $status === DeviceAssetDict::MEDIA_STATUS_REJECTED ? $reason : '',
                'operator_uid' => $this->uid,
                'update_at' => time(),
            ]);
            $this->refreshMediaCount($assetId);

            $activeCount = (new DeviceAssetMedia())->where([
                ['site_id', '=', $this->site_id],
                ['asset_id', '=', $assetId],
                ['media_type', '=', 'image'],
                ['status', '<>', DeviceAssetDict::MEDIA_STATUS_REJECTED],
            ])->count();
            $assetUpdate = [];
            if ($activeCount <= 0) {
                $assetUpdate = [
                    'status' => DeviceAssetDict::STATUS_PHOTO_REJECTED,
                    'photo_status' => DeviceAssetDict::PHOTO_STATUS_REJECTED,
                ];
            } elseif ($status === DeviceAssetDict::MEDIA_STATUS_APPROVED && $asset->photo_status === DeviceAssetDict::PHOTO_STATUS_REJECTED) {
                $assetUpdate = [
                    'status' => DeviceAssetDict::STATUS_PHOTO_REVIEW,
                    'photo_status' => DeviceAssetDict::PHOTO_STATUS_REVIEW,
                ];
            }
            if (!empty($assetUpdate)) {
                (new DeviceAssetItem())->where([
                    ['site_id', '=', $this->site_id],
                    ['id', '=', $assetId],
                ])->update($assetUpdate);
            }

            $this->writeLog($assetId, (int)$asset->device_id, DeviceAssetDict::ACTION_MEDIA_REVIEW, [
                'media_ids' => $mediaIds,
                'count' => $count,
                'status' => $status,
                'reason' => $reason,
                'batch' => true,
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }

        return $this->getInfo($assetId);
    }

    public function confirmPhotos(int $assetId): array
    {
        $asset = $this->getAsset($assetId);
        $approvedCount = (new DeviceAssetMedia())->where([
            ['site_id', '=', $this->site_id],
            ['asset_id', '=', $assetId],
            ['media_type', '=', 'image'],
            ['status', '=', DeviceAssetDict::MEDIA_STATUS_APPROVED],
        ])->count();
        if ($approvedCount <= 0) {
            throw new CommonException('请至少保留一张复检通过的图片');
        }

        Db::startTrans();
        try {
            $asset->save([
                'status' => DeviceAssetDict::STATUS_WAIT_PRICE,
                'photo_status' => DeviceAssetDict::PHOTO_STATUS_APPROVED,
                'photo_confirm_uid' => $this->uid,
                'photo_confirmed_at' => time(),
            ]);
            (new DeviceAssetPhotoTask())->where([
                ['site_id', '=', $this->site_id],
                ['asset_id', '=', $assetId],
                ['status', '<>', DeviceAssetDict::TASK_STATUS_CANCELLED],
            ])->update([
                'status' => DeviceAssetDict::TASK_STATUS_COMPLETED,
                'completed_at' => time(),
            ]);
            $this->ensurePriceOrder($asset);
            $this->writeLog($assetId, (int)$asset->device_id, DeviceAssetDict::ACTION_PHOTO_CONFIRM, [
                'approved_count' => $approvedCount,
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }

        return $this->getInfo($assetId);
    }

    public function completePrice(int $assetId, array $data): array
    {
        $asset = $this->getAsset($assetId);
        $salePrice = round((float)($data['sale_price'] ?? 0), 2);
        if ($salePrice <= 0) {
            throw new CommonException('请输入有效的销售价格');
        }

        Db::startTrans();
        try {
            $priceData = [
                'sale_price' => $salePrice,
                'peer_price' => round((float)($data['peer_price'] ?? 0), 2),
                'min_price' => round((float)($data['min_price'] ?? 0), 2),
                'price_remark' => (string)($data['remark'] ?? ''),
                'price_uid' => $this->uid,
                'priced_at' => time(),
                'price_status' => DeviceAssetDict::PRICE_STATUS_COMPLETED,
                'status' => DeviceAssetDict::STATUS_READY_EXPORT,
            ];
            $asset->save($priceData);

            $order = (new DeviceAssetPriceOrder())->where([
                ['site_id', '=', $this->site_id],
                ['asset_id', '=', $assetId],
            ])->order('id desc')->find();

            $orderData = [
                'status' => DeviceAssetDict::PRICE_STATUS_COMPLETED,
                'sale_price' => $priceData['sale_price'],
                'peer_price' => $priceData['peer_price'],
                'min_price' => $priceData['min_price'],
                'cost_price' => (float)$asset->recycle_final_price,
                'remark' => $priceData['price_remark'],
                'priced_uid' => $this->uid,
                'priced_at' => time(),
            ];
            if (empty($order)) {
                DeviceAssetPriceOrder::create(array_merge($orderData, [
                    'site_id' => $this->site_id,
                    'order_no' => $this->makeBizNo('PO'),
                    'asset_id' => $assetId,
                    'device_id' => (int)$asset->device_id,
                    'assign_uid' => 0,
                ]));
            } else {
                $order->save($orderData);
            }

            $this->writeLog($assetId, (int)$asset->device_id, DeviceAssetDict::ACTION_PRICE_COMPLETE, $orderData);
            $completedEvent = [
                'event_name' => 'device_asset.price.completed.v1',
                'site_id' => $this->site_id,
                'asset_id' => $assetId,
                'device_id' => (int)$asset->device_id,
                'operator' => ['id' => $this->uid, 'name' => $this->username ?: ''],
                'payload' => [
                    'erp_asset_id' => (int)($asset->ext_json['erp_asset_id'] ?? 0),
                    'sale_price' => $priceData['sale_price'],
                    'peer_price' => $priceData['peer_price'],
                    'min_price' => $priceData['min_price'],
                    'remark' => $priceData['price_remark'],
                ],
            ];
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
        event('DeviceAssetPriceCompleted', $completedEvent);

        return $this->getInfo($assetId);
    }

    public function exportExcel(array $where = []): string
    {
        $query = (new DeviceAssetItem())->where([['site_id', '=', $this->site_id]]);
        if (!empty($where['asset_ids']) && is_array($where['asset_ids'])) {
            $query->whereIn('id', array_filter(array_map('intval', $where['asset_ids'])));
        } else {
            if (!empty($where['status'])) {
                $query->where('status', '=', (string)$where['status']);
            }
            if (!empty($where['price_status'])) {
                $query->where('price_status', '=', (string)$where['price_status']);
            }
            if (!empty($where['keyword'])) {
                $keyword = trim((string)$where['keyword']);
                $query->whereLike('asset_no|imei|imei2|sn|model', '%' . $keyword . '%');
            }
        }

        $items = $query->with(['media'])->order('id desc')->select();
        if (count($items) === 0) {
            throw new CommonException('暂无可导出的资产数据');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = ['资产编号', '回收设备ID', '回收订单ID', 'IMEI', 'IMEI2', 'SN', '型号', '分类ID', '回收价', '销售价', '同行价', '最低价', '资产状态', '照片状态', '定价状态', '图片数量', '视频数量', '图片链接', '视频链接', '定价备注'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        $row = 2;
        $assetIds = [];
        foreach ($items as $asset) {
            $assetIds[] = (int)$asset->id;
            $imageUrls = [];
            $videoUrls = [];
            foreach (($asset->media ?? []) as $media) {
                if (($media['status'] ?? '') === DeviceAssetDict::MEDIA_STATUS_REJECTED) {
                    continue;
                }
                if (($media['media_type'] ?? '') === 'video') {
                    $videoUrls[] = $media['url'] ?? '';
                } else {
                    $imageUrls[] = $media['url'] ?? '';
                }
            }

            $values = [
                $asset->asset_no,
                $asset->device_id,
                $asset->recycle_order_id,
                $asset->imei,
                $asset->imei2,
                $asset->sn,
                $asset->model,
                $asset->category_id,
                $asset->recycle_final_price,
                $asset->sale_price,
                $asset->peer_price,
                $asset->min_price,
                $asset->status_name,
                $asset->photo_status_name,
                $asset->price_status_name,
                $asset->image_count,
                $asset->video_count,
                implode("\n", array_filter($imageUrls)),
                implode("\n", array_filter($videoUrls)),
                $asset->price_remark,
            ];
            foreach ($values as $index => $value) {
                $sheet->setCellValueByColumnAndRow($index + 1, $row, $value);
            }
            $row++;
        }

        foreach (range(1, count($headers)) as $column) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }

        $dir = runtime_path() . 'device_asset_export/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $filePath = $dir . 'device_asset_' . date('Ymd_His') . '.xlsx';
        (new Xlsx($spreadsheet))->save($filePath);

        (new DeviceAssetItem())->where([
            ['site_id', '=', $this->site_id],
        ])->whereIn('id', $assetIds)->update([
            'status' => DeviceAssetDict::STATUS_EXPORTED,
            'export_status' => DeviceAssetDict::EXPORT_STATUS_EXPORTED,
            'exported_at' => time(),
        ]);

        foreach ($assetIds as $assetId) {
            $this->writeLog($assetId, 0, DeviceAssetDict::ACTION_EXPORT, ['file' => basename($filePath)]);
        }

        return $filePath;
    }

    protected function allowedRecycleStatuses(array $where): array
    {
        if (!empty($where['status'])) {
            return [(int)$where['status']];
        }
        return [RecycleOrderDict::DEVICE_STATUS_RECYCLED, RecycleOrderDict::DEVICE_STATUS_CONSIGNED];
    }

    protected function buildAssetData(array $device): array
    {
        return [
            'site_id' => $this->site_id,
            'asset_no' => '',
            'device_id' => (int)$device['id'],
            'recycle_order_id' => (int)($device['order_id'] ?? 0),
            'imei' => (string)($device['imei'] ?? ''),
            'imei2' => (string)($device['imei2'] ?? ''),
            'sn' => (string)($device['sn'] ?? ''),
            'model' => (string)($device['model'] ?? ''),
            'category_id' => (int)($device['category_id'] ?? 0),
            'source_status' => (string)($device['status'] ?? ''),
            'recycle_final_price' => round((float)($device['final_price'] ?? 0), 2),
            'check_summary' => $this->buildCheckSummary($device),
            'ext_json' => [
                'capacity' => (string)($device['capacity'] ?? ''),
                'color' => (string)($device['color'] ?? ''),
                'initial_price' => round((float)($device['initial_price'] ?? 0), 2),
                'recycle_sell_price' => round((float)($device['sell_price'] ?? 0), 2),
                'check_at' => (int)($device['check_at'] ?? 0),
                'check_images' => (string)($device['check_images'] ?? ''),
                'check_images_seller' => (string)($device['check_images_seller'] ?? ''),
                'check_images_buyer' => (string)($device['check_images_buyer'] ?? ''),
            ],
            'status' => DeviceAssetDict::STATUS_WAIT_PHOTO,
            'photo_status' => DeviceAssetDict::PHOTO_STATUS_WAIT,
            'price_status' => DeviceAssetDict::PRICE_STATUS_WAIT,
            'export_status' => DeviceAssetDict::EXPORT_STATUS_PENDING,
            'imported_by' => $this->uid,
            'imported_at' => time(),
        ];
    }

    protected function normalizeJson($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $json = json_decode($value, true);
            return is_array($json) ? $json : ['raw' => $value];
        }
        return [];
    }

    protected function buildCheckSummary(array $device): array
    {
        $summary = [];
        foreach ([
            'check_result' => '内部质检',
            'check_result_seller' => '卖家质检',
            'check_result_buyer' => '买家质检',
        ] as $field => $label) {
            $value = trim((string)($device[$field] ?? ''));
            if ($value === '') {
                continue;
            }
            $decoded = $this->normalizeJson($value);
            if (!empty($decoded) && !isset($decoded['raw'])) {
                foreach ($decoded as $key => $item) {
                    if ($item !== '' && $item !== null) {
                        $summary[(string)$key] = is_array($item) ? json_encode($item, JSON_UNESCAPED_UNICODE) : (string)$item;
                    }
                }
                continue;
            }
            $summary[$label] = $value;
        }
        if (!empty($device['capacity'])) {
            $summary['容量'] = (string)$device['capacity'];
        }
        if (!empty($device['color'])) {
            $summary['颜色'] = (string)$device['color'];
        }
        return $summary;
    }

    protected function taskTypeCount($query, string $taskType): int
    {
        $this->applyTaskType($query, $taskType);
        return (int)$query->count();
    }

    protected function applyTaskType($query, string $taskType): void
    {
        $statusMap = [
            'pending' => [
                DeviceAssetDict::STATUS_WAIT_PHOTO,
                DeviceAssetDict::STATUS_PHOTOING,
                DeviceAssetDict::STATUS_PHOTO_REVIEW,
                DeviceAssetDict::STATUS_PHOTO_REJECTED,
                DeviceAssetDict::STATUS_WAIT_PRICE,
                DeviceAssetDict::STATUS_PRICED,
            ],
            'photo' => [
                DeviceAssetDict::STATUS_WAIT_PHOTO,
                DeviceAssetDict::STATUS_PHOTOING,
                DeviceAssetDict::STATUS_PHOTO_REVIEW,
                DeviceAssetDict::STATUS_PHOTO_REJECTED,
            ],
            'price' => [
                DeviceAssetDict::STATUS_WAIT_PRICE,
                DeviceAssetDict::STATUS_PRICED,
            ],
            'completed' => [
                DeviceAssetDict::STATUS_READY_EXPORT,
                DeviceAssetDict::STATUS_EXPORTED,
                DeviceAssetDict::STATUS_ARCHIVED,
            ],
        ];
        if (isset($statusMap[$taskType])) {
            $query->whereIn('status', $statusMap[$taskType]);
        }
    }

    protected function getAsset(int $assetId): DeviceAssetItem
    {
        $asset = (new DeviceAssetItem())->where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $assetId],
        ])->find();
        if (empty($asset)) {
            throw new CommonException('资产不存在');
        }
        return $asset;
    }

    protected function ensurePriceOrder(DeviceAssetItem $asset): void
    {
        $exists = (new DeviceAssetPriceOrder())->where([
            ['site_id', '=', $this->site_id],
            ['asset_id', '=', (int)$asset->id],
            ['status', '<>', DeviceAssetDict::PRICE_STATUS_COMPLETED],
        ])->find();
        if (!empty($exists)) {
            return;
        }

        DeviceAssetPriceOrder::create([
            'site_id' => $this->site_id,
            'order_no' => $this->makeBizNo('PO'),
            'asset_id' => (int)$asset->id,
            'device_id' => (int)$asset->device_id,
            'status' => DeviceAssetDict::PRICE_STATUS_PENDING,
            'assign_uid' => 0,
            'cost_price' => (float)$asset->recycle_final_price,
        ]);
        (new DeviceAssetItem())->where([
            ['site_id', '=', $this->site_id],
            ['id', '=', (int)$asset->id],
        ])->update(['price_status' => DeviceAssetDict::PRICE_STATUS_PENDING]);

        $this->writeLog((int)$asset->id, (int)$asset->device_id, DeviceAssetDict::ACTION_PRICE_ORDER_CREATE, []);
    }

    protected function refreshMediaCount(int $assetId): void
    {
        $imageCount = (new DeviceAssetMedia())->where([
            ['site_id', '=', $this->site_id],
            ['asset_id', '=', $assetId],
            ['media_type', '=', 'image'],
            ['status', '<>', DeviceAssetDict::MEDIA_STATUS_REJECTED],
        ])->count();
        $videoCount = (new DeviceAssetMedia())->where([
            ['site_id', '=', $this->site_id],
            ['asset_id', '=', $assetId],
            ['media_type', '=', 'video'],
            ['status', '<>', DeviceAssetDict::MEDIA_STATUS_REJECTED],
        ])->count();
        (new DeviceAssetItem())->where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $assetId],
        ])->update([
            'image_count' => $imageCount,
            'video_count' => $videoCount,
        ]);
    }

    protected function writeLog(int $assetId, int $deviceId, string $action, array $payload = []): void
    {
        if ($deviceId <= 0 && $assetId > 0) {
            $deviceId = (int)((new DeviceAssetItem())->where([
                ['site_id', '=', $this->site_id],
                ['id', '=', $assetId],
            ])->value('device_id') ?: 0);
        }

        DeviceAssetOperationLog::create([
            'site_id' => $this->site_id,
            'asset_id' => $assetId,
            'device_id' => $deviceId,
            'action' => $action,
            'action_name' => DeviceAssetDict::actionName($action),
            'operator_uid' => $this->uid,
            'operator_name' => $this->username ?: '',
            'payload' => $payload,
            'ip' => $this->request->ip(),
        ]);
    }

    protected function makeAssetNo(int $id): string
    {
        return 'DA' . date('Ymd') . str_pad((string)$this->site_id, 3, '0', STR_PAD_LEFT) . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
    }

    protected function makeBizNo(string $prefix): string
    {
        return $prefix . date('YmdHis') . mt_rand(1000, 9999);
    }
}
