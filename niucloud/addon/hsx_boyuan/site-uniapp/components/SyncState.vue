<template><view class="sync-state" :class="tone"><view class="sync-state-dot" /><text>{{ label || names[status] || '状态待核对' }}</text></view></template>
<script setup lang="ts">
import { computed } from 'vue'
const props = defineProps<{ status: string, label?: string }>()
const names: Record<string, string> = { pending: '待同步', retry: '等待重试', processing: '处理中', waiting: '等待原单', confirming: '结果待确认', blocked: '待补资料', rejected: '博远已拒绝', partial: '部分成功', attention: '需人工核验', manual_pending: '人工待核验', simulated: '模拟完成', succeeded: '已同步', queued: '已排队', completed: '已更新', running: '更新中', failed: '更新中断', needs_review: '数量待核对', skipped: '未重复提交' }
const tone = computed(() => ['succeeded', 'completed'].includes(props.status) ? 'success' : ['pending', 'processing', 'running', 'queued', 'retry', 'waiting', 'simulated'].includes(props.status) ? 'progress' : 'attention')
</script>
<style scoped lang="scss">
.sync-state { display: inline-flex; align-items: center; flex: 0 0 auto; gap: 5px; padding: 4px 7px; font-size: 12px; line-height: 1.4; border-radius: 4px; white-space: nowrap; color: #995318; background: #fff4e5; }
.sync-state-dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; flex-shrink: 0; }
.success { color: #19734d; background: #eaf7ef; }.progress { color: #315fb3; background: #edf3ff; }
</style>
