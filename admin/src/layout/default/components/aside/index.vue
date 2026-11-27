<template>
    <div
        v-if="!isMobile"
        class="workspace-sidebar-anchor"
        :class="{ 'is-rail': isRail, 'is-preview': previewVisible }"
        :style="{ width: `${reservedWidth}px` }"
    >
        <el-aside
            class="layout-aside workspace-sidebar-panel"
            :style="{ width: `${panelWidth}px` }"
            @mouseenter="schedulePreviewOpen"
            @mouseleave="schedulePreviewClose"
        >
            <side class="slide" :collapsed="menuCollapsed" />

            <el-tooltip
                :content="isRail ? '固定展开菜单' : '收起菜单，为业务数据让出空间'"
                placement="right"
                :show-after="300"
            >
                <button
                    type="button"
                    class="workspace-sidebar-toggle"
                    :aria-label="isRail ? '展开左侧菜单' : '收起左侧菜单'"
                    @click.stop="togglePinnedState"
                >
                    <icon :name="isRail ? 'element ArrowRightBold' : 'element ArrowLeftBold'" />
                </button>
            </el-tooltip>
        </el-aside>
    </div>

    <el-drawer v-model="systemStore.menuDrawer" direction="ltr" :with-header="false" custom-class="aside-drawer" size="210px">
        <template #default>
            <side :collapsed="false" />
        </template>
    </el-drawer>
</template>

<script lang="ts" setup>
import { watch, computed, ref, onMounted, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import side from './side.vue'
import useSystemStore from '@/stores/modules/system'

const systemStore = useSystemStore()
const dark = computed(() => {
    return systemStore.dark
})

const route = useRoute()
watch(route, () => {
    clearTimers()
    previewVisible.value = false
    systemStore.$patch(state => {
        state.menuDrawer = false
    })
})

const viewportWidth = ref(typeof window === 'undefined' ? 1440 : window.innerWidth)
const previewVisible = ref(false)
let openTimer: ReturnType<typeof setTimeout> | undefined
let closeTimer: ReturnType<typeof setTimeout> | undefined

const isMobile = computed(() => viewportWidth.value < 768)
const pageRequests = computed(() => Object.values(systemStore.workspaceSidebarRequests))
const requestedMode = computed<'expanded' | 'collapsed' | null>(() => {
    if (pageRequests.value.includes('collapsed')) return 'collapsed'
    if (pageRequests.value.includes('expanded')) return 'expanded'
    return null
})
const isRail = computed(() => {
    if (requestedMode.value) return requestedMode.value === 'collapsed'
    if (systemStore.workspaceSidebarPreference === 'collapsed') return true
    if (systemStore.workspaceSidebarPreference === 'expanded') return false
    return viewportWidth.value < 1440
})
const reservedWidth = computed(() => isRail.value ? 64 : 210)
const panelWidth = computed(() => isRail.value && !previewVisible.value ? 64 : 210)
const menuCollapsed = computed(() => isRail.value && !previewVisible.value)

const clearTimers = () => {
    if (openTimer) clearTimeout(openTimer)
    if (closeTimer) clearTimeout(closeTimer)
    openTimer = undefined
    closeTimer = undefined
}

const schedulePreviewOpen = () => {
    if (!isRail.value) return
    if (openTimer) clearTimeout(openTimer)
    if (closeTimer) clearTimeout(closeTimer)
    openTimer = setTimeout(() => {
        previewVisible.value = true
    }, 120)
}

const schedulePreviewClose = () => {
    if (!isRail.value) return
    if (openTimer) clearTimeout(openTimer)
    closeTimer = setTimeout(() => {
        previewVisible.value = false
    }, 180)
}

const togglePinnedState = () => {
    clearTimers()
    previewVisible.value = false
    systemStore.setWorkspaceSidebarPreference(isRail.value ? 'expanded' : 'collapsed')
}

const handleResize = () => {
    viewportWidth.value = window.innerWidth
    if (isMobile.value || !isRail.value) {
        clearTimers()
        previewVisible.value = false
    }
}

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') previewVisible.value = false
}

type WorkspaceLayoutEvent = CustomEvent<{
    source?: string
    sidebar?: 'expanded' | 'collapsed' | 'release'
}>

const handleWorkspaceLayoutEvent = (event: Event) => {
    const detail = (event as WorkspaceLayoutEvent).detail
    if (!detail?.source || !detail.sidebar) return
    if (detail.sidebar === 'release') {
        systemStore.releaseWorkspaceSidebar(detail.source)
    } else {
        systemStore.requestWorkspaceSidebar(detail.source, detail.sidebar)
    }
}

onMounted(() => {
    window.addEventListener('resize', handleResize, { passive: true })
    window.addEventListener('keydown', handleKeydown)
    window.addEventListener('niucloud:workspace-layout', handleWorkspaceLayoutEvent)
})

onBeforeUnmount(() => {
    clearTimers()
    window.removeEventListener('resize', handleResize)
    window.removeEventListener('keydown', handleKeydown)
    window.removeEventListener('niucloud:workspace-layout', handleWorkspaceLayoutEvent)
})
</script>

<style lang="scss">
.workspace-sidebar-anchor {
    position: relative;
    flex: 0 0 auto;
    height: 100vh;
    z-index: 100;
    transition: width 0.2s ease;
}

.workspace-sidebar-panel {
    position: absolute;
    inset: 0 auto 0 0;
    height: 100vh;
    overflow: visible;
    background: var(--el-bg-color, #fff);
    transition: width 0.2s ease, box-shadow 0.2s ease;
}

.workspace-sidebar-anchor.is-preview .workspace-sidebar-panel {
    z-index: 2100;
    box-shadow: 10px 0 28px rgba(15, 23, 42, 0.14);
}

.workspace-sidebar-panel > .workspace-side {
    overflow: hidden;
}

.workspace-sidebar-toggle {
    position: absolute;
    top: 50%;
    right: -12px;
    z-index: 10;
    width: 24px;
    height: 40px;
    margin-top: -20px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--el-border-color-lighter, #ebeef5);
    border-radius: 0 8px 8px 0;
    color: var(--el-text-color-secondary, #909399);
    background: var(--el-bg-color, #fff);
    box-shadow: 4px 0 10px rgba(15, 23, 42, 0.08);
    cursor: pointer;
    opacity: 0;
    transform: translateX(-4px);
    transition: color 0.18s ease, opacity 0.18s ease, transform 0.18s ease;
}

.workspace-sidebar-anchor:hover .workspace-sidebar-toggle,
.workspace-sidebar-toggle:focus-visible,
.workspace-sidebar-anchor.is-rail .workspace-sidebar-toggle {
    opacity: 1;
    transform: translateX(0);
}

.workspace-sidebar-toggle:hover,
.workspace-sidebar-toggle:focus-visible {
    color: var(--el-color-primary);
    border-color: var(--el-color-primary-light-5);
}

.layout-aside {
    &.bright {
        background-color: #F5F7F9;

        li {
            background-color: #F5F7F9;

            &.is-active:not(.is-opened) {
                position: relative;
                color: #333;
                background-color: #fff;

                &::after {
                    content: "";
                    position: absolute;
                    top: 0;
                    bottom: 0;
                    left: 0;
                    width: 2px;
                    background-color: var(--el-menu-active-color);
                }
            }
        }
    }

    .slide {
        border-right: 1px solid var(--el-border-color-extra-light);
    }
}

.aside-drawer {
    .el-drawer__body {
        padding: 0 !important;
    }
}

@media (prefers-reduced-motion: reduce) {
    .workspace-sidebar-anchor,
    .workspace-sidebar-panel,
    .workspace-sidebar-toggle {
        transition: none;
    }
}
</style>
