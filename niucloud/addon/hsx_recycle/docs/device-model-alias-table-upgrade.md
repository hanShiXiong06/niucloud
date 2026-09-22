# 型号别名绑定独立表升级

回收插件版本：0.0.4。设备桥版本不变。

## 修复范围

`POST /adminapi/recycle/recycle_device_model_dict/alias/bind` 不再把全站别名累加到 `sys_config.value`。
每个别名单独保存，解决绑定 JSON 超出字段容量的问题，并避免多人同时绑定不同型号时互相覆盖。
接口地址、请求参数和返回字段不变，签收页面和设备桥不需要重新构建或安装。

## 新增表

表名：`数据库前缀 + recycle_device_model_alias`，归属 `hsx_recycle`。

| 字段 | 类型 | 用途 |
| --- | --- | --- |
| id | int unsigned | 绑定主键 |
| site_id | int | 当前登录站点，不接受客户端指定其他站点 |
| alias | varchar(120) | 设备工具提供的原始型号别名 |
| normalized_alias | varbinary(512) | 去除空格、分隔符并转小写后的检索键 |
| category_id | int | 本站启用的叶子型号 ID |
| operator_uid | int | 最近执行绑定的管理员 ID |
| create_at | int | 首次绑定时间，纠正绑定时保留 |
| update_at | int | 最近绑定时间 |

唯一索引：`uk_site_alias(site_id, normalized_alias)`。
查询索引：`idx_site_category(site_id, category_id)`。
检索键采用二进制类型，避免数据库的不区分重音排序规则把不同型号别名合并。
不复制型号名称、完整分类路径、设备数据或质检模板。

## 线上手动更新

1. 备份数据库与待替换的后端文件。
2. 打开 `sql/update_0.0.4.sql`，把 `{{prefix}}` 替换为线上真实表前缀；没有前缀就替换为空。执行该文件中的建表语句。
3. 上传新增文件 `app/model/device/RecycleDeviceModelAlias.php`，再更新 `app/service/admin/device/RecycleDeviceModelDictService.php`。
4. 同步插件包中的 `Addon.php`、`info.json` 和本次三个 SQL 文件，确保后续安装、升级、卸载行为一致。
5. 若 PHP 开启不自动检查文件变更的 OPcache，按正常部署方式重载 PHP 服务，再测试绑定。

**现有线上系统只执行 `update_0.0.4.sql`，不要执行 `install.sql` 或 `uninstall.sql`，也不要通过卸载重装完成本次修复。**

本次升级 SQL 只有 `CREATE TABLE IF NOT EXISTS`，可以重复执行，不包含删除、修改旧表或历史数据搬迁。
通过框架正常升级时，插件 `Addon::upgrade()` 会调用框架 SQL 执行服务完成建表；全新安装已包含新表。
所有代码变更均在回收插件内，没有框架文件或 `sys_config` 字段变更。

## 已有绑定

旧配置 `recycle_device_model_alias_config` 保持原样，只作为已有绑定的读取来源，不批量迁移、不清空。
同一别名在新表有记录时，新表优先；重新绑定只写新表。
新表没有该别名时，才读取旧配置，避免已有正常绑定突然失效。
无论来源如何，都重新校验目标型号是否属于本站、启用且为叶子节点；失效记录不会创建分类或回退到同一别名的旧错误绑定。

## 验收

- 绑定已有型号不会新增分类；再次连接相同型号时能自动找到它。
- 同一别名重复提交或改绑只保留一条记录，创建时间和主键不变。
- 两个站点的同名别名互不影响，不能绑定另一站点的型号。
- 新绑定不再改变 `sys_config.value`，超过原 1000 条限制也不删除旧绑定。
- 一次多别名写入全成或全败，数据库错误不伪装成绑定成功。
- 漏建表时显示“请先执行回收插件 sql/update_0.0.4.sql”，不要改成运行时自动建表。

## 本地验证记录

`php scripts/test_device_model_alias.php`：通过。覆盖旧绑定只读、新表优先、叶子/站点/状态校验、Unicode、1100 条绑定和缺表提示。

`scripts/test_device_model_alias_mysql.php`：在临时 MySQL 9.3.0、PHP 8.4.7 和项目实际 ThinkORM 3.0.14 上通过。
真实复现 TEXT 的 1406 错误；验证新表重复升级、唯一键 upsert、1100 条绑定、整批失败回滚、8 个并发进程和缺表错误转换。
该脚本只允许连接 `/private/tmp/hsx-recycle-alias-mysql.*` 下的专用测试 socket，自动创建并清理随机测试库，不读取项目业务数据库配置。

以上是本地验证，尚未执行线上 SQL，也未完成线上页面验收。线上 MySQL 版本和字段结构仍以部署现场为准。
