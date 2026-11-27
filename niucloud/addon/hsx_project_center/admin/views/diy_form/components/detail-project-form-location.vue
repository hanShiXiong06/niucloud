<template>
    <div v-if="hasLocation" class="location-detail">
        <div class="location-heading">
            <div class="location-icon">
                <el-icon><LocationFilled /></el-icon>
            </div>
            <div class="location-main">
                <div class="location-name">{{ location.name || '已采集位置' }}</div>
                <div class="location-address">{{ location.full_address || '暂未解析详细地址' }}</div>
                <div v-if="addressFailed" class="location-error">{{ mapProviderText }}地址解析失败：{{ location.address_error || '旧记录未保存具体失败原因，请让客户重新定位' }}</div>
            </div>
        </div>

        <div class="location-meta">
            <div class="meta-item">
                <span class="meta-label">经纬度</span>
                <span class="meta-value">{{ coordinateText }}</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">采集方式</span>
                <span class="meta-value">{{ sourceLabel }}</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">定位精度</span>
                <span class="meta-value">{{ accuracyText }}</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">采集时间</span>
                <span class="meta-value">{{ capturedAtText }}</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">地址解析</span>
                <span class="meta-value" :class="{ 'is-error': addressFailed }">{{ addressStatusText }}</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">地图服务</span>
                <span class="meta-value">{{ mapProviderText }}</span>
            </div>
        </div>
    </div>
    <span v-else>{{ props.data.render_value || '-' }}</span>
</template>

<script lang="ts" setup>
import { computed } from 'vue'

const props = defineProps({
    data: {
        type: Object,
        default: () => ({})
    }
})

const location = computed<any>(() => {
    const raw = props.data.handle_field_value ?? props.data.field_value
    if (raw && typeof raw === 'object' && !Array.isArray(raw)) return raw

    if (typeof raw === 'string' && raw.trim()) {
        try {
            const parsed = JSON.parse(raw)
            return parsed && typeof parsed === 'object' ? parsed : {}
        } catch (_) {
            return {}
        }
    }
    return {}
})

const hasCoordinate = (value: any) => value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value))
const hasLocation = computed(() => hasCoordinate(location.value.latitude) && hasCoordinate(location.value.longitude))

const coordinateText = computed(() => {
    if (!hasLocation.value) return '-'
    return `${Number(location.value.latitude).toFixed(6)}, ${Number(location.value.longitude).toFixed(6)}`
})

const sourceLabel = computed(() => ({
    wechat_js_sdk: '微信定位',
    native_gps: '设备定位',
    manual_map: '地图选择'
} as Record<string, string>)[location.value.source] || '未知来源')

const accuracyText = computed(() => {
    if (location.value.accuracy === null || location.value.accuracy === undefined || location.value.accuracy === '') return '未提供'
    const accuracy = Number(location.value.accuracy)
    return Number.isFinite(accuracy) ? `约 ${accuracy.toFixed(1)} 米` : '未提供'
})

const capturedAtText = computed(() => {
    const timestamp = Number(location.value.captured_at)
    if (!timestamp) return '-'
    const date = new Date(timestamp * 1000)
    const pad = (value: number) => String(value).padStart(2, '0')
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`
})
const addressFailed = computed(() => location.value.address_status === 'failed' || (!location.value.full_address && !!location.value.address_error))
const addressStatusText = computed(() => location.value.full_address ? '解析成功' : addressFailed.value ? '解析失败' : '未记录（历史数据）')
const mapProviderText = computed(() => location.value.map_provider === 'tencent' ? '腾讯地图' : location.value.map_provider === 'tianditu' ? '天地图' : '未记录')
</script>

<style lang="scss" scoped>
.location-detail {
    width: min(620px, 100%);
    padding: 14px;
    border: 1px solid #e6eaf0;
    border-radius: 10px;
    background: #fafbfc;
}

.location-heading {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.location-icon {
    display: flex;
    width: 34px;
    height: 34px;
    flex: none;
    align-items: center;
    justify-content: center;
    border-radius: 9px;
    background: #eef4ff;
    color: var(--el-color-primary);
    font-size: 18px;
}

.location-main {
    min-width: 0;
    flex: 1;
}

.location-name {
    color: #344054;
    font-weight: 600;
}

.location-address {
    margin-top: 4px;
    color: #667085;
    line-height: 20px;
    word-break: break-word;
}
.location-error { margin-top: 8px; padding: 8px 10px; border-radius: 8px; background: #fff1f0; color: #d92d20; font-size: 12px; line-height: 19px; }

.location-meta {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px 18px;
    margin-top: 12px;
    padding-top: 11px;
    border-top: 1px dashed #dfe4ec;
}

.meta-item {
    display: flex;
    min-width: 0;
    gap: 8px;
    font-size: 12px;
    line-height: 20px;
}

.meta-label {
    flex: none;
    color: #98a2b3;
}

.meta-value {
    min-width: 0;
    color: #475467;
    word-break: break-all;
}
.meta-value.is-error { color: #d92d20; font-weight: 500; }

@media (max-width: 720px) {
    .location-meta {
        grid-template-columns: 1fr;
    }
}
</style>
