#!/bin/bash
# ERP 集成插件自动安装脚本
# 用法：bash install.sh

set -e  # 遇到错误立即退出

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
ADMIN_ROOT="$PROJECT_ROOT/admin"

echo "======================================"
echo "  ERP 集成插件自动安装"
echo "======================================"
echo ""

# 让 NiuCloud 安装器分发独立的后台副本。打包/卸载会删除再复制目录，不能软链回插件源码。
echo "📂 [1/4] 检查后台安装文件..."
ADDON_DIR="$ADMIN_ROOT/src/addon"
LINK_TARGET="$ADDON_DIR/hsx_erp_integration"

if [ ! -f "$SCRIPT_DIR/admin/views/integration/connection.vue" ]; then
    echo "   ✗ 安装包缺少 admin/views/integration/connection.vue。请先补齐对应版本的原始安装包，暂勿安装。"
    exit 1
fi
if [ -L "$LINK_TARGET" ] || { [ -d "$LINK_TARGET" ] && [ "$LINK_TARGET" -ef "$SCRIPT_DIR/admin" ]; }; then
    echo "   ✗ 宿主目录链接到其他位置，不可用于打包/安装。确认插件源码完整后，仅移开宿主链接，再由框架分发独立副本。"
    exit 1
fi
echo "   ✓ 后台文件已就绪，交由框架安装"

# 步骤2：检查后端是否已安装
echo ""
echo "🗄️  [2/4] 检查数据库..."
cd "$PROJECT_ROOT/niucloud"

# 检查配置文件
if [ ! -f "config/database.php" ]; then
    echo "   ✗ 数据库配置文件不存在"
    exit 1
fi

echo "   ✓ 数据库配置文件存在"

# 步骤3：提示手动安装插件
echo ""
echo "📦 [3/4] 插件安装..."
echo ""
echo "   请在后台管理系统中手动安装插件："
echo "   1. 访问：http://your-domain/admin"
echo "   2. 导航：系统 → 插件管理 → 本地插件"
echo "   3. 找到「ERP 集成」→ 点击「安装」"
echo ""
read -p "   插件安装完成后，按回车继续..."

# 步骤4：清除缓存
echo ""
echo "🧹 [4/4] 清除缓存..."
php think clear
echo "   ✓ 缓存已清除"

# 完成
echo ""
echo "======================================"
echo "  ✅ 安装完成！"
echo "======================================"
echo ""
echo "下一步："
echo "  1. 重启前端开发服务器："
echo "     cd $ADMIN_ROOT && npm run dev"
echo ""
echo "  2. 访问管理页面："
echo "     /site/hsx_erp_integration/connection（站点后台 → ERP 集成 → 连接管理）"
echo ""
echo "  3. 配置第一个连接："
echo "     选择适配器 → 填写API配置 → 测试连接"
echo ""
