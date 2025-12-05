<template>
    <div class="min-h-screen bg-black text-white flex">
        <!-- Left Sidebar -->
        <aside
            class="w-[280px] bg-gradient-to-b from-slate-900 via-slate-800 to-slate-900 border-r border-slate-700/50 flex flex-col relative overflow-hidden h-screen fixed left-0 top-0 z-50">
            <!-- 简化的背景装饰 -->
            <div class="absolute inset-0">
                <!-- 主背景渐变 -->
                <div class="absolute inset-0 bg-gradient-to-br from-slate-900/98 via-slate-800/95 to-slate-900/98">
                </div>
                <!-- 减少的网格纹理 - 仅在导航区域 -->
                <div class="absolute inset-0 opacity-[0.015]">
                    <div class="grid grid-cols-6 grid-rows-24 w-full h-full">
                        <div v-for="i in 144" :key="i" class="border border-slate-700/50"></div>
                    </div>
                </div>
                <!-- 顶部融合光效 -->
                <div class="absolute top-0 left-0 w-full h-24 bg-gradient-to-b from-slate-800/30 to-transparent"></div>
                <!-- 底部光效 -->
                <div class="absolute bottom-0 left-0 w-full h-20 bg-gradient-to-t from-slate-900/50 to-transparent">
                </div>
                <!-- 右侧边界线 -->
                <div
                    class="absolute top-0 right-0 w-px h-full bg-gradient-to-b from-transparent via-slate-600/30 to-transparent">
                </div>
            </div>

            <!-- Header -->
            <header class="h-[80px] flex-shrink-0 relative z-10 border-b border-slate-700/20">
                <layout-header />
            </header>

            <!-- Navigation -->
            <nav
                class="flex-1 py-6 relative z-10 overflow-y-auto scrollbar-thin scrollbar-thumb-slate-600 scrollbar-track-transparent">
                <!-- 导航标题 -->
                <div class="px-6 mb-4">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">导航菜单</h3>
                </div>
                <div class="space-y-2 px-6">
                    <div v-for="item in menuItems" :key="item.path" class="relative">
                        <!-- 一级菜单 -->
                        <div v-if="item.children && item.children.length > 0">
                            <div :class="[
                                'flex items-center justify-between px-4 py-3 mb-2 transition-all duration-300 ease-out w-full relative overflow-hidden group cursor-pointer rounded-xl',
                                isActive(item) || isChildActive(item)
                                    ? 'bg-gradient-to-r from-blue-600/20 to-purple-600/20 text-white shadow-lg shadow-blue-500/10 border border-blue-500/30 backdrop-blur-sm'
                                    : 'text-slate-300 hover:text-white hover:bg-slate-700/30 border border-transparent hover:border-slate-600/50'
                            ]" @click="toggleSubmenu(item)">
                                <!-- 背景光效 -->
                                <div v-if="isActive(item) || isChildActive(item)"
                                    class="absolute inset-0 bg-gradient-to-r from-blue-500/10 to-purple-500/10 rounded-xl">
                                </div>

                                <div class="flex items-center space-x-3 relative z-10">
                                    <div :class="[
                                        'w-8 h-8 rounded-lg flex items-center justify-center transition-all duration-300',
                                        isActive(item) || isChildActive(item)
                                            ? 'bg-blue-500/20 text-blue-300'
                                            : 'bg-slate-700/50 text-slate-400 group-hover:bg-slate-600/50 group-hover:text-slate-300'
                                    ]">
                                        <MenuIcon :icon="item.icon" class="w-4 h-4" />
                                    </div>
                                    <span
                                        :class="['font-medium text-sm', isActive(item) || isChildActive(item) ? 'text-white' : '']">{{
                                            item.name }}</span>
                                </div>
                                <div class="flex items-center space-x-2 relative z-10">
                                    <span v-if="item.badge" :class="[
                                        'px-2 py-1 text-xs font-bold rounded-lg transition-all duration-300',
                                        isActive(item) || isChildActive(item)
                                            ? 'bg-gradient-to-r from-orange-400 to-red-400 text-white shadow-lg shadow-orange-500/30'
                                            : 'bg-red-500/80 text-white'
                                    ]">
                                        {{ item.badge }}
                                    </span>
                                    <el-icon :class="[
                                        'transition-transform duration-200 ease-out w-4 h-4',
                                        isSubmenuOpen(item) ? 'rotate-90' : '',
                                        isActive(item) || isChildActive(item) ? 'text-blue-300' : 'text-slate-400'
                                    ]">
                                        <ArrowRight />
                                    </el-icon>
                                </div>
                            </div>

                            <!-- 二级菜单 -->
                            <Transition enter-active-class="transition-all duration-300 ease-out"
                                enter-from-class="opacity-0 transform -translate-y-2"
                                enter-to-class="opacity-100 transform translate-y-0"
                                leave-active-class="transition-all duration-200 ease-in"
                                leave-from-class="opacity-100 transform translate-y-0"
                                leave-to-class="opacity-0 transform -translate-y-1">
                                <div v-show="isSubmenuOpen(item)"
                                    class="ml-4 mb-3 space-y-1 bg-slate-800/30 rounded-lg p-2 border border-slate-700/30">
                                    <div v-for="child in item.children" :key="child.path" class="relative">
                                        <NuxtLink :to="child.path" :class="[
                                            'flex items-center px-3 py-2 transition-all duration-200 w-full relative group rounded-lg',
                                            isActive(child)
                                                ? 'bg-blue-500/20 text-white border border-blue-500/30'
                                                : 'text-slate-400 hover:text-white hover:bg-slate-700/40'
                                        ]">
                                            <div class="flex items-center space-x-3 relative z-10">
                                                <div :class="[
                                                    'w-6 h-6 rounded-md flex items-center justify-center transition-all duration-200',
                                                    isActive(child)
                                                        ? 'bg-blue-500/30 text-blue-300'
                                                        : 'bg-slate-700/50 text-slate-500 group-hover:bg-slate-600/50 group-hover:text-slate-400'
                                                ]">
                                                    <MenuIcon :icon="child.icon" class="w-3 h-3" />
                                                </div>
                                                <span
                                                    :class="['text-sm transition-colors duration-200', isActive(child) ? 'text-white font-medium' : '']">{{
                                                        child.name }}</span>
                                            </div>
                                        </NuxtLink>
                                    </div>
                                </div>
                            </Transition>
                        </div>

                        <!-- 普通菜单项 -->
                        <NuxtLink v-else-if="!item.external" :to="item.path" :class="[
                            'flex items-center justify-between px-4 py-3 mb-2 transition-all duration-300 ease-out w-full relative overflow-hidden group rounded-xl',
                            isActive(item)
                                ? 'bg-gradient-to-r from-blue-600/20 to-purple-600/20 text-white shadow-lg shadow-blue-500/10 border border-blue-500/30 backdrop-blur-sm'
                                : 'text-slate-300 hover:text-white hover:bg-slate-700/30 border border-transparent hover:border-slate-600/50'
                        ]">
                            <!-- 背景光效 -->
                            <div v-if="isActive(item)"
                                class="absolute inset-0 bg-gradient-to-r from-blue-500/10 to-purple-500/10 rounded-xl">
                            </div>

                            <div class="flex items-center space-x-3 relative z-10">
                                <div :class="[
                                    'w-8 h-8 rounded-lg flex items-center justify-center transition-all duration-300',
                                    isActive(item)
                                        ? 'bg-blue-500/20 text-blue-300'
                                        : 'bg-slate-700/50 text-slate-400 group-hover:bg-slate-600/50 group-hover:text-slate-300'
                                ]">
                                    <MenuIcon :icon="item.icon" class="w-4 h-4" />
                                </div>
                                <span :class="['font-medium text-sm', isActive(item) ? 'text-white' : '']">{{ item.name
                                }}</span>
                            </div>
                            <div class="flex items-center space-x-2 relative z-10">
                                <span v-if="item.badge" :class="[
                                    'px-2 py-1 text-xs font-bold rounded-lg transition-all duration-300',
                                    isActive(item)
                                        ? 'bg-gradient-to-r from-orange-400 to-red-400 text-white shadow-lg shadow-orange-500/30'
                                        : 'bg-red-500/80 text-white'
                                ]">
                                    {{ item.badge }}
                                </span>
                            </div>
                        </NuxtLink>
                        <a v-else :href="item.path" target="_blank" rel="noopener noreferrer"
                            class="flex items-center justify-between px-4 py-3 mb-2 text-slate-300 hover:text-white hover:bg-slate-700/30 border border-transparent hover:border-slate-600/50 transition-all duration-300 w-full group rounded-xl">
                            <div class="flex items-center space-x-3">
                                <div
                                    class="w-8 h-8 rounded-lg flex items-center justify-center bg-slate-700/50 text-slate-400 group-hover:bg-slate-600/50 group-hover:text-slate-300 transition-all duration-300">
                                    <MenuIcon :icon="item.icon" class="w-4 h-4" />
                                </div>
                                <span class="font-medium text-sm">{{ item.name }}</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span v-if="item.badge"
                                    class="px-2 py-1 text-xs font-bold text-white bg-red-500/80 rounded-lg hover:bg-red-600 transition-colors">
                                    {{ item.badge }}
                                </span>
                                <svg class="w-4 h-4 text-slate-400 group-hover:text-slate-300 transition-colors"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14">
                                    </path>
                                </svg>
                            </div>
                        </a>
                    </div>
                </div>
            </nav>

            <!-- User Section -->
            <div class="flex-shrink-0 relative z-10 border-t border-slate-700/20 mt-4">
                <layout-user />
            </div>
        </aside>

        <!-- Right Content Area -->
        <div class="flex-1 flex flex-col ml-[280px] min-h-screen relative">
            <!-- Background Elements -->
            <div class="absolute inset-0">
                <!-- Main background gradient -->
                <div class="absolute inset-0 bg-gradient-to-br from-slate-900/95 via-slate-800/90 to-slate-900/95">
                </div>
                <!-- Subtle grid texture -->
                <div class="absolute inset-0 opacity-[0.015]">
                    <div class="grid grid-cols-12 grid-rows-24 w-full h-full">
                        <div v-for="i in 288" :key="i" class="border border-slate-700/30"></div>
                    </div>
                </div>
                <!-- Top fade effect -->
                <div class="absolute top-0 left-0 w-full h-24 bg-gradient-to-b from-slate-800/20 to-transparent"></div>
                <!-- Bottom fade effect -->
                <div class="absolute bottom-0 left-0 w-full h-20 bg-gradient-to-t from-slate-900/50 to-transparent">
                </div>
                <!-- Left boundary line -->
                <div
                    class="absolute top-0 left-0 w-px h-full bg-gradient-to-b from-transparent via-slate-600/30 to-transparent">
                </div>
            </div>

            <!-- Main Content -->
            <main class="flex-1 overflow-y-auto relative z-10">
                <div class="min-h-full">
                    <slot></slot>
                </div>
            </main>

            <!-- Footer -->
            <footer class="flex-shrink-0 relative z-10">
                <layout-footer />
            </footer>
        </div>
    </div>
</template>

<script lang="ts" setup>
import layoutHeader from './aiimage/components/header/index.vue'
import layoutFooter from './aiimage/components/footer/index.vue'
import layoutUser from './aiimage/components/user/index.vue'
import MenuIcon from '~/addon/ai_image/components/MenuIcon.vue'
import { ArrowRight } from '@element-plus/icons-vue'
import { useMenu } from '~/addon/ai_image/composables/useMenu'

const { menuItems, isActive } = useMenu()

// 二级菜单展开状态
const openSubmenus = ref<string[]>([])

// 切换二级菜单
const toggleSubmenu = (item: any) => {
    const index = openSubmenus.value.indexOf(item.path)
    if (index > -1) {
        openSubmenus.value.splice(index, 1)
    } else {
        openSubmenus.value.push(item.path)
    }
}

// 检查二级菜单是否展开
const isSubmenuOpen = (item: any) => {
    return openSubmenus.value.includes(item.path)
}

// 检查是否有子菜单处于激活状态
const isChildActive = (item: any) => {
    if (!item.children) return false
    return item.children.some((child: any) => isActive(child))
}

// 监听路由变化，自动展开包含当前页面的二级菜单
watch(() => useRoute().path, (newPath) => {
    menuItems.value.forEach(item => {
        if (item.children && isChildActive(item)) {
            if (!openSubmenus.value.includes(item.path)) {
                openSubmenus.value.push(item.path)
            }
        }
    })
}, { immediate: true })
</script>

<style lang="scss" scoped>
/* 自定义滚动条样式 */
nav {
    scrollbar-width: thin;
    scrollbar-color: rgba(100, 116, 139, 0.6) transparent;
}

nav::-webkit-scrollbar {
    width: 6px;
}

nav::-webkit-scrollbar-track {
    background: transparent;
    border-radius: 3px;
}

nav::-webkit-scrollbar-thumb {
    background: rgba(100, 116, 139, 0.4);
    border-radius: 3px;
    transition: background 0.2s ease;
}

nav::-webkit-scrollbar-thumb:hover {
    background: rgba(100, 116, 139, 0.7);
}

/* 主内容区域滚动条样式 */
main {
    scrollbar-width: thin;
    scrollbar-color: rgba(75, 85, 99, 0.4) transparent;
}

main::-webkit-scrollbar {
    width: 6px;
}

main::-webkit-scrollbar-track {
    background: transparent;
}

main::-webkit-scrollbar-thumb {
    background: rgba(75, 85, 99, 0.4);
    border-radius: 3px;
    transition: background 0.2s ease;
}

main::-webkit-scrollbar-thumb:hover {
    background: rgba(75, 85, 99, 0.6);
}

/* 确保布局稳定性 */
aside {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    height: 100vh !important;
    overflow: hidden;
}

/* 右侧内容区域优化 */
.flex-1 {
    min-width: 0;
    /* 防止flex项目溢出 */
}
</style>
