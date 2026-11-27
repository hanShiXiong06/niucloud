# 会员卡管理端移动 UI 升级、样式修复与测试说明

日期：2026-09-09

## 1. 本次结果和边界

- 管理端会员卡 10 个页面统一为同一套移动端交互，直接复用项目现有的 uview-plus，不升级依赖。
- 修复卡种编辑页 `addon/hsx_member_card/pages/product/edit` 在小程序中丢失公共样式的问题。
- 插件运行目录与插件发布目录的 26 个文件已逐文件同步。
- 独立收款账户继续使用既有配置存储，不要求安装 ERP；修复默认账户及停用状态的判断。
- 管理端登录的点击无反馈、返回页面失败增加明确反馈及可重试处理。
- **无新表、无新字段、无 SQL 升级，无历史数据回填。未提交 Git，未部署线上。**

UI 与账户配置修改在会员卡插件内；框架仅保留此次登录修复和必要的管理端页面注册，不修改框架样式、依赖包或其他业务插件。

## 2. 卡种编辑页为什么没有样式

原来的编辑页在 `script` 中引入 `mobile.scss`，同时又有自己的页面 `style` 块。当前项目的小程序构建结果只生成了编辑页自身的三条布局样式，公共的卡片、表单、抽屉、底部操作栏样式没有进入该页。

本次直接检查了构建产物，不以“构建成功”作为样式正常的依据：

- 修复前，编辑页小程序 `edit.wxss` 只有 227 字节，缺少 `.mc-page`、`.mc-card`、`.mc-field` 等公共规则。
- 修复后，10 个页面各自的 `wxss` 都包含所需公共规则，编辑页还保留自己的滚动布局。
- H5 的页面样式会被 uni-app 自动加作用域，直接把公共样式放进页面样式块又会限制公共组件内部的样式。因此使用条件编译：**H5 从脚本加载公共样式；小程序从页面样式块加载。**
- 公共样式采用 `mc-` 前缀，不放入框架 `App.vue`，不改变全站样式。
- 新增构建产物回归检查，验证实际 CSS / WXSS，而非仅检查源文件是否写了 import。

重点文件：

- 运行源：`site-uniapp/src/addon/hsx_member_card/pages/product/edit.vue`
- 发布源：`niucloud/addon/hsx_member_card/site-uniapp/pages/product/edit.vue`
- 公共样式：上述两个插件目录下的 `styles/mobile.scss`

## 3. UI 与交互调整

| 页面 | 主要调整 |
| --- | --- |
| 工作台 | 克制的深蓝渐变，仅突出开卡、核销两个常用动作；经营数据按开卡 / 核销切换；统计口径折叠 |
| 快速开卡 | 选择客户 → 选择卡种 → 确认收款；选客、选卡、选账户使用统一抽屉；凭证和备注折叠 |
| 服务核销 | 手机号查询置前；客户身份、可用权益、实际用料在确认抽屉集中展示；原生复选确认 |
| 开卡记录 | 搜索和状态筛选置顶；突出金额、客户、处理状态；详细信息进入抽屉 |
| 核销记录 | 搜索置顶；区分服务核销与库存处理结果，异常说明可查看 |
| 客户列表 | 原生列表单元，显示姓名、联系方式、卡数量，减少重复装饰和内部编号 |
| 客户详情 | 会员、持卡、服务记录分区；明确可用权益 |
| 卡种列表 | 价格、权益、有效期与启用状态优先，保留必要管理动作 |
| 卡种编辑 | u-form、分段选择、数量输入统一样式；耗材与高级设置折叠；固定底部操作栏 |
| 收款与耗材 | 收款账户 / 耗材规则分段；账户分组列表；编辑抽屉内管理启停、默认、删除 |

统一使用或二次封装：`u-search`、`u-tabs`、`u-subsection`、`u-cell`、`u-form`、`u-button`、`u-popup`、`u-alert`、`u-switch`、`u-checkbox`、`u-radio`、`u-steps` 等现有组件。

交互原则：

- 页面加载、请求失败、没有数据是三种不同状态，失败有明确原因和重试入口。
- 金额缺失显示“—”，不冒充 0。
- 提交时有 loading 和防连点，上传未完成时不允许提交。
- 普通从详情返回保留列表位置；发生写操作后才触发数据刷新。
- 抽屉只在需要时出现；业务忙碌时不能误关闭。
- 金额、次数、状态与下一步操作优先；说明、凭证、内部记录按需展开。

## 4. 收款账户闭环及入口

入口：**会员卡工作台 → 收款与耗材 → 收款账户**。

### 未接入 ERP

- 可以新增、编辑、启用 / 停用、删除本店账户，并设置默认账户。
- 停用账户不再参与新开卡的收款选择。
- 停用或删除默认账户后，重新选取可用账户；全部停用时默认账户为空，不伪装成可用。
- 设置与读取使用同一份既有配置，不另建财务数据库表。

### ERP 托管

- 账户在 ERP 维护，会员卡页面选择默认收款账户，不再创建第二套同名账户。
- 修复 ERP 默认账户 ID 被本地账户列表错误校验并替换的问题。
- 明确兼容 ERP 账户选项没有 `status` 字段、独立账户必须依据 `status` 启停的现有接口契约。

这些账户用于记录“实际收到了哪个账户”，**不是自动扣款、支付通道开户或真实资金转账功能**。

## 5. 业务与登录修复的保护点

- 开卡失败重试沿用原请求标识，已有订单走原订单重试，不把重试变成第二笔开卡。
- 卡种创建分阶段处理，已创建的卡种 / 已完成的期初库存不在重试时重复创建。
- 盘点输入的是最终库存总量，必须填写数量和原因；不自动填 0。
- 切换统计周期、连续搜索时，旧请求不能覆盖新结果。
- 配置更新不丢失尚未保存的耗材选择；切换仓库要求重新确认库位。
- 登录表单未就绪、校验失败、配置获取失败、缺少 token 等情况有明确提示，不留下永久 loading。
- 登录成功后等待跳转结果，普通页面与 tabbar 分别处理；目标失效时降级到工作台。
- 如果已经登录但跳转仍失败，显示再次进入工作台的入口，不重复发送登录请求。
- 不绕过既有验证码；密码与短信登录沿用原认证方式。

## 6. 修改文件清单

下列 26 个插件移动端文件在两个目录中内容一致：

运行根目录：`site-uniapp/src/addon/hsx_member_card/`

插件发布根目录：`niucloud/addon/hsx_member_card/site-uniapp/`

```text
api/index.ts
components/MemberCardActionBar.vue
components/MemberCardButton.vue
components/MemberCardCollapse.vue
components/MemberCardListHeader.vue
components/MemberCardMemberPopup.vue
components/MemberCardNotice.vue
components/MemberCardOrderDetail.vue
components/MemberCardSectionHeader.vue
components/MemberCardSegmented.vue
components/MemberCardSheet.vue
components/MemberCardState.vue
components/MemberCardVoucherUploader.vue
hooks/useMemberCardList.ts
utils/presentation.ts
styles/mobile.scss
pages/dashboard/index.vue
pages/order/create.vue
pages/order/list.vue
pages/card/search.vue
pages/redemption/list.vue
pages/member/list.vue
pages/member/detail.vue
pages/product/list.vue
pages/product/edit.vue
pages/config/payment.vue
```

两个 PHP 文件：

```text
niucloud/addon/hsx_member_card/app/service/core/MemberCardConfigService.php
niucloud/addon/hsx_member_card/app/service/admin/MemberCardConfigAdminService.php
```

必要的管理端框架文件，单独列出便于手动控制：

```text
site-uniapp/src/app/pages/auth/login.vue
site-uniapp/src/hooks/useLogin.ts
site-uniapp/src/pages.json
```

`pages.json` 本次相关变动仅为注册会员详情登录页面、调整“收款与耗材”标题。不要因此覆盖其他插件的页面注册。插件中的 `package/uni-app-pages.php` 是销售 / 客户端页面定义，不应当用它替代管理端页面注册。

## 7. 测试结果

### 自动检查：104 项通过

| 测试 | 结果 | 覆盖范围 |
| --- | --- | --- |
| `tests/member_card_mobile_ui_smoke.cjs` | 62 / 62 | 22 个 Vue 文件分别进行 H5 / 小程序模板编译；另含列表竞态、分页、账户、开卡、核销、库存、样式入口等 18 项检查 |
| `tests/site_mobile_login_smoke.cjs` | 15 / 15 | 双端登录编译、密码 / 短信流程、失败提示、防连点、失效目标和 tabbar 返回、超时恢复 |
| `tests/member_card_account_config_smoke.php` | 15 / 15 | 实际配置服务在内存环境执行，覆盖独立账户 CRUD、默认账户、停用、ERP 默认选择、站点隔离 |
| `tests/member_card_mobile_styles_build.cjs` | 12 / 12 | 10 页实际小程序样式、H5 编辑页与公共组件样式、发布源和运行源完整一致性 |

自动测试使用内存模拟 API / 配置，不请求生产接口，不产生实际订单、核销或资金流水。

执行示例（使用环境中的 Node / PHP）：

```sh
node tests/member_card_mobile_ui_smoke.cjs
node tests/site_mobile_login_smoke.cjs
php tests/member_card_account_config_smoke.php
node tests/member_card_mobile_styles_build.cjs /tmp/member-card-ui-mp-20260909 /tmp/member-card-ui-h5-20260909
```

### 完整构建

- 管理端 H5：通过。
- 管理端微信小程序：通过。
- 输出到独立 `/tmp` 目录，没有调用发布脚本，没有覆盖当前站点的 `public/adminapp`。
- 现有的 Browserslist 数据过期、回收空 chunk、小程序 div 选择器警告仍存在，未通过升级依赖或改动其他插件来扩大本次范围。

### 本地页面查看

验收环境使用独立本机模拟接口，不连接生产数据库。查看了工作台、列表、详情、开卡、核销查询和收款设置的 UI。重点复查了：

- 编辑页直接打开后即有完整样式，不依赖先进入工作台。
- 390 × 844 下，两个底部按钮左右排列，表单与卡片样式正确。
- 320 × 667 下，生效 / 有效期设置、更多设置展开、文字输入区域能够继续滚动，底部按钮不遮挡末尾字段。
- 账户编辑抽屉内的账户类型分段、启停开关、默认开关、折叠区、保存按钮显示正常。
- 选客户、选卡种后开卡步骤和金额正确更新；此次 UI 检查没有点击真实收款动作。

### 尚需真机 / 实际业务验收的部分

不能把模拟测试和打包通过等同于真实业务已全部验证。以下仍需在测试站点配合真实环境确认：

- 微信真机键盘顶起、设备安全区、原生返回与网络切换。
- 实际短信服务和验证码联调。
- 实际权限账号、ERP 接入 / 不接入两种运行模式、真实服务端错误返回。
- 实际上传、开卡、核销、库存及财务联动。当前没有在生产环境执行这些动作。

## 8. 手动更新方式

1. 保留现场备份和其他未合并改动。
2. 本轮 UI 新增了公共组件、样式和工具文件，**不能只替换 `product/edit.vue` 而遗漏这些依赖**。同步本节列出的插件移动端目录，并保证运行源、插件发布源一致。
3. 如一并更新收款账户修复，上传两个 PHP 配置服务；如一并更新登录修复，单独合并两个登录文件和 `pages.json` 的相关修改。
4. 重新构建的是 **管理端 `site-uniapp`**，不是客户销售端 `uni-app`，也不是 PC `admin`。
5. 使用目标环境自己的接口地址重新构建、发布管理端 H5 / 微信小程序。本次 `/tmp` 测试产物指向本机模拟接口，**不得直接上传生产**。
6. 更新后从卡种列表进入编辑页，重点核对卡片间距、次数选择、折叠设置和底部双按钮；再验证账户管理和登录。

本次不需要执行任何数据库升级文件，也不需要删除业务数据、重装插件或清空整站缓存。
