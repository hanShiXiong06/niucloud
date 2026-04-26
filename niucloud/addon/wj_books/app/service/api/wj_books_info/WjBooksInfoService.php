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

namespace addon\wj_books\app\service\api\wj_books_info;

use addon\wj_books\app\model\wj_books_info\WjBooksInfo;
use addon\wj_books\app\service\api\wj_books_scan_records\WjBooksScanRecordsService;
use core\base\BaseApiService;
use think\facade\Log;

/**
 * 图书信息API服务层
 * Class WjBooksInfoService
 * @package addon\wj_books\app\service\api\wj_books_info
 */
class WjBooksInfoService extends BaseApiService
{
    protected $model;
    
    public function __construct()
    {
        parent::__construct();
        $this->model = new WjBooksInfo();
    }
    
    /**
     * 查询并保存图书信息
     * @param string $isbn
     * @param int $scan_type
     * @return array
     */
    public function queryAndSaveBookInfo(string $isbn, int $scan_type = 1)
    {
        // 判断是10位还是13位ISBN
        $isIsbn10 = strlen(trim($isbn)) == 10;
        
        // 查询数据库中是否已存在相同ISBN的图书记录
        $where = [['site_id', '=', $this->site_id]];
        
        // 根据输入的ISBN位数决定查询条件
        if ($isIsbn10) {
            $where[] = ['isbn10', '=', $isbn];
        } else {
            $where[] = ['isbn', '=', $isbn];
        }
        
        $book = $this->model->where($where)->findOrEmpty()->toArray();
        
        // 如果没找到，尝试用另一种ISBN格式查询（可能数据库中只存了一种格式）
        if (empty($book)) {
            $alternateWhere = [['site_id', '=', $this->site_id]];
            if ($isIsbn10) {
                $alternateWhere[] = ['isbn', '=', $isbn];
            } else {
                $alternateWhere[] = ['isbn10', '=', $isbn];
            }
            $book = $this->model->where($alternateWhere)->findOrEmpty()->toArray();
        }
        
        // 如果已有记录，记录扫描记录并返回
        if (!empty($book)) {
            // 添加扫描记录
            (new WjBooksScanRecordsService())->addScanRecord($isbn, $scan_type);
            
            return [
                'code' => 0,
                'msg' => '查询成功',
                'is_exist' => 1,
                'book_info' => $book
            ];
        }
        
        // 从配置表中获取API配置
        $configModel = new \addon\wj_books\app\model\wj_books_config\WjBooksConfig();
        $config = $configModel->where([['site_id', '=', $this->site_id]])->find();
        
        // 检查是否启用图书API(0:不启用只内部查询,1:调用外部API)
        $apiEnabled = !empty($config['book_api_enabled']) && $config['book_api_enabled'] == 1;
        
        // 如果未启用API或未配置密钥，则返回未找到图书的提示
        if (!$apiEnabled) {
            return ['code' => -1, 'msg' => '系统未启用外部图书查询功能，该图书尚未录入系统'];
        }
        
        // 检查API密钥是否已配置
        if (empty($config['book_api_key'])) {
            return ['code' => -1, 'msg' => '未配置图书API密钥，请先在系统设置中配置'];
        }
        
        // 根据API提供商选择不同的API接口
        $apiProvider = isset($config['book_api_provider']) ? $config['book_api_provider'] : '0';
        
        if ($apiProvider == '0') {
            // 调用聚合数据API获取图书信息
            $apiUrl = "http://apis.juhe.cn/isbn/query";
            $apiProviderName = "聚合数据";
            $paramKey = 'key';
        } else {
            // 调用LikeAPI获取图书信息
            $apiUrl = "https://likeapi.cn/api/isbn/query";
            $apiProviderName = "LikeAPI";
            $paramKey = 'ckey';
        }
        
        $key = $config['book_api_key'];
        
        $requestParams = [
            $paramKey => $key,
            'isbn' => $isbn,
        ];
        $requestParamsStr = http_build_query($requestParams);
        
        // 记录API请求信息
        Log::info("请求{$apiProviderName}图书API，ISBN: {$isbn}, URL: {$apiUrl}");
        
        // 发起网络请求
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($curl, CURLOPT_URL, $apiUrl . '?' . $requestParamsStr);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded"]);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($curl);
        curl_close($curl);
        
        // 记录API响应
        Log::info("图书API({$apiProviderName})响应: " . $response);
        
        // 解析API返回结果
        $responseResult = json_decode($response, true);
        
        // 判断API调用是否成功
        if (empty($responseResult)) {
            return ['code' => -1, 'msg' => '请求图书信息失败，响应为空'];
        }
        
        // 根据不同的API提供商处理响应
        if ($apiProvider == '0') {
            // 聚合数据API响应处理
            if (isset($responseResult['error_code']) && $responseResult['error_code'] != 0) {
                $error_msg = isset($responseResult['reason']) ? $responseResult['reason'] : '请求图书信息失败';
                return ['code' => -1, 'msg' => $error_msg];
            }
            
            // 获取图书信息
            $book_data = $responseResult['result']['data'];
        } else {
            // LikeAPI响应处理
            if (isset($responseResult['code']) && $responseResult['code'] != 200) {
                $error_msg = isset($responseResult['msg']) ? $responseResult['msg'] : '请求图书信息失败';
                return ['code' => -1, 'msg' => $error_msg];
            }
            
            // 获取图书信息 (假设LikeAPI的数据在data字段中)
            $book_data = $responseResult['data'];
        }
        
        // 如果没有获取到图书数据
        if (empty($book_data)) {
            return ['code' => -1, 'msg' => "{$apiProviderName}未找到该图书信息"];
        }
        
        $api_json = json_encode($responseResult, JSON_UNESCAPED_UNICODE);
        
        // 组装数据 (兼容两种API可能的不同字段命名)
        $book_info = [
            'site_id' => $this->site_id,
            'isbn' => $book_data['isbn'] ?? '',
            'isbn10' => $book_data['isbn10'] ?? '',
            'title' => $book_data['title'] ?? '',
            'author' => $book_data['author'] ?? '',
            'publisher' => $book_data['publisher'] ?? '',
            'pub_date' => $book_data['pubDate'] ?? ($book_data['pub_date'] ?? ''),
            'price' => floatval($book_data['price'] ?? 0),
            'binding' => $book_data['binding'] ?? '',
            'page' => intval($book_data['page'] ?? 0),
            'edition' => $book_data['edition'] ?? '',
            'img' => $book_data['img'] ?? ($book_data['image'] ?? ''),
            'small_img' => $book_data['smallImg'] ?? ($book_data['small_image'] ?? ''),
            'gist' => $book_data['gist'] ?? ($book_data['summary'] ?? ''),
            'recycle_price' => $this->calculateRecyclePrice($book_data, $config ? $config->toArray() : []),
            'can_recycle' => 1, // 默认可回收
            'recycle_count' => 0,
            'api_json' => $api_json,
            'api_query_time' => date('Y-m-d H:i:s'),
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
        ];
        
        // 保存到数据库
        try {
            $book_id = $this->model->create($book_info)->id;
            $book_info['id'] = $book_id;
            
            // 添加扫描记录
            (new WjBooksScanRecordsService())->addScanRecord($isbn, $scan_type);
            
            return [
                'code' => 0,
                'msg' => '查询成功',
                'is_exist' => 0,
                'book_info' => $book_info,
                'api_result' => $responseResult,
                'api_provider' => $apiProviderName
            ];
        } catch (\Exception $e) {
            return [
                'code' => -1,
                'msg' => '保存图书信息失败：' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 根据ID获取图书信息
     * @param int $id
     * @return array
     */
    public function getBookInfoById(int $id)
    {
        $field = 'id,site_id,isbn,isbn10,title,author,publisher,pub_date,price,binding,page,edition,img,small_img,gist,recycle_price,can_recycle,recycle_count,api_json,api_query_time,create_time,update_time';
        $info = $this->model->field($field)->where([['id', "=", $id], ['site_id', '=', $this->site_id]])->findOrEmpty()->toArray();
        return $info;
    }
    
    /**
     * 根据ISBN获取图书信息
     * @param string $isbn
     * @return array
     */
    public function getBookInfoByIsbn(string $isbn)
    {
        $isIsbn10 = strlen(trim($isbn)) == 10;
        $where = [['site_id', '=', $this->site_id]];
        
        if ($isIsbn10) {
            $where[] = ['isbn10', '=', $isbn];
        } else {
            $where[] = ['isbn', '=', $isbn];
        }
        
        $field = 'id,site_id,isbn,isbn10,title,author,publisher,pub_date,price,binding,page,edition,img,small_img,gist,recycle_price,can_recycle,recycle_count,api_json,api_query_time,create_time,update_time';
        $info = $this->model->field($field)->where($where)->findOrEmpty()->toArray();
        
        if (empty($info)) {
            $alternateWhere = [['site_id', '=', $this->site_id]];
            if ($isIsbn10) {
                $alternateWhere[] = ['isbn', '=', $isbn];
            } else {
                $alternateWhere[] = ['isbn10', '=', $isbn];
            }
            $info = $this->model->field($field)->where($alternateWhere)->findOrEmpty()->toArray();
        }
        
        return $info;
    }
    
    /**
     * 计算图书回收价格
     * @param array $book_data 图书数据
     * @return float 回收价格
     */
    private function calculateRecyclePrice(array $book_data, array $config = [])
    {
        // 获取图书原价
        $originalPrice = floatval($book_data['price'] ?? 0);
        
        // 如果没有价格信息或价格为0，给一个默认最低回收价
        if ($originalPrice <= 0) {
            return 0.5; // 默认最低回收价0.5元
        }
        
        // 基础回收比例 - 降低基础比例以符合市场水平
        $baseRatio = 0.08; // 降低到8%作为基础比例
        
        // 根据出版日期调整比例
        $pubDate = $book_data['pubDate'] ?? ($book_data['pub_date'] ?? '');
        $pubYear = 0;
        
        if (!empty($pubDate)) {
            // 尝试从出版日期中提取年份
            if (preg_match('/(\d{4})/', $pubDate, $matches)) {
                $pubYear = intval($matches[1]);
            }
        }
        
        $currentYear = intval(date('Y'));
        $ageRatio = 0;
        
        if ($pubYear > 0) {
            $bookAge = $currentYear - $pubYear;
            
            // 新书加价 (出版1年内)
            if ($bookAge <= 1) {
                $ageRatio = 0.04; // 增加4%的回收比例
            } 
            // 较新的书 (1-2年)
            else if ($bookAge <= 2) {
                $ageRatio = 0.02; // 增加2%的回收比例
            }
            // 中等年龄的书 (2-4年)
            else if ($bookAge <= 4) {
                $ageRatio = 0; // 不调整
            }
            // 较旧的书 (4-7年)
            else if ($bookAge <= 7) {
                $ageRatio = -0.02; // 减少2%的回收比例
            }
            // 老书 (7年以上)
            else {
                $ageRatio = -0.04; // 减少4%的回收比例
            }
        }
        
        // 根据装帧调整比例
        $bindingRatio = 0;
        $binding = $book_data['binding'] ?? '';
        
        if (strpos($binding, '精装') !== false || strpos($binding, 'hardcover') !== false) {
            $bindingRatio = 0.02; // 精装书增加2%的回收比例
        } else if (strpos($binding, '平装') !== false || strpos($binding, 'paperback') !== false) {
            $bindingRatio = 0; // 平装书不调整
        }
        
        // 根据页数调整比例
        $pageRatio = 0;
        $pages = intval($book_data['page'] ?? 0);
        
        if ($pages > 500) {
            $pageRatio = 0.01; // 厚书增加1%的回收比例
        } else if ($pages < 100) {
            $pageRatio = -0.01; // 薄书减少1%的回收比例
        }
        
        // 计算最终回收比例
        $finalRatio = $baseRatio + $ageRatio + $bindingRatio + $pageRatio;
        
        // 限制比例在合理范围内 (3%-15%)
        $finalRatio = max(0.03, min(0.15, $finalRatio));
        
        // 计算回收价格
        $recyclePrice = $originalPrice * $finalRatio;
        
        // 设置最低和最高回收价
        $minPrice = 0.1;  // 最低0.1元
        $maxPrice = 20.0; // 最高20元
        
        // 为高价书设置特殊上限
        if ($originalPrice > 200) {
            $maxPrice = 50.0; // 原价超过200元的书，最高回收价50元
        }
        
        // 在最低和最高价格之间取值
        $recyclePrice = max($minPrice, min($maxPrice, $recyclePrice));
        
        // 根据价格区间进行微调，使价格分布更贴合市场
        if ($originalPrice <= 20) {
            // 低价书籍，价格区间通常在0.5-1.5元
            $recyclePrice = min($recyclePrice, 1.5);
        } else if ($originalPrice <= 50) {
            // 中低价书籍，价格区间通常在1-3元
            $recyclePrice = min($recyclePrice, 3.0);
        } else if ($originalPrice <= 100) {
            // 中价书籍，价格区间通常在2-6元
            $recyclePrice = min($recyclePrice, 6.0);
        } else if ($originalPrice <= 150) {
            // 中高价书籍，价格区间通常在3-8元
            $recyclePrice = min($recyclePrice, 8.0);
        } else {
            // 高价书籍，价格区间通常在4-12元
            $recyclePrice = min($recyclePrice, 12.0);
        }
        
        $priceAdjustRate = floatval($config['price_adjust_rate'] ?? 0);
        if ($priceAdjustRate != 0) {
            $recyclePrice = $recyclePrice * (1 + $priceAdjustRate / 100);
        }

        // 调整后兜底最小值，避免出现负数或0
        $recyclePrice = max($minPrice, $recyclePrice);

        // 取整到0.1元，例如3.56元变为3.6元
        $recyclePrice = round($recyclePrice * 10) / 10;
        
        // 记录价格计算过程
        Log::info("图书回收价格计算 - 书名: {$book_data['title']}, 原价: {$originalPrice}, 出版年: {$pubYear}, 装帧: {$binding}, 页数: {$pages}, 最终比例: {$finalRatio}, 调整比例: {$priceAdjustRate}, 回收价: {$recyclePrice}");
        
        return $recyclePrice;
    }
}
