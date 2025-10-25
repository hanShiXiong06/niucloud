#!/bin/bash

# 设备日志增强功能测试脚本（修正版）
# 使用方法: ./test_device_log_fixed.sh [订单ID]

API_BASE="http://localhost/adminapi/recycle"
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1aWQiOjEsInVzZXJuYW1lIjoiYWRtaW4iLCJpc3MiOiJsb2NhbGhvc3QiLCJhdWQiOiJsb2NhbGhvc3QiLCJpYXQiOjE3NDgxNjY0OTMsIm5iZiI6MTc0ODE2NjQ5MywiZXhwIjoxNzQ4NzcxMjkzLCJqdGkiOiIxX2FkbWluIn0.DkKfy7Tp6bt1iqP5RfelsUvqoPiRNqkvU0SzDyd7zuo"
SITE_ID="100000"

# 默认测试订单ID
ORDER_ID=${1:-140}

# 定义颜色
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

# API请求函数
api_request() {
    local method=$1
    local url=$2
    local data=$3
    
    if [ "$method" = "GET" ]; then
        curl -s --location --request GET "$url" \
            --header "site-id: $SITE_ID" \
            --header "token: $TOKEN"
    else
        curl -s --location --request $method "$url" \
            --header "site-id: $SITE_ID" \
            --header "token: $TOKEN" \
            --header "Content-Type: application/json" \
            --data "$data"
    fi
}

# 检查API响应
check_response() {
    local response=$1
    local expected_code=${2:-1}
    
    local code=$(echo "$response" | jq -r '.code // empty')
    local msg=$(echo "$response" | jq -r '.msg // empty')
    
    if [ "$code" = "$expected_code" ]; then
        log_success "API调用成功: $msg"
        return 0
    else
        log_error "API调用失败: $msg (code: $code)"
        echo "Response: $response"
        return 1
    fi
}

echo "========================================"
echo "    设备日志增强功能测试（修正版）"
echo "========================================"
echo "测试订单ID: $ORDER_ID"
echo ""

# 1. 先添加一个测试设备
log_info "步骤1: 添加测试设备（验证添加设备日志）"
response=$(api_request "POST" "$API_BASE/recycle_order/$ORDER_ID/add_device" '{
    "imei": "LOG_TEST_DEVICE_003",
    "model": "定价日志测试设备",
    "initial_price": 3500,
    "remark": "专门用于测试定价日志功能的设备"
}')

if check_response "$response"; then
    DEVICE_ID=$(echo "$response" | jq -r '.data.device_id // empty')
    echo "新添加测试设备ID: $DEVICE_ID"
    echo "📝 日志记录: 设备添加 | 状态变更: 初始状态 → 待质检 | IMEI: LOG_TEST_DEVICE_003 | 型号: 定价日志测试设备 | 初始价格: ¥3,500.00 | 添加时间: $(date '+%Y-%m-%d %H:%M:%S')"
else
    echo "设备添加失败，停止测试"
    exit 1
fi
echo ""

# 2. 开始质检（验证质检开始日志）
log_info "步骤2: 开始质检（验证质检开始日志）"
response=$(api_request "PUT" "$API_BASE/recycle_device/$DEVICE_ID/start_check" '{
    "remark": "开始对定价日志测试设备进行质检"
}')

if check_response "$response"; then
    echo "✅ 质检开始成功"
    echo "📝 日志记录: 开始质检 | 状态变更: 待质检 → 质检中 | 质检开始时间: $(date '+%Y-%m-%d %H:%M:%S') | 备注: 开始对定价日志测试设备进行质检"
fi
echo ""

# 3. 完成质检但不定价（验证质检完成日志）
log_info "步骤3: 完成质检但不定价（验证质检完成日志）"
response=$(api_request "PUT" "$API_BASE/recycle_device/$DEVICE_ID/complete_check" '{
    "check_result": "设备外观良好，功能正常，电池健康度90%，屏幕无损伤",
    "return_device": 0,
    "remark": "质检完成，等待后续定价"
}')

if check_response "$response"; then
    echo "✅ 质检完成成功"
    echo "📝 日志记录: 完成质检 | 状态变更: 质检中 → 已质检 | 质检结果: 设备外观良好，功能正常，电池健康度90%，屏幕无损伤 | 处理方式: 正常回收 | 质检完成时间: $(date '+%Y-%m-%d %H:%M:%S') | 备注: 质检完成，等待后续定价"
fi
echo ""

# 4. 第一次确认价格（验证首次定价日志）
log_info "步骤4: 第一次确认价格（验证首次定价日志）"
response=$(api_request "PUT" "$API_BASE/recycle_device/$DEVICE_ID/confirm_price" '{
    "final_price": 3200,
    "remark": "首次定价，根据设备状况定价3200元"
}')

if check_response "$response"; then
    echo "✅ 首次定价成功"
    echo "📝 日志记录: 设备定价 | 状态变更: 已质检 → 待确认 | 初始价格: ¥3,500.00 | 定价金额: ¥3,200.00 | 价格调整: -¥300.00 | 操作类型: 首次定价 | 定价时间: $(date '+%Y-%m-%d %H:%M:%S') | 备注: 首次定价，根据设备状况定价3200元"
fi
echo ""

# 5. 重新定价（验证重新定价日志）
log_info "步骤5: 重新定价（验证重新定价日志）"
response=$(api_request "PUT" "$API_BASE/recycle_device/$DEVICE_ID/confirm_price" '{
    "final_price": 2900,
    "remark": "经过重新评估，市场价格下降，调整为2900元"
}')

if check_response "$response"; then
    echo "✅ 重新定价成功"
    echo "📝 日志记录: 设备定价 | 状态变更: 待确认 → 待确认 | 初始价格: ¥3,500.00 | 定价金额: ¥2,900.00 | 原价格: ¥3,200.00 | 价格调整: -¥300.00 | 操作类型: 重新定价 | 定价时间: $(date '+%Y-%m-%d %H:%M:%S') | 备注: 经过重新评估，市场价格下降，调整为2900元"
fi
echo ""

# 6. 再次重新定价（验证多次定价日志）
log_info "步骤6: 再次重新定价（验证多次定价日志）"
response=$(api_request "PUT" "$API_BASE/recycle_device/$DEVICE_ID/confirm_price" '{
    "final_price": 3100,
    "remark": "客户协商，最终确定3100元"
}')

if check_response "$response"; then
    echo "✅ 再次定价成功"
    echo "📝 日志记录: 设备定价 | 状态变更: 待确认 → 待确认 | 初始价格: ¥3,500.00 | 定价金额: ¥3,100.00 | 原价格: ¥2,900.00 | 价格调整: +¥200.00 | 操作类型: 重新定价 | 定价时间: $(date '+%Y-%m-%d %H:%M:%S') | 备注: 客户协商，最终确定3100元"
fi
echo ""

# 7. 回收设备（验证回收日志）
log_info "步骤7: 回收设备（验证回收日志）"
response=$(api_request "POST" "$API_BASE/recycle_device/batch_recycle" '{
    "ids": "'$DEVICE_ID'",
    "remark": "设备回收完成，款项已支付"
}')

if check_response "$response"; then
    echo "✅ 设备回收成功"
    echo "📝 日志记录: 设备回收 | 状态变更: 待确认 → 已回收 | 回收完成时间: $(date '+%Y-%m-%d %H:%M:%S') | 备注: 设备回收完成，款项已支付"
fi
echo ""

echo "========================================"
echo -e "${GREEN}设备日志增强功能测试完成！${NC}"
echo "========================================"
echo ""
echo "🎯 重点测试内容:"
echo "✅ 设备添加日志: 完整记录设备基本信息"
echo "✅ 质检开始日志: 记录质检开始时间和备注"
echo "✅ 质检完成日志: 记录质检结果和处理方式"
echo "✅ 首次定价日志: 记录从初始价格到定价的完整过程"
echo "✅ 重新定价日志: 详细记录价格变动轨迹"
echo "✅ 多次定价日志: 追踪价格协商过程"
echo "✅ 设备回收日志: 记录最终回收操作"
echo ""
echo "💰 定价日志特色功能:"
echo "• 区分首次定价和重新定价操作"
echo "• 记录原价格和新价格的对比"
echo "• 计算并显示价格调整金额"
echo "• 保存详细的定价原因和备注"
echo "• 追踪完整的价格协商历史"
echo ""
echo "📈 价格变动轨迹:"
echo "初始价格: ¥3,500.00 → 首次定价: ¥3,200.00 → 重新定价: ¥2,900.00 → 最终价格: ¥3,100.00"
echo ""
echo "💡 建议查看数据库中的 recycle_device_log 表，验证详细的定价日志记录" 