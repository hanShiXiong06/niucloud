# phone_shop / shop DIY 组件重名修复

## 先恢复这次线上打包

本次线上应急文件完全以用户提供的 506 行代码为基础，不使用本地插件集合覆盖线上。

已修复文件：[线上专用 index.vue](hotfix/2026-09-07-phone-shop-diy/group/index.vue)。

1. 备份服务器上的 `/www/wwwroot/gl.hsxbk.top/uni-app/src/addon/components/diy/group/index.vue`，备份放在组件目录之外。
2. 上传上述修复文件，替换该文件，保留原名称 `index.vue`。
3. 重新运行原来的打包命令：

```bash
cd /www/wwwroot/gl.hsxbk.top/uni-app
npm run build:h5
```

小程序则使用原有 `npm run build:mp-weixin` 流程。本次不需要重新安装依赖、清数据库、删除旧组件文件夹或执行 SQL。

**不要把本地 `uni-app/src/addon/components/diy/group/index.vue` 上传覆盖线上。** 两边插件不同。本次提供的线上专用文件保留了用户原文件内 `shop`、`tk_jhkd`、`sd_xiaoyuan`、回收及 AI 等插件。该文件只适用于本次提供的线上插件集合；其他环境应按自身插件重新生成，或使用下方的定向修复工具。

## 原因与改动范围

从用户原文件可直接复现：

```text
Identifier 'diyShopExchangeGoods' has already been declared. (49:10)
```

同一个生成文件同时导入了：

```text
shop/components/diy/shop-exchange-goods/index.vue
phone_shop/components/diy/shop-exchange-goods/index.vue
phone_shop/components/diy/phone-shop-exchange-goods/index.vue
```

前两者声明相同的 `diyShopExchangeGoods`。不只这一项：用户文件中共有 19 个 phone_shop 旧目录注册，其中大部分与 shop 撞名。

代码生成器原先扫描目录推导注册名称，而框架升级复制文件不会删除目的地的旧文件。旧目录和新目录共存时，生成器全部注册，导致重复导入和重复模板。

此次应急文件只做两件事：

- 移除 phone_shop 的 19 个旧名称导入及对应多余模板。
- 保留独立的 `Phone*` 注册和原商城 `Shop*` 注册，不改任何业务页面或存储的装修数据。

没有把所有 `Shop` 字符串做全局替换，也没有修改原商城组件。

## 本地源码的防复发修复

| 文件（相对项目根目录） | 作用 |
| --- | --- |
| `niucloud/app/service/core/addon/WapTrait.php` | 生成时读取插件注册声明；忽略已被替代的旧目录；发现其他未知重名时给出两处文件路径，并在覆盖前停止；只扫描准确的 `index.vue`，不把备份文件也当成组件 |
| `niucloud/addon/phone_shop/uni-app/components/diy/registration.json` | 插件发布源：声明 22 对旧目录与现有 `phone-*` 目录的对应关系，覆盖同类残留；并非新增 22 个组件 |
| `uni-app/src/addon/phone_shop/components/diy/registration.json` | 当前运行源码的相同声明，手工升级也要同步 |
| `uni-app/scripts/repair-phone-shop-diy.cjs` | 可选维护工具：只处理本次类型的生成文件，不要求线上与本地插件相同；默认预览，确认写入前备份 |

注意：本次有 **1 处框架源码改动**，即 `WapTrait.php`。后续升级框架时需要保留这处生成规则；只升级 phone_shop 插件并不会自动替换框架文件。未改表、未加字段、未改插件安装 SQL。

部署顺序：先上传两处 `registration.json`，再上传 `WapTrait.php`。当前已生成的错误文件不会因上传 PHP 自动改变，仍需替换本次应急文件，或运行修复工具。以后框架执行插件安装/升级的组件生成步骤时，会按声明生成正确注册。

当旧目录存在但新的替代文件没有上传时，会明确提示缺少哪个 `phone-*` 文件，不能靠跳过错误生成缺组件的页面。未声明的其他插件重名不会静默选一份覆盖另一份。

## 手工维护多个不同插件环境

上传工具及当前环境的 `registration.json` 后，在对应环境的 `uni-app` 目录执行：

```bash
node scripts/repair-phone-shop-diy.cjs --check
```

- 显示没有重复注册：不需要改动，退出码为 0。
- 列出 phone_shop 旧注册：只是预览，未写入，退出码为 2。
- 配置/模板结构不符、缺新组件、还有未知重名：退出码为 1，不修改文件，先按提示检查。

确认预览仅为本次旧注册后执行：

```bash
node scripts/repair-phone-shop-diy.cjs --write
```

它会先将原始生成文件备份到 `uni-app/.diy-backups/`，再定向修复。输出会告知实际备份路径。重复执行不会重复修改，不删除任何组件目录，不连接数据库。之后再打包。

## 测试记录

测试时间：2026-09-07。本地执行 36 项检查，全部通过。

| 验证内容 | 结果 |
| --- | --- |
| 直接用用户粘贴文件复现原始重复声明及行列信息 | 通过 |
| 线上专用完整文件的 Vue SFC 脚本、模板编译 | 通过 |
| 交付文件与原文件定向修复结果逐字比较 | 通过（仅忽略文件结尾空行） |
| 原文件所有其他插件导入原样保留 | 通过 |
| phone_shop 旧名称全部定向处理；原商城不删除 | 通过 |
| CRLF 换行保持、重复修复幂等 | 通过 |
| 新文件缺失、导入缺失、模板被改动、未知冲突时拒绝盲修 | 通过 |
| 命令默认只读、确认后备份、重复写入不新增无意义备份 | 通过 |
| PHP 真实生成：shop 和 phone_shop 同时安装、反向安装顺序、仅 phone_shop | 通过 |
| PHP 生成结果再通过 Vue 脚本、模板编译 | 通过 |
| 系统与插件重名、两个未知插件重名、非法声明 | 通过；均未覆盖已有文件 |
| 新组件路径和 phone_shop 现有字典一致；发布源和运行声明一致 | 通过 |

复测（项目根目录，需要本机 Node 依赖和 PHP 8.1+）：

```bash
node tests/diy_component_registration_smoke.cjs
```

如果 PHP 不在命令路径中，可以通过 `PHP_BIN` 指定 PHP 可执行文件。

测试不会启动应用、连接数据库或改业务数据。PHP 生成测试全部在临时目录执行。

**验证边界：** 已确认并修复这份线上文件的重复声明问题，但没有拿本地不同插件集合的打包结果冒充线上全量构建成功。线上其他插件是否还有独立的缺文件、依赖或编译问题，仍以替换后服务器实际打包结果为准。
