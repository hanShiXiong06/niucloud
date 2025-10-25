# 设备查询功能封装使用说明

## 功能概述

本功能对IMEI/序列号查询进行了完整的封装，提供了配置管理、接口清单、缓存机制和查询结果存储等功能。

## 架构组成

### 1. 模型层
- `DeviceQueryConfig` - 站点查询配置管理
- `DeviceQueryApi` - API接口清单管理  
- `DeviceQueryResult` - 查询结果存储和缓存

### 2. 服务层
- `DeviceQueryService` - 核心查询服务，封装所有查询逻辑

### 3. 数据库表
- `device_query_config` - 配置表
- `device_query_api` - 接口清单表
- `device_query_result` - 查询结果表

## 使用方法

### 1. 初始化配置

```php
// 创建站点配置
$config = new DeviceQueryConfig();
$configData = [
    'site_id' => 1,
    'name' => '默认查询配置',
    'api_key' => 'your_api_key_here',
    'base_url' => 'http://api.3023data.com',
    'enabled_apis' => ['/apple/model', '/apple/coverage'],
    'timeout' => 30,
    'cache_time' => 3600,
    'status' => 1
];
$config->save($configData);

// 初始化API清单
DeviceQueryApi::initDefaultApiList();
```

### 2. 基本查询

```php
$queryService = new DeviceQueryService();
$result = $queryService->queryDevice('123456789012345', '/apple/model', 1);

if ($result['success']) {
    echo "查询成功: " . json_encode($result['data']);
    echo "费用: " . $result['cost'] . "元";
}
```

### 3. 支持的查询接口

#### 苹果设备
- `/apple/model` - 苹果型号查询 (0.05元)
- `/apple/coverage` - 苹果保修查询 (0.2-0.8元)
- `/apple/activationlock` - 激活锁查询 (0.4元)

#### 安卓设备  
- `/huawei/coverage` - 华为保修查询 (0.4元)
- `/xiaomi/coverage` - 小米保修查询 (0.8元)

#### IMEI查询
- `/imei/model` - IMEI查询(型号) (0.2元)
- `/imei/blacklist` - IMEI查询(黑名单) (0.4元)

## 特性

1. **配置化管理** - 支持多站点独立配置
2. **智能缓存** - 自动缓存查询结果，节省费用
3. **批量查询** - 支持批量处理多个查询
4. **查询统计** - 提供详细的查询统计数据
5. **错误处理** - 完善的错误处理和重试机制
6. **平滑迁移** - 兼容原有查询方式，支持平滑升级 