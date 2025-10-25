#!/bin/bash

# 回收订单流转自动化测试脚本
# 使用方法: ./test_recycle_order.sh

# 配置信息
BASE_URL="http://localhost"
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1aWQiOjEsInVzZXJuYW1lIjoiYWRtaW4iLCJpc3MiOiJsb2NhbGhvc3QiLCJhdWQiOiJsb2NhbGhvc3QiLCJpYXQiOjE3NDgxNjY0OTMsIm5iZiI6MTc0ODE2NjQ5MywiZXhwIjoxNzQ4NzcxMjkzLCJqdGkiOiIxX2FkbWluIn0.DkKfy7Tp6bt1iqP5RfelsUvqoPiRNqkvU0SzDyd7zuo"
SITE_ID="100000"

# 通用Header
HEADERS="-H 'Content-Type: application/json' -H 'token: $TOKEN' -H 'site-id: $SITE_ID'"

# 颜色输出
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 日志函数
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

# 检查响应结果
check_response() {
    local response="$1"
    local step="$2"
    
    if echo "$response" | grep -q '"code":1'; then
        log_success "$step 成功"
        return 0
    else
        log_error "$step 失败: $response"
        return 1
    fi
}

# 提取JSON字段值
extract_json_value() {
    local json="$1"
    local key="$2"
    echo "$json" | grep -o "\"$key\":[^,}]*" | cut -d':' -f2 | tr -d '"'
}

echo "=========================================="
echo "回收订单流转自动化测试开始"
echo "=========================================="

# 1. 创建订单
log_info "步骤1: 创建订单"
CREATE_DATA='{
  "member_id": 1,
  "customer_name": "张三",
  "customer_phone": "13800138000",
  "delivery_type": 1,
  "express_no": "SF123456789",
  "express_company": "顺丰快递",
  "remark": "自动化测试订单",
  "devices": [
    {
      "imei": "123456789012345",
      "model": "iPhone 13 Pro",
      "initial_price": 5000
    },
    {
      "imei": "123456789012346", 
      "model": "iPhone 12",
      "initial_price": 3500
    }
  ]
}'

CREATE_RESPONSE=$(curl -s -X POST "$BASE_URL/adminapi/recycle/recycle_order/create" \
  $HEADERS \
  -d "$CREATE_DATA")

if ! check_response "$CREATE_RESPONSE" "创建订单"; then
    exit 1
fi

# 提取订单ID（这里需要根据实际返回格式调整）
ORDER_ID=$(extract_json_value "$CREATE_RESPONSE" "order_id")
if [ -z "$ORDER_ID" ]; then
    log_warning "无法从响应中提取订单ID，请手动设置"
    read -p "请输入订单ID: " ORDER_ID
fi

log_info "订单ID: $ORDER_ID"

# 2. 查询订单详情
log_info "步骤2: 查询订单详情"
DETAIL_RESPONSE=$(curl -s -X GET "$BASE_URL/adminapi/recycle/recycle_order/detail/$ORDER_ID" \
  $HEADERS)

if ! check_response "$DETAIL_RESPONSE" "查询订单详情"; then
    exit 1
fi

# 提取设备ID
DEVICE_ID_1=$(extract_json_value "$DETAIL_RESPONSE" "device_id_1")
DEVICE_ID_2=$(extract_json_value "$DETAIL_RESPONSE" "device_id_2")

if [ -z "$DEVICE_ID_1" ] || [ -z "$DEVICE_ID_2" ]; then
    log_warning "无法从响应中提取设备ID，请手动设置"
    read -p "请输入第一台设备ID: " DEVICE_ID_1
    read -p "请输入第二台设备ID: " DEVICE_ID_2
fi

log_info "设备ID1: $DEVICE_ID_1, 设备ID2: $DEVICE_ID_2"

# 3. 签收订单
log_info "步骤3: 签收订单"
SIGN_DATA='{
  "action": "order_sign",
  "devices": [
    {
      "id": "'$DEVICE_ID_1'",
      "imei": "123456789012345",
      "model": "iPhone 13 Pro",
      "initial_price": 5000
    },
    {
      "id": "'$DEVICE_ID_2'",
      "imei": "123456789012346",
      "model": "iPhone 12", 
      "initial_price": 3500
    }
  ],
  "remark": "自动化测试签收"
}'

SIGN_RESPONSE=$(curl -s -X PUT "$BASE_URL/adminapi/recycle/recycle_order/$ORDER_ID" \
  $HEADERS \
  -d "$SIGN_DATA")

if ! check_response "$SIGN_RESPONSE" "签收订单"; then
    exit 1
fi

# 4. 开始质检第一台设备
log_info "步骤4: 开始质检第一台设备"
START_CHECK_DATA='{
  "remark": "开始质检iPhone 13 Pro"
}'

START_CHECK_RESPONSE=$(curl -s -X PUT "$BASE_URL/adminapi/recycle_device/$DEVICE_ID_1/start_check" \
  $HEADERS \
  -d "$START_CHECK_DATA")

if ! check_response "$START_CHECK_RESPONSE" "开始质检第一台设备"; then
    exit 1
fi

# 5. 开始质检第二台设备
log_info "步骤5: 开始质检第二台设备"
START_CHECK_DATA2='{
  "remark": "开始质检iPhone 12"
}'

START_CHECK_RESPONSE2=$(curl -s -X PUT "$BASE_URL/adminapi/recycle_device/$DEVICE_ID_2/start_check" \
  $HEADERS \
  -d "$START_CHECK_DATA2")

if ! check_response "$START_CHECK_RESPONSE2" "开始质检第二台设备"; then
    exit 1
fi

# 6. 完成第一台设备质检（正常回收）
log_info "步骤6: 完成第一台设备质检（正常回收）"
COMPLETE_CHECK_DATA1='{
  "check_data": {
    "check_result": "设备功能正常，外观良好，电池健康度85%",
    "check_images": ["check1.jpg", "check2.jpg"]
  },
  "final_price": 4500,
  "remark": "质检完成，设备状态良好"
}'

COMPLETE_CHECK_RESPONSE1=$(curl -s -X PUT "$BASE_URL/adminapi/recycle_device/$DEVICE_ID_1/complete_check" \
  $HEADERS \
  -d "$COMPLETE_CHECK_DATA1")

if ! check_response "$COMPLETE_CHECK_RESPONSE1" "完成第一台设备质检"; then
    exit 1
fi

# 7. 完成第二台设备质检（退回）
log_info "步骤7: 完成第二台设备质检（退回）"
COMPLETE_CHECK_DATA2='{
  "check_data": {
    "check_result": "设备屏幕有裂痕，不符合回收标准",
    "check_images": ["check3.jpg", "check4.jpg"],
    "check_status": 2
  },
  "remark": "设备有损坏，建议退回"
}'

COMPLETE_CHECK_RESPONSE2=$(curl -s -X PUT "$BASE_URL/adminapi/recycle_device/$DEVICE_ID_2/complete_check" \
  $HEADERS \
  -d "$COMPLETE_CHECK_DATA2")

if ! check_response "$COMPLETE_CHECK_RESPONSE2" "完成第二台设备质检"; then
    exit 1
fi

# 8. 确认第一台设备价格
log_info "步骤8: 确认第一台设备价格"
CONFIRM_PRICE_DATA='{
  "data": {
    "final_price": 4500,
    "price_remark": "根据质检结果确认价格",
    "remark": "价格确认完成"
  }
}'

CONFIRM_PRICE_RESPONSE=$(curl -s -X PUT "$BASE_URL/adminapi/recycle_device/$DEVICE_ID_1/confirm_price" \
  $HEADERS \
  -d "$CONFIRM_PRICE_DATA")

if ! check_response "$CONFIRM_PRICE_RESPONSE" "确认设备价格"; then
    exit 1
fi

# 9. 退回第二台设备
log_info "步骤9: 退回第二台设备"
RETURN_DATA='{
  "ids": "'$DEVICE_ID_2'",
  "remark": "设备有损坏，退回给用户"
}'

RETURN_RESPONSE=$(curl -s -X POST "$BASE_URL/adminapi/recycle_device/batch_return" \
  $HEADERS \
  -d "$RETURN_DATA")

if ! check_response "$RETURN_RESPONSE" "退回设备"; then
    exit 1
fi

# 10. 回收第一台设备
log_info "步骤10: 回收第一台设备"
RECYCLE_DATA='{
  "ids": "'$DEVICE_ID_1'",
  "remark": "设备回收完成"
}'

RECYCLE_RESPONSE=$(curl -s -X POST "$BASE_URL/adminapi/recycle_device/batch_recycle" \
  $HEADERS \
  -d "$RECYCLE_DATA")

if ! check_response "$RECYCLE_RESPONSE" "回收设备"; then
    exit 1
fi

# 11. 确认打款
log_info "步骤11: 确认打款"
PAYMENT_DATA='{
  "pay_account": "6222021234567890",
  "pay_type": 1,
  "pay_name": "张三",
  "pay_remark": "回收款项已打款",
  "remark": "订单完成"
}'

PAYMENT_RESPONSE=$(curl -s -X PUT "$BASE_URL/adminapi/recycle/recycle_order/$ORDER_ID/payment_confirm" \
  $HEADERS \
  -d "$PAYMENT_DATA")

if ! check_response "$PAYMENT_RESPONSE" "确认打款"; then
    exit 1
fi

# 12. 最终状态检查
log_info "步骤12: 最终状态检查"
FINAL_DETAIL_RESPONSE=$(curl -s -X GET "$BASE_URL/adminapi/recycle/recycle_order/detail/$ORDER_ID" \
  $HEADERS)

if ! check_response "$FINAL_DETAIL_RESPONSE" "最终状态检查"; then
    exit 1
fi

echo "=========================================="
log_success "回收订单流转自动化测试完成！"
echo "订单ID: $ORDER_ID"
echo "设备1 ID: $DEVICE_ID_1 (已回收)"
echo "设备2 ID: $DEVICE_ID_2 (已退回)"
echo "=========================================="

# 显示最终状态
echo "最终订单状态:"
echo "$FINAL_DETAIL_RESPONSE" | python3 -m json.tool 2>/dev/null || echo "$FINAL_DETAIL_RESPONSE" 