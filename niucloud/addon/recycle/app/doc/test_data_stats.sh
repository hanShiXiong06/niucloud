#!/bin/bash

# 测试数据统计脚本
# 使用方法: ./test_data_stats.sh

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

# 获取订单列表
get_orders() {
    curl -s --location --request GET "$API_BASE/lists?limit=100" \
        --header "site-id: $SITE_ID" \
        --header "token: $TOKEN"
}

echo "========================================"
echo -e "${CYAN}    测试数据统计分析${NC}"
echo "========================================"

# 获取数据
echo -e "${BLUE}[INFO]${NC} 正在获取订单数据..."
orders_data=$(get_orders)

if [ $? -ne 0 ]; then
    echo -e "${RED}[ERROR]${NC} 获取订单数据失败"
    exit 1
fi

# 检查是否获取到数据
total_orders=$(echo "$orders_data" | jq '.data.total // 0')
if [ "$total_orders" -eq 0 ]; then
    echo -e "${YELLOW}[WARNING]${NC} 没有找到订单数据"
    exit 0
fi

echo -e "${GREEN}[SUCCESS]${NC} 成功获取到 $total_orders 个订单\n"

# 1. 基础统计
echo -e "${CYAN}=== 📊 基础统计 ===${NC}"
orders_list=$(echo "$orders_data" | jq '.data.data[]')

# 订单状态统计
echo -e "${BLUE}订单状态分布：${NC}"
echo "$orders_list" | jq -r '.status_name' | sort | uniq -c | while read count status; do
    printf "  %-12s: %s个\n" "$status" "$count"
done

echo ""

# 设备数量统计
echo -e "${BLUE}设备数量统计：${NC}"
total_devices=$(echo "$orders_list" | jq '.device_count' | awk '{sum += $1} END {print sum}')
avg_devices=$(echo "$orders_list" | jq '.device_count' | awk '{sum += $1; count++} END {print sum/count}')
max_devices=$(echo "$orders_list" | jq '.device_count' | sort -nr | head -1)
min_devices=$(echo "$orders_list" | jq '.device_count' | sort -n | head -1)

printf "  总设备数: %s台\n" "$total_devices"
printf "  平均每单: %.2f台\n" "$avg_devices"
printf "  最多单订单: %s台\n" "$max_devices"
printf "  最少单订单: %s台\n" "$min_devices"

echo ""

# 快递公司统计
echo -e "${BLUE}快递公司分布：${NC}"
echo "$orders_list" | jq -r '.express_company // "未填写"' | sort | uniq -c | while read count company; do
    if [ "$company" != "" ]; then
        printf "  %-15s: %s个订单\n" "$company" "$count"
    fi
done

echo ""

# 2. 客户分析
echo -e "${CYAN}=== 👥 客户分析 ===${NC}"

# 客户名称统计（排除空值）
echo -e "${BLUE}活跃客户TOP10：${NC}"
echo "$orders_list" | jq -r 'select(.customer_name != "") | .customer_name' | sort | uniq -c | sort -nr | head -10 | while read count name; do
    printf "  %-20s: %s个订单\n" "$name" "$count"
done

echo ""

# 3. 价格分析
echo -e "${CYAN}=== 💰 价格分析 ===${NC}"

# 获取所有设备的价格信息
echo -e "${BLUE}设备价格统计：${NC}"
all_devices=$(echo "$orders_list" | jq -r '.devices[]?')

if [ "$all_devices" != "" ]; then
    # 计算价格统计
    initial_prices=$(echo "$all_devices" | jq -r '.initial_price' | grep -v null)
    if [ "$initial_prices" != "" ]; then
        total_initial_value=$(echo "$initial_prices" | awk '{sum += $1} END {print sum}')
        avg_initial_price=$(echo "$initial_prices" | awk '{sum += $1; count++} END {print sum/count}')
        max_initial_price=$(echo "$initial_prices" | sort -nr | head -1)
        min_initial_price=$(echo "$initial_prices" | sort -n | head -1)
        
        printf "  总初始价值: ¥%.2f元\n" "$total_initial_value"
        printf "  平均设备价格: ¥%.2f元\n" "$avg_initial_price"
        printf "  最高设备价格: ¥%.2f元\n" "$max_initial_price"
        printf "  最低设备价格: ¥%.2f元\n" "$min_initial_price"
    fi
fi

echo ""

# 4. 设备品牌分析
echo -e "${CYAN}=== 📱 设备品牌分析 ===${NC}"

echo -e "${BLUE}热门设备型号TOP15：${NC}"
echo "$all_devices" | jq -r '.model // "未知"' | sort | uniq -c | sort -nr | head -15 | while read count model; do
    printf "  %-25s: %s台\n" "$model" "$count"
done

echo ""

# 5. 测试场景分析
echo -e "${CYAN}=== 🧪 测试场景分析 ===${NC}"

echo -e "${BLUE}测试场景分类：${NC}"

# 分析客户名称中的测试标识
test_scenarios=0
enterprise_orders=0
student_orders=0
elderly_orders=0
high_value_orders=0
damaged_orders=0
bulk_orders=0
urgent_orders=0
international_orders=0
boundary_orders=0

echo "$orders_list" | jq -r '.customer_name // ""' | while read name; do
    case "$name" in
        *"企业"*|*"经理"*) echo "enterprise" ;;
        *"学生"*|*"小刘"*) echo "student" ;;
        *"阿姨"*|*"陈"*) echo "elderly" ;;
        *"土豪"*|*"李总"*) echo "high_value" ;;
        *"损坏"*|*"马用户"*) echo "damaged" ;;
        *"批量"*|*"二手商"*) echo "bulk" ;;
        *"急需"*|*"紧急"*) echo "urgent" ;;
        *"海归"*|*"国际"*) echo "international" ;;
        *"边界"*|*"测试"*) echo "boundary" ;;
        *) echo "other" ;;
    esac
done | sort | uniq -c | while read count type; do
    case "$type" in
        "enterprise") printf "  📱 企业批量订单: %s个\n" "$count" ;;
        "student") printf "  🎓 学生用户订单: %s个\n" "$count" ;;
        "elderly") printf "  👵 老年用户订单: %s个\n" "$count" ;;
        "high_value") printf "  💎 高价值订单: %s个\n" "$count" ;;
        "damaged") printf "  🔧 损坏设备订单: %s个\n" "$count" ;;
        "bulk") printf "  📦 大批量订单: %s个\n" "$count" ;;
        "urgent") printf "  ⚡ 紧急订单: %s个\n" "$count" ;;
        "international") printf "  🌍 国际版本订单: %s个\n" "$count" ;;
        "boundary") printf "  🧪 边界测试订单: %s个\n" "$count" ;;
        "other") printf "  📋 其他订单: %s个\n" "$count" ;;
    esac
done

echo ""

# 6. 系统性能指标
echo -e "${CYAN}=== ⚡ 系统性能指标 ===${NC}"

echo -e "${BLUE}数据量指标：${NC}"
printf "  总订单数: %s个\n" "$total_orders"
printf "  总设备数: %s台\n" "$total_devices"

# 计算数据密度
if [ "$total_orders" -gt 0 ]; then
    data_density=$(echo "scale=2; $total_devices / $total_orders" | bc)
    printf "  数据密度: %.2f台/订单\n" "$data_density"
fi

# 估算数据库记录数
db_records=$((total_orders * 2 + total_devices))  # 订单表 + 设备表 + 其他关联表
printf "  估算数据库记录: %s条\n" "$db_records"

echo ""

# 7. 测试覆盖率分析
echo -e "${CYAN}=== 📋 测试覆盖率分析 ===${NC}"

echo -e "${BLUE}业务流程覆盖：${NC}"

# 检查各种状态的订单
status_coverage=$(echo "$orders_list" | jq -r '.status_name' | sort -u | wc -l)
printf "  订单状态覆盖: %s种状态\n" "$status_coverage"

# 检查快递类型
delivery_coverage=$(echo "$orders_list" | jq -r '.delivery_type_name' | sort -u | wc -l)
printf "  配送方式覆盖: %s种方式\n" "$delivery_coverage"

# 检查价格范围
price_ranges=0
if [ "$initial_prices" != "" ]; then
    # 低价格设备 (<1000)
    low_price=$(echo "$initial_prices" | awk '$1 < 1000' | wc -l)
    # 中价格设备 (1000-5000)
    mid_price=$(echo "$initial_prices" | awk '$1 >= 1000 && $1 <= 5000' | wc -l)
    # 高价格设备 (>5000)
    high_price=$(echo "$initial_prices" | awk '$1 > 5000' | wc -l)
    
    printf "  价格分布覆盖:\n"
    printf "    低价设备(<¥1000): %s台\n" "$low_price"
    printf "    中价设备(¥1000-5000): %s台\n" "$mid_price"
    printf "    高价设备(>¥5000): %s台\n" "$high_price"
fi

echo ""

echo -e "${GREEN}=== ✅ 统计完成 ===${NC}"
echo "测试数据已创建完成，涵盖了多种业务场景和边界情况。"
echo "建议进行以下测试："
echo "1. 🔄 运行批量业务流程测试"
echo "2. 📊 性能压力测试"
echo "3. 🛡️ 安全性测试"
echo "4. 🌐 兼容性测试" 