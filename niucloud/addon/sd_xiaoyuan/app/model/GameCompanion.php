<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 游戏陪玩模型
 */
class GameCompanion extends BaseModel
{
    protected $name = 'xiaoyuan_game_companion';
    protected $pk = 'id';

    // 游戏类型
    const GAME_LOL = 'LOL';
    const GAME_WZRY = 'WZRY';
    const GAME_PUBG = 'PUBG';
    const GAME_CSGO = 'CSGO';
    const GAME_YS = 'YS';
    const GAME_EGG = 'EGG';
    const GAME_OTHER = 'OTHER';

    // 服务类型
    const SERVICE_PLAY_WITH = 'PLAY_WITH';   // 陪玩
    const SERVICE_BOOST = 'BOOST';           // 代练
    const SERVICE_TEACH = 'TEACH';           // 教学
    const SERVICE_TEAM = 'TEAM';             // 组队

    // 状态
    const STATUS_PENDING = 0;    // 待审核
    const STATUS_ONLINE = 1;     // 上架
    const STATUS_OFFLINE = 2;    // 下架
    const STATUS_REFUSED = 3;    // 拒绝

    public static function getGameTypeList(): array
    {
        return [
            self::GAME_WZRY => '王者荣耀',
            self::GAME_LOL => '英雄联盟',
            self::GAME_PUBG => '和平精英',
            self::GAME_CSGO => 'CS2',
            self::GAME_YS => '原神',
            self::GAME_EGG => '蛋仔派对',
            self::GAME_OTHER => '其他',
        ];
    }

    public static function getServiceTypeList(): array
    {
        return [
            self::SERVICE_PLAY_WITH => '陪玩',
            self::SERVICE_BOOST => '代练',
            self::SERVICE_TEACH => '教学',
            self::SERVICE_TEAM => '组队',
        ];
    }

    public static function getStatusList(): array
    {
        return [
            self::STATUS_PENDING => '待审核',
            self::STATUS_ONLINE => '上架中',
            self::STATUS_OFFLINE => '已下架',
            self::STATUS_REFUSED => '已拒绝',
        ];
    }

    public function searchGameTypeAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('game_type', $value);
        }
    }

    public function searchServiceTypeAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('service_type', $value);
        }
    }

    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('status', $value);
        }
    }

    public function searchMemberIdAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('member_id', $value);
        }
    }

    public function searchSchoolIdAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('school_id', $value);
        }
    }
}
