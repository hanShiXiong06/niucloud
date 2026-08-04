<template>
    <div class="topnav-layout flex h-screen min-w-0 flex-col overflow-hidden bg-page">
        <el-header class="topnav-primary !h-[60px] !p-0">
            <layout-header />
        </el-header>

        <context-navigation />

        <div class="topnav-tabs" v-if="systemStore.tab">
            <layout-tabs />
        </div>

        <el-main class="min-h-0 flex-1 !p-0 bg-page">
            <el-scrollbar>
                <div class="topnav-content p-[12px] xl:p-[15px]">
                    <router-view v-slot="{ Component, route }" v-if="appStore.routeRefreshTag">
                        <keep-alive :include="tabbarStore.tabNames" :max="15">
                            <component :is="Component" :key="route.fullPath" />
                        </keep-alive>
                    </router-view>
                </div>
            </el-scrollbar>
        </el-main>
    </div>
</template>

<script lang="ts" setup>
import layoutHeader from '../routine/components/header/index.vue'
import layoutTabs from '../default/components/tabs.vue'
import contextNavigation from './components/context-navigation.vue'
import useAppStore from '@/stores/modules/app'
import useTabbarStore from '@/stores/modules/tabbar'
import useSystemStore from '@/stores/modules/system'

const appStore = useAppStore()
const tabbarStore = useTabbarStore()
const systemStore = useSystemStore()
</script>

<style lang="scss" scoped>
.topnav-layout {
    --topnav-context-height: 44px;
}

.topnav-primary {
    flex: 0 0 60px;
    position: relative;
    z-index: 20;
}

/*
 * 顶栏始终保持「固定品牌区 / 可滚动菜单区 / 固定操作区」。
 * routine header 本身继续复用，这里的约束仅作用于 topnav 布局。
 */
.topnav-primary :deep(.left-panel) {
    flex: 0 0 auto;
}

.topnav-primary :deep(.left-panel + .flex) {
    flex: 1 1 auto;
    min-width: 0;
    overflow-x: auto;
    overflow-y: hidden;
    overscroll-behavior-x: contain;
    scrollbar-width: none;
    white-space: nowrap;
}

.topnav-primary :deep(.left-panel + .flex::-webkit-scrollbar) {
    display: none;
}

.topnav-primary :deep(.left-panel + .flex > *) {
    flex: 0 0 auto;
}

.topnav-primary :deep(.right-panel) {
    position: relative;
    z-index: 2;
    flex: 0 0 auto;
    margin-left: 10px;
    padding-left: 8px;
    background: var(--layout-header-bg, var(--el-bg-color));
    box-shadow: -10px 0 16px -14px rgb(15 23 42 / 45%);
}

.topnav-tabs {
    flex: 0 0 40px;
    height: 40px;
    overflow: hidden;
    background: var(--el-bg-color);
    border-bottom: 1px solid var(--el-border-color-lighter);
}

.topnav-tabs :deep(.el-tabs__header),
.topnav-tabs :deep(.el-tabs__nav-wrap),
.topnav-tabs :deep(.el-tabs__item) {
    height: 40px;
}

.topnav-content {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

@media (max-width: 1280px) {
    .topnav-content {
        padding: 10px !important;
    }
}
</style>
