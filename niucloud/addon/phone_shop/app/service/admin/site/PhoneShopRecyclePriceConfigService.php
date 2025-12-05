<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\admin\site;

use addon\phone_shop\app\model\site\PhoneShopRecyclePriceConfig;
use addon\phone_shop\app\model\site\PhoneShopRecyclerPriceRange;
use core\base\BaseAdminService;
use think\db\exception\DbException;

/**
 * 回收商价格配置服务
 */
class PhoneShopRecyclePriceConfigService extends BaseAdminService
{
    /**
     * @var PhoneShopRecyclePriceConfig
     */
    protected $model;

    /**
     * @var PhoneShopRecyclerPriceRange
     */
    protected $rangeModel;

    public function __construct()
    {
        parent::__construct();
        $this->model = new PhoneShopRecyclePriceConfig();
        $this->rangeModel = new PhoneShopRecyclerPriceRange();
    }

    /**
     * 添加价格配置
     * @param array $data
     * @return int
     * @throws DbException
     */
    public function add(array $data)
    {
       
        // 开启事务
        $this->model->startTrans();
        try {
            // 添加基本配置
            $config = [
                'site_id' => $this->site_id,
                'recycler_id' => $data['recycler_id'],
                'price_type' => $data['price_type'],
                'member_markup' => $data['member_markup'] ?? 0,
                'non_member_markup' => $data['non_member_markup'] ?? 0,
            ];
            // 将 配置信息 添加到数据库中 通过 create 
            $config_id = $this->model->create($config);


            // 如果是区间加价，添加价格区间
            if ($data['price_type'] == 2 && !empty($data['price_ranges'])) {
                foreach ($data['price_ranges'] as $range) {
                    $range_data = [
                        'config_id' => $config_id,
                        'min_price' => $range['min_price'],
                        'max_price' => $range['max_price'],
                        'member_markup' => $range['member_markup']
                    ];
                    $this->rangeModel->create($range_data);
                }
            }

            $this->model->commit();
            return true;
        } catch (\Exception $e) {
            $this->model->rollback();
            throw new DbException($e->getMessage());
        }
    }

    /**
     * 获取价格配置信息
     * @param int $recycler_id
     * @return array
     */
    public function getInfo(int $recycler_id)
    {
        
        $config = $this->model->where([
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty()->toArray();
           
        if (!$config) {
            return [];
        }

      

        // 如果是区间加价，获取价格区间
        if ($config['price_type'] == 2) {
            $ranges = $this->rangeModel->where('config_id', $config['id'])->select()->toArray();
            $config['price_ranges'] = $ranges;
        }
        // 返回当前站点的配置即可
        
        return $config;
    }

    /**
     * 更新价格配置
     * @param int $id
     * @param array $data
     * @return bool
     * @throws DbException
     */
    public function update( array $data)
    {   
        
        // 开启事务
        $this->model->startTrans();
        try {
            // 更新基本配置
            $config = [
                'price_type' => $data['price_type'],
                'member_markup' => $data['member_markup'] ?? 0,
                'non_member_markup' => $data['non_member_markup'] ?? 0,
            ];
            $this->model->where([['id', '=', $data['id']],['site_id', '=', $this->site_id]])->update($config);

            // 如果是区间加价，更新价格区间
            if ($data['price_type'] == 2) {
                // 删除原有区间
                $this->rangeModel->where([['config_id', '=', $data['id']]])->delete();
                
                // 添加新区间
                if (!empty($data['price_ranges'])) {
                    foreach ($data['price_ranges'] as $range) {
                        $range_data = [
                            'config_id' => $data['id'],
                            'min_price' => $range['min_price'],
                            'max_price' => $range['max_price'],
                            'member_markup' => $range['member_markup']
                        ];
                        $this->rangeModel->create($range_data);
                    }
                }
            }

            $this->model->commit();
            return true;
        } catch (\Exception $e) {
            $this->model->rollback();
            throw new DbException($e->getMessage());
        }
    }

    /**
     * 删除价格配置
     * @param int $id
     * @return bool
     * @throws DbException
     */
    public function delete(int $id)
    {
        // 开启事务
        $this->model->startTrans();
        try {
            // 删除基本配置
            $this->model->where('id', $id)->delete();
            // 删除价格区间
            $this->rangeModel->where('config_id', $id)->delete();

            $this->model->commit();
            return true;
        } catch (\Exception $e) {
            $this->model->rollback();
            throw new DbException($e->getMessage());
        }
    }
    
    // 更新价格配置的信息
    public function price_update()
    {
        // 判断$data中的加价类型 如果为1 则更新基本加价 如果为2 则更新区间加价
        if ($data['price_type'] == 1) {
            $res = $this->model->where('site_id', $this->site_id)->update($data);
        } else {
            // 删除原有区间
            $this->rangeModel->where('config_id', $data['id'])->delete();
            // 添加新区间
            foreach ($data['price_ranges'] as $range) {
                $range_data = [
                    'config_id' => $data['id'],
                    'min_price' => $range['min_price'],
                    'max_price' => $range['max_price'],
                    'member_markup' => $range['member_markup']
                ];
                $this->rangeModel->add($range_data);
            }
            $res = $this->model->where('site_id', $this->site_id)->update($data);
        }
        return $res;
    }
    // 删除价格配置的信息
    public function price_delete($id)
    {
        // 获取加价的类型
        $price_type = $this->model->where('id', $id)->value('price_type');
        
        $res = $this->model->where('id', $id)->delete();
        return $res;
    }

    // 根据site_id 获取回收商价格配置
    public function getRecyclerPriceConfigBySiteId(int $site_id)
    {
      // 参考 getInfo 方法
      return $this->getInfo($site_id);
    }
}
