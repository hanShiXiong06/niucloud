<template>
    <div class="flex w-full h-screen compact-root">
        <!-- 左侧边栏（复用框架默认组件） -->
        <layout-aside></layout-aside>

        <!-- 注意：el-header 被包进 transition 后不再是 el-container 的直接子元素，
             el-container 会退回成横向(row)排列，必须显式 direction="vertical" 强制纵向 -->
        <el-container direction="vertical" class="compact-body">
            <!-- 可折叠顶栏 -->
            <transition name="compact-header-fold">
                <el-header v-show="!headerCollapsed" class="compact-header">
                    <layout-header></layout-header>
                </el-header>
            </transition>

            <!-- 折叠/展开按钮：固定右上角，贴近顶栏右侧图标区 -->
            <div
                class="compact-fold-btn"
                :class="{ 'is-collapsed': headerCollapsed }"
                :title="headerCollapsed ? '展开顶栏' : '收起顶栏'"
                @click="toggleHeader"
            >
                <icon :name="headerCollapsed ? 'element ArrowDownBold' : 'element ArrowUpBold'" />
            </div>

            <!-- 标签页（复用框架默认组件） -->
            <layout-tab />

            <!-- 主体 -->
            <el-main class="h-full p-0 bg-page">
                <el-scrollbar>
                    <div class="p-[5px]">
                        <router-view v-slot="{ Component, route }" v-if="appStore.routeRefreshTag">
                            <keep-alive :include="tabbarStore.tabNames" :max="15">
                                <component :is="Component" :key="route.fullPath" />
                            </keep-alive>
                        </router-view>
                    </div>
                </el-scrollbar>
            </el-main>
        </el-container>
    </div>
</template>

<script lang="ts" setup>
import { ref } from 'vue'
import layoutHeader from '@/layout/default/components/header/index.vue'
import layoutAside from '@/layout/default/components/aside/index.vue'
import layoutTab from '@/layout/default/components/tabs.vue'
import useAppStore from '@/stores/modules/app'
import useTabbarStore from '@/stores/modules/tabbar'

const appStore = useAppStore()
const tabbarStore = useTabbarStore()

// 顶栏折叠状态（持久化，刷新后保留）
const STORAGE_KEY = 'compact_layout_header_collapsed'
const headerCollapsed = ref(localStorage.getItem(STORAGE_KEY) === '1')

const toggleHeader = () => {
    headerCollapsed.value = !headerCollapsed.value
    localStorage.setItem(STORAGE_KEY, headerCollapsed.value ? '1' : '0')
}

</script>

<style lang="scss" scoped>
.compact-root {
    position: relative;
}

.compact-body {
    position: relative;
    min-width: 0;
}

.compact-header {
    overflow: hidden;
}

/* 折叠/展开按钮：绝对定位右上角 */
.compact-fold-btn {
    position: absolute;
    top: 17px;
    right: 6px;
    z-index: 2000;
    width: 26px;
    height: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    color: #909399;
    background-color: var(--el-bg-color, #fff);
    border: 1px solid var(--el-border-color-lighter, #ebeef5);
    transition: color 0.2s, border-color 0.2s, top 0.25s ease;
    user-select: none;
}

.compact-fold-btn:hover {
    color: var(--el-color-primary, #409eff);
    border-color: var(--el-color-primary, #409eff);
}

/* 折叠后顶栏消失，按钮移到最顶部 */
.compact-fold-btn.is-collapsed {
    top: 6px;
}

.compact-header-fold-enter-active,
.compact-header-fold-leave-active {
    transition: max-height 0.25s ease, opacity 0.25s ease;
    max-height: 200px;
    overflow: hidden;
}

.compact-header-fold-enter-from,
.compact-header-fold-leave-to {
    max-height: 0;
    opacity: 0;
}
</style>
