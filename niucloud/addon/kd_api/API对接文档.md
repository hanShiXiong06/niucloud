# KD API 对接文档

## 1. 概述

本文档描述了与 KD API 系统的对接规范，包括认证机制、请求格式、接口说明等内容。

### 1.1 基本信息
- **API版本**: v1.0
- **协议**: HTTP/HTTPS
- **数据格式**: JSON
- **字符编码**: UTF-8

## 2. 环境配置

### 2.1 接入域名
- **生产环境**: `http://niuaddon.saas` (请填写接入站点域名)

### 2.2 认证参数
接入前需要获取以下参数：
- `pub_id`: 推广者ID
- `api_key`: API密钥
- `api_secret`: API密钥(用于签名)

## 3. 认证机制

### 3.1 签名算法
采用 **HMAC-SHA256** 签名认证方式：

#### 签名步骤：
1. 构造签名字符串：`api_key + request_id + timestamp`
2. 使用 HMAC-SHA256 算法对签名字符串进行签名
3. 签名密钥为 `api_secret`
4. request_id 为每次请求的随机标识，自行生成即可

#### 代码示例（PHP）：
```php
$api_key = 'your_api_key';
$api_secret = 'your_api_secret';
$request_id = uniqid(); // 唯一请求ID
$timestamp = strtotime(date('YmdHis')); // 时间戳

$sign_string = $api_key . $request_id . $timestamp;
$signature = hash_hmac('sha256', $sign_string, $api_secret, false);
```

### 3.2 时间戳说明
- 格式：Unix时间戳
- 生成方式：`strtotime(date('YmdHis'))`
- 有效期：建议5分钟内有效（具体以服务端配置为准）

## 4. 请求格式

### 4.1 HTTP 请求规范
- **请求方法**: POST
- **Content-Type**: `application/json`
- **字符编码**: UTF-8

### 4.2 请求头
```
Content-Type: application/json
pub-id: {pub_id}
```

### 4.3 请求体结构
```json
{
    "api_key": "API密钥",
    "timestamp": 1609459200,
    "sign": "HMAC-SHA256签名",
    "request_id": "唯一请求标识",
    "content": {
        // 具体业务参数
    }
}
```

#### 公共参数说明：
| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| api_key | string | 是 | API密钥 |
| timestamp | int | 是 | 请求时间戳 |
| sign | string | 是 | 请求签名 |
| request_id | string | 是 | 唯一请求ID（建议使用uniqid()生成） |
| content | object | 是 | 业务参数对象 |

## 5. 接口说明

### 5.1 获取链接接口

#### 接口地址
```
POST /kdapi/getlink
```

#### 业务参数 (content字段)
```json
{
    "sid": "跟单参数SID"
}
```

#### 参数说明
| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| sid | string | 是 | 站点标识ID |

#### 请求示例
```json
{
    "api_key": "9833214a4faddd4aa42ff02c4968fb81",
    "timestamp": 1609459200,
    "sign": "calculated_signature",
    "request_id": "req_123456789",
    "content": {
        "sid": "tkceshisid45544"
    }
}
```

### 5.2 获取订单接口

#### 接口地址
```
POST /kdapi/getorder
```

#### 业务参数 (content字段)
```json
{
    "pages": 1,
    "limit": 10,
    "sid": "站点ID",
    "status": 1,
    "is_js": 0,
    "start_time": "2023-01-01",
    "end_time": "2023-01-02"
}
```

#### 参数说明
| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| pages | int | 是 | 页码，从1开始 |
| limit | int | 是 | 每页数量，建议不超过100 |
| sid | string | 是 | 站点标识ID |
| status | int | 否 | 订单状态：1已支付，2已完成，3已取消 |
| is_js | int | 否 | 结算状态：0未结算，1已结算 |
| start_time | string | 否 | 开始时间，格式：YYYY-MM-DD |
| end_time | string | 否 | 结束时间，格式：YYYY-MM-DD |

#### 请求示例
```json
{
    "api_key": "9833214a4faddd4aa42ff02c4968fb81",
    "timestamp": 1609459200,
    "sign": "calculated_signature",
    "request_id": "req_123456790",
    "content": {
        "pages": 1,
        "limit": 10,
        "sid": "tkceshisid",
        "status": 1,
        "is_js": 0,
        "start_time": "2023-01-01",
        "end_time": "2023-01-02"
    }
}
```

## 6. 响应格式

### 6.1 成功响应
```json
{
    "code": 200,
    "message": "success",
    "data": {
        // 具体业务数据
    }
}
```

### 6.2 错误响应
```json
{
    "code": 400,
    "message": "错误描述",
    "data": null
}
```

### 6.3 常见错误码
| 错误码 | 说明 |
|--------|------|
| 1 | 请求成功 |
| 其他 | 请求失败 |

## 7. SDK示例代码

### 7.1 PHP SDK
```php
<?php

class KdApiClient
{
    private $domain;
    private $pub_id;
    private $api_key;
    private $api_secret;

    public function __construct($domain, $pub_id, $api_key, $api_secret)
    {
        $this->domain = $domain;
        $this->pub_id = $pub_id;
        $this->api_key = $api_key;
        $this->api_secret = $api_secret;
    }

    /**
     * 执行API请求
     */
    public function execute($url, $data)
    {
        $url = $this->domain . $url;
        $request_id = uniqid();
        $timestamp = strtotime(date('YmdHis'));
        
        // 生成签名
        $sign_string = $this->api_key . $request_id . $timestamp;
        $signature = hash_hmac('sha256', $sign_string, $this->api_secret, false);
        
        // 构造请求体
        $payload = json_encode([
            'api_key' => $this->api_key,
            'timestamp' => $timestamp,
            'sign' => $signature,
            'request_id' => $request_id,
            'content' => $data
        ], JSON_UNESCAPED_UNICODE);
        
        // 发送请求
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "pub-id: " . $this->pub_id
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        
        $result = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        
        if ($err) {
            throw new Exception('Curl Error: ' . $err);
        }
        
        $response = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response: ' . json_last_error_msg());
        }
        
        return $response;
    }

    /**
     * 获取链接
     */
    public function getLink($sid)
    {
        return $this->execute('/kdapi/getlink', ['sid' => $sid]);
    }

    /**
     * 获取订单
     */
    public function getOrder($params)
    {
        return $this->execute('/kdapi/getorder', $params);
    }
}

// 使用示例
$client = new KdApiClient(
    'http://niuaddon.saas',
    2,
    '9833214a4faddd4aa42ff02c4968fb81',
    'b2ff0fa0c8a0ebdb96cf60fcacae80a9fd0cea97727ae158017e9d41aa0be693'
);

// 获取链接
$result = $client->getLink('tkceshisid45544');

// 获取订单
$result = $client->getOrder([
    'pages' => 1,
    'limit' => 10,
    'sid' => 'tkceshisid',
    'status' => 1,
    'is_js' => 0,
    'start_time' => '2023-01-01',
    'end_time' => '2023-01-02'
]);
```


**注意**: 本文档基于当前API版本编写，如有更新请及时关注最新版本。