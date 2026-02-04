-- 第三方服务配置 SQL
-- 站点ID: 100000

-- 1. 阿里云快递查询服务
INSERT INTO `saas_third_party_service` (
    `site_id`,
    `service_type`,
    `provider_name`,
    `priority`,
    `config`,
    `status`,
    `balance`,
    `min_balance_alert`,
    `create_at`,
    `update_at`
) VALUES (
    100000,
    'express_query',
    'ali_express',
    1,
    '{
        "base_url": "https://kzexpress.market.alicloudapi.com",
        "api_key": "f61c5bd1d2cc42c4b64012d38c5565bf",
        "enabled_apis": "/api-mall/api/express/query",
        "timeout": 30,
        "max_retry": 3,
        "cache_time": 3600,
        "daily_limit": 1000
    }',
    1,
    0.00,
    100.00,
    UNIX_TIMESTAMP(),
    UNIX_TIMESTAMP()
);

-- 2. 3023设备查询服务（主服务商 - 优先级1）
INSERT INTO `saas_third_party_service` (
    `site_id`,
    `service_type`,
    `provider_name`,
    `priority`,
    `config`,
    `status`,
    `balance`,
    `min_balance_alert`,
    `create_at`,
    `update_at`
) VALUES (
    100000,
    'device_query',
    '3023',
    1,
    '{
        "base_url": "http://api.3023data.com",
        "api_key": "x7U77AYc9TEI9KWzh1vGLi6T14BmwPEh",
        "enabled_apis": "/apple/coverage-capacity",
        "timeout": 30,
        "max_retry": 3,
        "cache_time": 3600,
        "daily_limit": 1000
    }',
    1,
    0.00,
    100.00,
    UNIX_TIMESTAMP(),
    UNIX_TIMESTAMP()
);

-- 3. 安果ERP快递服务
INSERT INTO `saas_third_party_service` (
    `site_id`,
    `service_type`,
    `provider_name`,
    `priority`,
    `config`,
    `status`,
    `balance`,
    `min_balance_alert`,
    `create_at`,
    `update_at`
) VALUES (
    100000,
    'express_order',
    'anguo',
    1,
    '{
        "base_url": "http://115.190.35.168:3000",
        "api_key": "afdd0b4ad2ec172c586e2150770fbf9e",
        "timeout": 30
    }',
    1,
    0.00,
    100.00,
    UNIX_TIMESTAMP(),
    UNIX_TIMESTAMP()
);
