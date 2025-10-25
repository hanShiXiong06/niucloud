#!/bin/bash

# 快速订单流转测试脚本
# 使用方法: ./quick_flow_test.sh [场景编号]

API_BASE="http://localhost/adminapi/recycle"
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1aWQiOjEsInVzZXJuYW1lIjoiYWRtaW4iLCJpc3MiOiJsb2NhbGhvc3QiLCJhdWQiOiJsb2NhbGhvc3QiLCJpYXQiOjE3NDgxNjY0OTMsIm5iZiI6MTc0ODE2NjQ5MywiZXhwIjoxNzQ4NzcxMjkzLCJqdGkiOiIxX2FkbWluIn0.DkKfy7Tp6bt1iqP5RfelsUvqoPiRNqkvU0SzDyd7zuo"
SITE_ID="100000"

# 颜色定义
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

# API请求函数
api_request() {
    local method=$1 url=$2 data=$3
    if [ "$method" = "GET" ]; then
        curl -s --location --request GET "$url" \
            --header "site-id: $SITE_ID" --header "token: $TOKEN"
    else
        curl -s --location --request $method "$url" \
            --header "site-id: $SITE_ID" --header "token: $TOKEN" \
            --header "Content-Type: application/json" --data "$data"
    fi
}

# 检查响应
check_api() {
    local response=$1 operation=$2
    local code=$(echo "$response" | jq -r '.code // empty')
    if [ "$code" = "1" ]; then
        echo -e "${GREEN}✅ $operation 成功${NC}"
        return 0
    else
        echo -e "${RED}❌ $operation 失败: $(echo "$response" | jq -r '.msg // "未知错误"')${NC}"
        return 1
    fi
}

# 快速执行场景函数
run_scenario() {
    local scenario=$1
    
    case $scenario in
        1)
            echo -e "${BLUE}🚀 快速测试: 正常回收流转${NC}"
                         # 创建订单
             response=$(api_request "POST" "$API_BASE/recycle_order/create" '{
                 "member_id": 1, "customer_name": "快速测试用户", "customer_phone": "13800138999",
                 "delivery_type": 1, "remark": "快速正常回收测试", "devices": []
             }')
            check_api "$response" "创建订单" || return 1
            local order_id=$(echo "$response" | jq -r '.data.id')
            
                         # 添加设备（使用随机IMEI避免重复）
             local random_suffix=$(date +%s%N | cut -c-10)
             response=$(api_request "POST" "$API_BASE/recycle_order/$order_id/add_device" '{
                 "imei": "QUICK_TEST_'$random_suffix'", "model": "iPhone 14", "initial_price": 5000, "remark": "快速测试设备"
             }')
            check_api "$response" "添加设备" || return 1
            local device_id=$(echo "$response" | jq -r '.data.device_id')
            
                         # 先更新订单状态为已签收
             api_request "PUT" "$API_BASE/recycle_order/$order_id" '{
                 "action": "sign", "devices": [], "remark": "订单签收确认"
             }' > /dev/null
             
             # 质检流程
             api_request "PUT" "$API_BASE/recycle_device/$device_id/start_check" '{"remark": "开始质检"}' > /dev/null
            response=$(api_request "PUT" "$API_BASE/recycle_device/$device_id/complete_check" '{
                "check_result": "设备完好", "final_price": 4800, "return_device": 0, "remark": "质检完成"
            }')
            check_api "$response" "完成质检和定价" || return 1
            
            # 回收
            response=$(api_request "POST" "$API_BASE/recycle_device/batch_recycle" '{
                "ids": "'$device_id'", "remark": "确认回收"
            }')
            check_api "$response" "设备回收" || return 1
            
            # 打款
            response=$(api_request "PUT" "$API_BASE/recycle_order/$order_id/payment_confirm" '{
                "payment_amount": 4800, "payment_method": "银行转账", "remark": "打款完成"
            }')
            check_api "$response" "确认打款" || return 1
            
            echo -e "${GREEN}🎉 正常回收流转测试完成！订单ID: $order_id, 设备ID: $device_id${NC}"
            ;;
            
        2)
            echo -e "${BLUE}🚀 快速测试: 设备退回流转${NC}"
                         # 创建订单和设备
             response=$(api_request "POST" "$API_BASE/recycle_order/create" '{
                 "member_id": 1, "customer_name": "退回测试用户", "customer_phone": "13800138998",
                 "delivery_type": 1, "remark": "快速退回测试", "devices": []
             }')
            check_api "$response" "创建订单" || return 1
            local order_id=$(echo "$response" | jq -r '.data.id')
            
                         local random_suffix=$(date +%s%N | cut -c-10)
             response=$(api_request "POST" "$API_BASE/recycle_order/$order_id/add_device" '{
                 "imei": "QUICK_RETURN_'$random_suffix'", "model": "iPad Pro", "initial_price": 4000, "remark": "退回测试设备"
             }')
            check_api "$response" "添加设备" || return 1
            local device_id=$(echo "$response" | jq -r '.data.device_id')
            
                         # 先更新订单状态为已签收
             api_request "PUT" "$API_BASE/recycle_order/$order_id" '{
                 "action": "sign", "devices": [], "remark": "订单签收确认"
             }' > /dev/null
             
             # 质检但价格过低
             api_request "PUT" "$API_BASE/recycle_device/$device_id/start_check" '{"remark": "开始质检"}' > /dev/null
            response=$(api_request "PUT" "$API_BASE/recycle_device/$device_id/complete_check" '{
                "check_result": "设备有损伤", "final_price": 2000, "return_device": 0, "remark": "价格偏低"
            }')
            check_api "$response" "完成质检和定价" || return 1
            
            # 客户要求退回
            response=$(api_request "POST" "$API_BASE/recycle_device/batch_return" '{
                "ids": "'$device_id'", "remark": "客户对价格不满意，要求退回"
            }')
            check_api "$response" "设备退回" || return 1
            
            echo -e "${GREEN}🎉 设备退回流转测试完成！订单ID: $order_id, 设备ID: $device_id${NC}"
            ;;
            
        3)
            echo -e "${BLUE}🚀 快速测试: 重新定价流转${NC}"
                         # 创建订单和设备
             response=$(api_request "POST" "$API_BASE/recycle_order/create" '{
                 "member_id": 1, "customer_name": "重新定价用户", "customer_phone": "13800138997",
                 "delivery_type": 1, "remark": "重新定价测试", "devices": []
             }')
            check_api "$response" "创建订单" || return 1
            local order_id=$(echo "$response" | jq -r '.data.id')
            
                         local random_suffix=$(date +%s%N | cut -c-10)
             response=$(api_request "POST" "$API_BASE/recycle_order/$order_id/add_device" '{
                 "imei": "QUICK_REPRICE_'$random_suffix'", "model": "MacBook Pro", "initial_price": 8000, "remark": "重新定价测试"
             }')
            check_api "$response" "添加设备" || return 1
            local device_id=$(echo "$response" | jq -r '.data.device_id')
            
                         # 先更新订单状态为已签收
             api_request "PUT" "$API_BASE/recycle_order/$order_id" '{
                 "action": "sign", "devices": [], "remark": "订单签收确认"
             }' > /dev/null
             
             # 质检定价
             api_request "PUT" "$API_BASE/recycle_device/$device_id/start_check" '{"remark": "开始质检"}' > /dev/null
            response=$(api_request "PUT" "$API_BASE/recycle_device/$device_id/complete_check" '{
                "check_result": "设备状况良好", "final_price": 7000, "return_device": 0, "remark": "初次定价"
            }')
            check_api "$response" "初次定价" || return 1
            
            # 重新定价
            response=$(api_request "PUT" "$API_BASE/recycle_device/$device_id/confirm_price" '{
                "final_price": 7500, "remark": "客户协商，适当提高价格"
            }')
            check_api "$response" "重新定价" || return 1
            
            # 最终定价并回收
            response=$(api_request "PUT" "$API_BASE/recycle_device/$device_id/confirm_price" '{
                "final_price": 7300, "remark": "最终协商价格"
            }')
            check_api "$response" "最终定价" || return 1
            
            response=$(api_request "POST" "$API_BASE/recycle_device/batch_recycle" '{
                "ids": "'$device_id'", "remark": "客户同意最终价格"
            }')
            check_api "$response" "确认回收" || return 1
            
            echo -e "${GREEN}🎉 重新定价流转测试完成！价格轨迹: ¥8,000 → ¥7,000 → ¥7,500 → ¥7,300${NC}"
            echo -e "${GREEN}订单ID: $order_id, 设备ID: $device_id${NC}"
            ;;
            
        4)
            echo -e "${BLUE}🚀 快速测试: 质检中直接退回${NC}"
                         # 创建订单和设备
             response=$(api_request "POST" "$API_BASE/recycle_order/create" '{
                 "member_id": 1, "customer_name": "损坏设备用户", "customer_phone": "13800138996",
                 "delivery_type": 1, "remark": "损坏设备测试", "devices": []
             }')
            check_api "$response" "创建订单" || return 1
            local order_id=$(echo "$response" | jq -r '.data.id')
            
                         local random_suffix=$(date +%s%N | cut -c-10)
             response=$(api_request "POST" "$API_BASE/recycle_order/$order_id/add_device" '{
                 "imei": "QUICK_DAMAGED_'$random_suffix'", "model": "iPhone 13", "initial_price": 5000, "remark": "损坏设备测试"
             }')
            check_api "$response" "添加设备" || return 1
            local device_id=$(echo "$response" | jq -r '.data.device_id')
            
                         # 先更新订单状态为已签收
             api_request "PUT" "$API_BASE/recycle_order/$order_id" '{
                 "action": "sign", "devices": [], "remark": "订单签收确认"
             }' > /dev/null
             
             # 质检发现严重损坏，直接退回
             api_request "PUT" "$API_BASE/recycle_device/$device_id/start_check" '{"remark": "开始质检"}' > /dev/null
            response=$(api_request "PUT" "$API_BASE/recycle_device/$device_id/complete_check" '{
                "check_result": "设备主板损坏，无法开机", "return_device": 1, "remark": "设备严重损坏，建议退回"
            }')
            check_api "$response" "质检发现问题并退回" || return 1
            
            echo -e "${GREEN}🎉 质检直接退回流转测试完成！订单ID: $order_id, 设备ID: $device_id${NC}"
            ;;
            
        *)
            echo -e "${YELLOW}请选择测试场景：${NC}"
            echo "1 - 正常回收流转（签收→质检→定价→回收→打款）"
            echo "2 - 设备退回流转（质检后客户不满意价格退回）"
            echo "3 - 重新定价流转（多轮价格协商）"
            echo "4 - 质检直接退回（发现设备严重损坏）"
            echo ""
            echo "使用方法: $0 [1-4]"
            ;;
    esac
}

# 主程序
scenario=${1:-0}

echo -e "${BLUE}======================================${NC}"
echo -e "${BLUE}    快速订单流转测试工具${NC}"
echo -e "${BLUE}======================================${NC}"
echo ""

if [ "$scenario" -eq 0 ]; then
    echo -e "${YELLOW}选择要测试的场景：${NC}"
    echo "1️⃣  正常回收流转"
    echo "2️⃣  设备退回流转"
    echo "3️⃣  重新定价流转"
    echo "4️⃣  质检直接退回"
    echo ""
    read -p "请输入场景编号 (1-4): " scenario
fi

echo ""
run_scenario $scenario

echo ""
echo -e "${BLUE}======================================${NC}"
echo -e "${BLUE}    测试完成${NC}"
echo -e "${BLUE}======================================${NC}" 