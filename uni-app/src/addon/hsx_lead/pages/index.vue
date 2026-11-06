<template>
    <view class="lead-home" :style="themeColor()">
        <loading-page :loading="diy.getLoading() && !timedOut" />
        <view v-if="timedOut" class="lead-home-error"><u-icon name="wifi-off" size="36" color="#91a6c1" /><text>首页暂时没有加载出来</text><u-button :custom-style="cooperationButtonStyle" type="primary" shape="circle" text="重新加载" @click="retry" /></view>
        <view v-show="!diy.getLoading()" :style="diy.pageStyle()"><diy-group ref="groupRef" :data="diy.data" /></view>
    </view>
</template>
<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, unref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { useDiy } from '@/hooks/useDiy'
import { useShare } from '@/hooks/useShare'
import { parseJsonValue } from '@/utils/json'
import { redirect } from '@/utils/common'
import diyGroup from '@/addon/components/diy/group/index.vue'
import { cooperationButtonStyle } from '../utils/mobile-ui'
import { applyCooperationDiyTitle } from '../utils/diy-title'
const diy = useDiy({ name: 'DIY_HSX_LEAD_INDEX' }), groupRef = ref<any>(null), timedOut = ref(false)
const { setShare } = useShare()
let timer: ReturnType<typeof setTimeout> | undefined
let shown = false, disposed = false, entryRefresh: Promise<void> | null = null
onLoad(() => { timer = setTimeout(() => { if (diy.getLoading()) timedOut.value = true },12000) })
diy.onLoad()
diy.onShow((data: any) => {
    timedOut.value = false; if (timer) clearTimeout(timer)
    // Match the framework's chosen-page mode as well as component decoration.
    if (!data.value && data.page) { redirect({ url:data.page, mode:'reLaunch' }); return }
    applyCooperationDiyTitle(unref(diy.data)); setShare(parseJsonValue(data.share,{})); groupRef.value?.refresh?.(); void refreshEntries()
})
diy.onHide(); diy.onUnload(); diy.onPageScroll()
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
onBeforeUnmount(() => { disposed = true; if (timer) clearTimeout(timer) })
</script>
<style lang="scss" scoped>
.lead-home{min-height:100vh;background:#fff}.lead-home-error{padding:180rpx 40rpx;display:flex;flex-direction:column;align-items:center;gap:30rpx;color:#718199;font-size:28rpx}.lead-home-error>u-button{width:300rpx}
</style>
