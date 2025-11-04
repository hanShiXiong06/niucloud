#!/bin/bash

# ============================================
# 跑腿功能完整性检查脚本
# 用途：验证升级后跑腿功能是否完整
# 使用：chmod +x check_errand.sh && ./check_errand.sh
# ============================================

echo ""
echo "========================================="
echo "   跑腿功能完整性检查"
echo "========================================="
echo ""

# 计数器
total=0
passed=0

# 检查函数
check_file() {
    total=$((total + 1))
    if [ -f "$1" ]; then
        echo "✅ [$total] $2"
        passed=$((passed + 1))
        return 0
    else
        echo "❌ [$total] $2 - 文件不存在: $1"
        return 1
    fi
}

check_directory() {
    total=$((total + 1))
    if [ -d "$1" ]; then
        echo "✅ [$total] $2"
        passed=$((passed + 1))
        return 0
    else
        echo "❌ [$total] $2 - 目录不存在: $1"
        return 1
    fi
}

check_code() {
    total=$((total + 1))
    if grep -q "$2" "$1" 2>/dev/null; then
        echo "✅ [$total] $3"
        passed=$((passed + 1))
        return 0
    else
        echo "❌ [$total] $3 - 代码不存在: $2 in $1"
        return 1
    fi
}

# ============================================
# 1. 文档检查
# ============================================
echo "📄 1. 文档文件检查"
echo "-----------------------------------------"
check_file "ERRAND_BUSINESS_GUIDE.md" "跑腿业务集成指南"
check_file "docs/ERRAND_CONFIG_EXAMPLE.md" "跑腿配置示例"
check_file "GIT_UPGRADE_MERGE_PLAN.md" "Git升级合并方案"
check_file "QUICK_START_UPGRADE.md" "快速升级指南"
echo ""

# ============================================
# 2. 数据库脚本检查
# ============================================
echo "🗄️  2. 数据库脚本检查"
echo "-----------------------------------------"
check_file "errand_business_migration.sql" "跑腿业务数据库迁移脚本"
check_code "errand_business_migration.sql" "is_errand" "订单表字段: is_errand"
check_code "errand_business_migration.sql" "errand_items" "订单表字段: errand_items"
check_code "errand_business_migration.sql" "pickup_code" "订单项表字段: pickup_code"
echo ""

# ============================================
# 3. 前端组件检查
# ============================================
echo "🎨 3. 前端组件检查"
echo "-----------------------------------------"
check_directory "uni-app/user/components/errand-order-form" "跑腿订单表单组件目录"
check_file "uni-app/user/components/errand-order-form/errand-order-form.vue" "跑腿订单表单组件"
check_code "uni-app/user/pages/goods/detail.vue" "errand-order-form" "商品详情页引入跑腿组件"
check_code "uni-app/user/pages/order/payment.vue" "errand" "支付页面跑腿逻辑"
echo ""

# ============================================
# 4. 后端核心代码检查
# ============================================
echo "🔧 4. 后端核心代码检查"
echo "-----------------------------------------"
check_file "app/service/core/order/CoreOrderCreateService.php" "订单创建核心服务"
check_code "app/service/core/order/CoreOrderCreateService.php" "handleErrandBusinessGoods" "跑腿商品处理方法"
check_code "app/service/core/order/CoreOrderCreateService.php" "type.*===.*errand" "跑腿业务类型检测"
check_code "app/model/order/Order.php" "is_errand" "订单模型: is_errand字段"
check_code "app/model/order/Order.php" "errand_items" "订单模型: errand_items字段"
echo ""

# ============================================
# 5. Git提交检查
# ============================================
echo "📦 5. Git提交历史检查"
echo "-----------------------------------------"
if git rev-parse --git-dir > /dev/null 2>&1; then
    echo "✅ Git仓库存在"
    
    # 检查是否有跑腿相关的提交
    if git log --all --oneline | grep -i "errand\|跑腿" > /dev/null 2>&1; then
        echo "✅ 存在跑腿相关的提交记录"
        echo ""
        echo "   最近的跑腿相关提交："
        git log --all --oneline --grep="errand\|跑腿" -5 | sed 's/^/   /'
    else
        echo "⚠️  未找到跑腿相关的提交记录"
    fi
    
    # 检查是否有保护分支
    if git branch -a | grep "feature/errand" > /dev/null 2>&1; then
        echo "✅ 存在跑腿功能保护分支"
    else
        echo "⚠️  未找到跑腿功能保护分支 (feature/errand-business)"
    fi
    
    # 检查是否有标签
    if git tag -l | grep "errand" > /dev/null 2>&1; then
        echo "✅ 存在跑腿功能版本标签"
    else
        echo "⚠️  未找到跑腿功能版本标签"
    fi
else
    echo "❌ 当前目录不是Git仓库"
fi
echo ""

# ============================================
# 6. 代码统计
# ============================================
echo "📊 6. 代码统计"
echo "-----------------------------------------"
if [ -f "uni-app/user/components/errand-order-form/errand-order-form.vue" ]; then
    lines=$(wc -l < "uni-app/user/components/errand-order-form/errand-order-form.vue")
    echo "   跑腿订单表单组件: $lines 行"
fi

if [ -f "ERRAND_BUSINESS_GUIDE.md" ]; then
    lines=$(wc -l < "ERRAND_BUSINESS_GUIDE.md")
    echo "   跑腿业务指南文档: $lines 行"
fi

if [ -f "app/service/core/order/CoreOrderCreateService.php" ]; then
    errand_lines=$(grep -c "errand\|Errand" "app/service/core/order/CoreOrderCreateService.php")
    echo "   订单服务中跑腿相关代码: $errand_lines 处"
fi
echo ""

# ============================================
# 总结
# ============================================
echo "========================================="
echo "   检查完成"
echo "========================================="
echo ""
echo "总检查项: $total"
echo "通过项: $passed"
echo "失败项: $((total - passed))"
echo ""

# 计算通过率
if [ $total -gt 0 ]; then
    percentage=$((passed * 100 / total))
    echo "完整度: $percentage%"
    echo ""
    
    if [ $percentage -ge 90 ]; then
        echo "✅ 跑腿功能完整，可以继续测试"
        exit 0
    elif [ $percentage -ge 70 ]; then
        echo "⚠️  跑腿功能部分缺失，建议检查失败项"
        exit 1
    else
        echo "❌ 跑腿功能严重缺失，建议重新合并"
        exit 2
    fi
else
    echo "❌ 检查项为0，脚本可能有问题"
    exit 3
fi


