<template>
    <div class="device-pay" :class="'device-pay--' + state">
        <strong>{{ label }}</strong>
        <template v-if="state === 'pending' || state === 'partial'">
            <el-button v-if="canView" link type="primary" title="查看本机的原应付记录，不会付款" @click="$emit('view')">查看应付</el-button>
            <small v-else>财务跟进</small>
        </template>
        <el-button v-else-if="state === 'settled' && canView" link type="primary" title="只读查看这台设备的原应付与结算凭证" @click="$emit('view')">查看结算</el-button>
        <small v-else-if="state === 'review'">请财务核对原单</small>
    </div>
</template>
<script setup lang="ts">
defineProps<{ state: string; label: string; canView: boolean }>()
defineEmits<{ (e: 'view'): void }>()
</script>
<style scoped>
.device-pay {display:flex;align-items:baseline;gap:3px 10px;flex-wrap:wrap}
.device-pay strong {font-size:14px;font-weight:600;font-variant-numeric:tabular-nums;color:var(--el-text-color-primary);white-space:nowrap}
.device-pay--pending strong::before,.device-pay--partial strong::before,.device-pay--review strong::before {content:'';display:inline-block;vertical-align:2px;width:6px;height:6px;margin-right:6px;border-radius:50%;background:var(--el-color-warning)}
.device-pay--settled strong,.device-pay--void strong {font-weight:400;color:var(--el-text-color-regular)}
.device-pay small {font-size:12px;line-height:18px;color:var(--el-text-color-regular)}
.device-pay :deep(.el-button.is-link) {padding:0;min-height:24px;font-size:12px}
</style>
