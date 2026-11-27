<template>
    <div class="erp-device-identity" :class="{ 'erp-device-identity--compact': compact }">
        <div class="erp-device-identity__model" :title="compact ? [model, spec].filter(Boolean).join(' · ') : undefined">
            {{ model || '-' }}<small v-if="compact && spec && !String(model || '').includes(spec)"> · {{ spec }}</small>
        </div>
        <div v-if="spec && !compact" class="erp-device-identity__spec">{{ spec }}</div>
        <div class="erp-device-identity__serials">
            <span v-if="imei">IMEI <ErpCopyText v-if="compact" :value="imei" title="设备 IMEI" /><template v-else>{{ imei }}</template></span>
            <span v-if="sn">SN <ErpCopyText v-if="compact" :value="sn" title="设备 SN" /><template v-else>{{ sn }}</template></span>
            <span v-if="!imei && !sn">未录入 IMEI / SN</span>
        </div>
    </div>
</template>

<script setup lang="ts">
import ErpCopyText from './ErpCopyText.vue'
defineProps<{
    compact?: boolean
    model?: string
    spec?: string
    imei?: string
    sn?: string
    assetNo?: string
}>()
</script>

<style scoped>
.erp-device-identity__model {
    color: #0f172a;
    font-size: 14px;
    font-weight: 650;
    line-height: 20px;
}
.erp-device-identity__spec {
    margin-top: 3px;
    color: #475569;
    font-size: 12px;
    line-height: 18px;
}
.erp-device-identity__serials {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 10px;
    margin-top: 4px;
    color: #334155;
    font-size: 12px;
    line-height: 18px;
}
.erp-device-identity--compact .erp-device-identity__model { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:var(--el-text-color-primary); }
.erp-device-identity--compact small {font-size:12px;font-weight:400;color:var(--el-text-color-regular)}
.erp-device-identity--compact .erp-device-identity__serials {margin-top:2px;color:var(--el-text-color-regular);font-variant-numeric:tabular-nums}
.erp-device-identity--compact .erp-device-identity__serials > span {display:inline-flex;align-items:center;gap:4px;min-width:0}
</style>
