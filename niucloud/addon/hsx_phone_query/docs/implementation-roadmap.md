# hsx_phone_query 重构实施路径

更新时间：2026-05-23

版本策略：

- 当前按全新安装包处理，插件版本从 `0.0.1` 开始。
- 不做历史升级 SQL，不考虑旧版本平滑升级。
- 表结构直接写入 `sql/install.sql`。
- 初始化查询项通过 `resource/query_items.php` 和同步服务处理。

状态说明：

- `[todo]` 未开始
- `[doing]` 正在实现
- `[done]` 已完成并通过基础验证
- `[blocked]` 被环境或业务确认阻塞

## 总目标

把 `hsx_phone_query` 从“基础接口对接插件”重构成可运营、可收费、可扩展、可推广的设备查询产品。

核心原则：

- 使用 NiuCloud 原生写法：`CoreConfigService`、框架支付、框架通知、框架 poster、插件安装 SQL、`BaseApiService` / `BaseAdminService`。
- 后端强制单渠道生效，不依赖前端判断。
- 前端查询项完全由后端当前生效渠道返回。
- 管理端要让管理者看得懂：当前渠道、项目售价、成本、利润、查询量、消耗量、失败率。
- 用户端要让用户愿意付费：查询清晰、支付清晰、报告清晰、分享好看。

## 实施阶段

### 0. 路径和进度文件

状态：`[done]`

实现方式：

- 新增本文档作为后续开发进度看板。
- 后续每完成一个阶段，都在本文档更新状态、改动文件和验证结果。

涉及文件：

- `niucloud/addon/hsx_phone_query/docs/implementation-roadmap.md`

验证结果：

- 文件已创建。

### 1. 框架 poster 分享图接入

状态：`[done]`

目标：

- 不自研 canvas。
- 使用框架 `CorePosterService`、`GetPosterType`、`GetPosterData`、`share-poster` 组件生成设备查询报告分享图。
- 分享图支持保存图片、分享给朋友、二维码/小程序码、邀请参数、水印、品牌名、页脚、客服电话。

实现方式：

- 新增海报类型：`hsx_phone_query_report`。
- 新增海报数据监听器，根据查询记录生成 poster 模板数据。
- 新增默认海报模板。
- 在插件 `event.php` 注册 `GetPosterType` 和 `GetPosterData`。
- 详情页用框架 `share-poster` 替换当前自研 `SharePosterPanel`。

计划涉及文件：

- `niucloud/addon/hsx_phone_query/app/event.php`
- `niucloud/addon/hsx_phone_query/app/listener/poster/QueryReportPosterType.php`
- `niucloud/addon/hsx_phone_query/app/listener/poster/QueryReportPoster.php`
- `niucloud/addon/hsx_phone_query/app/dict/poster/template.php`
- `niucloud/addon/hsx_phone_query/app/dict/HsxPhoneQueryConfigDict.php`
- `admin/src/addon/hsx_phone_query/views/hsx_phone_query_config/hsx_phone_query_config.vue`
- `niucloud/addon/hsx_phone_query/admin/views/hsx_phone_query_config/hsx_phone_query_config.vue`
- `uni-app/src/addon/hsx_phone_query/pages/detail.vue`
- `niucloud/addon/hsx_phone_query/uni-app/pages/detail.vue`

验证方式：

- PHP 语法检查。
- 检查 poster 事件注册。
- 前端详情页能调用 `share-poster` 并传入 `result_id`、`invite_member_id`。

当前结果：

- 已接入框架 poster。
- 已新增 `hsx_phone_query_report` 海报类型。
- 已新增报告海报数据监听器，支持查询记录、设备图、摘要、水印、页脚、客服电话、邀请参数。
- 已新增默认设备查询报告海报模板。
- 已在插件事件中注册 `GetPosterType` 和 `GetPosterData`。
- 详情页已改用框架 `share-poster` 组件，分享参数包含 `result_id` 和 `invite_member_id`。
- 已修复详情页分享海报空模板问题：
  - `share-poster.openShare()` 支持直接传入本次分享参数，避免 Vue prop 未及时同步导致 `result_id` 为空。
  - 每次打开分享会重新按当前参数请求海报，避免复用上一张空图。
  - 设备查询详情页直接传入 `type=hsx_phone_query_report`、`result_id`、`invite_member_id`。
- 已优化默认报告海报模板：
  - 保留框架 poster 生成方式，不自研 canvas。
  - 海报展示品牌名、设备标题、查询项目、IMEI/SN、4 行报告摘要、查询时间、二维码、水印、页脚和客服电话。
  - 摘要由后端拆成独立字段，避免框架 poster 渲染器对长文本换行不稳定。
- 已修复 `channel=weapp` 生成海报时报 `WEAPP_NOT_EXIST` 的问题：
  - 小程序配置完整时继续生成小程序码。
  - 当前站点未配置小程序或小程序码生成失败时，自动降级为 H5 普通二维码，保证海报可生成。
- 已根据实际生成效果继续修正海报：
  - 模板背景改为纯白保守方案，降低底层 Imagick 透明/色块处理导致黑底的风险。
  - 海报设备图改为插件本地图标，不再直接渲染第三方接口返回的远程设备图，避免透明大图/裁切异常导致只显示几像素。
  - 查询时间兼容时间戳和字符串时间，避免字符串时间被强转为 `0` 后显示 `1970-01-01`。
  - 默认模板去掉水印组件，避免遮挡内容；后端仍保留 `watermark_text` 数据，便于以后自定义模板使用。
- 已修复数据库旧默认海报模板污染问题：
  - 框架 poster 支持 `posterId < 0` 时强制使用代码内置模板，不读取数据库默认海报。
  - 设备查询详情页分享海报传入 `posterId=-1`，确保使用插件当前内置模板。
  - 其他业务仍保持原框架逻辑：`posterId=0` 使用数据库默认海报，`posterId>0` 使用指定海报。
- 已移除海报模板里的 `draw` 绘制矩形：
  - 实测框架底层 `filled_rectangle` 会把白色矩形画成黑块。
  - 默认模板改为只使用全局白底、文字和二维码，避免黑色大块背景。
  - 同时移除默认模板里的设备图组件，避免图像渲染异常影响主体报告。
- 已定位并修复框架 Imagick 海报绘制 bug：
  - GD 驱动的 `filled_rectangle` 会正确填充颜色。
  - Imagick 驱动原本只设置了描边色，未设置填充色，导致多边形/矩形组件生成后填充为默认黑色。
  - 已在 `vendor/kkokk/poster/src/Image/Drivers/ImagickDriver.php` 的 `filled_rectangle` 分支补充 `setFillColor($color)`。
  - 后台自定义海报里的绘画矩形组件应能按所选颜色生成。
- 服务商配置页已增加“客服电话”配置项，用于分享海报。
- PHP 语法检查通过。
- `uni-app/src` 与插件包 `uni-app` 目录保持一致。
- `admin/src` 与插件包 `admin` 目录保持一致。

### 2. 查询项初始化配置和数据修复

状态：`[doing]`

目标：

- 查询项从可编辑初始化文件导入。
- 查询项明确归属渠道。
- 修复历史脏数据。
- 支持保留人工售价，也支持覆盖导入。

实现方式：

- 新增 `resource/query_items.php`。
- 新增 `QueryItemImportService`。
- 后台新增“同步初始化查询项”和“修复渠道归属”能力。
- 增加唯一索引：`site_id + channel_key + service_code`。

计划涉及文件：

- `niucloud/addon/hsx_phone_query/resource/query_items.php`
- `niucloud/addon/hsx_phone_query/app/service/core/item/QueryItemImportService.php`
- `niucloud/addon/hsx_phone_query/app/service/core/item/QueryItemService.php`
- `niucloud/addon/hsx_phone_query/sql/install.sql`
- `niucloud/addon/hsx_phone_query/app/adminapi/controller/hsx_phone_query_category/HsxPhoneQueryCategory.php`
- `niucloud/addon/hsx_phone_query/app/adminapi/route/route.php`
- `niucloud/addon/hsx_phone_query/app/dict/menu/site.php`
- `admin/src/addon/hsx_phone_query/api/hsx_phone_query_category.ts`
- `niucloud/addon/hsx_phone_query/admin/api/hsx_phone_query_category.ts`
- `admin/src/addon/hsx_phone_query/views/hsx_phone_query_category/hsx_phone_query_category.vue`
- `niucloud/addon/hsx_phone_query/admin/views/hsx_phone_query_category/hsx_phone_query_category.vue`

验证方式：

- 3023 和爱查查询项可分别导入。
- 同一渠道同一服务不会重复。
- 当前渠道过滤只返回当前渠道项目。

当前结果：

- 已新增 `resource/query_items.php`，可集中覆盖初始化售价、成本、上架状态和排序。
- `HsxPhoneQueryCategoryDict::defaultItems()` 已支持读取 `resource/query_items.php` 覆盖配置。
- 已新增 `QueryItemImportService`，支持同步缺失查询项目，默认不覆盖人工售价。
- 已新增渠道基础信息修复能力，修复 `channel_name`、`query_param`、`type_id`，不修改售价。
- 已新增后台接口：`sync_items`、`repair_items`。
- 已新增后台按钮：`同步查询项`、`修复渠道信息`。
- 后台查询项目列表已增加“查询渠道”筛选和渠道列，管理者可以明确看到项目归属。
- 已补充菜单权限节点。
- PHP 语法检查通过。
- `install.sql` 已补齐全新安装结构：
  - 查询项目表增加 `uk_site_channel_service`，避免同站点同渠道同服务重复。
  - 查询结果表增加 `order_id`、`service_code`、`channel_key`、`query_param`，后续统计不再依赖反查。
  - 查询订单表增加 `service_code`、`channel_key`、`query_param`、`unit_price`、`unit_cost`、`success_count`、`fail_count`。
- 业务主链路已开始写入并使用订单/结果表新增快照字段：
  - 创建现金订单时写入 `service_code`、`channel_key`、`channel_name`、`query_param`、`unit_price`、`unit_cost`。
  - 积分查询订单同样写入上述快照字段。
  - 查询结果写入 `order_id`、`service_code`、`channel_key`、`query_param`。
  - 24 小时缓存命中增加 `service_code`、`channel_key` 条件，避免不同渠道/服务混用缓存。
  - 支付成功回调增加幂等保护，`SUCCESS` 和 `QUERYING` 状态不会重复执行查询。
  - 后台查询记录列表优先使用结果表 `order_id/channel_key/service_code` 和订单快照，不再优先依赖接口日志反查。
- 不再做升级 SQL，也不做旧版本历史脏数据迁移；按全新安装版本处理。
- PHP 全量语法检查通过。
- 后台查询项目页已修复删除按钮参数错误。
- 后台查询项目新增/编辑弹窗已增加“查询渠道”，并按渠道默认设置查询参数。
- 后端已校验 `channel_key`、`service_code` 必填，并禁止同站点同渠道同服务编码重复。

### 3. 后端服务拆分和单渠道闭环

状态：`[doing]`

目标：

- 拆分当前过大的 `HsxPhoneQueryService`。
- 保存配置时后端强制只启用一个服务商。
- 查询项展示、创建订单、支付成功查询全部由后端校验当前渠道。

计划拆分服务：

- `ProviderChannelService`
- `ProviderCatalogService`
- `QueryItemService`
- `QueryOrderService`
- `QueryExecutionService`
- `AichaProvider`
- `Data3023Provider`
- `QueryReportService`
- `QueryStatsService`
- `QueryNoticeService`

验证方式：

- 当前渠道为 3023 时，爱查项目无法下单。
- 当前渠道为爱查时，3023 项目无法下单。

### 4. 框架通知系统接入

状态：`[done]`

目标：

- 不自研通知系统，使用框架 `NoticeService`、`NoticeData` 事件、后台通知模板配置、通知记录。
- 后台能看到设备查询插件的通知配置项。
- 查询完成和查询失败都能通过框架通知链路发送。
- 一次查询多个串号时，成功通知要展示成功数、失败数和退款说明，避免用户误解。

实现方式：

- 插件新增 `app/dict/notice/notice.php`，注册通知项：
  - `hsx_phone_query_success`：设备查询完成通知。
  - `hsx_phone_query_fail`：设备查询失败通知。
- 插件新增 `app/dict/notice/weapp.php`、`wechat.php`、`sms.php`，声明各渠道默认模板结构。
- 小程序订阅消息已接入微信模板：
  - 模板编号：`4075`
  - 标题：查询结果通知
  - 类目：信息查询
  - 关键词：查询编号、查询结果、查询时间
- 复用已有 `NoticeData` listener：
  - `QuerySuccess`
  - `QueryFail`
- 补齐通知变量：
  - 查询结果、成功数、失败数、支付说明、退款说明。
  - 失败通知补齐退款说明。
- 用户端查询入口已接入框架 `useSubscribeMessage()`：
  - 现金支付创建订单前请求订阅 `hsx_phone_query_success,hsx_phone_query_fail`。
  - 积分查询扣积分前请求订阅 `hsx_phone_query_success,hsx_phone_query_fail`。
  - 用户拒绝订阅不阻断查询。

涉及文件：

- `niucloud/addon/hsx_phone_query/app/dict/notice/notice.php`
- `niucloud/addon/hsx_phone_query/app/dict/notice/weapp.php`
- `niucloud/addon/hsx_phone_query/app/dict/notice/wechat.php`
- `niucloud/addon/hsx_phone_query/app/dict/notice/sms.php`
- `niucloud/addon/hsx_phone_query/app/listener/notice/QuerySuccess.php`
- `niucloud/addon/hsx_phone_query/app/listener/notice/QueryFail.php`
- `uni-app/src/addon/hsx_phone_query/pages/index.vue`
- `niucloud/addon/hsx_phone_query/uni-app/pages/index.vue`

配置说明：

- 插件只负责把通知类型、变量、默认模板结构注册给框架。
- 是否开启小程序/公众号/短信通知、模板 ID、短信模板等，仍在框架后台通知配置中维护。
- 微信小程序真机发送订阅消息，需要站点已配置小程序 `app_id` / `app_secret`，会员有 `weapp_openid`，并且用户完成订阅授权。
- 微信模板 ID 不建议写死在插件业务代码里；如果微信后台已有合适模板，可以在框架通知配置中填写或同步。

验证结果：

- PHP 语法检查通过。
- 通知配置现在能被框架 `DictLoader('Notice')` 识别。
- 支付成功重复回调不重复查询。

当前结果：

- 配置字典 `HsxPhoneQueryConfigDict::normalizeConfig()` 已强制只有一个服务商渠道处于启用状态。
- 全新安装默认渠道已调整为 `3023_main`，未填写 3023 API Key 前不会误判可用；保存配置后才生效。
- 已新增 `ProviderChannelService`，集中处理服务商配置读取、当前生效渠道解析、接口映射匹配。
- 用户端分类接口和下单/执行查询共用 `ProviderChannelService`，减少“前台能显示、后端不能下单”或“后端能下单、前台不显示”的分叉风险。
- 用户端分类接口按当前 `default_channel_key`、渠道密钥就绪状态、接口映射启用状态、查询项目上架状态过滤。
- 用户端分类接口展示名称、售价、排序、参数名以后台查询项目表为准；内置接口目录只用于判断当前渠道支持哪些服务，避免后台人工配置被字典覆盖。
- 创建订单/积分查询时，后端会校验查询项目是否属于当前启用渠道；用户传入其他渠道项目 ID 会被拒绝。
- 支付成功回调已经避免 `QUERYING` / `SUCCESS` 状态重复查询。
- 已付款订单执行查询时按订单创建时保存的 `channel_key/service_code/query_param/unit_price/unit_cost` 快照执行，避免管理员中途切换渠道影响已付款用户。
- 订单表新增并写入 `endpoint_type`、`endpoint_value`，已付款订单执行查询时也固定使用创建订单时的接口值，避免管理员后改映射影响已付款用户。
- 接口日志绑定查询结果时使用订单站点 ID，不依赖回调请求上下文里的站点 ID。
- 支付回调/订单执行时读取配置、缓存、分类均优先使用订单中的站点 ID，避免回调请求上下文影响多站点数据。
- 积分查询在全部失败时会通过 `hsx_phone_query_refund` 返还本次扣除积分。
- 海报数据监听器在查询记录不存在时返回空数据，不再生成空报告；查询项目名称按当前站点读取。
- 还未完成：`HsxPhoneQueryService` 仍然偏大，后续需要按 provider、order、execution、stats 继续拆分，减少黑盒。
- PHP 全量语法检查通过。
- `admin/src/addon/hsx_phone_query` 与插件包 `admin` 目录保持一致。
- `uni-app/src/addon/hsx_phone_query` 与插件包 `uni-app` 目录保持一致。
- `app/upgrade` 目录下无升级 SQL 文件，当前仍按 `0.0.1` 全新安装处理。
- MySQL 提权连接后可用，已在临时库 `hsx_phone_query_verify` 验证：
  - `install.sql` 替换临时前缀后执行成功，创建 4 张表：查询项目、查询结果、查询订单、接口日志。
  - 订单表包含 `endpoint_type`、`endpoint_value`、`unit_price`、`unit_cost`、`success_count`、`fail_count` 等快照和统计字段。
  - 查询项目表唯一索引 `uk_site_channel_service(site_id, channel_key, service_code)` 存在。
  - `uninstall.sql` 替换临时前缀后执行成功，4 张临时表已清理。
  - 尚未使用真实 3023/爱查密钥发起外部接口查询，需重装后在页面配置密钥再做业务测试。

### 4. 后台体验重构

状态：`[doing]`

目标：

- 服务商配置页清楚展示“当前生效渠道”和“保存后生效”。
- 查询项页从“分类 CRUD”改为“售卖项目配置”。
- 查询记录页能看渠道、成本、利润、失败原因。
- 运营看板能看每日消耗、查询量、成本、利润。

计划页面：

- 服务商配置
- 查询项目
- 查询记录
- 运营看板
- 展示配置
- 推广奖励

当前结果：

- 查询项目编辑保存已补齐前后端闭环：
  - 后端编辑前先校验当前站点记录是否存在，不存在时返回明确错误。
  - 编辑接口不再默认把未提交的 `is_show` 改成 `1`、`sort` 改成 `0`，避免“只改价格却把隐藏状态/排序改掉”。
  - 服务层只保存允许修改的字段，减少前端多传字段造成的黑盒写入。
  - 编辑弹窗提交普通对象，不直接提交 Vue reactive 对象。
  - 编辑成功提示“查询项目已保存/已添加”，失败提示具体错误，并刷新当前页。
- 查询项目显示/隐藏已补齐交互闭环：
  - 后端 `modify_show` 会校验记录是否存在，不存在时返回错误。
  - 前端切换开关成功提示“查询项目已显示/已隐藏”。
  - 切换失败会提示错误，并重新拉取当前页，避免开关停留在错误的乐观状态。
- 查询项目排序已补齐交互闭环：
  - 后端 `modify_sort` 会校验记录是否存在，不存在时返回错误。
  - 单项排序和拖拽排序成功后提示“排序已保存”，失败后提示错误并刷新当前页。
- `admin/src/addon/hsx_phone_query` 与插件包 `admin` 目录保持一致。
- PHP 全量语法检查通过。

### 5. 用户端体验重构

状态：`[doing]`

目标：

- 查询页只展示当前渠道可用项目。
- 批量输入体验明确。
- 查询前展示数量、单价、总价。
- 详情报告更商业化。
- 分享图带邀请参数。

验证方式：

- `/api/hsx_phone_query/category` 请求一次即可满足页面渲染。
- 前端不展示后端未返回的任何查询项目。
- 旧项目 ID 创建订单会被后端拒绝。

当前结果：

- 用户端历史记录已从“结果表列表”改为“订单列表”：
  - 支付后即使还没有查询结果，也能看到订单状态。
  - 支持展示“已支付 / 查询中 / 查询成功 / 查询失败”。
  - 查询成功的订单挂载首条结果，可进入报告详情。
  - 查询失败、查询中、已支付未完成的订单不会跳转空详情页，会给出明确提示。
- 历史记录页已补齐用户感知：
  - 首屏加载中显示“正在读取查询记录...”。
  - 接口失败显示错误卡片和“重新加载”按钮。
  - 空列表才显示“暂无查询记录”，不再加载中直接空白。
  - 每条记录显示状态标签、支付金额/积分、查询次数、失败原因和退款状态。
- 失败退款闭环已补齐：
  - 积分查询全部失败时继续通过框架会员账户服务退积分，并把订单标记为已退。
  - 现金支付查询全部失败时通过框架 `CoreRefundService` 创建并发起原路退款，成功发起后标记订单已退。
  - 历史页根据 `refund_status` 显示“积分已退还 / 已原路退还 / 退款处理中”。
- `uni-app/src/addon/hsx_phone_query` 与插件包 `uni-app` 目录保持一致。
- PHP 全量语法检查通过。
- 当前本机 `http://localhost/api/hsx_phone_query/list` 端口 80 未响应，未能通过 curl 直接验证接口返回，需要在你正在运行的本地站点页面中测试。
- 查询结果汉化已改为后端统一字典：
  - 新增 `HsxPhoneQueryResultDict`，集中维护第三方字段名、常见英文值、国家/地区、状态值映射。
  - 新增 `QueryResultFormatter`，把原始 `info` 转成 `display_info`、`display_summary`、`display_status_tags`、`display_title`、`display_subtitle`、`display_image`。
  - 用户端详情接口和历史接口保留原始 `info`，同时返回后端格式化后的展示字段。
  - 后台查询记录列表/详情已接入同一套 `display_*` 数据。
  - 框架 poster 海报数据已接入同一套格式化服务，不再单独拼英文原始字段。
  - 用户端详情页优先渲染后端 `display_info`，前端格式化只作为旧数据兜底。
  - 已用小米保修样例验证：
    - `activated: true` => `已激活`
    - `coverage.status: Out Of Warranty` => `已过保`
    - `purchase.country: China` => `中国`
    - `activationlock.locked: false` => `未开启`

### 6. 推广奖励闭环

状态：`[todo]`

目标：

- 分享报告建立邀请关系。
- 支持注册奖励、首充奖励、首查奖励。
- 积分发放走框架会员账户服务。
- 后台可看奖励记录和邀请效果。

验证方式：

- A 分享报告给 B。
- B 注册后绑定 A 为上级。
- B 触发配置的奖励动作后，A 获得积分。

### 7. 全链路验证

状态：`[todo]`

验证清单：

- 服务商切换。
- 查询项过滤。
- 现金支付查询。
- 积分查询。
- 支付成功幂等。
- 第三方日志。
- 查询记录。
- 详情报告。
- 分享海报。
- 通知发送。
- 后台统计。
