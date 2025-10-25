#!/bin/bash

# 创建测试数据脚本 - 生成更多测试订单
# 使用方法: ./create_test_data.sh

API_BASE="http://localhost/adminapi/recycle/recycle_order"
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1aWQiOjEsInVzZXJuYW1lIjoiYWRtaW4iLCJpc3MiOiJsb2NhbGhvc3QiLCJhdWQiOiJsb2NhbGhvc3QiLCJpYXQiOjE3NDgxNjY0OTMsIm5iZiI6MTc0ODE2NjQ5MywiZXhwIjoxNzQ4NzcxMjkzLCJqdGkiOiIxX2FkbWluIn0.DkKfy7Tp6bt1iqP5RfelsUvqoPiRNqkvU0SzDyd7zuo"
SITE_ID="100000"

# 定义颜色
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
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
echo "    创建更多测试数据"
echo "========================================"

# 1. 企业批量回收订单
log_info "创建企业批量回收订单..."
response=$(api_request '{
    "member_id": 1,
    "customer_name": "张企业经理",
    "customer_phone": "13888888888",
    "delivery_type": 1,
    "express_no": "SF888888888",
    "express_company": "顺丰快递",
    "remark": "企业设备更新换代，批量回收",
    "devices": [
        {"imei": "100001000010001", "model": "iPhone 13", "initial_price": 4500},
        {"imei": "100001000010002", "model": "iPhone 13", "initial_price": 4500},
        {"imei": "100001000010003", "model": "iPhone 13", "initial_price": 4500},
        {"imei": "100001000010004", "model": "iPhone 13", "initial_price": 4500},
        {"imei": "100001000010005", "model": "iPhone 13", "initial_price": 4500},
        {"imei": "200001000010001", "model": "华为P50", "initial_price": 3200},
        {"imei": "200001000010002", "model": "华为P50", "initial_price": 3200},
        {"imei": "200001000010003", "model": "华为P50", "initial_price": 3200},
        {"imei": "300001000010001", "model": "小米12", "initial_price": 2800},
        {"imei": "300001000010002", "model": "小米12", "initial_price": 2800}
    ]
}')
check_response "$response" "张企业经理"

# 2. 学生预算有限订单
log_info "创建学生预算有限订单..."
response=$(api_request '{
    "member_id": 1,
    "customer_name": "小刘学生",
    "customer_phone": "13666666666",
    "delivery_type": 2,
    "express_no": "",
    "express_company": "",
    "remark": "学生党，希望能卖个好价钱",
    "devices": [
        {"imei": "STU001234567890", "model": "iPhone 11", "initial_price": 2800},
        {"imei": "STU001234567891", "model": "AirPods Pro", "initial_price": 800}
    ]
}')
check_response "$response" "小刘学生"

# 3. 老年人简单订单
log_info "创建老年人简单订单..."
response=$(api_request '{
    "member_id": 1,
    "customer_name": "陈阿姨",
    "customer_phone": "13555555555",
    "delivery_type": 1,
    "express_no": "YTO999888777",
    "express_company": "圆通快递",
    "remark": "换智能机了，旧手机不用了",
    "devices": [
        {"imei": "OLD123456789012", "model": "华为畅享20", "initial_price": 600}
    ]
}')
check_response "$response" "陈阿姨"

# 4. 高端收藏家订单
log_info "创建高端收藏家订单..."
response=$(api_request '{
    "member_id": 1,
    "customer_name": "土豪李总",
    "customer_phone": "13999999999",
    "delivery_type": 1,
    "express_no": "EMS888999000",
    "express_company": "EMS",
    "remark": "收藏设备出售，都是限量版",
    "devices": [
        {"imei": "LTD001234567890", "model": "iPhone 14 Pro Max 1TB 紫色", "initial_price": 12000},
        {"imei": "LTD001234567891", "model": "三星Galaxy Z Fold4", "initial_price": 9800},
        {"imei": "LTD001234567892", "model": "华为Mate50 RS 保时捷版", "initial_price": 8800}
    ]
}')
check_response "$response" "土豪李总"

# 5. 损坏设备回收订单
log_info "创建损坏设备回收订单..."
response=$(api_request '{
    "member_id": 1,
    "customer_name": "马用户",
    "customer_phone": "13777777777",
    "delivery_type": 1,
    "express_no": "ZJS777888999",
    "express_company": "中通快递",
    "remark": "设备都有不同程度损坏",
    "devices": [
        {"imei": "DMG123456789001", "model": "iPhone 12 Pro（屏幕破裂）", "initial_price": 2000},
        {"imei": "DMG123456789002", "model": "三星S22（进水）", "initial_price": 1500},
        {"imei": "DMG123456789003", "model": "小米11（电池鼓包）", "initial_price": 800}
    ]
}')
check_response "$response" "马用户"

# 6. 配件齐全订单
log_info "创建配件齐全订单..."
response=$(api_request '{
    "member_id": 1,
    "customer_name": "配件齐全张",
    "customer_phone": "13444444444",
    "delivery_type": 1,
    "express_no": "JD444555666",
    "express_company": "京东快递",
    "remark": "原装配件齐全，包装完好",
    "devices": [
        {"imei": "SET123456789001", "model": "iPhone 13 Pro 全套装", "initial_price": 6800},
        {"imei": "SET123456789002", "model": "iPad Air 5 + Apple Pencil", "initial_price": 4500}
    ]
}')
check_response "$response" "配件齐全张"

# 7. 二手商贩订单
log_info "创建二手商贩订单..."
response=$(api_request '{
    "member_id": 1,
    "customer_name": "二手商老王",
    "customer_phone": "13333333333",
    "delivery_type": 1,
    "express_no": "SF333444555",
    "express_company": "顺丰快递",
    "remark": "二手商批量出货",
    "devices": [
        {"imei": "BIZ123456789001", "model": "iPhone XR", "initial_price": 2200},
        {"imei": "BIZ123456789002", "model": "iPhone XR", "initial_price": 2200},
        {"imei": "BIZ123456789003", "model": "华为Nova9", "initial_price": 1800},
        {"imei": "BIZ123456789004", "model": "华为Nova9", "initial_price": 1800},
        {"imei": "BIZ123456789005", "model": "OPPO Reno8", "initial_price": 2000},
        {"imei": "BIZ123456789006", "model": "vivo X80", "initial_price": 3000}
    ]
}')
check_response "$response" "二手商老王"

# 8. 紧急处理订单
log_info "创建紧急处理订单..."
response=$(api_request '{
    "member_id": 1,
    "customer_name": "急需钱用户",
    "customer_phone": "13222222222",
    "delivery_type": 2,
    "express_no": "",
    "express_company": "",
    "remark": "急需用钱，希望快速处理",
    "devices": [
        {"imei": "URG123456789001", "model": "iPhone 14", "initial_price": 5500}
    ]
}')
check_response "$response" "急需钱用户"

# 9. 海外版本订单
log_info "创建海外版本订单..."
response=$(api_request '{
    "member_id": 1,
    "customer_name": "海归留学生",
    "customer_phone": "13111111111",
    "delivery_type": 1,
    "express_no": "SF111222333",
    "express_company": "顺丰快递",
    "remark": "海外版本，需要验证网络制式",
    "devices": [
        {"imei": "INT123456789001", "model": "iPhone 14 Pro (美版)", "initial_price": 7000},
        {"imei": "INT123456789002", "model": "三星S23 Ultra (欧版)", "initial_price": 6500}
    ]
}')
check_response "$response" "海归留学生"

# 10. 测试边界情况订单
log_info "创建测试边界情况订单..."
response=$(api_request '{
    "member_id": 1,
    "customer_name": "边界测试用户",
    "customer_phone": "13000000000",
    "delivery_type": 1,
    "express_no": "TEST000111222",
    "express_company": "测试快递",
    "remark": "用于测试系统边界情况的订单",
    "devices": [
        {"imei": "000000000000001", "model": "极低价值设备", "initial_price": 1},
        {"imei": "999999999999999", "model": "极高价值设备", "initial_price": 99999}
    ]
}')
check_response "$response" "边界测试用户"

echo -e "\n========================================"
echo -e "${GREEN}测试数据创建完成！${NC}"
echo "========================================"
echo "已创建的测试订单类型："
echo "1. 📱 企业批量回收订单（10台设备）"
echo "2. 🎓 学生预算有限订单（2台设备）"
echo "3. 👵 老年人简单订单（1台设备）"
echo "4. 💎 高端收藏家订单（3台设备）"
echo "5. 🔧 损坏设备回收订单（3台设备）"
echo "6. 📦 配件齐全订单（2台设备）"
echo "7. 🏪 二手商贩订单（6台设备）"
echo "8. ⚡ 紧急处理订单（1台设备）"
echo "9. 🌍 海外版本订单（2台设备）"
echo "10. 🧪 边界测试订单（2台设备）"
echo ""
echo "总计：32台测试设备"
echo "涵盖场景：批量回收、损坏设备、高价值设备、边界测试等" 