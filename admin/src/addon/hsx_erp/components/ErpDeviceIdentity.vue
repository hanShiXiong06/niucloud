<template>
    <div class="erp-device-identity">
        <div class="erp-device-identity__model">{{ model || '-' }}</div>
        <div v-if="spec" class="erp-device-identity__spec">{{ spec }}</div>
        <div class="erp-device-identity__serials">
            <span v-if="imei">IMEI {{ imei }}</span>
            <span v-if="sn">SN {{ sn }}</span>
            <span v-if="!imei && !sn">未录入 IMEI / SN</span>
        </div>
        <div v-if="assetNo" class="erp-device-identity__asset">系统资产号 {{ assetNo }}</div>
    </div>
</template>

<script setup lang="ts">
defineProps<{
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
.erp-device-identity__asset {
    margin-top: 3px;
    color: #94a3b8;
    font-size: 11px;
    line-height: 16px;
}
</style>
