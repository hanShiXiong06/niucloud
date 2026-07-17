<template><el-tag :type="meta.type" effect="plain" size="small">{{ meta.text }}</el-tag></template>
<script setup lang="ts">
import { computed } from 'vue'
const props = defineProps<{ value?: string }>()
const map: Record<string, { text: string; type: any }> = {
    draft: { text: '草稿', type: 'info' }, enabled: { text: '启用', type: 'success' }, disabled: { text: '停用', type: 'info' },
    processing: { text: '处理中', type: 'warning' }, active: { text: '有效', type: 'success' }, failed: { text: '处理失败', type: 'danger' },
    pending: { text: '待结算', type: 'warning' }, partial: { text: '部分结算', type: 'warning' }, settled: { text: '已结清', type: 'success' },
    exhausted: { text: '已用完', type: 'info' }, expired: { text: '已过期', type: 'info' }, frozen: { text: '已冻结', type: 'danger' },
    cancelled: { text: '已取消', type: 'info' }, refund_pending: { text: '退款中', type: 'warning' }, refunded: { text: '已退款', type: 'info' },
    success: { text: '核销成功', type: 'success' }, reversed: { text: '已冲正', type: 'info' }, paid: { text: '已退款', type: 'success' }, void: { text: '已作废', type: 'info' },
}
const meta = computed(() => map[String(props.value || '')] || { text: String(props.value || '未知'), type: 'info' })
</script>
