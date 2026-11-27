<template>
    <div class="erp-workspace-header" :class="{ 'is-collapsed': collapsed }">
        <HsxTitle size="page" collapsible-subtitle>
            <template #default><slot name="title" /></template>
            <template v-if="!collapsed && $slots.subtitle" #subtitle><slot name="subtitle" /></template>
            <template #extra>
                <slot name="extra" />
                <el-button v-if="collapsed && filterCount" link type="primary" :icon="Filter" @click="setCollapsed(false)">筛选 {{ filterCount }}</el-button>
                <el-tooltip :content="toggleLabel" placement="bottom">
                    <span class="erp-workspace-header__trigger"><el-button class="erp-workspace-header__toggle" :icon="collapsed ? ArrowDown : ArrowUp" :aria-label="toggleLabel" :aria-expanded="!collapsed" :aria-controls="bodyId" @click="setCollapsed(!collapsed)" /></span>
                </el-tooltip>
            </template>
        </HsxTitle>
        <div v-if="$slots.toolbar" class="erp-workspace-header__toolbar"><slot name="toolbar" /></div>
        <div :id="bodyId" v-show="!collapsed" class="erp-workspace-header__body"><slot /></div>
    </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { ArrowDown, ArrowUp, Filter } from '@element-plus/icons-vue'
import { HsxTitle } from '@/addon/hsx_components/core'
import useUserStore from '@/stores/modules/user'
import { erpHeaderPreferenceKey, readErpHeaderCollapsed, writeErpHeaderCollapsed } from '../utils/desktop-layout'

const props = withDefaults(defineProps<{ page: string; filterCount?: number; regionLabel?: string }>(), {
    filterCount: 0,
    regionLabel: '顶部筛选与统计'
})
const emit = defineEmits<{ (event: 'change', collapsed: boolean): void }>()
const user = useUserStore()
const preferenceKey = computed(() => erpHeaderPreferenceKey(props.page, user.siteInfo?.site_id || 0, (user.userInfo as { uid?: number }).uid || 0))
const bodyId = computed(() => `erp-header-${props.page}`)
const collapsed = ref(false)
const toggleLabel = computed(() => `${collapsed.value ? '展开' : '收起'}${props.regionLabel}`)
let preferences: Storage | undefined
try { preferences = window.localStorage } catch { /* Private browsing may disable storage. */ }

function restore() {
    collapsed.value = readErpHeaderCollapsed(preferences, preferenceKey.value, window.innerWidth, window.innerHeight)
}
function setCollapsed(value: boolean) {
    collapsed.value = value
    writeErpHeaderCollapsed(preferences, preferenceKey.value, value)
}
watch(preferenceKey, restore, { immediate: true })
watch(collapsed, async value => { await nextTick(); emit('change', value) })
</script>

<style scoped>
.erp-workspace-header { min-width: 0; }
.erp-workspace-header > .hsx-title { margin-bottom: 12px; }
.erp-workspace-header.is-collapsed > .hsx-title { margin-bottom: 6px; }
.erp-workspace-header__body { display: flow-root; }
.erp-workspace-header__trigger { display: inline-flex; flex: 0 0 32px; }
.erp-workspace-header__toggle { flex: 0 0 32px; width: 32px; height: 32px; padding: 0; }
.erp-workspace-header__toggle:hover, .erp-workspace-header__toggle:focus-visible { color: var(--erp-text-accent); }
.erp-workspace-header__toolbar { margin-bottom: 10px; }
.erp-workspace-header :deep(.hsx-title__extra) { flex-wrap: wrap; }
</style>
