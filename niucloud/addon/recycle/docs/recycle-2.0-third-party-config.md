# 回收插件 2.0 第三方配置中心改造说明

## 改造目标

| 目标 | 处理方式 |
| --- | --- |
| 第三方接口配置不要散落在代码、字典、旧配置表里 | 新增统一配置中心，配置保存到 `sys_config` 的 `recycle_third_party_config` |
| 快递服务只保留亿速 | 主流程、服务商字典、第三方 provider 选择都只允许 `yisu` |
| 保留旧数据兼容 | 未保存新配置的站点继续读取旧 `third_party_service` / `device_query_config` / `express_provider_config` |
| 新配置生效后行为清晰 | 站点一旦保存过 `recycle_third_party_config`，第三方调用优先且仅按配置中心执行 |
| 第三方接口地址可维护 | 亿速、阿里快递查询、3023、芯烨云打印的接口地址和超时配置都集中在配置中心 |

## 本次主要改动

| 模块 | 文件 | 改动 |
| --- | --- | --- |
| 配置键 | `app/dict/config/RecycleConfigKeyDict.php` | 新增 `THIRD_PARTY = recycle_third_party_config` |
| 核心配置服务 | `app/service/core/third_party/RecycleThirdPartyConfigService.php` | 新增默认配置、读取、保存、脱敏、保留 masked 密钥、按服务类型取 provider 配置 |
| 管理端配置服务 | `app/service/admin/third_party/RecycleThirdPartyConfigService.php` | 管理端封装配置中心读写 |
| 管理端接口 | `app/adminapi/controller/third_party/ThirdPartyConfig.php` | 新增获取配置、保存配置、获取默认配置接口 |
| 管理端路由 | `app/adminapi/route/route.php` | 新增 `GET/POST recycle/third_party_config` 和 `GET recycle/third_party_config/default` |
| 管理端页面 | `admin/src/addon/recycle/views/third_party/config.vue` | 新增“第三方配置中心”页面，分为亿速快递、快递查询、3023 查询、芯烨云打印 |
| 管理端 API | `admin/src/addon/recycle/api/third_party.ts` | 新增配置中心接口方法 |
| 菜单 | `app/dict/menu/site.php` | 在“第三方服务”下新增“配置中心”入口和权限节点 |
| 第三方调用壳 | `app/service/core/third_party/CoreThirdPartyService.php` | 优先使用 `sys_config` 配置中心；未保存新配置时继续旧表兜底 |
| 亿速 provider | `provider/express_order/ProviderYisu.php` | 去掉 provider 内部默认接口地址，配置不完整时直接报错 |
| 阿里快递查询 provider | `provider/express_query/ProviderAliExpress.php` | 支持 `api_path`，配置不完整时报错，健康检查不再发真实测试请求 |
| 3023 provider | `provider/device_query/Provider3023.php` | 去掉 provider 内部默认接口地址，健康检查要求 `base_url + api_key` |
| 快递主流程 | `app/service/core/express/RecycleExpressService.php` | 若配置中心已保存，则按配置中心判断亿速是否可用，不再依赖旧快递服务商表 |
| 打印 | `app/service/admin/printer/template/TemplatePrintService.php` | 芯烨云标签打印接口地址、路径、超时改为从配置中心读取 |
| 快递查询兼容接口 | `app/service/api/DeviceQueryService.php`、`app/service/admin/DeviceQueryService.php` | 快递轨迹查询优先读取配置中心的阿里快递配置，旧表兜底 |
| 第三方字典 | `app/dict/third_party/ThirdPartyDict.php` | 快递下单 provider 列表移除安果，只保留亿速 |

## 配置结构

配置写入 `sys_config.value`，key 为 `recycle_third_party_config`：

```json
{
  "express_order": {
    "enabled": 1,
    "provider": "yisu",
    "yisu": {
      "base_url": "http://open.yisuopen.com",
      "appid": "",
      "app_secret": "",
      "version": "V1.0",
      "timeout": 30
    }
  },
  "express_query": {
    "enabled": 1,
    "provider": "ali_express",
    "ali_express": {
      "base_url": "https://kzexpress.market.alicloudapi.com",
      "api_key": "",
      "api_path": "/api-mall/api/express/query",
      "timeout": 30
    }
  },
  "device_query": {
    "enabled": 1,
    "provider": "3023",
    "3023": {
      "base_url": "http://api.3023data.com",
      "api_key": "",
      "timeout": 30
    }
  },
  "printer": {
    "enabled": 1,
    "provider": "xpyun",
    "xpyun": {
      "base_url": "https://open.xpyun.net/api/openapi",
      "print_label_path": "/xprinter/printLabel",
      "timeout": 30,
      "connect_timeout": 10
    }
  }
}
```

## 测试建议

| 测试项 | 操作 | 预期结果 |
| --- | --- | --- |
| 配置保存 | 后台进入“第三方服务 > 配置中心”，填写四类配置并保存 | `sys_config` 新增或更新 `recycle_third_party_config` |
| 密钥脱敏 | 保存后刷新页面 | `api_key`、`app_secret` 显示为 `******`，再次保存不会清空真实密钥 |
| 亿速报价 | 创建回收订单前触发快递报价 | 使用配置中心的亿速 `base_url/appid/app_secret` 请求 |
| 亿速下单 | 用户端或后台发起平台快递下单 | 只允许亿速，返回运单号后写入订单和快递记录 |
| 亿速关闭 | 将“亿速快递”禁用后再报价/下单 | 返回“快递服务未启用/没有可用 provider”类错误，不调用第三方 |
| 阿里快递查询 | 订单列表悬停或接口查询物流轨迹 | 使用配置中心的阿里 `base_url/api_path/api_key` |
| 3023 查询 | 质检/设备查询中输入 IMEI 或序列号 | 使用配置中心的 3023 `base_url/api_key` |
| 打印标签 | 打印设备标签或模板测试打印 | 使用配置中心的芯烨云 `base_url + print_label_path` |
| 旧配置兜底 | 未保存配置中心的旧站点直接使用原功能 | 仍按旧表读取，不应中断老站点 |
| 安果隔离 | 搜索后台和用户端快递下单入口 | 新下单流程不再出现或调用安果 |

## 后续建议

| 优先级 | 建议 |
| --- | --- |
| 高 | 给配置中心增加“测试连接”按钮，分别测试亿速余额、阿里快递查询、3023 余额、芯烨云状态 |
| 高 | 做一段迁移脚本，把旧 `third_party_service`、`device_query_config`、`express_provider_config` 自动写入 `sys_config` |
| 中 | 后台旧“服务配置”和“设备查询配置”逐步改成只读或隐藏，避免运营人员同时改两套配置 |
| 中 | 清理 SQL 中的硬编码密钥和测试站点数据，安装包只保留空配置模板 |
| 低 | 历史安果订单如还需要售后查询，可加“只读历史兼容”能力，禁止新建即可 |
