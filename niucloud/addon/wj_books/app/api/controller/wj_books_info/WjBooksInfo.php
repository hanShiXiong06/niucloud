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

namespace addon\wj_books\app\api\controller\wj_books_info;

use core\base\BaseApiController;
use addon\wj_books\app\service\api\wj_books_info\WjBooksInfoService;
use addon\wj_books\app\service\api\wj_books_scan_records\WjBooksScanRecordsService;
use addon\wj_books\app\service\api\wj_books_cart\WjBooksCartService;
use addon\wj_books\app\service\api\wj_books_config\WjBooksConfigService;
use addon\wj_books\app\service\api\wj_books_order\WjBooksOrderService;
use think\Response;

/**
 * 图书信息API控制器
 * Class WjBooksInfo
 * @package addon\wj_books\app\api\controller\wj_books_info
 */
class WjBooksInfo extends BaseApiController
{
    /**
     * 通过ISBN查询图书信息
     * @return Response
     */
    public function queryBookInfo()
    {
        $data = $this->request->params([
            ["isbn", ""],
            ["scan_type", 1], // 默认为扫码
        ]);
        
        // 验证ISBN参数
        if (empty($data['isbn'])) {
            return fail('ISBN不能为空');
        }
        
        // 确保scan_type是整数类型
        $data['scan_type'] = (int)$data['scan_type'];
        
        // 调用服务查询图书信息并记录扫描记录
        $result = (new WjBooksInfoService())->queryAndSaveBookInfo($data['isbn'], $data['scan_type']);
        return success($result);
    }
    
    /**
     * 获取系统配置
     * @return Response
     */
    public function getConfig()
    {
        $result = (new WjBooksConfigService())->getConfig();
        return success($result);
    }
    
    /**
     * 添加图书到回收车
     * @return Response
     */
    public function addToCart()
    {
        $data = $this->request->params([
            ["isbn", ""],
            ["quantity", 1],
        ]);
        
        // 验证ISBN参数
        if (empty($data['isbn'])) {
            return fail('ISBN不能为空');
        }
        
        // 添加到回收车
        $result = (new WjBooksCartService())->addToCart($data['isbn'], $data['quantity']);
        
        // 更新扫描记录状态
        if ($result['code'] == 0) {
            (new WjBooksScanRecordsService())->updateStatusByIsbn($data['isbn'], 1);
        }
        
        return success($result);
    }
    
    /**
     * 获取回收车列表
     * @return Response
     */
    public function getCartList()
    {
        $result = (new WjBooksCartService())->getCartList();
        return success($result);
    }
    
    /**
     * 从回收车中移除图书
     * @return Response
     */
    public function removeFromCart()
    {
        $data = $this->request->params([
            ["cart_id", 0],
        ]);
        
        if (empty($data['cart_id'])) {
            return fail('回收车ID不能为空');
        }
        
        $result = (new WjBooksCartService())->removeFromCart($data['cart_id']);
        return success($result);
    }
    
    /**
     * 提交回收
     * @return Response
     */
    public function submitRecycle()
    {
        $result = (new WjBooksCartService())->submitRecycle();
        return success($result);
    }
    
    /**
     * 更新回收书籍数量
     * @return Response
     */
    public function updateCartQuantity()
    {
        $data = $this->request->params([
            ["cart_id", 0],
            ["quantity", 1],
        ]);
        
        if (empty($data['cart_id'])) {
            return fail('回收车ID不能为空');
        }
        
        if ($data['quantity'] < 1) {
            return fail('数量不能小于1');
        }
        
        $result = (new WjBooksCartService())->updateCartQuantity($data['cart_id'], $data['quantity']);
        return success($result);
    }
    
    /**
     * 申请取回拒收书籍
     * @return Response
     */
    public function applyRetrieveBooks()
    {
        $data = $this->request->params([
            ["order_id", 0],
            ["book_ids", []],
            ["address_id", 0],
            ["remark", ""]
        ]);
        
        // 验证参数
        if (empty($data['order_id'])) {
            return fail('订单ID不能为空');
        }
        
        if (empty($data['book_ids'])) {
            return fail('请选择要取回的书籍');
        }
        
        if (empty($data['address_id'])) {
            return fail('收货地址不能为空');
        }
        
        // 调用服务申请取回
        $result = (new WjBooksOrderService())->applyRetrieve($data);
        
        if ($result['code'] == 0) {
            return success($result['data'], $result['msg']);
        } else {
            return fail($result['msg']);
        }
    }
} 