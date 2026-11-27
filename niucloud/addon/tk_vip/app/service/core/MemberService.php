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

namespace addon\tk_vip\app\service\core;


use addon\tk_vip\app\model\fenxiao\FenxiaoMember;
use addon\tk_vip\app\model\vip\Vip;
use app\model\member\MemberLevel;
use app\service\core\member\CoreMemberService;
use app\service\core\site\CoreSiteService;
use core\base\BaseApiService;
use think\Exception;
use think\facade\Db;
use think\facade\Log;

/**
 * 会员等级管理公共服务层
 */
class MemberService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
    }

    public function bindFenxiao($data)
    {

    }

    /**
     * @Notes:进行会员VIP修改
     * @Interface changeVip
     * @param $site_id
     * @param $member_id
     * @param $level_id
     * @param $day
     * @author: TK
     * @Time: 2024/10/23   下午10:10
     */
    public function changeVip($site_id, $member_id, $level_id, $day, $type = 'pay')
    {
        Db::startTrans();
        try {
            $vipModel = new Vip();
            $vip = $vipModel->where([['site_id', '=', $site_id], ['member_id', '=', $member_id]])->findOrEmpty();
            if ($vip->isEmpty()) {
                $over_time = time() + $day * 60 * 60 * 24;
                if ($day == 0) {
                    $over_time = 0;
                }
                $vipModel->create([
                    'site_id' => $site_id,
                    'member_id' => $member_id,
                    'level_id' => $level_id,
                    'over_time' => $over_time,
                    'create_time' => time(),
                    'update_time' => time()
                ]);

            } else {
                if ($vip['level_id'] == $level_id) {
                    $old_time = $vip['over_time'] > 0 ? strtotime($vip['over_time']) : 0;
                    $over_time = $day == 0 ? 0 : ($old_time < time() ? time() + $day * 60 * 60 * 24 : $old_time + $day * 60 * 60 * 24);

                } else {
                    $over_time = $day == 0 ? 0 : time() + $day * 60 * 60 * 24;
                }
                $vipModel->where([
                    ['site_id', '=', $site_id],
                    ['member_id', '=', $member_id]
                ])->update([
                    'level_id' => $level_id,
                    'over_time' => $over_time,
                    'update_time' => time()
                ]);
            }
            (new CoreMemberService())->modify($site_id, $member_id, 'member_level', $level_id);
            if ($type == 'pay') {
                $body = '用户购买会员';
            }
            if ($type == 'sign') {
                $body = '签到赠送会员';
            }
            if ($type == 'admin') {
                $body = '后台变动会员';
            }
            if ($type == 'gift') {
                $body = '新用户注册赠送';
            } else {
                $body = '会员变动';
            }
            event('VipLog', [
                'site_id' => $site_id,
                'member_id' => $member_id,
                'level_id' => $level_id,
                'over_time' => $over_time,
                'type' => $type,
                'body' => $body
            ]);
            Db::commit();
        } catch (Exception $e) {
            Log::write('=======   VIP更改会员等级失败   =======' . date('Y-m-d H:i:s'));
            Log::write($e->getMessage());
            Db::rollback();
            return false;
        }
    }

    public function changeVipV1($orderInfo, $type = 'pay')
    {
        Db::startTrans();
        try {
            $site_id = $orderInfo['site_id'];
            $member_id = $orderInfo['member_id'];
            $level_id = $orderInfo['level_id'];
            $day = $orderInfo['day'];
            $over_type = $orderInfo['type'];
            $vipModel = new Vip();
            $vip = $vipModel->where([['site_id', '=', $site_id], ['member_id', '=', $member_id]])->findOrEmpty();
            if ($vip->isEmpty()) {
                if ($over_type == 'fixed') {
                    $over_time = strtotime($orderInfo['over_time']);
                } else {
                    $over_time = time() + $day * 60 * 60 * 24;
                    if ($day == 0) {
                        $over_time = 0;
                    }
                }
                $vipModel->create([
                    'site_id' => $site_id,
                    'member_id' => $member_id,
                    'level_id' => $level_id,
                    'over_time' => $over_time,
                    'create_time' => time(),
                    'update_time' => time()
                ]);
            } else {
                if ($over_type == 'fixed') {
                    $over_time = strtotime($orderInfo['over_time']);
                } else {
                    if ($vip['level_id'] == $level_id) {
                        $old_time = $vip['over_time'] > 0 ? strtotime($vip['over_time']) : 0;
                        $over_time = $day == 0 ? 0 : ($old_time < time() ? time() + $day * 60 * 60 * 24 : $old_time + $day * 60 * 60 * 24);

                    } else {
                        $over_time = $day == 0 ? 0 : time() + $day * 60 * 60 * 24;
                    }
                }
                $vipModel->where([
                    ['site_id', '=', $site_id],
                    ['member_id', '=', $member_id]
                ])->update([
                    'level_id' => $level_id,
                    'over_time' => $over_time,
                    'update_time' => time()
                ]);
            }
            (new CoreMemberService())->modify($site_id, $member_id, 'member_level', $level_id);
            if ($type == 'pay') {
                $body = '用户购买会员';
            }
            if ($type == 'sign') {
                $body = '签到赠送会员';
            }
            if ($type == 'admin') {
                $body = '后台变动会员';
            }
            if ($type == 'gift') {
                $body = '新用户注册赠送';
            } else {
                $body = '会员变动';
            }
            event('VipLog', [
                'site_id' => $site_id,
                'member_id' => $member_id,
                'level_id' => $level_id,
                'over_time' => $over_time,
                'type' => $type,
                'body' => $body
            ]);
            Db::commit();
        } catch (Exception $e) {
            Log::write('=======   VIP更改会员等级失败   =======' . date('Y-m-d H:i:s'));
            Log::write($e->getMessage());
            Db::rollback();
            return false;
        }
    }

    /**
     * @Notes:同步修改分销会员等级
     * @Interface changeFenxiao
     * @param $orderInfo
     * @return true|void
     * @throws \think\db\exception\DbException
     * @author: TK
     * @Time: 2025/7/4   09:50
     */
    public function changeFenxiao($orderInfo)
    {
        $site_id = $orderInfo['site_id'];
        $member_id = $orderInfo['member_id'];
        $level_id = $orderInfo['level_id'];
        //检查站点是否拥有商城分销权限
        $addons = (new CoreSiteService())->getAddonKeysBySiteId($site_id);
        if (!in_array('shop_fenxiao', $addons)) return true;
        $level_info = (new MemberLevel())->where([['level_id', '=', $level_id]])->field('site_id,level_id,level_benefits')->findOrEmpty()->toArray();
        if ($level_info['level_benefits']) {
            $level_benefits = $level_info['level_benefits'];
            foreach ($level_benefits as $k => $v) {
                if ($k == 'tk_vip_fee' && $v['is_use'] == 1) {
                    //分销等级处理
                    if ($v['fenxiao_level_id'] > 0) {
                        $v_member = (new FenxiaoMember())->where([['site_id', '=', $site_id], ['member_id', '=', $member_id]])->findOrEmpty();
                        if ($v_member->isEmpty()) {
                            $parent = 0;
                        } else {
                            $parent = $v_member['pid'];
                        }
                        if ($parent > 0) {
                            //查询在分销表里面是不是有分销权限
                            $parent_info = Db::name('shop_fenxiao')->where([['site_id', '=', $site_id], ['member_id', '=', $parent]])->findOrEmpty();
                            if (!empty($parent_info)) {
                                $parent = $parent_info['member_id'] ?? 0;
                            } else {
                                $parent = 0;
                            }

                        }
                        //查询申请同步通过
                        Db::name('shop_fenxiao_apply')->where([['site_id', '=', $site_id], ['member_id', '=', $member_id]])->update(['status' => 2, 'audit_time' => time()]);
                        $fenxiao = Db::name('shop_fenxiao')->where([
                            ['site_id', '=', $site_id],
                            ['member_id', '=', $member_id],
                        ])->findOrEmpty();
                        if (empty($fenxiao)) {
                            Db::name('shop_fenxiao')->insert([
                                'site_id' => $site_id,
                                'member_id' => $member_id,
                                'level_id' => $v['fenxiao_level_id'],
                                'parent' => $parent,
                                'fenxiao_no' => "NO" . (100000 + $member_id),
                                'status' => 1,
                                'create_time' => time(),
                            ]);
                            //同步修改分销会员关系表
                            Db::name('shop_fenxiao_member')->where([['site_id', '=', $site_id], ['member_id', '=', $member_id]])->update([
                                'is_fenxiao' => 1,
                                'fenxiao_member_id' => $member_id
                            ]);

                            //查询会员绑定信息
                            $fenxiao_member_info = Db::name('shop_fenxiao_member')->where([['site_id', '=', $site_id], ['member_id', '=', $member_id]])->findOrEmpty();
                            //如果当前用户已经是分销商且有推荐人
                            if (isset($fenxiao_member_info['is_fenxiao']) && $fenxiao_member_info['is_fenxiao'] == 1 && $parent > 0) {
                                //累加上级分销商下级人数
                                //维护分销商的数据信息
                                Db::name('shop_fenxiao')->where([['site_id', '=', $site_id], ['member_id', '=', $member_id]])->update(
                                    [
                                        'child_fenxiao_num' => Db::raw('child_fenxiao_num + ' . 1),
                                    ]
                                );
                                event('ShopFenxiaoChildFenxiaoChangeAfter', ['site_id' => $site_id, 'member_id' => $member_id, 'data' => ['child_fenxiao_num' => 1]]);
                            }
                        } else {
                            Db::name('shop_fenxiao')->where([
                                ['site_id', '=', $site_id],
                                ['member_id', '=', $member_id],
                            ])->update([
                                'status' => 1,
                                'level_id' => $v['fenxiao_level_id'],
                            ]);
                        }
                    }
                }
            }
        }
        return true;
    }

    /**
     * @Notes:会员等级到期取消分销封装
     * @Interface cancelFenxiao
     * @param $site_id
     * @param $member_id
     * @return true
     * @throws \think\db\exception\DbException
     * @author: TK
     * @Time: 2025/7/4   09:49
     */
    public function cancelFenxiao($site_id, $member_id)
    {
        //检查站点是否拥有商城分销权限
        $addons = (new CoreSiteService())->getAddonKeysBySiteId($site_id);
        if (!in_array('shop_fenxiao', $addons)) return true;
        $fenxiao = Db::name('shop_fenxiao')->where([
            ['site_id', '=', $site_id],
            ['member_id', '=', $member_id],
        ])->findOrEmpty()->toArray();
        if ($fenxiao) {
            Db::name('shop_fenxiao')->where([
                ['site_id', '=', $site_id],
                ['member_id', '=', $member_id],
            ])->delete([
                'status' => -1,
            ]);
            //查询上级会员信息
            if ($fenxiao['parent'] > 0) {
                $parent_info = Db::name('shop_fenxiao')->where([['site_id', '=', $site_id], ['member_id', '=', $fenxiao['parent']]])->findOrEmpty()->toArray();
                if ($parent_info) {
                    //查询上级会员下级人数
                    $parent_child_fenxiao_num = Db::name('shop_fenxiao_member')->where([['site_id', '=', $site_id], ['fenxiao_member_id', '=', $fenxiao['parent']]])->value('child_fenxiao_num');
                    if ($parent_child_fenxiao_num > 0) {
                        //维护分销商的数据信息
                        Db::name('shop_fenxiao_member')->where([['site_id', '=', $site_id], ['member_id', '=', $fenxiao['parent']]])->update(
                            [
                                'child_fenxiao_num' => Db::raw('child_fenxiao_num - ' . 1),
                            ]
                        );
                        event('ShopFenxiaoChildFenxiaoChangeAfter', ['site_id' => $site_id, 'member_id' => $member_id, 'data' => ['child_fenxiao_num' => -1]]);
                    }
                }
            }
        }
        return true;
    }
}