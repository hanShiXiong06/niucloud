<template>
    <template v-if="name || imei">
        <el-tooltip
            v-if="hasMore"
            placement="top"
            :show-after="100"
            effect="dark"
        >
            <template #content>
                <div class="di-pop">
                    <div class="di-pop-name">{{ name || '设备' }}</div>
                    <div v-if="summaryFields.length" class="di-pop-spec">
                        <span
                            v-for="(s, i) in summaryFields"
                            :key="i"
                            class="di-pop-spec-item"
                        >{{ s.field_name ? s.field_name + '：' : '' }}{{ s.label }}</span>
                    </div>
                    <div v-else-if="subtitle" class="di-pop-spec">{{ subtitle }}</div>
                    <div v-if="imei" class="di-pop-imei">IMEI {{ imei }}</div>
                </div>
            </template>
            <div class="di-cell">
                <div class="di-name">
                    {{ name || '设备' }}
                    <el-icon class="di-more"><InfoFilled /></el-icon>
                </div>
                <div class="di-imei">{{ imei || '—' }}</div>
            </div>
        </el-tooltip>
        <div v-else class="di-cell">
            <div class="di-name">{{ name || '设备' }}</div>
            <div class="di-imei">{{ imei || '—' }}</div>
        </div>
    </template>
    <span v-else class="di-empty">—</span>
</template>

<script lang="ts" setup>
import { computed } from 'vue'
import { InfoFilled } from '@element-plus/icons-vue'

// 统一"设备身份"展示:默认紧凑(名称+串号),小标题(≤5 个加入描述的质检属性)收进 hover 气泡。
// 后端各处统一吐 device_name / device_subtitle / device_summary_fields / device_imei / device_identity_text。
const props = defineProps<{
    row?: Record<string, any>
    // 也支持直接传单值(非 row 场景)
    name?: string
    imei?: string
    subtitle?: string
    summaryFields?: Array<Record<string, any>>
}>()

const src = computed(() => props.row || {})
const name = computed(() => props.name ?? (src.value.device_name || src.value.device_model || ''))
const imei = computed(() => props.imei ?? (src.value.device_imei || ''))
const subtitle = computed(() => props.subtitle ?? (src.value.device_subtitle || ''))
const summaryFields = computed<Array<Record<string, any>>>(() => {
    const v = props.summaryFields ?? src.value.device_summary_fields
    return Array.isArray(v) ? v : []
})
const hasMore = computed(() => summaryFields.value.length > 0 || !!subtitle.value)
</script>

<style scoped>
.di-cell { line-height: 1.35; }
.di-name { font-weight: 500; display: inline-flex; align-items: center; gap: 2px; }
.di-more { font-size: 12px; color: var(--el-text-color-placeholder); }
.di-imei { font-size: 12px; color: var(--el-text-color-secondary); }
.di-empty { color: var(--el-text-color-placeholder); }
.di-pop { max-width: 260px; line-height: 1.7; }
.di-pop-name { font-weight: 600; }
.di-pop-spec { margin-top: 2px; }
.di-pop-spec-item:not(:last-child)::after { content: ' · '; opacity: .6; }
.di-pop-imei { margin-top: 2px; opacity: .85; font-size: 12px; }
</style>
