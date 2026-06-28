<template>
    <el-dialog
        v-model="innerVisible"
        title="录入设备质检信息"
        :width="isMobile ? '94vw' : '640px'"
        append-to-body
        :close-on-click-modal="false"
        class="check-summary-dialog"
    >
        <div class="csd-head">
            <span class="csd-head__model">{{ deviceTitle || '未填写型号' }}</span>
            <el-tag v-if="templateName" size="small" type="success" effect="plain">{{ templateName }}</el-tag>
        </div>

        <div v-if="loading" class="csd-loading">
            <el-icon class="is-loading"><Loading /></el-icon>
            <span>正在加载该型号的质检模板...</span>
        </div>
        <CheckSummaryFields v-else :fields="fields" v-model="localValues" :imei="imei" :brand="deviceTitle" />

        <template #footer>
            <el-button @click="innerVisible = false">取消</el-button>
            <el-button type="primary" @click="handleConfirm">确定</el-button>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { Loading } from '@element-plus/icons-vue'
import CheckSummaryFields from './CheckSummaryFields.vue'
import { validateSummaryRequired } from './summaryUtil'
import type { CheckSummaryField } from './types'

const props = withDefaults(defineProps<{
    visible: boolean
    fields?: CheckSummaryField[]
    values?: Record<string, any>
    templateName?: string
    deviceTitle?: string
    imei?: string
    loading?: boolean
}>(), {
    fields: () => [],
    values: () => ({}),
    templateName: '',
    deviceTitle: '',
    imei: '',
    loading: false
})

const emit = defineEmits<{
    (e: 'update:visible', v: boolean): void
    (e: 'confirm', values: Record<string, any>): void
}>()

const innerVisible = computed({
    get: () => props.visible,
    set: (v: boolean) => emit('update:visible', v)
})

const isMobile = computed(() => typeof window !== 'undefined' && window.innerWidth <= 768)

// 编辑副本，确认前不污染原数据
const localValues = ref<Record<string, any>>({})

watch(() => props.visible, (v) => {
    if (v) {
        const base: Record<string, any> = {}
        // 用字段默认值兜底，再覆盖已有值
        ;(props.fields || []).forEach((f) => {
            const isMultiple = f.component === 'checkbox' || f.selection_mode === 'multiple'
            base[f.field_key] = isMultiple ? [] : ''
        })
        localValues.value = { ...base, ...(props.values || {}) }
    }
})

const handleConfirm = () => {
    const err = validateSummaryRequired(props.fields || [], localValues.value)
    if (err) {
        ElMessage.warning(err)
        return
    }
    emit('confirm', { ...localValues.value })
    innerVisible.value = false
}
</script>

<style lang="scss" scoped>
.csd-head {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
}

.csd-head__model {
    font-size: 15px;
    font-weight: 500;
    color: #303133;
}

.csd-loading {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #409eff;
    font-size: 13px;
    padding: 12px 0;
}
</style>
