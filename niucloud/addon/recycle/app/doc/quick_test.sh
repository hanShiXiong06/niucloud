#!/bin/bash

# 快速测试脚本 - 用于单独测试各个接口
# 使用方法: ./quick_test.sh [action] [params...]

BASE_URL="http://localhost"
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1aWQiOjEsInVzZXJuYW1lIjoiYWRtaW4iLCJpc3MiOiJsb2NhbGhvc3QiLCJhdWQiOiJsb2NhbGhvc3QiLCJpYXQiOjE3NDgxNjY0OTMsIm5iZiI6MTc0ODE2NjQ5MywiZXhwIjoxNzQ4NzcxMjkzLCJqdGkiOiIxX2FkbWluIn0.DkKfy7Tp6bt1iqP5RfelsUvqoPiRNqkvU0SzDyd7zuo"
SITE_ID="100000"

# 通用Header
HEADERS="-H 'Content-Type: application/json' -H 'token: $TOKEN' -H 'site-id: $SITE_ID'"

# 显示帮助信息
show_help() {
    echo "回收订单快速测试工具"
    echo ""
    echo "使用方法: ./quick_test.sh [action] [params...]"
    echo ""
    echo "可用操作:"
    echo "  create                    - 创建订单"
    echo "  list                      - 查询订单列表"
    echo "  detail [order_id]         - 查询订单详情"
    echo "  sign [order_id]           - 签收订单"
    echo "  start_check [device_id]   - 开始质检设备"
    echo "  complete_check [device_id] - 完成质检设备"
    echo "  confirm_price [device_id] - 确认设备价格"
    echo "  return [device_id]        - 退回设备"
    echo "  recycle [device_id]       - 回收设备"
    echo "  payment [order_id]        - 确认打款"
    echo "  status                    - 获取订单状态"
    echo "  device [device_id]        - 获取设备信息"
    echo ""
    echo "示例:"
    echo "  ./quick_test.sh create"
    echo "  ./quick_test.sh detail 123"
    echo "  ./quick_test.sh sign 123"
}

# 格式化输出JSON
format_json() {
    echo "$1" | python3 -m json.tool 2>/dev/null || echo "$1"
}

# 执行请求并格式化输出
execute_request() {
    local method="$1"
    local url="$2"
    local data="$3"
    
    echo "请求: $method $url"
    if [ -n "$data" ]; then
        echo "数据: $data"
    fi
    echo "----------------------------------------"
    
    if [ "$method" = "GET" ]; then
        response=$(curl -s -X GET "$url" $HEADERS)
    else
        response=$(curl -s -X "$method" "$url" $HEADERS -d "$data")
    fi
    
    echo "响应:"
    format_json "$response"
    echo ""
}

# 主要功能
case "$1" in
    "create")
        echo "创建测试订单..."
        data='{
          "member_id": 1,
          "customer_name": "测试用户",
          "customer_phone": "13800138000",
          "delivery_type": 1,
          "express_no": "TEST123456",
          "express_company": "顺丰快递",
          "remark": "快速测试订单",
          "devices": [
            {
              "imei": "123456789012345",
              "model": "iPhone 13 Pro",
              "initial_price": 5000
            }
          ]
        }'
        execute_request "POST" "$BASE_URL/adminapi/recycle/recycle_order/create" "$data"
        ;;
        
    "list")
        echo "查询订单列表..."
        execute_request "GET" "$BASE_URL/adminapi/recycle/recycle_order/lists?page=1&limit=10"
        ;;
        
    "detail")
        if [ -z "$2" ]; then
            echo "错误: 请提供订单ID"
            echo "使用方法: ./quick_test.sh detail [order_id]"
            exit 1
        fi
        echo "查询订单详情 (ID: $2)..."
        execute_request "GET" "$BASE_URL/adminapi/recycle/recycle_order/detail/$2"
        ;;
        
    "sign")
        if [ -z "$2" ]; then
            echo "错误: 请提供订单ID"
            echo "使用方法: ./quick_test.sh sign [order_id]"
            exit 1
        fi
        echo "签收订单 (ID: $2)..."
        data='{
          "action": "order_sign",
          "devices": [
            {
              "id": "1",
              "imei": "123456789012345",
              "model": "iPhone 13 Pro",
              "initial_price": 5000
            }
          ],
          "remark": "快速测试签收"
        }'
        execute_request "PUT" "$BASE_URL/adminapi/recycle/recycle_order/$2" "$data"
        ;;
        
    "start_check")
        if [ -z "$2" ]; then
            echo "错误: 请提供设备ID"
            echo "使用方法: ./quick_test.sh start_check [device_id]"
            exit 1
        fi
        echo "开始质检设备 (ID: $2)..."
        data='{
          "remark": "开始质检设备"
        }'
        execute_request "PUT" "$BASE_URL/adminapi/recycle_device/$2/start_check" "$data"
        ;;
        
    "complete_check")
        if [ -z "$2" ]; then
            echo "错误: 请提供设备ID"
            echo "使用方法: ./quick_test.sh complete_check [device_id]"
            exit 1
        fi
        echo "完成质检设备 (ID: $2)..."
        data='{
          "check_data": {
            "check_result": "设备功能正常，外观良好",
            "check_images": ["check1.jpg"]
          },
          "final_price": 4500,
          "remark": "质检完成"
        }'
        execute_request "PUT" "$BASE_URL/adminapi/recycle_device/$2/complete_check" "$data"
        ;;
        
    "confirm_price")
        if [ -z "$2" ]; then
            echo "错误: 请提供设备ID"
            echo "使用方法: ./quick_test.sh confirm_price [device_id]"
            exit 1
        fi
        echo "确认设备价格 (ID: $2)..."
        data='{
          "data": {
            "final_price": 4500,
            "price_remark": "价格确认",
            "remark": "价格确认完成"
          }
        }'
        execute_request "PUT" "$BASE_URL/adminapi/recycle_device/$2/confirm_price" "$data"
        ;;
        
    "return")
        if [ -z "$2" ]; then
            echo "错误: 请提供设备ID"
            echo "使用方法: ./quick_test.sh return [device_id]"
            exit 1
        fi
        echo "退回设备 (ID: $2)..."
        data='{
          "ids": "'$2'",
          "remark": "设备退回"
        }'
        execute_request "POST" "$BASE_URL/adminapi/recycle_device/batch_return" "$data"
        ;;
        
    "recycle")
        if [ -z "$2" ]; then
            echo "错误: 请提供设备ID"
            echo "使用方法: ./quick_test.sh recycle [device_id]"
            exit 1
        fi
        echo "回收设备 (ID: $2)..."
        data='{
          "ids": "'$2'",
          "remark": "设备回收完成"
        }'
        execute_request "POST" "$BASE_URL/adminapi/recycle_device/batch_recycle" "$data"
        ;;
        
    "payment")
        if [ -z "$2" ]; then
            echo "错误: 请提供订单ID"
            echo "使用方法: ./quick_test.sh payment [order_id]"
            exit 1
        fi
        echo "确认打款 (ID: $2)..."
        data='{
          "pay_account": "6222021234567890",
          "pay_type": 1,
          "pay_name": "测试用户",
          "pay_remark": "回收款项已打款",
          "remark": "订单完成"
        }'
        execute_request "PUT" "$BASE_URL/adminapi/recycle/recycle_order/$2/payment_confirm" "$data"
        ;;
        
    "status")
        echo "获取订单状态..."
        execute_request "GET" "$BASE_URL/adminapi/recycle/recycle_order/status"
        ;;
        
    "device")
        if [ -z "$2" ]; then
            echo "错误: 请提供设备ID"
            echo "使用方法: ./quick_test.sh device [device_id]"
            exit 1
        fi
        echo "获取设备信息 (ID: $2)..."
        execute_request "GET" "$BASE_URL/adminapi/recycle/recycle_device/$2"
        ;;
        
    "help"|"-h"|"--help"|"")
        show_help
        ;;
        
    *)
        echo "错误: 未知操作 '$1'"
        echo ""
        show_help
        exit 1
        ;;
esac 