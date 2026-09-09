# phone_shop：主页返回与支付金额核对

日期：2026-09-07。

> 后续已将本次登录返回和支付守卫收回插件层。下文关于“必须修改公共框架文件”的方案已被替代，发布请以 [插件化说明](/Users/a123/Documents/1-work/niucloud/niucloud/docs/phone_shop_登录返回与支付修复插件化_2026-09-07.md) 为准，不再按本文件旧清单给公共框架增加扩展。

本次按要求只走查和修改代码，做语法检查；没有启动业务测试，没有创建订单、操作支付、修改数据库或部署线上。实际验收由用户完成。

## 插件边界说明（按“尽量不入侵框架”调整）

- 商城首页地址由插件的商品入口 hook 写入登录返回信息 `homeUrl`，公共返回组件不再识别 `phone_shop` 或硬编码商城路由。
- 是否核对收银台金额，由商城确认订单、订单列表、订单详情传入 `verifyAmount` 开启；公共收银台默认关闭此可选能力，不再判断插件名称。
- 订单定价、手续费、商城支付开关与订单金额核对，仍在插件中实现。框架只保留通用参数传递、确认金额比较和支付前事件。
- 仍有框架修改，**不是零侵入**：登录页本身属于框架，需要接入首页按钮；实际支付由框架发起，复用旧支付单时不一定触发 `PayCreate`，因此需要一个通用支付前校验入口。具体文件见下方，后续框架升级要保留这些扩展点。
- 本次没有增加插件私有登录页、复制整套框架收银台，也没有覆盖框架支付路由。

## 结论与改动

### 1. tabbar 进入登录后没有上一页

当前 tabbar 使用 `reLaunch`，会清空页面栈。保持 tabbar 本身不变：

- 登录页有上一页时保留原返回行为。
- 登录页没有上一页时提供“首页”入口；从 phone_shop 进入登录，则返回手机商城首页，不再误跳到另一个 shop 插件。
- H5 原生标题栏在根页面不提供返回键，因此补充首页按钮；小程序自定义标题栏切换为首页图标。
- 商品 list 页没有上一页时也提供商城首页入口。
- 登录成功仍沿用之前已完成的登录返回逻辑，回到原商品入口及其参数；权限校验仍只显示 loading。

### 2. 支付金额

当前本地代码中，普通会员价、展示价和下单价已经共用价格解析服务，没有发现再次乘一次会员折扣的代码；微信支付的元转分也只执行一次。这不能直接证明线上已部署同一版本，也不能据此认定此前“约便宜一半”的具体原因。

本次修正静态走查中确认存在的问题：

| 问题 | 修改后的行为 |
| --- | --- |
| 多次试算共用缓存编号，可能覆盖客户看到的报价；中间还会写入不完整缓存 | 每次试算独立保存，只有计算完成的快照才能下单 |
| 旧请求后返回可能覆盖新结果，计算失败后仍能使用旧金额 | 前端仅接收最新请求；计算中、计算失败时禁止提交，并提供重试入口 |
| 提交时没有核对客户最后看到的金额 | 客户端携带确认金额，仅用于与服务端报价比较，不参与服务端定价 |
| 同一个 SKU 重复传入，金额累加但明细被覆盖；未明确拒绝非正整数数量 | 服务端拒绝重复 SKU 和无效数量，并限定购物车记录属于当前会员 |
| 新人活动在最终提交时跳过价格校验 | 使用现有新人活动计算逻辑重新校验资格、单价和商品小计，不覆盖已确认快照 |
| 后台改价只改变订单金额，遗漏手续费及商家净额 | 按订单原有身份、费率和承担方重新计算金额快照；锁定订单后再次检查状态 |
| 已存在的支付单可能跳过创建支付单时的业务校验 | 真正发起支付前，通过插件监听器重新核对订单、支付开关和支付单金额 |
| 客户打开收银台后，后台又改价，可能按不同于收银台显示的金额付款 | 核对收银台显示金额；不一致时停止发起付款，提示关闭收银台并刷新订单 |
| 支付成功直接将应付金额记为实收，没有核对支付流水 | 普通商城订单核对本站、本订单、已完成支付流水的金额；不一致时不标记付清，记录日志并提示勿重复付款 |

保留合法的后台线下确认收款和零元订单流程。第三方托管订单的支付流水仍归源业务处理，本次没有将秒杀等源业务流水强行当成 phone_shop 的支付单。

## 本次需要更新的文件

以下是本次改动清单，不包含此前已有的其他未提交修改。没有新增表、字段或 SQL 升级。

### PHP：必须配套更新

插件 7 个文件：

1. [下单计算与快照校验](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/app/service/core/order/CoreOrderCreateService.php)
2. [订单创建接口参数](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/app/api/controller/order/OrderCreate.php)
3. [后台订单改价](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/app/service/admin/order/OrderService.php)
4. [支付单创建校验](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/app/listener/pay/PayCreateListener.php)
5. [发起支付前校验（新增）](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/app/listener/pay/PayBeforeListener.php)
6. [插件事件注册](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/app/event.php)
7. [支付结果金额核对](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/app/service/core/order/CoreOrderPayService.php)

框架支付入口 3 个文件（确实有修改，不能漏传）：

1. [支付 API 控制器](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/app/api/controller/pay/Pay.php)：接收收银台确认金额。
2. [支付 API 服务](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/app/service/api/pay/PayService.php)：透传确认金额。
3. [支付 Core 服务](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/app/service/core/pay/CorePayService.php)：发起支付前校验确认金额，提供 `PayBefore` 通用事件；商城规则保留在插件内。

### 移动端：更新源码后重新打包发布

商城 5 个文件：

1. [商品列表](/Users/a123/Documents/1-work/niucloud/niucloud/uni-app/src/addon/phone_shop/pages/goods/list.vue)
2. [确认订单](/Users/a123/Documents/1-work/niucloud/niucloud/uni-app/src/addon/phone_shop/pages/order/payment.vue)
3. [订单列表](/Users/a123/Documents/1-work/niucloud/niucloud/uni-app/src/addon/phone_shop/pages/order/list.vue)
4. [订单详情](/Users/a123/Documents/1-work/niucloud/niucloud/uni-app/src/addon/phone_shop/pages/order/detail.vue)
5. [商品入口权限 hook：声明商城首页](/Users/a123/Documents/1-work/niucloud/niucloud/uni-app/src/addon/phone_shop/hooks/useGoodsPageAccess.ts)

框架公共部分 6 个文件：

1. [返回或首页逻辑（新增）](/Users/a123/Documents/1-work/niucloud/niucloud/uni-app/src/hooks/useBackOrHome.ts)
2. [H5 根页面首页按钮（新增）](/Users/a123/Documents/1-work/niucloud/niucloud/uni-app/src/components/page-home/page-home.vue)
3. [公共顶部导航](/Users/a123/Documents/1-work/niucloud/niucloud/uni-app/src/components/top-tabbar/top-tabbar.vue)
4. [登录入口页](/Users/a123/Documents/1-work/niucloud/niucloud/uni-app/src/app/pages/auth/index.vue)
5. [账号/手机号登录页](/Users/a123/Documents/1-work/niucloud/niucloud/uni-app/src/app/pages/auth/login.vue)
6. [公共收银台](/Users/a123/Documents/1-work/niucloud/niucloud/uni-app/src/components/pay/pay.vue)

同时已同步插件发布源码，避免后续插件安装覆盖修复：

- [插件内商品列表](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/uni-app/pages/goods/list.vue)
- [插件内确认订单](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/uni-app/pages/order/payment.vue)
- [插件内订单列表](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/uni-app/pages/order/list.vue)
- [插件内订单详情](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/uni-app/pages/order/detail.vue)
- [插件内商品入口权限 hook](/Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/phone_shop/uni-app/hooks/useGoodsPageAccess.ts)

注意：本地 phone_shop 插件目录被 Git 忽略，不能仅凭 Git 变更列表判断是否上传完整。

## 发布注意

- 这次不是只更新 `list.vue`，也不是只更新 PHP。按上面的清单配套更新后端、移动端源文件，再发布对应 H5/小程序产物；不需要重新打包 admin。
- 如生产启用了 PHP OPcache 或事件缓存，按现有发布流程刷新对应缓存/进程，确保新的事件监听器已加载。
- 升级前已经打开的确认订单页可能提示“金额尚未计算完成/凭证过期”，让客户重新进入确认订单页计算即可。没有进行历史订单回填或兼容迁移。
- 此前 `useLogin.ts` 的登录返回修复保留；如果尚未上传，仍需包含上一版的文件。商品入口权限 hook 本次新增了首页声明，需使用这次的版本。

## 检查状态与建议验收

已做：PHP 语法检查；H5/微信小程序条件编译后的 Vue 模板、脚本及 TypeScript 静态语法检查；商城运行源码与插件发布源码一致性检查。

未做：整包构建、浏览器操作、数据库/接口联调、真实支付、退款、订单测试。以下是待用户验收的场景，不代表已经通过：

1. H5 和小程序：通过 tabbar 进入受限分类/list，再进入登录；无历史时能回商城首页，有历史时能正常返回。
2. 普通价、会员价、新人价：单件/多件的商品小计、优惠、运费、最终金额与预期一致。
3. 同行客户承担手续费、商家承担手续费，以及零售客户：金额规则与后台配置一致；线下订单不追加线上手续费。
4. 快速切换支付方式、地址、优惠券：计算中不能提交；网络失败可重新计算，旧结果不会覆盖最新结果。
5. 待付订单打开收银台后在后台改价：旧收银台不能直接按变化后的金额扣款；重新打开后看到新金额。
6. 支付完成后核对订单实收与支付流水；后台线下确认收款、零元订单及 ERP 同步分别验收。

发现异常时优先提供：订单号、站点、购买账号等级、商品小计/优惠/运费/手续费/应付金额，以及收银台显示金额。金额核对失败日志前缀为 `[phone_shop 支付金额核对失败]`。请勿提供支付密钥。
