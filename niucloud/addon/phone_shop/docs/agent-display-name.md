# 子站的主站显示名称

## 使用入口

子站后台进入 `/site/phone_shop/agent`，在「本站跟随状态」中填写「主站显示名称」，点击「保存名称」。最多 12 个字，清空并保存后恢复实际主站名称，例如「天泰通讯」。

保存后重新进入商城三级分类页，「全部 / 本地仓 / 主站名称」中的最后一项使用新名称。每个子站独立配置；不修改主站实际名称、站点关系、商品归属、价格或筛选条件。主站自身不显示此设置。

## 配置与权限

- 复用框架配置服务，配置键 `PHONE_SHOP_AGENT_DISPLAY`，值为 `agent_name`，归属当前登录子站。
- 没有新增数据库表、字段或 SQL，没有修改公共框架。
- `GET phone_shop/agent/master_config` 增加 `display_config`，原有字段不变。
- `PUT phone_shop/agent/display_config` 保存名称。站点 ID 从登录上下文获取，不接受提交其他站点 ID 来改名。
- 商城仓库选项接口继续返回 `agent_name`，优先使用别名，未配置则使用主站原名；主站无名称时回退为「代理仓」。
- 新增按钮权限 `phone_shop_agent_display_edit`，名称为「修改主站显示名称」。

## 发布

1. 更新后端插件文件：
   - `app/service/core/agent/AgentConfigService.php`
   - `app/service/api/goods/GoodsService.php`
   - `app/adminapi/controller/agent/Agent.php`
   - `app/adminapi/route/route.php`
   - `app/dict/menu/site.php`
2. 更新 PC 前端 `admin/src/addon/phone_shop` 下的 `api/agent.ts`、`views/agent/index.vue`、`views/agent/components/AgentDisplaySettings.vue`。插件 `admin/` 发布副本已同步。
3. 在后端 `niucloud/` 目录刷新菜单，按现有流程发布 PC 前端并重新登录。员工角色需授权「修改主站显示名称」。

```sh
php think menu:refresh --addon phone_shop
```

4. 名称沿用现有接口字段，已发布的小程序在重新加载分类页时即可读取新名称。本次还优化了长名称省略显示，涉及 `uni-app/src/addon/phone_shop/pages/goods/components/category-template-three-one.vue` 及插件内同名发布副本；该样式需要随下次小程序版本发布。

根仓库忽略了 `niucloud/addon/phone_shop/` 和根目录 `tests/`；只上传 Git 中的前端变更不包含后端，请通过现有插件发布渠道一并更新。此次没有提交、构建或部署线上。已在本地执行菜单刷新并复核数据库中的页面入口与 18 项按钮权限，未修改员工角色授权。

菜单入口为「二手商城 → 站点商品自动跟随」；「修改主站显示名称」是该页面内的按钮权限，不是独立的侧边栏菜单。仅修改文件不会自动更新已安装站点的菜单，更新后需执行上述刷新命令并重新登录。

## 验证

- `tests/phone_shop_agent_display_smoke.php`：29 项断言通过，覆盖默认值、保存清空、站点隔离、伪造站点参数、输入校验，以及商品筛选条件不变。使用真实服务代码与模拟存储，无真实业务数据写入。
- `tests/phone_shop_agent_menu_smoke.php`：78 项断言通过，覆盖页面和按钮权限注册。
- `tests/phone_shop_agent_display_browser.cjs`：真实 Vue 组件配模拟接口，验证保存、重新加载、清空、失败重试、长名称布局及原筛选值保持不变；检查源码与插件发布副本一致，组件编译通过。
- 已查看桌面与手机宽度截图。尚未在真实站点账号或微信真机上验收。
