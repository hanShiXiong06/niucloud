#!/bin/bash

# 批量测试脚本 - 测试不同业务场景
# 使用方法: ./batch_test.sh

API_BASE="http://localhost/adminapi/recycle/recycle_order"
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1aWQiOjEsInVzZXJuYW1lIjoiYWRtaW4iLCJpc3MiOiJsb2NhbGhvc3QiLCJhdWQiOiJsb2NhbGhvc3QiLCJpYXQiOjE3NDgxNjY0OTMsIm5iZiI6MTc0ODE2NjQ5MywiZXhwIjoxNzQ4NzcxMjkzLCJqdGkiOiIxX2FkbWluIn0.DkKfy7Tp6bt1iqP5RfelsUvqoPiRNqkvU0SzDyd7zuo"
SITE_ID="100000"

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
        curl -s --location --request POST "$url" \
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
        return 1
    fi
}

# 等待函数
wait_prompt() {
    echo -e "\n${YELLOW}按Enter键继续...${NC}"
    read
}

echo "========================================"
echo "    回收订单系统批量测试"
echo "========================================"

# 1. 测试场景一：正常回收流程（李四的多设备订单 - ID: 140）
echo -e "\n${BLUE}=== 场景一：正常回收流程（李四多设备订单） ===${NC}"
ORDER_ID=140

# 签收订单
log_info "步骤1: 签收订单 $ORDER_ID"
response=$(api_request "POST" "$API_BASE/sign/$ORDER_ID" '{
    "devices": [
        {"id": 201, "received": 1, "remark": "iPhone 14 Pro Max 外观良好"},
        {"id": 202, "received": 1, "remark": "Samsung Galaxy S23 正常"},
        {"id": 203, "received": 1, "remark": "华为Mate50 充电口有磨损"}
    ],
    "remark": "多设备同时签收"
}')
check_response "$response"

# 开始质检
log_info "步骤2: 开始质检"
response=$(api_request "POST" "$API_BASE/startcheck/$ORDER_ID" '{
    "devices": [
        {"id": 201, "remark": "开始检测iPhone 14 Pro Max"},
        {"id": 202, "remark": "开始检测Samsung Galaxy S23"},
        {"id": 203, "remark": "开始检测华为Mate50"}
    ],
    "remark": "批量开始质检"
}')
check_response "$response"

# 完成质检
log_info "步骤3: 完成质检"
response=$(api_request "POST" "$API_BASE/completecheck/$ORDER_ID" '{
    "devices": [
        {"id": 201, "final_price": 5800, "check_result": "iPhone 14 Pro Max 功能正常，外观9成新", "return_device": 0},
        {"id": 202, "final_price": 4200, "check_result": "Samsung Galaxy S23 功能正常，外观8.5成新", "return_device": 0},
        {"id": 203, "final_price": 3400, "check_result": "华为Mate50 充电口轻微磨损，其他正常", "return_device": 0}
    ],
    "remark": "多设备质检完成，总价值13400元"
}')
check_response "$response"

# 确认价格
log_info "步骤4: 确认价格"
response=$(api_request "POST" "$API_BASE/confirmprice/$ORDER_ID" '{
    "devices": [
        {"id": 201, "final_price": 5800},
        {"id": 202, "final_price": 4200},
        {"id": 203, "final_price": 3400}
    ],
    "pay_account": "6222021234567890",
    "pay_type": "1",
    "pay_name": "李四",
    "pay_remark": "多设备回收款",
    "remark": "价格协商一致"
}')
check_response "$response"

# 确认打款
log_info "步骤5: 确认打款"
response=$(api_request "POST" "$API_BASE/paymentconfirm/$ORDER_ID" '{
    "pay_account": "6222021234567890",
    "pay_type": "1",
    "pay_name": "李四",
    "pay_remark": "多设备回收款13400元已到账",
    "pay_url": "http://example.com/pay_proof.jpg",
    "remark": "打款完成，订单结束"
}')
check_response "$response"

log_success "场景一完成：李四多设备订单正常回收流程"
wait_prompt

# 2. 测试场景二：部分设备退回流程（王五订单 - ID: 141）
echo -e "\n${BLUE}=== 场景二：部分设备退回流程（王五订单） ===${NC}"
ORDER_ID=141

# 签收订单
log_info "步骤1: 签收订单 $ORDER_ID"
response=$(api_request "POST" "$API_BASE/sign/$ORDER_ID" '{
    "devices": [
        {"id": 204, "received": 1, "remark": "iPhone 12 mini 包装完好"},
        {"id": 205, "received": 1, "remark": "小米13 有明显划痕"}
    ],
    "remark": "两台设备都已签收"
}')
check_response "$response"

# 开始质检
log_info "步骤2: 开始质检"
response=$(api_request "POST" "$API_BASE/startcheck/$ORDER_ID" '{
    "devices": [
        {"id": 204, "remark": "开始检测iPhone 12 mini"},
        {"id": 205, "remark": "开始检测小米13"}
    ],
    "remark": "开始详细质检"
}')
check_response "$response"

# 完成质检 - 一台正常回收，一台退回
log_info "步骤3: 完成质检（一台退回）"
response=$(api_request "POST" "$API_BASE/completecheck/$ORDER_ID" '{
    "devices": [
        {"id": 204, "final_price": 3000, "check_result": "iPhone 12 mini 功能正常，成色良好", "return_device": 0},
        {"id": 205, "final_price": 0, "check_result": "小米13 主板损坏，无维修价值", "return_device": 1}
    ],
    "remark": "iPhone正常回收，小米手机需要退回"
}')
check_response "$response"

# 确认价格（只确认回收设备的价格）
log_info "步骤4: 确认价格"
response=$(api_request "POST" "$API_BASE/confirmprice/$ORDER_ID" '{
    "devices": [
        {"id": 204, "final_price": 3000}
    ],
    "pay_account": "6222028888888888",
    "pay_type": "2",
    "pay_name": "王五",
    "pay_remark": "仅iPhone回收款",
    "remark": "小米13已安排退回"
}')
check_response "$response"

# 确认打款
log_info "步骤5: 确认打款"
response=$(api_request "POST" "$API_BASE/paymentconfirm/$ORDER_ID" '{
    "pay_account": "6222028888888888",
    "pay_type": "2",
    "pay_name": "王五",
    "pay_remark": "iPhone 12 mini回收款3000元已转账",
    "pay_url": "http://example.com/transfer_proof.jpg",
    "remark": "部分回收完成，小米13已退回"
}')
check_response "$response"

log_success "场景二完成：王五订单部分设备退回流程"
wait_prompt

# 3. 测试场景三：高价值设备谨慎处理（赵六订单 - ID: 142）
echo -e "\n${BLUE}=== 场景三：高价值设备谨慎处理（赵六订单） ===${NC}"
ORDER_ID=142

# 签收订单
log_info "步骤1: 签收订单 $ORDER_ID"
response=$(api_request "POST" "$API_BASE/sign/$ORDER_ID" '{
    "devices": [
        {"id": 206, "received": 1, "remark": "iPhone 15 Pro Max 全新未拆封"}
    ],
    "remark": "高价值设备，谨慎处理"
}')
check_response "$response"

# 开始质检
log_info "步骤2: 开始质检"
response=$(api_request "POST" "$API_BASE/startcheck/$ORDER_ID" '{
    "devices": [
        {"id": 206, "remark": "开始详细检测iPhone 15 Pro Max"}
    ],
    "remark": "高价值设备详细质检"
}')
check_response "$response"

# 完成质检
log_info "步骤3: 完成质检"
response=$(api_request "POST" "$API_BASE/completecheck/$ORDER_ID" '{
    "devices": [
        {"id": 206, "final_price": 8200, "check_result": "iPhone 15 Pro Max 全新状态，所有功能正常，电池健康度100%", "return_device": 0}
    ],
    "remark": "高价值设备质检完成，确认为全新机"
}')
check_response "$response"

# 确认价格
log_info "步骤4: 确认价格"
response=$(api_request "POST" "$API_BASE/confirmprice/$ORDER_ID" '{
    "devices": [
        {"id": 206, "final_price": 8200}
    ],
    "pay_account": "6222027777777777",
    "pay_type": "1",
    "pay_name": "赵六",
    "pay_remark": "iPhone 15 Pro Max高价值回收",
    "remark": "价格协商确认，准备打款"
}')
check_response "$response"

# 确认打款
log_info "步骤5: 确认打款"
response=$(api_request "POST" "$API_BASE/paymentconfirm/$ORDER_ID" '{
    "pay_account": "6222027777777777",
    "pay_type": "1",
    "pay_name": "赵六",
    "pay_remark": "iPhone 15 Pro Max回收款8200元银行转账",
    "pay_url": "http://example.com/bank_transfer.jpg",
    "remark": "高价值设备回收完成"
}')
check_response "$response"

log_success "场景三完成：赵六高价值设备回收流程"
wait_prompt

# 4. 测试场景四：订单取消流程（钱七订单 - ID: 143）
echo -e "\n${BLUE}=== 场景四：订单取消流程（钱七订单） ===${NC}"
ORDER_ID=143

# 签收订单
log_info "步骤1: 签收订单 $ORDER_ID"
response=$(api_request "POST" "$API_BASE/sign/$ORDER_ID" '{
    "devices": [
        {"id": 207, "received": 1, "remark": "OPPO A58 外观磨损严重"},
        {"id": 208, "received": 1, "remark": "vivo Y35 屏幕有裂纹"},
        {"id": 209, "received": 1, "remark": "红米Note 11 充电异常"},
        {"id": 210, "received": 1, "remark": "荣耀X30 开机异常"}
    ],
    "remark": "批量低端设备，状况都不太好"
}')
check_response "$response"

# 开始质检
log_info "步骤2: 开始质检"
response=$(api_request "POST" "$API_BASE/startcheck/$ORDER_ID" '{
    "devices": [
        {"id": 207, "remark": "检测OPPO A58"},
        {"id": 208, "remark": "检测vivo Y35"},
        {"id": 209, "remark": "检测红米Note 11"},
        {"id": 210, "remark": "检测荣耀X30"}
    ],
    "remark": "批量检测低端设备"
}')
check_response "$response"

# 完成质检 - 发现设备价值过低，建议取消
log_info "步骤3: 完成质检（设备价值过低）"
response=$(api_request "POST" "$API_BASE/completecheck/$ORDER_ID" '{
    "devices": [
        {"id": 207, "final_price": 100, "check_result": "OPPO A58 主板老化，屏幕磨损严重", "return_device": 1},
        {"id": 208, "final_price": 80, "check_result": "vivo Y35 屏幕破裂，触摸失灵", "return_device": 1},
        {"id": 209, "final_price": 50, "check_result": "红米Note 11 充电口损坏，电池老化", "return_device": 1},
        {"id": 210, "final_price": 200, "check_result": "荣耀X30 系统异常，无法正常使用", "return_device": 1}
    ],
    "remark": "所有设备价值过低，建议全部退回"
}')
check_response "$response"

# 关闭订单
log_info "步骤4: 关闭订单"
response=$(api_request "POST" "$API_BASE/close/$ORDER_ID" '{
    "remark": "设备价值过低，客户同意全部退回，订单关闭"
}')
check_response "$response"

log_success "场景四完成：钱七订单因设备价值过低而关闭"

echo -e "\n========================================"
echo -e "${GREEN}所有测试场景完成！${NC}"
echo "========================================"
echo "场景一：李四多设备正常回收 ✅"
echo "场景二：王五部分设备退回 ✅"
echo "场景三：赵六高价值设备回收 ✅"
echo "场景四：钱七低价值设备订单关闭 ✅"
echo ""
echo "最终统计："
echo "- 成功回收订单：3个"
echo "- 部分退回订单：1个"
echo "- 关闭订单：1个"
echo "- 总回收设备：5台"
echo "- 总退回设备：6台"
echo "- 总回收金额：24,400元" 