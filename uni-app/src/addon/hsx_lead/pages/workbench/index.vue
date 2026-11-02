<template>
    <view v-if="legacy" class="coop-legacy-container"><view class="coop-return-new" @tap="legacy = false">‹ 返回商户协作</view><LegacyWorkbench ref="legacyRef" :initial-task-id="legacyTaskId" /></view>
    <CooperationWorkbench v-else ref="cooperationRef" :initial-id="collaborationId" @legacy="legacy = true" />
</template>
<script setup lang="ts">
import { ref } from 'vue'
import { onHide, onLoad, onPullDownRefresh, onReachBottom, onShow } from '@dcloudio/uni-app'
import CooperationWorkbench from '../../components/cooperation/CooperationWorkbench.vue'
import LegacyWorkbench from './LegacyWorkbench.vue'
const legacy = ref(false), legacyTaskId = ref(0), collaborationId = ref(0), cooperationRef = ref<any>(null), legacyRef = ref<any>(null)
function positiveId(value: unknown) { const id = Number(value); return Number.isSafeInteger(id) && id > 0 && id <= 4294967295 ? id : 0 }
onLoad(options => { const task = positiveId(options?.task_id); if (task) { legacyTaskId.value = task; legacy.value = true }; const collaboration = positiveId(options?.collaboration_id); if (collaboration) { collaborationId.value = collaboration; legacy.value = false } })
onShow(() => { if (legacy.value) legacyRef.value?.resume?.(); else cooperationRef.value?.resume?.() })
onHide(() => { cooperationRef.value?.pause?.(); legacyRef.value?.pause?.() })
onPullDownRefresh(async () => { try { if (legacy.value) await legacyRef.value?.refresh?.(); else await cooperationRef.value?.refresh?.() } finally { uni.stopPullDownRefresh() } })
onReachBottom(() => { if (legacy.value) legacyRef.value?.loadMore?.() })
</script>
<style scoped>.coop-return-new { padding: 14px 16px; color: var(--primary-color, #d93833); font-size: 14px; background: #fff; border-bottom: 1px solid #edf0f3; }</style>
