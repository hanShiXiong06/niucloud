#!/bin/bash

# 高级测试数据脚本 - 包含异常场景和复杂业务逻辑
# 使用方法: ./advanced_test_data.sh

API_BASE="http://localhost/adminapi/recycle/recycle_order"
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1aWQiOjEsInVzZXJuYW1lIjoiYWRtaW4iLCJpc3MiOiJsb2NhbGhvc3QiLCJhdWQiOiJsb2NhbGhvc3QiLCJpYXQiOjE3NDgxNjY0OTMsIm5iZiI6MTc0ODE2NjQ5MywiZXhwIjoxNzQ4NzcxMjkzLCJqdGkiOiIxX2FkbWluIn0.DkKfy7Tp6bt1iqP5RfelsUvqoPiRNqkvU0SzDyd7zuo"
SITE_ID="100000"

# 定义颜色
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_advanced() {
    echo -e "${PURPLE}[ADVANCED]${NC} $1"
}

# API请求函数
api_request() {
    local data=$1
    
    curl -s --location --request POST "$API_BASE/create" \
        --header "site-id: $SITE_ID" \
        --header "token: $TOKEN" \
        --header "Content-Type: application/json" \
        --data "$data"
}

# 检查API响应
check_response() {
    local response=$1
    local name=$2
    
    local code=$(echo "$response" | jq -r '.code // empty')
    local msg=$(echo "$response" | jq -r '.msg // empty')
    
    if [ "$code" = "1" ]; then
        log_success "✅ $name 订单创建成功"
        return 0
    else
        log_error "❌ $name 订单创建失败: $msg"
        return 1
    fi
}

echo "========================================"
echo -e "${CYAN}    高级测试数据生成器${NC}"
echo "========================================"

# 1. 极限数量测试订单
log_advanced "创建极限数量测试订单（单订单最多设备）..."
devices_data=""
for i in {1..50}; do
    imei=$(printf "MAX%015d" $i)
    if [ $i -eq 1 ]; then
        devices_data="\"imei\": \"$imei\", \"model\": \"批量设备$i\", \"initial_price\": $((RANDOM % 5000 + 1000))"
    else
        devices_data="$devices_data},{\"imei\": \"$imei\", \"model\": \"批量设备$i\", \"initial_price\": $((RANDOM % 5000 + 1000))"
    fi
done

response=$(api_request "{
    \"member_id\": 1,
    \"customer_name\": \"极限测试用户\",
    \"customer_phone\": \"13999999999\",
    \"delivery_type\": 1,
    \"express_no\": \"MAX" + $(date +%s) + "\",
    \"express_company\": \"压力测试快递\",
    \"remark\": \"测试系统最大设备数量处理能力\",
    \"devices\": [{$devices_data}]
}")
check_response "$response" "极限数量测试"

# 2. 特殊字符测试订单
log_advanced "创建特殊字符测试订单..."
response=$(api_request '{
    "member_id": 1,
    "customer_name": "特殊字符测试用户@#￥%…&*()—+",
    "customer_phone": "13000000001",
    "delivery_type": 1,
    "express_no": "SPEC!@#$%^&*()",
    "express_company": "特殊字符快递<script>alert(1)</script>",
    "remark": "测试特殊字符处理：<>&\"\\'\''\"JSON注入测试{\"evil\":\"payload\"}",
    "devices": [
        {"imei": "SPEC!@#$%^&*()_+", "model": "设备<script>alert(1)</script>", "initial_price": 2000},
        {"imei": "中文IMEI测试12345", "model": "设备名称包含emoji😀📱💻", "initial_price": 3000}
    ]
}')
check_response "$response" "特殊字符测试"

# 3. 零价格和负价格测试
log_advanced "创建价格边界测试订单..."
response=$(api_request '{
    "member_id": 1,
    "customer_name": "价格边界测试",
    "customer_phone": "13000000002",
    "delivery_type": 1,
    "express_no": "PRICE000111",
    "express_company": "价格测试快递",
    "remark": "测试价格边界情况处理",
    "devices": [
        {"imei": "PRICE00000000001", "model": "零价格设备", "initial_price": 0},
        {"imei": "PRICE00000000002", "model": "超低价格设备", "initial_price": 0.01},
        {"imei": "PRICE00000000003", "model": "超高价格设备", "initial_price": 999999.99}
    ]
}')
check_response "$response" "价格边界测试"

# 4. 超长字符串测试
log_advanced "创建超长字符串测试订单..."
long_string=$(printf '%*s' 1000 '' | tr ' ' 'A')
response=$(api_request "{
    \"member_id\": 1,
    \"customer_name\": \"超长字符串测试用户\",
    \"customer_phone\": \"13000000003\",
    \"delivery_type\": 1,
    \"express_no\": \"LONG123456789\",
    \"express_company\": \"超长测试快递\",
    \"remark\": \"$long_string\",
    \"devices\": [
        {\"imei\": \"LONG000000000001\", \"model\": \"超长字符串设备\", \"initial_price\": 2000}
    ]
}")
check_response "$response" "超长字符串测试"

# 5. 并发冲突测试数据
log_advanced "创建并发冲突测试订单..."
for i in {1..5}; do
    response=$(api_request "{
        \"member_id\": 1,
        \"customer_name\": \"并发测试用户$i\",
        \"customer_phone\": \"1300000000$i\",
        \"delivery_type\": 1,
        \"express_no\": \"CON$(date +%s)$i\",
        \"express_company\": \"并发测试快递\",
        \"remark\": \"测试并发创建订单场景$i\",
        \"devices\": [
            {\"imei\": \"CON$(date +%s)00000$i\", \"model\": \"并发测试设备$i\", \"initial_price\": $((2000 + i * 100))}
        ]
    }") &
done
wait
log_success "✅ 并发冲突测试订单创建完成"

# 6. 国际化测试订单
log_advanced "创建国际化测试订单..."
response=$(api_request '{
    "member_id": 1,
    "customer_name": "Международный пользователь",
    "customer_phone": "13000000010",
    "delivery_type": 1,
    "express_no": "INTL123456789",
    "express_company": "International Express",
    "remark": "Testing multilingual support: 中文、English、日本語、한국어、العربية、Русский",
    "devices": [
        {"imei": "INTL000000000001", "model": "iPhone 14 Pro （国际版）", "initial_price": 7000},
        {"imei": "INTL000000000002", "model": "Samsung Galaxy S23 (グローバル版)", "initial_price": 6000},
        {"imei": "INTL000000000003", "model": "华为 Mate50 Pro (المؤسسة النسخة)", "initial_price": 5000}
    ]
}')
check_response "$response" "国际化测试"

# 7. 异常IMEI格式测试
log_advanced "创建异常IMEI格式测试订单..."
response=$(api_request '{
    "member_id": 1,
    "customer_name": "IMEI格式测试",
    "customer_phone": "13000000011",
    "delivery_type": 1,
    "express_no": "IMEI123456789",
    "express_company": "IMEI测试快递",
    "remark": "测试各种异常IMEI格式",
    "devices": [
        {"imei": "", "model": "空IMEI设备", "initial_price": 1000},
        {"imei": "123", "model": "过短IMEI设备", "initial_price": 1000},
        {"imei": "12345678901234567890123456789012345", "model": "过长IMEI设备", "initial_price": 1000},
        {"imei": "ABC-DEF-GHI-JKL", "model": "字母IMEI设备", "initial_price": 1000},
        {"imei": "123.456.789.012", "model": "带点IMEI设备", "initial_price": 1000}
    ]
}')
check_response "$response" "异常IMEI格式测试"

# 8. 时间测试订单（模拟不同时间点创建）
log_advanced "创建时间测试订单..."
response=$(api_request '{
    "member_id": 1,
    "customer_name": "时间测试用户",
    "customer_phone": "13000000012",
    "delivery_type": 1,
    "express_no": "TIME123456789",
    "express_company": "时间测试快递",
    "remark": "用于测试时间相关功能，如统计、排序等",
    "devices": [
        {"imei": "TIME000000000001", "model": "时间测试设备1", "initial_price": 3000},
        {"imei": "TIME000000000002", "model": "时间测试设备2", "initial_price": 3500}
    ]
}')
check_response "$response" "时间测试"

# 9. 大批量二手商订单
log_advanced "创建大批量二手商订单..."
bulk_devices=""
for i in {1..30}; do
    brand_idx=$((i % 4 + 1))
    case $brand_idx in
        1) brand="iPhone" ;;
        2) brand="华为" ;;
        3) brand="小米" ;;
        4) brand="三星" ;;
    esac
    
    imei=$(printf "BULK%011d" $i)
    price=$((RANDOM % 3000 + 1000))
    
    if [ $i -eq 1 ]; then
        bulk_devices="\"imei\": \"$imei\", \"model\": \"$brand设备$i\", \"initial_price\": $price"
    else
        bulk_devices="$bulk_devices},{\"imei\": \"$imei\", \"model\": \"$brand设备$i\", \"initial_price\": $price"
    fi
done

response=$(api_request "{
    \"member_id\": 1,
    \"customer_name\": \"大批量二手商\",
    \"customer_phone\": \"13000000013\",
    \"delivery_type\": 1,
    \"express_no\": \"BULK" + $(date +%s) + "\",
    \"express_company\": \"批量快递\",
    \"remark\": \"大批量设备回收，测试系统处理大量数据的能力\",
    \"devices\": [{$bulk_devices}]
}")
check_response "$response" "大批量二手商"

# 10. SQL注入测试订单
log_advanced "创建SQL注入测试订单..."
response=$(api_request '{
    "member_id": 1,
    "customer_name": "SQL注入测试用户'\''OR 1=1--",
    "customer_phone": "13000000014",
    "delivery_type": 1,
    "express_no": "SQL'\''OR 1=1--",
    "express_company": "SQL注入快递'\''OR 1=1--",
    "remark": "测试SQL注入防护：'\''OR 1=1-- UNION SELECT * FROM users--",
    "devices": [
        {"imei": "SQL'\''OR 1=1--", "model": "SQL注入设备'\''OR 1=1--", "initial_price": 2000}
    ]
}')
check_response "$response" "SQL注入测试"

echo -e "\n========================================"
echo -e "${GREEN}高级测试数据创建完成！${NC}"
echo "========================================"
echo -e "${CYAN}已创建的高级测试场景：${NC}"
echo "🔢 1. 极限数量测试（50台设备）"
echo "🔤 2. 特殊字符测试"
echo "💰 3. 价格边界测试"
echo "📏 4. 超长字符串测试"
echo "⚡ 5. 并发冲突测试（5个订单）"
echo "🌍 6. 国际化测试"
echo "📱 7. 异常IMEI格式测试"
echo "⏰ 8. 时间测试"
echo "📦 9. 大批量二手商测试（30台设备）"
echo "🛡️ 10. SQL注入测试"
echo ""
echo -e "${YELLOW}测试目的：${NC}"
echo "- 系统性能和稳定性测试"
echo "- 数据验证和安全性测试"
echo "- 边界条件和异常处理测试"
echo "- 国际化和多语言支持测试"
echo "- 大数据量处理能力测试" 