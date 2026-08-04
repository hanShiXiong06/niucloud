<template>
    <div class="context-navigation flex min-w-0 items-center bg-[var(--el-bg-color)]">
        <button class="refresh-button" type="button" title="刷新当前页面" @click="refreshRouter">
            <icon name="element Refresh" />
        </button>

        <div class="secondary-menu min-w-0 flex-1" :class="{ 'is-empty': !secondaryMenus.length }">
            <el-menu
                v-if="secondaryMenus.length"
                mode="horizontal"
                :ellipsis="true"
                :default-active="String(route.name || '')"
                :router="false"
            >
                <horizontal-menu-item
                    v-for="menu in secondaryMenus"
                    :key="String(menu.name)"
                    :routes="menu"
                />
            </el-menu>
        </div>

        <div class="breadcrumb-wrap" :title="breadcrumbText">
            <el-breadcrumb separator-icon="ArrowRight">
                <el-breadcrumb-item v-for="item in breadcrumbs" :key="String(item.name || item.path)">
                    {{ item.meta.title }}
                </el-breadcrumb-item>
            </el-breadcrumb>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { cloneDeep } from 'lodash-es'
import horizontalMenuItem from './horizontal-menu-item.vue'
import useAppStore from '@/stores/modules/app'
import useUserStore from '@/stores/modules/user'
import storage from '@/utils/storage'
import { getShowApp, getShowSpecialMenu } from '@/app/api/site'
import { formatRouters } from '@/router/routers'

const route = useRoute()
const appStore = useAppStore()
const userStore = useUserStore()
const secondaryMenus = ref<Record<string, any>[]>([])

const breadcrumbs = computed(() => {
    return route.matched
        .filter(item => item.path !== '/' && item.meta?.title)
        .slice(-4)
})

const breadcrumbText = computed(() => {
    return breadcrumbs.value.map(item => String(item.meta.title)).join(' / ')
})

const refreshRouter = () => {
    if (appStore.routeRefreshTag) appStore.refreshRouterView()
}

const getAddonAllKeys = (addonData: any) => {
    if (!addonData || typeof addonData !== 'object') return []
    const keys: string[] = []
    Object.values(addonData).forEach((category: any) => {
        if (!Array.isArray(category?.list)) return
        category.list.forEach((item: any) => item?.key && keys.push(item.key))
    })
    return keys
}

const processSpecialMenus = () => {
    const source = storage.get('specialAppList')
    if (!Array.isArray(source) || !source.length) return []

    const menus = cloneDeep(source)
    const activeAppKey = storage.get('activeAppKey')
    menus.forEach((menu: any) => {
        if (!Array.isArray(menu.children)) return
        menu.children.forEach((child: any) => {
            if (child?.is_show !== undefined) child.is_show = child.menu_key === activeAppKey ? 1 : 0
        })
    })
    return formatRouters(menus.filter((menu: any) => Array.isArray(menu.children) && menu.children.length))
}

const updateSpecialMenuNames = (menus: Record<string, any>[]) => {
    const allNames: string[] = []
    const levelOneNames: string[] = []
    const walk = (items: Record<string, any>[]) => {
        items.forEach(item => {
            if (item.name) allNames.push(item.name)
            if (Array.isArray(item.children)) walk(item.children)
        })
    }
    menus.forEach(menu => {
        if (menu.name) levelOneNames.push(menu.name)
        if (Array.isArray(menu.children)) walk(menu.children)
    })
    storage.set({ key: 'specialMenuNames', data: allNames })
    storage.set({ key: 'specialMenuNamesLevel1', data: levelOneNames })
}

const resolveMenuParent = () => {
    const matched = route.matched
    const apps = userStore.siteInfo?.apps || []

    if (route.meta?.attr !== undefined && route.meta.attr !== '') return matched[1]
    if (apps.length > 1) return matched[2]

    const firstMenu = matched[2]
    if (!firstMenu) return null
    if (!firstMenu.meta?.addon) return firstMenu
    if (firstMenu.meta.addon === apps[0]?.key) return matched[3] || firstMenu
    return firstMenu
}

const rebuildSecondaryMenus = () => {
    const parent = resolveMenuParent()
    const menus = cloneDeep((parent?.children || []) as Record<string, any>[])
    const addonKeys = getAddonAllKeys(storage.get('defaultAppList'))
    let result = menus.filter(child => !child.name || !addonKeys.includes(String(child.name)))

    if (parent?.name === 'addon') {
        const specialMenus = processSpecialMenus()
        if (specialMenus.length) {
            const addonListIndex = result.findIndex(item => item.name === 'addon_list')
            addonListIndex >= 0
                ? result.splice(addonListIndex + 1, 0, ...specialMenus)
                : result.push(...specialMenus)
            updateSpecialMenuNames(specialMenus)
        }
    }

    secondaryMenus.value = result
}

const loadDynamicMenus = async () => {
    const results = await Promise.allSettled([getShowApp(), getShowSpecialMenu()])
    const appResult: any = results[0]
    const specialResult: any = results[1]

    if (appResult.status === 'fulfilled') {
        storage.set({ key: 'defaultAppList', data: appResult.value.data })
    }
    if (specialResult.status === 'fulfilled') {
        const list = specialResult.value.data?.list || []
        storage.set({ key: 'specialAppList', data: list })
        updateSpecialMenuNames(processSpecialMenus())
    }
    rebuildSecondaryMenus()
}

watch(() => route.fullPath, rebuildSecondaryMenus, { immediate: true })
onMounted(loadDynamicMenus)
</script>

<style lang="scss" scoped>
.context-navigation {
    position: relative;
    z-index: 12;
    flex: 0 0 var(--topnav-context-height);
    height: var(--topnav-context-height);
    padding: 0 12px;
    border-bottom: 1px solid var(--el-border-color-lighter);
    box-shadow: 0 2px 8px rgb(15 23 42 / 3%);
}

.refresh-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: none;
    width: 32px;
    height: 32px;
    margin-right: 8px;
    color: var(--el-text-color-secondary);
    background: transparent;
    border: 0;
    border-radius: 6px;
    cursor: pointer;

    &:hover {
        color: var(--el-color-primary);
        background: var(--el-color-primary-light-9);
    }
}

.secondary-menu {
    height: 44px;
    overflow: hidden;
}

.secondary-menu :deep(.el-menu--horizontal) {
    height: 44px;
    border-bottom: 0;
    background: transparent;
}

.secondary-menu :deep(.el-menu--horizontal > .el-menu-item),
.secondary-menu :deep(.el-menu--horizontal > .el-sub-menu .el-sub-menu__title) {
    height: 44px;
    padding: 0 14px;
    line-height: 44px;
    border-bottom-width: 2px;
}

.secondary-menu :deep(.el-menu--horizontal > .el-menu-item.is-active) {
    font-weight: 600;
}

.breadcrumb-wrap {
    flex: 0 1 360px;
    min-width: 150px;
    max-width: 360px;
    margin-left: 16px;
    padding-left: 16px;
    overflow: hidden;
    border-left: 1px solid var(--el-border-color-lighter);
}

.breadcrumb-wrap :deep(.el-breadcrumb) {
    display: flex;
    flex-wrap: nowrap;
    overflow: hidden;
}

.breadcrumb-wrap :deep(.el-breadcrumb__item) {
    display: inline-flex;
    min-width: 0;
    flex: none;
}

.breadcrumb-wrap :deep(.el-breadcrumb__inner) {
    display: block;
    max-width: 120px;
    overflow: hidden;
    color: var(--el-text-color-secondary);
    font-weight: 400;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.breadcrumb-wrap :deep(.el-breadcrumb__item:last-child .el-breadcrumb__inner) {
    color: var(--el-text-color-primary);
    font-weight: 500;
}

@media (max-width: 1366px) {
    .breadcrumb-wrap {
        flex-basis: 240px;
        max-width: 240px;
    }
}

@media (max-width: 1100px) {
    .breadcrumb-wrap {
        display: none;
    }
}
</style>
