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

namespace addon\wj_books\app\service\api\wj_books_cart;

use addon\wj_books\app\model\wj_books_cart\WjBooksCart;
use addon\wj_books\app\service\api\wj_books_info\WjBooksInfoService;
use addon\wj_books\app\service\api\wj_books_scan_records\WjBooksScanRecordsService;
use core\base\BaseApiService;

/**
 * 回收车API服务层
 * Class WjBooksCartService
 * @package addon\wj_books\app\service\api\wj_books_cart
 */
class WjBooksCartService extends BaseApiService
{
    protected $model;
    
    public function __construct()
    {
        parent::__construct();
        $this->model = new WjBooksCart();
    }
    
    /**
     * 添加图书到回收车
     * @param string $isbn
     * @param int $quantity
     * @return array
     */
    public function addToCart(string $isbn, int $quantity = 1)
    {
        // 查询图书信息
        $bookService = new WjBooksInfoService();
        $bookInfo = $bookService->getBookInfoByIsbn($isbn);
        
        if (empty($bookInfo)) {
            return ['code' => -1, 'msg' => '图书信息不存在'];
        }
        
        // 检查图书是否可回收
       // if ($bookInfo['can_recycle'] != 1) {
       //     return ['code' => -1, 'msg' => '该图书不可回收'];
       // }
        
        // 检查回收车中是否已存在该图书
        $cartItem = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
            ['isbn', '=', $isbn]
        ])->find();
        
        try {
            if ($cartItem) {
                // 已存在，更新数量
                $cartItem->quantity = $cartItem->quantity + $quantity;
                $cartItem->update_time = date('Y-m-d H:i:s');
                $cartItem->save();
                $cart_id = $cartItem->id;
            } else {
                // 不存在，添加新记录
                $data = [
                    'site_id' => $this->site_id,
                    'member_id' => $this->member_id,
                    'isbn' => $isbn,
                    'book_id' => $bookInfo['id'],
                    'quantity' => $quantity,
                    'add_time' => date('Y-m-d H:i:s'),
                    'create_time' => date('Y-m-d H:i:s'),
                    'update_time' => date('Y-m-d H:i:s')
                ];
                $res = $this->model->create($data);
                $cart_id = $res->id;
            }
            
            // 更新扫描记录状态
            (new WjBooksScanRecordsService())->updateStatusByIsbn($isbn, 1);
            
            return [
                'code' => 0,
                'msg' => '添加到回收车成功',
                'cart_id' => $cart_id
            ];
        } catch (\Exception $e) {
            return [
                'code' => -1,
                'msg' => '添加到回收车失败：' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取回收车列表
     * @return array
     */
    public function getCartList()
    {
        $field = 'c.id,c.site_id,c.member_id,c.isbn,c.book_id,c.quantity,c.add_time,c.create_time,c.update_time,
                 b.title,b.author,b.publisher,b.img,b.small_img,b.recycle_price,b.can_recycle';
        
        $list = $this->model->alias('c')
            ->join('wj_books_info b', 'c.book_id = b.id')
            ->field($field)
            ->where([
                ['c.site_id', '=', $this->site_id],
                ['c.member_id', '=', $this->member_id]
            ])
            ->select()
            ->toArray();
        
        // 计算总价
        $total_price = 0;
        foreach ($list as &$item) {
            $item['subtotal'] = $item['recycle_price'] * $item['quantity'];
            $total_price += $item['subtotal'];
        }
        
        return [
            'list' => $list,
            'total_price' => $total_price,
            'total_count' => count($list)
        ];
    }
    
    /**
     * 从回收车中移除图书
     * @param int $cart_id
     * @param int $scan_id 扫描记录ID，可选
     * @param int $status 更新的状态，默认为0(仅扫码)
     * @return array
     */
    public function removeFromCart(int $cart_id, int $scan_id = 0, int $status = 0)
    {
        $cartItem = $this->model->where([
            ['id', '=', $cart_id],
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id]
        ])->find();
        
        if (!$cartItem) {
            return ['code' => -1, 'msg' => '回收车项不存在'];
        }
        
        try {
            $isbn = $cartItem->isbn;
            $cartItem->delete();
            
            // 更新扫描记录状态为仅扫码(0)
            if ($scan_id > 0) {
                // 如果提供了扫描记录ID，直接更新该记录
                (new WjBooksScanRecordsService())->updateStatusById($scan_id, $status);
            } else {
                // 否则根据ISBN更新
                (new WjBooksScanRecordsService())->updateStatusByIsbn($isbn, $status);
            }
            
            return [
                'code' => 0,
                'msg' => '从回收车移除成功'
            ];
        } catch (\Exception $e) {
            return [
                'code' => -1,
                'msg' => '从回收车移除失败：' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 提交回收
     * @return array
     */
    public function submitRecycle()
    {
        // 获取回收车列表
        $cartItems = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id]
        ])->select()->toArray();
        
        if (empty($cartItems)) {
            return ['code' => -1, 'msg' => '回收车为空'];
        }
        
        // 收集所有ISBN
        $isbns = [];
        foreach ($cartItems as $item) {
            $isbns[] = $item['isbn'];
        }
        
        try {
            // 更新扫描记录状态为已提交回收
            (new WjBooksScanRecordsService())->batchUpdateStatusByIsbns($isbns, 2);
            
            // 更新图书回收次数
            $bookService = new WjBooksInfoService();
            foreach ($cartItems as $item) {
                $bookInfo = $bookService->getBookInfoByIsbn($item['isbn']);
                if (!empty($bookInfo)) {
                    $bookModel = new \addon\wj_books\app\model\wj_books_info\WjBooksInfo();
                    $bookModel->where('id', $bookInfo['id'])->inc('recycle_count', $item['quantity'])->update();
                }
            }
            
            // 清空回收车
            $this->model->where([
                ['site_id', '=', $this->site_id],
                ['member_id', '=', $this->member_id]
            ])->delete();
            
            return [
                'code' => 0,
                'msg' => '提交回收成功',
                'data' => [
                    'count' => count($cartItems)
                ]
            ];
        } catch (\Exception $e) {
            return [
                'code' => -1,
                'msg' => '提交回收失败：' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 更新回收书籍数量
     * @param int $cart_id 回收车ID
     * @param int $quantity 新数量
     * @return array
     */
    public function updateCartQuantity(int $cart_id, int $quantity)
    {
        // 验证参数
        if ($quantity < 1) {
            return ['code' => -1, 'msg' => '数量不能小于1'];
        }
        
        // 查询回收车项
        $cartItem = $this->model->where([
            ['id', '=', $cart_id],
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id]
        ])->find();
        
        if (!$cartItem) {
            return ['code' => -1, 'msg' => '回收车项不存在'];
        }
        
        try {
            // 更新数量
            $cartItem->quantity = $quantity;
            $cartItem->update_time = date('Y-m-d H:i:s');
            $cartItem->save();
            
            // 重新计算价格
            $bookService = new WjBooksInfoService();
            $bookInfo = $bookService->getBookInfoByIsbn($cartItem->isbn);
            $subtotal = 0;
            
            if (!empty($bookInfo)) {
                $subtotal = $bookInfo['recycle_price'] * $quantity;
            }
            
            return [
                'code' => 0,
                'msg' => '更新数量成功',
                'data' => [
                    'cart_id' => $cart_id,
                    'quantity' => $quantity,
                    'subtotal' => $subtotal
                ]
            ];
        } catch (\Exception $e) {
            return [
                'code' => -1,
                'msg' => '更新数量失败：' . $e->getMessage()
            ];
        }
    }
} 