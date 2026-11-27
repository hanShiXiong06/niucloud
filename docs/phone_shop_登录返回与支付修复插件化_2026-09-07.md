# phone_shop：登录返回与支付修复收回插件层

日期：2026-09-07。

本说明替代同日《主页返回与支付金额核对》中关于必须修改公共登录、公共支付文件的部署要求。

## 结果和边界

- 本次登录返回、商城收银台金额核对不再要求修改框架源码；前后端业务实现均在 phone_shop 插件目录。
- 复用原公共登录页、登录方式选择、原收银台及支付渠道；没有复制整套公共实现，也没有覆盖公共支付路由。
- 不新增数据库表、字段，不回填历史订单，不升级依赖。
- 未连接数据库、创建订单或调用支付；没有做浏览器操作和生产发布。按用户要求仅走查代码、执行静态检查，实际业务验收由用户完成。
- 工作区此前另一个 DIY 组件重名任务仍有 `niucloud/app/service/core/addon/WapTrait.php` 的框架修改，本次没有撤销或改写它。因此不能把整个工作区说成“所有任务都已零侵入”。

## 登录返回

1. 商品分类、list、详情的插件门禁继续读取后台开关；需要登录时，检查阶段只显示 loading。
2. 打开公共登录页之前，插件先退回最近的可访问来源页，再调用原登录能力。这样取消登录会回到真实来源，而不是再次进入受限页。
3. tabbar/reLaunch 清空了历史时，先建立 phone_shop 首页，再打开登录页。公共标题栏的返回键因此有实际可返回的首页，不再需要在公共登录页补首页图标。
4. 登录成功仍返回原商品页及入口参数。如果登录选择、手机号登录或注册形成多层登录页，只清理本插件发起的这段登录历史。H5 等待实际页面栈变化后才继续跳转。
5. 不改全局 tabbar，不替换框架的账号密码、短信、微信授权或绑定手机号实现。

新增 `usePhoneShopLoginNavigation.ts` 管理上述流程，入口为已有的 `useGoodsPageAccess.ts`。没有新增页面，不需要修改框架 pages 配置。

## 支付链路

### 客户端

三个普通商城订单入口使用插件 `PhoneShopPay.vue` 包装原公共收银台。页面样式、渠道唤起及支付结果处理仍由原组件负责。

插件通过 uni 的请求拦截扩展，为当前收银台的本站公共支付请求补充 `phone_shop_expected_money`，值来自收银台实际展示的金额；不改请求地址，不把客户端金额用作定价依据。拦截同时限制 API 地址、POST 方法、业务类型和收银台订单，离开页面、关闭、卸载时解除，重新显示时恢复。[uni-app 官方拦截器说明](https://uniapp.dcloud.net.cn/api/interceptor)

### 服务端

```text
框架 HttpRun 事件
  → 注册插件控制器中间件
  → 公共支付路由原有的站点、渠道、会员认证
  → 插件仅检查 API 的 pay.Pay 控制器、pay/info 操作、phone_shop 业务
  → 取得本订单互斥锁，核对订单、开关、现有支付单及展示金额
  → 继续执行原公共支付控制器和支付渠道
  → finally 释放锁
```

- 注册使用框架已有事件，不依赖上一版新增的 `PayBefore` 事件；路由缓存开启时也会注册。
- 普通商城订单的公共支付请求都经过服务端检查，不仅限于更新后的三个页面。旧页面、帮付入口不携带展示金额时，服务端仍检查订单状态、线上支付规则和支付单金额一致性；它们没有新增的“最后展示金额”比对，不能把这部分说成与新页面完全相同。
- `phone_shop_expected_money` 存在且与订单不一致时，停止发起付款并要求刷新确认；它不是用户可修改的订单金额。
- 后台改价和公共支付使用相同的订单命名锁，避免检查金额后又被并发改价。锁按数据库、表前缀、站点及订单隔离，等待最多 3 秒后给出明确提示。
- 使用 MySQL 已有 `GET_LOCK` / `RELEASE_LOCK`，不建表；不把整个支付调用包进新增数据库事务，避免新支付单在网关回调时尚不可见。基础设施需要允许这两个 MySQL 函数；不可用时会阻止本次操作，不静默跳过校验。
- 微信/支付宝扣款、其他插件业务和回调路由不被这个中间件接管。原插件支付结果金额核对、合法后台线下收款与零元单路径保留。

## 本轮部署文件

以下路径以项目根目录为基准。前端运行源码与插件发布源码必须配套。

### 后端插件

| 路径 | 作用 |
| --- | --- |
| `niucloud/addon/phone_shop/app/event.php` | 改为通过 HttpRun 注册插件中间件 |
| `niucloud/addon/phone_shop/app/listener/pay/RegisterPaymentGuard.php` | 新增：注册入口 |
| `niucloud/addon/phone_shop/app/middleware/PhoneShopPaymentGuard.php` | 新增：公共支付入口的商城守卫 |
| `niucloud/addon/phone_shop/app/service/core/order/CoreOrderPaymentGuardService.php` | 新增：金额核对和并发互斥 |
| `niucloud/addon/phone_shop/app/service/admin/order/OrderService.php` | 后台改价使用同一订单互斥锁 |
| `niucloud/addon/phone_shop/app/listener/pay/PayCreateListener.php` | 委托同一 Core 服务检查可支付规则，监听器不重复实现业务 |

上一轮修复仍须保留并配套部署：

- `niucloud/addon/phone_shop/app/service/core/order/CoreOrderCreateService.php`
- `niucloud/addon/phone_shop/app/api/controller/order/OrderCreate.php`
- `niucloud/addon/phone_shop/app/service/core/order/CoreOrderPayService.php`

`app/listener/pay/PayBeforeListener.php` 是上一版新建的监听器，本轮已移除注册和本地文件，不再依赖它。

### 移动端插件

以下 7 个相对路径同时位于 `uni-app/src/addon/phone_shop/` 和 `niucloud/addon/phone_shop/uni-app/`：

1. `hooks/useGoodsPageAccess.ts`
2. `hooks/usePhoneShopLoginNavigation.ts`（新增）
3. `hooks/usePhoneShopPaymentAmount.ts`（新增）
4. `components/PhoneShopPay.vue`（新增）
5. `pages/order/payment.vue`
6. `pages/order/list.vue`
7. `pages/order/detail.vue`

原商品分类、详情、list 入口修复及 `PhoneGoodsAccessState.vue` 继续保留。如果上一版尚未发布，应一并带上此前这些插件文件。修改移动端后重新生成 H5/小程序产物；本轮不需要 admin 前端重打包。

### 之前的框架文件怎么处理

本地以下 8 个文件已恢复到本次修复前的版本，代码及换行格式与当前 Git HEAD 相同：

1. `niucloud/app/api/controller/pay/Pay.php`
2. `niucloud/app/service/api/pay/PayService.php`
3. `niucloud/app/service/core/pay/CorePayService.php`
4. `uni-app/src/components/pay/pay.vue`
5. `uni-app/src/components/top-tabbar/top-tabbar.vue`
6. `uni-app/src/app/pages/auth/index.vue`
7. `uni-app/src/app/pages/auth/login.vue`
8. `uni-app/src/hooks/useLogin.ts`

本次此前新建的 `uni-app/src/hooks/useBackOrHome.ts` 和 `uni-app/src/components/page-home/page-home.vue` 已删除，没有引用残留。

如果线上没有发布过上述公共文件改动，本次不用替换它们。如果线上已经发布过，要彻底回到框架原版，需要一次性恢复这 8 个文件，并确认没有其他线上独有改动；之后这项修复不再依赖修改框架。不要用整目录覆盖线上其他修改。

注意：本地 `niucloud/addon/phone_shop/` 被 Git 忽略，不能只靠 Git 变更列表挑选发布文件。若启用 OPcache/事件缓存，按现有部署流程刷新，确保新插件事件注册生效。

## 静态检查与待验收

检查脚本：`tests/phone_shop_plugin_boundary_static.cjs`，只读取和解析文件，不运行页面、业务服务或数据库。

- 检查 H5、微信小程序、App 的条件编译后 Vue 模板与脚本、TypeScript 语法。
- 检查 7 对运行/发布源码一致。
- 检查上述 8 个公共文件已恢复、不再引用删除的公共首页组件。
- 检查事件注册、包装组件引用，以及支付/改价共同使用订单锁的代码约束。
- PHP 文件另做 `php -l` 语法检查。

本次执行结果：40 项静态检查通过，9 个配套 PHP 文件语法检查通过；两个已有模拟回归脚本已更新依赖，仅检查脚本语法，没有执行模拟流程。未做整包构建，静态解析不能保证打包及真机链路已通过。

以下尚待实际验收，不能以静态通过代替：

| 场景 | 预期 |
| --- | --- |
| H5/小程序从有历史的分类、list、详情进入登录后取消 | 回到来源页，不反复跳登录；能保留来源页状态 |
| tabbar/reLaunch 或直接链接进入受限页 | 登录前建立商城首页；返回时进入 phone_shop 首页 |
| 账号登录、手机号登录、注册、微信授权后返回 | 返回指定商品页及参数；再次返回不进入残留登录页 |
| 登录期间网络失败/快速离开，登录过期或门禁请求失败 | 不展示未授权商品；有明确重试入口，不突然跳转其他页面 |
| 三个订单入口打开收银台后，后台改价 | 旧展示金额不能直接按新金额付款，要求刷新确认 |
| 支付和改价同时操作 | 一个操作先完成，另一个等待或提示稍后重试，不出现混合金额 |
| 直接调用公共支付接口、不带新增参数 | 不得绕过商城订单状态、支付开关和服务端金额一致性校验 |
| 帮付、支付取消后重试、从微信/其他应用返回 | 原流程可用，实际收款与订单一致；新页面恢复展示金额核对 |
| 普通价/会员价/新人价、多件商品、B/C 端手续费 | 确认页、收银台、实际收款及订单实收一致 |
| 非 phone_shop 插件、退款回调、后台线下确认、零元订单 | 不受本次公共支付中间件接管 |

本次没有执行真实订单和支付测试，也没有把上述待验收项目标记为通过。
