# 第三方 API 配置中心 重构方案（控制层 / 服务层 / 模型层 + UX）

> 范围：服务能力中心（`/site/third_party/config`）下的 5 个能力——易速快递发件、快递查询、地址解析、设备查询、芯烨云打印。
> 目标：按"职责单一原则"把代码重构为清晰的 控制层 / 服务层 / 模型层；同时解决"用户体验极差"的问题。
> 本文档先求**对齐**，确认后再分阶段落地代码。

---

## 一、现状：完整流程（已摸清）

### 1.1 五个能力的当前链路

| 能力 | 配置存储 | 调用链路（执行时） | Provider |
| --- | --- | --- | --- |
| 易速快递发件 | `sys_config`(THIRD_PARTY 键) | 前端 → ExpressOrder 控制器 → `core/ExpressOrderService` → `CoreThirdPartyService::call()` → `provider/express_order/ProviderYisu` → 易速API；回调 `api/.../ExpressController@yisuPush` → `YisuExpressPushService` | ProviderYisu（**重复**：delivery/ + express_order/ 各一份） |
| 快递查询 | 同上 | 前端 → ExpressOrder@track → `CoreThirdPartyService::call()` → `provider/express_query/ProviderAliExpress` → 阿里云市场 | ProviderAliExpress |
| 地址解析 | 同上 | 前端 → `third_party/AddressParse@parse` → `core/address/AddressParseService`(混了本地 SysArea 匹配) → `CoreThirdPartyService::call()` → `provider/address_parse/ProviderTencentCloudMarketAddress` | 腾讯云市场 |
| 设备查询 | `sys_config`(DEVICE_QUERY 键，独立) | 前端质检弹窗 → `device_query_api/query` → `admin/device_query/DeviceQueryService`(一行转发) → **`CoreDeviceQueryService`(自带 selector/normalizer/price/recorder 引擎)** → `device_query/provider/PathQueryProvider|ServiceIdQueryProvider` → 3023/爱查；结果写 `third_party_device_query_result` 表并回填质检字段 | 自有引擎，**不走** CoreThirdPartyService |
| 芯烨云打印 | `sys_config`(THIRD_PARTY 键) | 打印场景 → `admin/printer/template/TemplatePrintService`(混了状态检查/XML/签名/HTTP) → 芯烨云 openapi | 不走 provider 体系，自己发 HTTP |

### 1.2 一句话总结现状的根本矛盾

> **存在两套并行的"第三方调用"架构，互不统一：**
> - **A 套（上帝类）**：`CoreThirdPartyService`(539 行) 一个类包揽 Provider 加载 / 主备切换 / 日志写入 / 成本统计 / 余额更新——快递发件、快递查询、地址解析走这套。
> - **B 套（分层清晰）**：`device_query/*` 自带 ConfigService / ChannelSelector / Normalizer / PriceCalculator / ResultRecorder / Provider 引擎——设备查询走这套，是五个里架构最好的。
> - **打印**则两套都不走，自己发 HTTP。

重构的总思路 = **把 A 套和打印，向 B 套（device_query 的分层范式）看齐，统一到一套清晰分层里**。

---

## 二、职责单一原则被违反的清单（按严重度）

### 高
1. **`CoreThirdPartyService` 上帝类**（`service/core/third_party/CoreThirdPartyService.php`，539 行）
   - Provider 加载/实例化/健康检查（119-183）、主备切换（254-321）、日志写入（352-379）、成本统计（391-436）、余额更新（446-476）全混在一个类。
2. **`TemplatePrintService` 跨域混杂**（`service/admin/printer/template/TemplatePrintService.php`）
   - 打印机状态检查(46-87)、XML 修复(90)、配置读取(92-108)、签名(113-114)、HTTP 调用(129) 全在一个方法链里。
3. **ProviderYisu 代码重复两份**（`provider/delivery/ProviderYisu.php` 与 `provider/express_order/ProviderYisu.php`，11KB/15KB，约 70% 相似），改一个漏一个。

### 中
4. **`BaseProvider` 不纯**：基类里塞了 HTTP/CURL 工具(91-195) + 日志(214-236)，Provider 无法独立测试。
5. **`RecycleThirdPartyConfigService` 配置逻辑过重**：merge(243-263)/normalize(265-292)/mask(294-308)/keepMaskedSecret(310-324)/required(340-367) 都在一个类。
6. **`ThirdPartyCapabilityService` 概览硬编码**：5 个 `buildXxxCapability()` 高度重复，能力名/必填字段/动作列表硬编码在代码里，新增能力要改多处。
7. **`AddressParseService` 混本地业务**：第三方调用 + 本地 SysArea 地区匹配揉在一起。
8. **`CoreDeviceQueryService` 主方法偏长**（query() 24-130），编排 + 参数解析 + 缓存 + 记录都在内（这套已较好，属优化级）。

### 低 / 命名混乱（强烈建议先清理）
9. **三个重名/误导的 DeviceQueryService**：
   - `service/admin/DeviceQueryService.php`（旧版，建议弃用/改名 Legacy）
   - `service/admin/device_query/DeviceQueryService.php`（新版转发，保留）
   - `service/api/DeviceQueryService.php`（**其实是快递查询**，应改名 `ExpressQueryService`）
10. **新旧模型双份**：`model/third_party/DeviceQuery*` 与 `model/DeviceQuery*` 并存。
11. 控制器写业务：`yisu/Yisu.php@createOrder`(17-68)、`api/.../ExpressController@yisuPush`(155-183) 在控制器里做参数转换/payload 规范化。

---

## 三、目标架构（按 SRP 分层）

统一为四层，**所有第三方能力共用同一套"调用底座"**：

```
控制层 Controller            只做：取参 → 调 Service → 返回。零业务逻辑。
  └─ adminapi/controller/third_party/*  + 各能力控制器

服务层 Service（分三类，单一职责）
  ├─ 能力服务 CapabilityService   每个能力一个，编排自己的业务
  │    ExpressOrderService / ExpressQueryService / AddressParseService
  │    / DeviceQueryService / PrinterService
  ├─ 调用底座（从上帝类拆出，5 个小类）
  │    ProviderFactory      ——按 serviceType+provider 造 Provider
  │    ProviderRouter       ——主备选择 + 健康检查
  │    ApiCallLogger        ——写 ThirdPartyApiLog
  │    CostStatsAggregator  ——写 ThirdPartyCostStats
  │    ThirdPartyInvoker    ——编排上面四个，对外只暴露 call()
  └─ 配置服务（从 RecycleThirdPartyConfigService 拆出）
       ConfigRepository  / ConfigMerger / SecretMasker / ConfigValidator
       + CapabilityRegistry（能力元数据：名称/必填/动作，配置化，替代硬编码）

适配层 Provider（纯净）
  ├─ contract/ProviderInterface       只声明业务方法
  ├─ http/HttpClient + AuthStrategy   HTTP/签名独立，可复用
  └─ provider/<serviceType>/Provider* 只写"如何拼参数、如何解析返回"
       —— 合并重复的 ProviderYisu 为一份

模型层 Model（每表一模型，零业务）
  ThirdPartyApiLog / ThirdPartyCostStats / ThirdPartyService
  ExpressOrderRecord / ExpressAddressBook / YisuProductConfig
  DeviceQueryResult / RecyclePrinter / RecyclePrintScene / ...
  —— 清理新旧重复模型，保留 model/third_party/* 一套
```

要点：
- **以 device_query 的分层为范本**，把快递/地址/打印改造成同构结构（Provider 纯净、底座统一、能力服务只编排）。
- **CapabilityRegistry 配置化**：把"5 个能力的名称、provider、必填字段、动作、配置在哪个页面维护"集中成一份元数据，概览/校验/前端 tab 全部由它驱动，新增能力只改一处。

---

## 四、UX 重构方案

对应上一轮发现的体验问题，落到具体改造：

1. **"恢复默认 + 保存"会清空密钥/设备查询配置** → 恢复默认时跳过密钥与 device_query（只重置地址/路径/超时类非敏感项）；确认弹窗文案明确写"会清空已填密钥"。
2. **密钥无法区分已配置/未配置** → 密钥框 placeholder 写"已配置，留空不修改"；旁边加"已配置 ✓ / 未配置"标记（复用概览 `missing_fields`）。
3. **保存动作混乱** → 顶部按钮改"保存接口配置"，产品/设备查询等就近独立保存；视觉分区。
4. **能力"半在本页半在别处"割裂** → device_query / 打印模板这类在别处维护的，卡片明确标注"配置在 XX 页面，此处仅看状态"，并从全局保存里剥离（同时根治问题 1 的清空风险）。
5. **配完无法验证** → 每个能力加"测试连接"按钮，接到已有的 `third_party/Test` 测试控制器，即时返回成功/失败与错误信息。
6. **能力卡片点击依赖 key===tabname** → 由 CapabilityRegistry 统一 key，点击做映射兜底。
7. **无保存前校验 + 接口失败无提示** → 用 CapabilityRegistry 的必填规则做前端校验并定位到对应 tab；加载失败给错误提示。
8. **设备查询 1500 行 composable / CheckDeviceDialog 关注点混杂** → 拆分为多个小 composable（加载/对话框/导入/质检内查询各一），降低维护成本。

---

## 五、分阶段执行计划（建议顺序，可逐阶段确认）

> 原则：**先低风险清理与 UX，再动后端架构**；每阶段独立可验收、可回滚。

- **阶段 0 安全网**：跑通 `php -l`/现有用例，记录 5 个能力当前行为基线（手动验收清单）。
- **阶段 1 UX（纯前端，收益最快、风险最低）**：恢复默认不清密钥(1) + 密钥状态提示(2) + 测试连接(5) + 保存校验/失败提示(7) + 保存按钮分区(3)。
- **阶段 2 命名/重复清理（低风险）**：合并两份 ProviderYisu(3 高)；改名 `api/DeviceQueryService`→`ExpressQueryService`(9)；旧版 `admin/DeviceQueryService` 标 Deprecated；清理新旧重复模型(10)。
- **阶段 3 拆调用底座（核心）**：把 `CoreThirdPartyService` 拆成 Factory/Router/Logger/StatsAggregator/Invoker(1 高)；Provider 抽出 HttpClient+AuthStrategy，使 BaseProvider 变纯(4)。
- **阶段 4 配置与概览**：拆 `RecycleThirdPartyConfigService`(5)；建 `CapabilityRegistry`，概览/校验/前端 tab 改为元数据驱动(6)。
- **阶段 5 能力服务收尾**：地址解析抽 `AreaMatcher`(7)；打印 `TemplatePrintService` 拆出 `PrinterApiService` 专管通信/签名(2 高)；控制器去业务化(11)。
- **阶段 6 前端深拆**：设备查询大 composable / CheckDeviceDialog 拆分(8)。

---

## 六、已确认的架构决策

1. **能力 / 服务商分层（已定）**：业务只依赖"能力"的统一契约，不认识具体服务商。每个能力下可挂多个服务商，服务商是配置行（启用 + 指定默认 + 凭证 + 参数 + 声明支持的子能力）。差异由"入参适配 + 出参归一"吸收。
2. **选择策略 = 严格手动指定（已定）**：每个能力**只用商户指定的那一个服务商**，**不做自动故障转移**（成本可控，挂了由人工切换，客户会反馈）。选择器只负责"取指定的那家 + 校验它是否启用/健康"，不顺延别家。
3. **统一契约字段口径（已定）**：按业务实际要用的字段定义每个能力的统一出参；服务商多出来的字段进"原始报文"存档备查。
   - 设备查询：`保修状态 / 到期日 / 激活锁 / MDM`
   - 快递发件：`运单号 / 费用 / 状态 / 轨迹 / 面单`
   - 快递查询：`物流状态 / 轨迹节点[]`
   - 地址解析：`姓名 / 电话 / 省市区 / 详细地址`

待你定的仅剩执行层面：执行顺序、起步范围、是否允许删除废弃旧类、测试账号。

---

## 七、快递100（Kuaidi100）接入规格

> 结论：快递100 **同时具备"快递查询"和"快递发件"两种能力**，因此作为**可切换服务商**分别挂到这两个能力下，与易速、阿里并列。它的鉴权和入参与现有服务商完全不同 —— 正好印证"每个服务商配置 schema 各异"。
> 来源：快递100 官方 API 文档（见文末）。

### 7.1 能力一：快递查询（express_query）——「实时快递查询」接口

- 请求地址：`POST https://poll.kuaidi100.com/poll/query.do`，`Content-Type: application/x-www-form-urlencoded`
- 鉴权/入参（form 三字段）：
  - `customer`：授权码（企业版）
  - `sign`：`MD5(param + key + customer)` 转 **32 位大写**（`signType` 可选 MD5/SHA256/SM3，默认 MD5）
  - `param`：JSON 串，字段 `{com 快递公司编码(小写), num 单号, phone, from 出发地, to 目的地, resultv2, show, order}`
- 关键返回：`state`(物流状态)、`com`、`nu`、`data[]{ftime,time,context,status,...}`；`503`=验签失败。
- **服务商配置 schema（query）**：`{ base_url, customer, key, sign_type(默认MD5), resultv2(默认0/4), timeout }`
- 归一化到统一契约：`data[]` → `轨迹节点[]{时间, 描述, 状态}`，`state` → `物流状态`。

### 7.2 能力二：快递发件（express_order）——「电子面单下单」接口

- 请求地址：`POST https://api.kuaidi100.com/label/order`
- 鉴权/入参：
  - `method`：业务类型，默认 `order`
  - `key`：企业授权 key
  - `t`：时间戳
  - `sign`：`MD5(param + t + key + secret)` 转 **大写**；`secret` 在企业管理后台获取
  - `param`：JSON 串，关键字段 `{kuaidicom 快递公司编码, recMan{} 收件人, sendMan{} 寄件人, cargo 物品, weight, tempId 面单模板, printType(IMAGE/HTML/CLOUD), siid 云打印机设备码, ...}`
- 关键返回：`kuaidinum`(运单号)、`label`(面单短链, IMAGE/HTML 时)、各家快递 `data` 字段不一。
- 取消：`https://api.kuaidi100.com/label/order`（不同 method）。
- **服务商配置 schema（order）**：`{ base_url, key, secret, default_kuaidicom, tempId, printType, siid, timeout }`
- 归一化到统一契约：`kuaidinum` → `运单号`，`label` → `面单`，状态/费用按返回映射。

### 7.3 注意两套鉴权完全不同（架构价值的实证）

| | 快递查询(query.do) | 电子面单(label/order) | 现有易速 |
| --- | --- | --- | --- |
| 凭证字段 | customer + key | key + secret | appid + app_secret |
| 签名公式 | MD5(param+key+customer) | MD5(param+t+key+secret) | 易速自有签名 |

→ 所以服务商配置**不能用同一张表/同一组字段**，必须"每服务商一份 schema"。这正是新架构里 `provider 配置行 + 适配器`要解决的。接快递100 = 加两行服务商配置（query 一行、order 一行）+ 写两个适配器类 `ProviderKuaidi100`（分别在 express_query / express_order 目录下），业务层与前端零改动，商户在配置中心把默认服务商从"易速/阿里"切到"快递100"即可。

### 7.4 参考文档
- 实时快递查询接口：https://api.kuaidi100.com/document/5f0ffb5ebc8da837cbd8aefc
- 电子面单下单接口(V2)：https://api.kuaidi100.com/document/dianzimiandanV2
- 订阅推送接口：https://api.kuaidi100.com/document/60509440a62a19500e1987b7
- 快递公司编码字典：https://api.kuaidi100.com/document/5f0ff6e82977d50a94e10237.html
