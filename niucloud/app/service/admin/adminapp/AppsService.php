<?php
// HSX_RECYCLE_ADMINAPP_COMPAT_BRIDGE

namespace app\service\admin\adminapp;

/**
 * 手机管理端应用服务兼容桥接。
 *
 * 线上如果还残留旧的 app/adminapi/controller/adminapp 控制器，会引用当前命名空间。
 * 实际实现统一转到 hsx_recycle 插件，避免核心目录继续维护一份 adminapp 业务代码。
 */
class AppsService extends \addon\hsx_recycle\app\service\admin\adminapp\AppsService
{
}
