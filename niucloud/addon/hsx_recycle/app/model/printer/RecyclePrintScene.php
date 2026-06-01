<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\printer;

use addon\hsx_recycle\app\dict\printer\PrinterSceneDict;
use core\base\BaseModel;

/**
 * 回收打印场景模型
 * Class RecyclePrintScene
 * @package addon\hsx_recycle\app\model\printer
 */
class RecyclePrintScene extends BaseModel
{
    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'scene_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'recycle_print_scene';

    /**
     * 自动写入时间戳
     * @var bool
     */
    protected $autoWriteTimestamp = true;

    /**
     * 创建时间字段
     * @var string
     */
    protected $createTime = 'create_time';

    /**
     * 更新时间字段
     * @var string
     */
    protected $updateTime = 'update_time';

    /**
     * 获取场景清单
     * @return array
     */
    public static function sceneList(): array
    {
        return PrinterSceneDict::getSceneList();
    }

    /**
     * 获取场景名称
     * @param string $sceneKey
     * @return string
     */
    public static function getSceneName(string $sceneKey): string
    {
        return PrinterSceneDict::getSceneName($sceneKey);
    }
}
