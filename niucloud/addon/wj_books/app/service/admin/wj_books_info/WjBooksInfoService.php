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

namespace addon\wj_books\app\service\admin\wj_books_info;

use addon\wj_books\app\model\wj_books_info\WjBooksInfo;
use addon\wj_books\app\model\wj_books_config\WjBooksConfig;
use core\base\BaseAdminService;
use core\lib\Log;


/**
 * 图书信息服务层
 * Class WjBooksInfoService
 * @package addon\wj_books\app\service\admin\wj_books_info
 */
class WjBooksInfoService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new WjBooksInfo();
    }

    /**
     * 获取图书信息列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,isbn,isbn10,title,author,publisher,pub_date,price,binding,page,edition,img,small_img,gist,recycle_price,can_recycle,recycle_count,api_json,api_query_time,create_time,update_time';
        $order = '';

        $search_model = $this->model->where([ [ 'site_id' ,"=", $this->site_id ] ])->withSearch(["isbn","isbn10","title","can_recycle"], $where)->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取图书信息信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,site_id,isbn,isbn10,title,author,publisher,pub_date,price,binding,page,edition,img,small_img,gist,recycle_price,can_recycle,recycle_count,api_json,api_query_time,create_time,update_time';

        $info = $this->model->field($field)->where([['id', "=", $id]])->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 添加图书信息
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $res = $this->model->create($data);
        return $res->id;
    }

    /**
     * 图书信息编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {

        $this->model->where([['id', '=', $id],['site_id', '=', $this->site_id]])->update($data);
        return true;
    }

    /**
     * 删除图书信息
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where([['id', '=', $id],['site_id', '=', $this->site_id]])->find();
        $res = $model->delete();
        return $res;
    }
    
    /**
     * 通过ISBN查询图书信息
     * @param string $isbn
     * @return array
     */
    public function queryBookInfoByIsbn(string $isbn)
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
        
        // 如果已有记录，返回已有记录信息
        if (!empty($book)) {
            return ['is_exist' => 1, 'book_info' => $book];
        }
        
        // 从配置表中获取API配置
        $configModel = new WjBooksConfig();
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
       // Log::write("请求{$apiProviderName}图书API，ISBN: {$isbn}, URL: {$apiUrl}", 'info');
        
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
        //Log::write("图书API({$apiProviderName})响应: " . $response, 'info');
        
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
            'api_json' => $api_json,
            'api_query_time' => date('Y-m-d H:i:s'),
        ];
        
        return [
            'is_exist' => 0,
            'book_info' => $book_info,
            'api_result' => $responseResult,
            'api_provider' => $apiProviderName
        ];
    }
}
