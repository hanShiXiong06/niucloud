<template>
    <div class="flex w-full h-screen hsx-layout">
        <!-- 左侧边栏（复用框架默认组件） -->
        <layout-aside></layout-aside>

        <el-container direction="vertical" class="hsx-body">
            <!-- 可折叠顶栏 -->
            <transition name="hsx-header-fold">
                <el-header v-show="!headerCollapsed" class="hsx-header">
                    <layout-header></layout-header>
                </el-header>
            </transition>

            <!-- 折叠/展开按钮：固定右上角，贴近顶栏右侧图标区 -->
            <div
                class="hsx-fold-btn"
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
                    <div class="p-[15px]">
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
// 复用框架默认布局的侧栏 / 顶栏 / 标签组件，保持与平台一致
import layoutHeader from '@/layout/default/components/header/index.vue'
import layoutAside from '@/layout/default/components/aside/index.vue'
import layoutTab from '@/layout/default/components/tabs.vue'
import useAppStore from '@/stores/modules/app'
import useTabbarStore from '@/stores/modules/tabbar'

const appStore = useAppStore()
const tabbarStore = useTabbarStore()

// 顶栏折叠状态（持久化，刷新后保留）
const STORAGE_KEY = 'hsx_recycle_header_collapsed'
const headerCollapsed = ref(localStorage.getItem(STORAGE_KEY) === '1')

const toggleHeader = () => {
    headerCollapsed.value = !headerCollapsed.value
    localStorage.setItem(STORAGE_KEY, headerCollapsed.value ? '1' : '0')
}

</script>

<style lang="scss" scoped>
.hsx-layout {
    position: relative;
}

.hsx-body {
    position: relative;
    min-width: 0;
}

.hsx-header {
    overflow: hidden;
}

/* 折叠/展开按钮：绝对定位右上角 */
.hsx-fold-btn {
    position: absolute;
    top: 14px;
    right: 14px;
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

.hsx-fold-btn:hover {
    color: var(--el-color-primary, #409eff);
    border-color: var(--el-color-primary, #409eff);
}

.hsx-fold-btn.is-collapsed {
    top: 6px;
}

/* 顶栏折叠/展开过渡 */
.hsx-header-fold-enter-active,
.hsx-header-fold-leave-active {
    transition: max-height 0.25s ease, opacity 0.25s ease;
    max-height: 200px;
    overflow: hidden;
}

.hsx-header-fold-enter-from,
.hsx-header-fold-leave-to {
    max-height: 0;
    opacity: 0;
}
</style>
