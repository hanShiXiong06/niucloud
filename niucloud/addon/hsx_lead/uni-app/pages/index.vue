<template>
    <view class="lead-home lead-diy-page" :style="themeColor()">
        <CoopDiyPageStatus :loading="pageLoad.loading.value" :error="pageLoad.error.value" @retry="retry" />
        <view v-show="pageLoad.ready.value" class="diy-template-wrap bg-index" :style="diy.pageStyle()"
            ><diy-group ref="groupRef" :data="diy.data"
        /></view>
        <CoopPendingEntry
            :active="entryActive && pageLoad.ready.value && !decorating"
            @publish="openCooperationTask('publish')"
        />
    </view>
</template>
<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, unref } from 'vue'
import { onHide, onLoad, onShow } from '@dcloudio/uni-app'
import { useDiy } from '@/hooks/useDiy'
import { useShare } from '@/hooks/useShare'
import { parseJsonValue } from '@/utils/json'
import { redirect } from '@/utils/common'
import diyGroup from '@/addon/components/diy/group/index.vue'
import { applyCooperationDiyTitle } from '../utils/diy-title'
import { useCooperationDiyLoad } from '../composables/useCooperationDiyLoad'
import CoopDiyPageStatus from '../components/cooperation/CoopDiyPageStatus.vue'
import CoopPendingEntry from '../components/cooperation/CoopPendingEntry.vue'
import { openCooperationTask } from '../utils/cooperation-navigation'
const pageLoad = useCooperationDiyLoad('首页')
const diy = useDiy({ name: 'DIY_HSX_LEAD_INDEX', ...pageLoad.requestOptions }), groupRef = ref<any>(null)
const { setShare } = useShare()
const entryActive = ref(false), decorating = ref(false)
let shown = false, disposed = false, entryRefresh: Promise<void> | null = null
diy.onLoad()
onLoad(options => { decorating.value = options?.mode === 'decorate'; if (decorating.value) pageLoad.complete(); else pageLoad.start() })
onShow(() => { entryActive.value = true })
onHide(() => { entryActive.value = false })
diy.onShow((data: any) => {
    if (!pageLoad.complete()) return
    // Match the framework's chosen-page mode as well as component decoration.
    if (!data.value && data.page) { entryActive.value = false; redirect({ url:data.page, mode:'reLaunch' }); return }
    applyCooperationDiyTitle(unref(diy.data)); setShare(parseJsonValue(data.share,{})); groupRef.value?.refresh?.(); void refreshEntries()
})
diy.onHide(() => pageLoad.pause()); diy.onUnload(); diy.onPageScroll()
function retry() { uni.redirectTo({ url: '/addon/hsx_lead/pages/index' }) }
function refreshEntries(): Promise<void> {
    // The child owns its initial onMounted read; only a later page return needs
    // another business read. Framework group.refresh refreshes the topbar only.
    if (!shown) { shown = true; return Promise.resolve() }
    if (entryRefresh) return entryRefresh
    entryRefresh = (async () => {
        await nextTick()
        if (disposed) return
        let refs: any
        try { refs = groupRef.value?.getFormRef?.()?.componentRefs } catch { return }
        const entries = [refs?.diyLeadCooperationEntryRef, refs?.diyLeadBusinessNavigationRef].flatMap(component => Array.isArray(component) ? component : component ? [component] : [])
        await Promise.all([...new Set(entries)].filter(entry => typeof entry?.refresh === 'function').map(entry => Promise.resolve().then(() => entry.refresh()).catch(() => undefined)))
    })().finally(() => { entryRefresh = null })
    return entryRefresh
}
onBeforeUnmount(() => { disposed = true; pageLoad.dispose() })
</script>
<style lang="scss" scoped>
@import '@/styles/diy.scss';
@import '../components/cooperation/cooperation-diy-page';
.lead-home{min-height:100vh;background:#fff}
</style>
