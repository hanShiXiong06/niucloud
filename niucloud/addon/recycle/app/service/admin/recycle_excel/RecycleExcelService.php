<?php

// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\recycle\app\service\admin\recycle_excel;


use addon\recycle\app\model\RecycleDeviceBrand;
use addon\recycle\app\model\RecycleDeviceModel;
use addon\recycle\app\model\RecycleDevicePrice;
use addon\recycle\app\model\RecyclePriceImportRecord;
use core\base\BaseAdminService;
use think\facade\Db;
use think\facade\Cache;
use think\facade\Event;

/**
 * Excel数据管理服务层
 * Class RecycleExcelService
 * @package addon\recycle\app\service\admin\recycle_excel
 */
class RecycleExcelService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleDeviceModel();
        
        // 监听数据变更事件
        Event::listen('recycle_excel_data_change', function() {
            $this->clearListCache();
        });
    }

    /**
     * 获取设备列表
     * @param array $where
     * @return array
     */
    public function getPage($where=[])
    {
        // 获取分页参数


      
        // 处理价格查询日期
        $price_date = $where['create_at'] ?? date('Y-m-d');
        
        // 生成完整的缓存参数，包含所有影响查询结果的因素
        $cacheParams = [
            'site_id' => $this->site_id,
            'where' => $where,
            // 'page' => $page,
            // 'limit' => $limit,
            'price_date' => $price_date
        ];
        
        // 生成缓存key，确保不同查询条件有不同的缓存
        $cache_key = 'recycle_excel_list:' . $this->site_id . ':' . md5(json_encode($cacheParams));
        
        // 尝试从缓存获取数据
        $cache_data = Cache::get($cache_key);
        if ($cache_data !== null) {
            return $cache_data;
        }
        
        $field = 'id,site_id,brand_id,model_name,network_model,capacity,device_type,status,create_at,update_at';
        $order = 'id asc';
        
        $where['site_id'] = $this->site_id;

        $list = $this->model
            ->where([['site_id', '=', $this->site_id]])
            ->with([
                'brand' => function($query) {
                    $query->field('id,brand_name,brand_code');
                },
                'currentPrice' => function($query) use ($price_date) {
                    // 使用use关键字将外部变量传入闭包
                    if ($price_date) {
                        $query->where('price_date', $price_date);
                    }
                    $query->field('id,device_model_id,price_data,price_date,is_current');
                }
            ])
            ->withSearch(['site_id', 'brand_id', 'device_type', 'keyword'], $where)
            ->field($field)
            ->order($order)
            ->select()
            ->toArray();

        // $list = $this->pageQuery($search_model);
        
        // 处理价格数据
        foreach ($list as &$item) {
            if (isset($item['current_price']['price_data'])) {
                $item['price_detail'] = json_decode($item['current_price']['price_data'], true) ?: [];
            } else {
                $item['price_detail'] = [];
            }
        }

        // 根据查询类型设置不同的缓存时间
        $cache_time = $this->getCacheTime($where, $price_date);
        
        // 设置缓存
        Cache::set($cache_key, $list, $cache_time);

        return $list;
    }

    /**
     * 根据查询条件获取合适的缓存时间
     * @param array $where
     * @param string $price_date
     * @return int
     */
    private function getCacheTime(array $where, string $price_date): int
    {
        $today = date('Y-m-d');
        
        // 如果查询今天的价格数据，使用较短的缓存时间
        if ($price_date === $today) {
            return 60; // 1分钟缓存
        }
        
        // 如果有其他实时查询需求的条件，也使用短缓存
        if (!empty($where['keyword'])) {
            return 120; // 2分钟缓存
        }
        
        // 历史数据或无特殊查询条件，使用较长缓存
        return 300; // 5分钟缓存
    }

    /**
     * 获取设备列表（deviceList方法）
     * @param array $where
     * @return array
     */
    public function getDeviceList(array $where = [])
    {
        return $this->getPage($where);
    }

    /**
     * 获取品牌列表
     * @return array
     */
    public function getBrands()
    {
        return (new RecycleDeviceBrand())
            ->where([ ['status', '=', 1]])
            ->withCount(['deviceModels'])
            ->order('id desc')
            ->select()
            ->toArray();
    }

    /**
     * 获取统计信息
     * @return array
     */
    public function getStatistics()
    {
        $stats = [
            'total_brands' => (new RecycleDeviceBrand())->where('site_id', $this->site_id)->count(),
            'total_devices' => (new RecycleDeviceModel())->where('site_id', $this->site_id)->count(),
            'total_prices' => (new RecycleDevicePrice())->count(),
            'import_records' => 0
        ];

        // 设备类型分布
        $deviceTypes = (new RecycleDeviceModel())
            ->where('site_id', $this->site_id)
            ->group('device_type')
            ->column('count(*) as count, device_type');

        $stats['device_type_distribution'] = $deviceTypes;

        // 品牌设备数量排行
        $brandStats = Db::name('recycle_device_brand')
            ->alias('b')
            ->leftJoin('recycle_device_model m', 'b.id = m.brand_id')
            ->where('b.site_id', $this->site_id)
            ->group('b.id')
            ->field('b.brand_name, b.brand_code, COUNT(m.id) as device_count')
            ->order('device_count desc')
            ->select()
            ->toArray();

        $stats['brand_device_count'] = $brandStats;

        return $stats;
    }

    /**
     * 获取导入记录
     * @param array $where
     * @return array
     */
    public function getRecords(array $where = [])
    {
        return [
            'data' => [],
            'total' => 0,
            'per_page' => 20,
            'current_page' => 1,
            'last_page' => 1
        ];
    }

    /**
     * 获取设备详情
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,site_id,brand_id,model_name,network_model,capacity,device_type,status,create_at,update_at';

        $info = $this->model
            ->with([
                'brand' => function($query) {
                    $query->field('id,brand_name,brand_code');
                },
                'currentPrice' => function($query) {
                    $query->field('id,device_model_id,price_data,price_date,is_current');
                }
            ])
            ->where([['id', '=', $id], ['site_id', '=', $this->site_id]])
            ->field($field)
            ->findOrEmpty()
            ->toArray();

        if (empty($info)) {
            throw new \Exception('设备不存在');
        }

        // 解析价格数据
        if (isset($info['current_price']['price_data'])) {
            $info['price_detail'] = json_decode($info['current_price']['price_data'], true) ?: [];
        } else {
            $info['price_detail'] = [];
        }

        return $info;
    }

    /**
     * 搜索设备
     * @param array $data
     * @return array
     */
    public function search(array $data)
    {
        $keyword = $data['keyword'] ?? '';
        $limit = $data['limit'] ?? 10;

        if (empty($keyword)) {
            return [];
        }

        return $this->model
            ->with(['brand' => function($query) {
                $query->field('id,brand_name');
            }])
            ->where('site_id', $this->site_id)
            ->where(function($query) use ($keyword) {
                $query->whereLike('model_name', '%' . $keyword . '%')
                      ->whereOr('network_model', '%' . $keyword . '%')
                      ->whereOr('capacity', '%' . $keyword . '%');
            })
            ->limit($limit)
            ->field('id,model_name,network_model,capacity,device_type')
            ->select()
            ->toArray();
    }

    /**
     * 批量删除设备
     * @param array $data
     * @return bool
     */
    public function batchDelete(array $data)
    {
        $ids = $data['ids'] ?? [];
        
        if (empty($ids) || !is_array($ids)) {
            throw new \Exception('请选择要删除的设备');
        }

        Db::startTrans();
        try {
            (new RecycleDevicePrice())->whereIn('device_model_id', $ids)->delete();
            $this->model->whereIn('id', $ids)->delete();
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new \Exception('批量删除失败：' . $e->getMessage());
        }
    }

    /**
     * 更新设备价格
     * @param array $data
     * @return bool
     */
    public function updatePrice(array $data)
    {
        $device_id = $data['device_id'] ?? 0;
        $price_data = $data['price_data'] ?? [];

        $device = $this->model
            ->where('id', $device_id)
            
            ->findOrEmpty();

        if ($device->isEmpty()) {
            throw new \Exception('设备不存在');
        }

        $priceModel = (new RecycleDevicePrice())
            ->where('device_model_id', $device_id)
            
            ->findOrEmpty();

        $priceJson = json_encode($price_data, JSON_UNESCAPED_UNICODE);
        $now = time();

        if (!$priceModel->isEmpty()) {
            $priceModel->save([
                'price_data' => $priceJson,
                'price_date' => date('Y-m-d'),
                'update_at' => $now
            ]);
        } else {
            (new RecycleDevicePrice())->save([
                'site_id' => $this->site_id,
                'device_model_id' => $device_id,
                'price_data' => $priceJson,
                'price_date' => date('Y-m-d'),
                'is_current' => 1,
                'status' => 1,
                'create_at' => $now,
                'update_at' => $now
            ]);
        }

        return true;
    }

    /**
     * 导出设备数据
     * @param array $data
     * @return array
     */
    public function export(array $data)
    {
        $brand_id = $data['brand_id'] ?? '';
        $device_type = $data['device_type'] ?? '';
        $keyword = $data['keyword'] ?? '';

        $query = $this->model
            ->withJoin([
                'brand' => ['brand_name', 'brand_code'],
                'currentPrice' => ['price_data', 'price_date']
            ])
            ;

        if (!empty($brand_id)) {
            $query->where('brand_id', $brand_id);
        }
        if (!empty($device_type)) {
            $query->where('device_type', $device_type);
        }
        if (!empty($keyword)) {
            $query->where(function($q) use ($keyword) {
                $q->whereLike('model_name', '%' . $keyword . '%')
                  ->whereOr('network_model', '%' . $keyword . '%')
                  ->whereOr('capacity', '%' . $keyword . '%');
            });
        }

        $list = $query->limit(5000)->select()->toArray();

        $exportData = [];
        foreach ($list as $item) {
            $priceDetail = [];
            if (isset($item['current_price']['price_data'])) {
                $priceDetail = json_decode($item['current_price']['price_data'], true) ?: [];
            }

            $exportData[] = [
                'ID' => $item['id'],
                '品牌' => $item['brand']['brand_name'] ?? '',
                '设备型号' => $item['model_name'],
                '网络型号' => $item['network_model'],
                '容量' => $item['capacity'],
                '设备类型' => $this->getDeviceTypeName($item['device_type']),
                '高保充新' => $priceDetail['高保充新'] ?? '',
                '充新' => $priceDetail['充新'] ?? '',
                '靓机' => $priceDetail['靓机'] ?? '',
                '价格日期' => $item['current_price']['price_date'] ?? ''
            ];
        }

        return [
            'total' => count($exportData),
            'data' => $exportData
        ];
    }

    /**
     * 获取价格图表数据
     * @param array $data
     * @return array
     */
    public function getChart(array $data)
    {
        $brand_id = $data['brand_id'] ?? '';
        $device_type = $data['device_type'] ?? '';

        $query = $this->model
            ->withJoin([
                'brand' => ['brand_name'],
                'currentPrice' => ['price_data']
            ])
            ;

        if (!empty($brand_id)) {
            $query->where('brand_id', $brand_id);
        }
        if (!empty($device_type)) {
            $query->where('device_type', $device_type);
        }

        $list = $query->limit(100)->select()->toArray();

        $priceRanges = [
            '0-1000' => 0,
            '1000-3000' => 0,
            '3000-5000' => 0,
            '5000-8000' => 0,
            '8000+' => 0
        ];

        $brandPrices = [];

        foreach ($list as $item) {
            if (isset($item['current_price']['price_data'])) {
                $priceDetail = json_decode($item['current_price']['price_data'], true) ?: [];
                $price = $priceDetail['高保充新'] ?? 0;
                
                if ($price > 0) {
                    if ($price < 1000) {
                        $priceRanges['0-1000']++;
                    } elseif ($price < 3000) {
                        $priceRanges['1000-3000']++;
                    } elseif ($price < 5000) {
                        $priceRanges['3000-5000']++;
                    } elseif ($price < 8000) {
                        $priceRanges['5000-8000']++;
                    } else {
                        $priceRanges['8000+']++;
                    }

                    $brandName = $item['brand']['brand_name'] ?? '未知品牌';
                    if (!isset($brandPrices[$brandName])) {
                        $brandPrices[$brandName] = [];
                    }
                    $brandPrices[$brandName][] = $price;
                }
            }
        }

        $brandAvgPrices = [];
        foreach ($brandPrices as $brand => $prices) {
            $brandAvgPrices[$brand] = round(array_sum($prices) / count($prices), 2);
        }

        return [
            'price_ranges' => $priceRanges,
            'brand_avg_prices' => $brandAvgPrices
        ];
    }

    /**
     * 获取设备类型名称
     * @param string $type
     * @return string
     */
    private function getDeviceTypeName(string $type): string
    {
        $names = [
            'phone' => '手机',
            'tablet' => '平板',
            'watch' => '手表'
        ];
        
        return $names[$type] ?? $type;
    }

    /**
     * 清除列表缓存
     * @return void
     */
    public function clearListCache()
    {
        $pattern = 'recycle_excel_list:' . $this->site_id . ':*';
        
        // 记录开始清除缓存的日志
        \think\facade\Log::info("开始清除缓存 - Site ID: {$this->site_id}, Pattern: {$pattern}");
        
        try {
            // 尝试获取所有匹配的key
            $keys = Cache::handler()->keys($pattern);
            \think\facade\Log::info("找到缓存keys数量: " . count($keys), ['keys' => $keys]);
            
            if (!empty($keys)) {
                $deleted_count = 0;
                foreach ($keys as $key) {
                    $deleted = Cache::delete($key);
                    if ($deleted) {
                        $deleted_count++;
                        \think\facade\Log::info("成功删除缓存key: {$key}");
                    } else {
                        \think\facade\Log::warning("删除缓存key失败: {$key}");
                    }
                }
                \think\facade\Log::info("缓存清除完成 - 删除了 {$deleted_count} 个缓存");
            } else {
                \think\facade\Log::info("没有找到匹配的缓存key");
            }
            
        } catch (\Exception $e) {
            \think\facade\Log::error("缓存清除异常: " . $e->getMessage());
            
            // 备用方案：尝试清除所有相关缓存
            try {
                // 尝试使用不同的模式匹配
                $alternative_patterns = [
                    'recycle_excel_list:*',
                    '*recycle_excel_list*'
                ];
                
                foreach ($alternative_patterns as $alt_pattern) {
                    \think\facade\Log::info("尝试备用模式: {$alt_pattern}");
                    $alt_keys = Cache::handler()->keys($alt_pattern);
                    \think\facade\Log::info("备用模式找到keys: " . count($alt_keys), ['keys' => $alt_keys]);
                    
                    if (!empty($alt_keys)) {
                        foreach ($alt_keys as $key) {
                            // 确保只删除当前站点的缓存
                            if (strpos($key, $this->site_id) !== false) {
                                $deleted = Cache::delete($key);
                                \think\facade\Log::info("备用模式删除key: {$key}, 结果: " . ($deleted ? '成功' : '失败'));
                            }
                        }
                    }
                }
                
                // 尝试手动构造可能的缓存key并删除
                $this->manualClearCache();
                
            } catch (\Exception $e2) {
                \think\facade\Log::error("备用缓存清除也失败: " . $e2->getMessage());
            }
        }
    }
    
    /**
     * 手动清除缓存（当模式匹配失败时）
     */
    private function manualClearCache()
    {
        \think\facade\Log::info("开始手动清除缓存");
        
        // 常见的查询条件组合
        $common_conditions = [
            [],
            ['brand_id' => ''],
            ['device_type' => ''],
            ['keyword' => ''],
            ['create_at' => date('Y-m-d')],
            ['brand_id' => '', 'device_type' => ''],
            ['brand_id' => '', 'keyword' => ''],
        ];
        
        foreach ($common_conditions as $where) {
            for ($page = 1; $page <= 5; $page++) {
                for ($limit = 10; $limit <= 50; $limit += 10) {
                    $price_date = date('Y-m-d');
                    
                    $cacheParams = [
                        'site_id' => $this->site_id,
                        'where' => $where,
                        'page' => $page,
                        'limit' => $limit,
                        'price_date' => $price_date
                    ];
                    
                    $cache_key = 'recycle_excel_list:' . $this->site_id . ':' . md5(json_encode($cacheParams));
                    
                    if (Cache::has($cache_key)) {
                        $deleted = Cache::delete($cache_key);
                        \think\facade\Log::info("手动删除缓存key: {$cache_key}, 结果: " . ($deleted ? '成功' : '失败'));
                    }
                }
            }
        }
    }

    /**
     * 触发数据变更事件
     */
    protected function triggerDataChange()
    {
        Event::trigger('recycle_excel_data_change');
    }

    /**
     * 重写父类方法，添加缓存清理
     */
    protected function afterWrite()
    {
        parent::afterWrite();
        $this->triggerDataChange();
    }

    /**
     * 重写父类方法，添加缓存清理
     */
    protected function afterDelete()
    {
        parent::afterDelete();
        $this->triggerDataChange();
    }

    /**
     * 导出Excel数据
     * @param array $where
     * @return string
     */
    public function exportToExcel($where = [])
    {
        // 获取数据
        $field = 'id,site_id,brand_id,model_name,network_model,capacity,device_type,status,create_at,update_at';
        $where['site_id'] = $this->site_id;

        $list = $this->model
            ->where([['site_id', '=', $this->site_id]])
            ->with([
                'brand' => function($query) {
                    $query->field('id,brand_name,brand_code');
                },
                'currentPrice' => function($query) {
                    $query->field('id,device_model_id,price_data,price_date,is_current');
                }
            ])
            ->withSearch(['site_id', 'brand_id', 'device_type', 'keyword'], $where)
            ->field($field)
            ->order('id asc')
            ->select()
            ->toArray();

        // 处理价格数据
        foreach ($list as &$item) {
            if (isset($item['current_price']['price_data'])) {
                $item['price_detail'] = json_decode($item['current_price']['price_data'], true) ?: [];
            } else {
                $item['price_detail'] = [];
            }
        }

        // 使用PhpSpreadsheet创建Excel
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        // 设置默认字体，mpdf 内置 DejaVu Sans 支持中日韩
        $spreadsheet->getDefaultStyle()->getFont()->setName('DejaVu Sans')->setSize(10);
        $sheet = $spreadsheet->getActiveSheet();
        
        // 设置表头
        $headers = ['型号', '网络型号', '容量', '品牌', '高保充新', '充新', '靓机', '小花', '大花', '外爆', '内爆'];
        
        foreach ($headers as $index => $header) {
            $col = chr(65 + $index);
            $sheet->setCellValue($col . '1', $header);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
        }

        // 填充数据
        $row = 2;
        foreach ($list as $item) {
            $sheet->setCellValue('A' . $row, $item['model_name']);
            $sheet->setCellValue('B' . $row, $item['network_model']);
            $sheet->setCellValue('C' . $row, $item['capacity']);
            $sheet->setCellValue('D' . $row, $item['brand']['brand_name'] ?? '');
            $sheet->setCellValue('E' . $row, $item['price_detail']['高保充新'] ?? '');
            $sheet->setCellValue('F' . $row, $item['price_detail']['充新'] ?? '');
            $sheet->setCellValue('G' . $row, $item['price_detail']['靓机'] ?? '');
            $sheet->setCellValue('H' . $row, $item['price_detail']['小花'] ?? '');
            $sheet->setCellValue('I' . $row, $item['price_detail']['大花'] ?? '');
            $sheet->setCellValue('J' . $row, $item['price_detail']['外爆'] ?? '');
            $sheet->setCellValue('K' . $row, $item['price_detail']['内爆'] ?? '');
            $row++;
        }

        // 设置列宽
        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // 保存文件
        $fileName = 'export_' . date('YmdHis') . '.xlsx';
        $filePath = public_path() . 'upload/excel/export/' . $fileName;
        
        // 确保目录存在
        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($filePath);

        return $filePath;
    }

    
   
}