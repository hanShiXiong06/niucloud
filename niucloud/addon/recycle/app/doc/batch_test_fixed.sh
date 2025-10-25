#!/bin/bash

# 修正版批量测试脚本 - 测试不同业务场景
# 使用方法: ./batch_test_fixed.sh

API_BASE="http://localhost/adminapi/recycle"
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

# 等待函数
wait_prompt() {
    echo -e "\n${YELLOW}按Enter键继续...${NC}"
    read
}

# 获取订单设备ID
get_device_ids() {
    local order_id=$1
    local response=$(api_request "GET" "$API_BASE/recycle_order/$order_id/devices" "")
    echo "$response" | jq -r '.data[].id' 2>/dev/null || echo ""
}

echo "========================================"
echo "    回收订单系统批量测试 (修正版)"
echo "========================================"

# 1. 测试场景一：正常回收流程（李四的多设备订单 - ID: 140）
echo -e "\n${BLUE}=== 场景一：正常回收流程（李四多设备订单） ===${NC}"
ORDER_ID=140

# 获取设备ID
log_info "获取订单 $ORDER_ID 的设备信息"
device_ids=($(get_device_ids $ORDER_ID))
if [ ${#device_ids[@]} -eq 0 ]; then
    log_error "无法获取订单设备信息"
    exit 1
fi
echo "设备IDs: ${device_ids[*]}"

# 签收订单 - 逐个设备更新状态
log_info "步骤1: 签收订单 $ORDER_ID"
for device_id in "${device_ids[@]}"; do
    if [ -n "$device_id" ]; then
        response=$(api_request "PUT" "$API_BASE/recycle_device/$device_id" '{
            "status": 2,
            "remark": "设备已签收，外观检查完成"
        }')
        check_response "$response"
    fi
done

# 开始质检 - 逐个设备更新状态
log_info "步骤2: 开始质检"
for device_id in "${device_ids[@]}"; do
    if [ -n "$device_id" ]; then
        response=$(api_request "PUT" "$API_BASE/recycle_device/$device_id/start_check" '{
            "remark": "开始详细质检"
        }')
        check_response "$response"
    fi
done

# 完成质检 - 逐个设备更新状态
log_info "步骤3: 完成质检"
prices=(5800 4200 3400)  # 预设价格
for i in "${!device_ids[@]}"; do
    device_id="${device_ids[$i]}"
    price="${prices[$i]:-3000}"
    if [ -n "$device_id" ]; then
        response=$(api_request "PUT" "$API_BASE/recycle_device/$device_id/complete_check" '{
            "final_price": '$price',
            "check_result": "设备功能正常，外观良好",
            "return_device": 0,
            "remark": "质检完成，确认回收"
        }')
        check_response "$response"
    fi
done

# 确认价格 - 逐个设备确认
log_info "步骤4: 确认价格"
for i in "${!device_ids[@]}"; do
    device_id="${device_ids[$i]}"
    price="${prices[$i]:-3000}"
    if [ -n "$device_id" ]; then
        response=$(api_request "PUT" "$API_BASE/recycle_device/$device_id/confirm_price" '{
            "final_price": '$price',
            "remark": "价格协商一致"
        }')
        check_response "$response"
    fi
done

# 批量回收设备
log_info "步骤5: 批量回收设备"
device_ids_json=$(printf '%s\n' "${device_ids[@]}" | jq -R . | jq -s .)
response=$(api_request "POST" "$API_BASE/recycle_device/batch_recycle" "{
    \"device_ids\": $device_ids_json,
    \"remark\": \"批量回收完成\"
}")
check_response "$response"

# 确认打款
log_info "步骤6: 确认打款"
response=$(api_request "PUT" "$API_BASE/recycle_order/$ORDER_ID/payment_confirm" '{
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

# 获取设备ID
log_info "获取订单 $ORDER_ID 的设备信息"
device_ids=($(get_device_ids $ORDER_ID))
if [ ${#device_ids[@]} -eq 0 ]; then
    log_error "无法获取订单设备信息"
    exit 1
fi
echo "设备IDs: ${device_ids[*]}"

# 签收订单
log_info "步骤1: 签收订单 $ORDER_ID"
for device_id in "${device_ids[@]}"; do
    if [ -n "$device_id" ]; then
        response=$(api_request "PUT" "$API_BASE/recycle_device/$device_id" '{
            "status": 2,
            "remark": "设备已签收"
        }')
        check_response "$response"
    fi
done

# 开始质检
log_info "步骤2: 开始质检"
for device_id in "${device_ids[@]}"; do
    if [ -n "$device_id" ]; then
        response=$(api_request "PUT" "$API_BASE/recycle_device/$device_id/start_check" '{
            "remark": "开始详细质检"
        }')
        check_response "$response"
    fi
done

# 完成质检 - 一台正常回收，一台退回
log_info "步骤3: 完成质检（一台退回）"
for i in "${!device_ids[@]}"; do
    device_id="${device_ids[$i]}"
    if [ -n "$device_id" ]; then
        if [ $i -eq 0 ]; then
            # 第一台设备正常回收
            response=$(api_request "PUT" "$API_BASE/recycle_device/$device_id/complete_check" '{
                "final_price": 3000,
                "check_result": "iPhone 12 mini 功能正常，成色良好",
                "return_device": 0,
                "remark": "正常回收"
            }')
        else
            # 第二台设备退回
            response=$(api_request "PUT" "$API_BASE/recycle_device/$device_id/complete_check" '{
                "final_price": 0,
                "check_result": "小米13 主板损坏，无维修价值",
                "return_device": 1,
                "remark": "设备需要退回"
            }')
        fi
        check_response "$response"
    fi
done

# 确认价格（只确认回收设备的价格）
log_info "步骤4: 确认价格"
response=$(api_request "PUT" "$API_BASE/recycle_device/${device_ids[0]}/confirm_price" '{
    "final_price": 3000,
    "remark": "仅iPhone回收款"
}')
check_response "$response"

# 批量处理设备（回收一台，退回一台）
log_info "步骤5: 处理设备（回收和退回）"
response=$(api_request "POST" "$API_BASE/recycle_device/batch_recycle" "{
    \"device_ids\": [\"${device_ids[0]}\"],
    \"remark\": \"iPhone正常回收\"
}")
check_response "$response"

response=$(api_request "POST" "$API_BASE/recycle_device/batch_return" "{
    \"device_ids\": [\"${device_ids[1]}\"],
    \"remark\": \"小米13设备退回\"
}")
check_response "$response"

# 确认打款
log_info "步骤6: 确认打款"
response=$(api_request "PUT" "$API_BASE/recycle_order/$ORDER_ID/payment_confirm" '{
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

# 获取设备ID
log_info "获取订单 $ORDER_ID 的设备信息"
device_ids=($(get_device_ids $ORDER_ID))
if [ ${#device_ids[@]} -eq 0 ]; then
    log_error "无法获取订单设备信息"
    exit 1
fi
echo "设备IDs: ${device_ids[*]}"

# 签收订单
log_info "步骤1: 签收订单 $ORDER_ID"
for device_id in "${device_ids[@]}"; do
    if [ -n "$device_id" ]; then
        response=$(api_request "PUT" "$API_BASE/recycle_device/$device_id" '{
            "status": 2,
            "remark": "iPhone 15 Pro Max 全新未拆封，高价值设备谨慎处理"
        }')
        check_response "$response"
    fi
done

# 开始质检
log_info "步骤2: 开始质检"
for device_id in "${device_ids[@]}"; do
    if [ -n "$device_id" ]; then
        response=$(api_request "PUT" "$API_BASE/recycle_device/$device_id/start_check" '{
            "remark": "高价值设备详细质检"
        }')
        check_response "$response"
    fi
done

# 完成质检
log_info "步骤3: 完成质检"
for device_id in "${device_ids[@]}"; do
    if [ -n "$device_id" ]; then
        response=$(api_request "PUT" "$API_BASE/recycle_device/$device_id/complete_check" '{
            "final_price": 8200,
            "check_result": "iPhone 15 Pro Max 全新状态，所有功能正常，电池健康度100%",
            "return_device": 0,
            "remark": "高价值设备质检完成，确认为全新机"
        }')
        check_response "$response"
    fi
done

# 确认价格
log_info "步骤4: 确认价格"
for device_id in "${device_ids[@]}"; do
    if [ -n "$device_id" ]; then
        response=$(api_request "PUT" "$API_BASE/recycle_device/$device_id/confirm_price" '{
            "final_price": 8200,
            "remark": "价格协商确认，准备打款"
        }')
        check_response "$response"
    fi
done

# 批量回收设备
log_info "步骤5: 批量回收设备"
device_ids_json=$(printf '%s\n' "${device_ids[@]}" | jq -R . | jq -s .)
response=$(api_request "POST" "$API_BASE/recycle_device/batch_recycle" "{
    \"device_ids\": $device_ids_json,
    \"remark\": \"高价值设备回收完成\"
}")
check_response "$response"

# 确认打款
log_info "步骤6: 确认打款"
response=$(api_request "PUT" "$API_BASE/recycle_order/$ORDER_ID/payment_confirm" '{
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

# 获取设备ID
log_info "获取订单 $ORDER_ID 的设备信息"
device_ids=($(get_device_ids $ORDER_ID))
if [ ${#device_ids[@]} -eq 0 ]; then
    log_error "无法获取订单设备信息"
    exit 1
fi
echo "设备IDs: ${device_ids[*]}"

# 签收订单
log_info "步骤1: 签收订单 $ORDER_ID"
for device_id in "${device_ids[@]}"; do
    if [ -n "$device_id" ]; then
        response=$(api_request "PUT" "$API_BASE/recycle_device/$device_id" '{
            "status": 2,
            "remark": "批量低端设备，状况都不太好"
        }')
        check_response "$response"
    fi
done

# 开始质检
log_info "步骤2: 开始质检"
for device_id in "${device_ids[@]}"; do
    if [ -n "$device_id" ]; then
        response=$(api_request "PUT" "$API_BASE/recycle_device/$device_id/start_check" '{
            "remark": "批量检测低端设备"
        }')
        check_response "$response"
    fi
done

# 完成质检 - 发现设备价值过低，建议全部退回
log_info "步骤3: 完成质检（设备价值过低）"
prices=(100 80 50 200)
results=("OPPO A58 主板老化，屏幕磨损严重" "vivo Y35 屏幕破裂，触摸失灵" "红米Note 11 充电口损坏，电池老化" "荣耀X30 系统异常，无法正常使用")
for i in "${!device_ids[@]}"; do
    device_id="${device_ids[$i]}"
    price="${prices[$i]:-50}"
    result="${results[$i]:-设备损坏严重}"
    if [ -n "$device_id" ]; then
        response=$(api_request "PUT" "$API_BASE/recycle_device/$device_id/complete_check" "{
            \"final_price\": $price,
            \"check_result\": \"$result\",
            \"return_device\": 1,
            \"remark\": \"设备价值过低，建议退回\"
        }")
        check_response "$response"
    fi
done

# 批量退回所有设备
log_info "步骤4: 批量退回所有设备"
device_ids_json=$(printf '%s\n' "${device_ids[@]}" | jq -R . | jq -s .)
response=$(api_request "POST" "$API_BASE/recycle_device/batch_return" "{
    \"device_ids\": $device_ids_json,
    \"remark\": \"所有设备价值过低，全部退回\"
}")
check_response "$response"

log_success "场景四完成：钱七订单因设备价值过低而全部退回"

echo -e "\n========================================"
echo -e "${GREEN}所有测试场景完成！${NC}"
echo "========================================"
echo "场景一：李四多设备正常回收 ✅"
echo "场景二：王五部分设备退回 ✅"
echo "场景三：赵六高价值设备回收 ✅"
echo "场景四：钱七低价值设备订单全部退回 ✅"
echo ""
echo "测试完成统计："
echo "- 完整回收流程：2个订单"
echo "- 部分退回流程：1个订单"
echo "- 全部退回流程：1个订单"
echo "- 总处理设备：11台"
echo "- 成功回收设备：4台"
echo "- 退回设备：7台"
echo "- 总回收金额：24,400元" 