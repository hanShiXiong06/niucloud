<template>
    <el-input
        v-model="inner"
        :placeholder="placeholder || '保修日期 / 过保 / 未激活'"
        clearable
        @input="onInput"
        @change="onInput"
    >
        <template #append>
            <el-button :loading="loading" :icon="Search" @click="query">查保修</el-button>
        </template>
    </el-input>
</template>

<script lang="ts" setup>
import { ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { Search } from '@element-plus/icons-vue'
import { queryDeviceByService } from '@/addon/hsx_recycle/api/device_query_api'
import { getDeviceQueryConfigList } from '@/addon/hsx_recycle/api/device_query_config'

/**
 * 查保修输入框(二次封装):输入框 + 右侧「查保修」按钮。
 * 按钮用本机 IMEI/SN 调 coverage 查询服务,把保修状态回填到本字段(只填保修)。
 * IMEI 可能是 15 位数字,也可能是 SN 号 —— 为空或查不到时给出明确提醒。
 */
const props = defineProps<{
    modelValue?: string
    imei?: string
    placeholder?: string
}>()
const emit = defineEmits<{ (e: 'update:modelValue', v: string): void }>()

const inner = ref(props.modelValue || '')
watch(() => props.modelValue, (v) => { inner.value = v || '' })
const onInput = () => emit('update:modelValue', inner.value)

const loading = ref(false)
const coverageService = ref<any>(null)

const loadCoverageService = async () => {
    if (coverageService.value) return coverageService.value
    try {
        const res: any = await getDeviceQueryConfigList({ page: 1, limit: 100 })
        const list = res.data?.list || res.data?.data || res.data || []
        coverageService.value = (Array.isArray(list) ? list : []).find((s: any) => String(s.result_handler || '') === 'coverage') || null
    } catch {
        coverageService.value = null
    }
    return coverageService.value
}

// 兼容多品牌的保修状态解析(与回收质检弹窗口径一致)
const parseCoverageStatus = (coverage: any): string => {
    if (!coverage) return ''
    const status = String(coverage.status || '').trim()
    const date = String(coverage.date || '').trim()
    if (status === 'Out Of Warranty') return '过保'
    if (status === 'Not Activated' || (!date && !status)) return '未激活'
    if (status === 'In Warranty' || status === 'Active') {
        return date ? `保 ${date}` : '在保'
    }
    return date || status || '在保'
}

const query = async () => {
    const code = String(props.imei || '').trim()
    if (!code) {
        ElMessage.warning('请先填写设备 IMEI / SN 再查保修(IMEI 为 15 位数字,也可能是 SN 号)')
        return
    }
    loading.value = true
    try {
        const svc = await loadCoverageService()
        if (!svc) {
            ElMessage.error('未配置保修(coverage)查询服务,请到「设备查询」中配置')
            return
        }
        const res: any = await queryDeviceByService({
            service_code: svc.code || svc.service_code,
            query_code: code,
            query_type: svc.query_type || 'imei',
        })
        const data = res?.data?.data || res?.data || {}
        let warranty = ''
        if (data.coverage) {
            warranty = parseCoverageStatus(data.coverage)
        } else if (data.coverage_status || data.coverage_date) {
            warranty = parseCoverageStatus({ status: data.coverage_status, date: data.coverage_date })
        }
        if (!warranty) {
            ElMessage.warning('未查到保修信息,请确认 IMEI/SN 是否正确(IMEI 为 15 位数字,也可能是 SN 号)')
            return
        }
        inner.value = warranty
        emit('update:modelValue', warranty)
        ElMessage.success('保修信息已填入')
    } catch (e: any) {
        ElMessage.error(e?.message || '查保修失败,请检查 IMEI/SN 或「设备查询」配置')
    } finally {
        loading.value = false
    }
}
</script>
