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
import { parseCoverageStatus } from './deviceReadings'
import { ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { Search } from '@element-plus/icons-vue'
import { queryDeviceByService } from '@/addon/hsx_recycle/api/device_query_api'
import { getDeviceQueryConfigList } from '@/addon/hsx_recycle/api/device_query_config'

/**
 * 查保修输入框(二次封装):输入框 + 右侧「查保修」按钮。
 * 按钮用本机 IMEI/SN 调 coverage 查询服务,把保修状态回填到本字段(只填保修)。
 * 关键:按设备品牌分发到对应品牌的保修接口(苹果走 apple_coverage、华为走 huawei_coverage…),
 * 不再写死取第一个 coverage(否则华为机用了苹果接口必然失败)。
 * IMEI 可能是 15 位数字,也可能是 SN 号 —— 为空或查不到时给出明确提醒。
 */
const props = defineProps<{
    modelValue?: string
    imei?: string
    /** 设备品牌/型号文本(用于识别品牌、分发对应保修接口),一般传型号标题即可 */
    brand?: string
    placeholder?: string
}>()
const emit = defineEmits<{
    (e: 'update:modelValue', v: string): void
    (e: 'query-result', result: Record<string, any>): void
}>()

const inner = ref(props.modelValue || '')
watch(() => props.modelValue, (v) => { inner.value = v || '' })
const onInput = () => emit('update:modelValue', inner.value)

const loading = ref(false)
const services = ref<any[]>([])

// 设备品牌关键词 → coverage 服务 code 前缀。前端识别品牌、分发不同的查询。
const BRAND_PREFIX: Array<[RegExp, string]> = [
    [/苹果|apple|iphone|ipad|ipod|imac|macbook|\bmac\b|\bwatch\b/i, 'apple'],
    [/华为|huawei|mate|nova|pura|畅享|麦芒/i, 'huawei'],
    [/荣耀|honor|magic/i, 'honor'],
    [/小米|xiaomi|redmi|红米/i, 'xiaomi'],
    [/oppo|reno|\bfind\b/i, 'oppo'],
    [/vivo|iqoo/i, 'vivo'],
    [/三星|samsung|galaxy/i, 'samsung'],
    [/真我|realme/i, 'realme'],
    [/努比亚|nubia|红魔|redmagic/i, 'nubia'],
    [/摩托|moto|motorola/i, 'motorola'],
    [/中兴|zte/i, 'zte'],
]
const resolveBrandPrefix = (text: string): string => {
    const hay = String(text || '')
    for (const [re, prefix] of BRAND_PREFIX) {
        if (re.test(hay)) return prefix
    }
    return ''
}

const loadServices = async () => {
    if (services.value.length) return services.value
    try {
        const res: any = await getDeviceQueryConfigList({ page: 1, limit: 200 })
        const list = res.data?.list || res.data?.data || res.data || []
        services.value = Array.isArray(list) ? list : []
    } catch {
        services.value = []
    }
    return services.value
}

const codeOf = (s: any): string => String(s?.code || s?.service_code || '')
// 在已加载服务里挑指定品牌的 coverage:优先精确 ${prefix}_coverage,否则该品牌任一 coverage
const pickCoverageService = (list: any[], prefix: string): any => {
    const coverages = (Array.isArray(list) ? list : [])
        .filter((s: any) => String(s.result_handler || '') === 'coverage' && Number(s.enabled ?? 1) === 1)
    return coverages.find((s: any) => codeOf(s) === `${prefix}_coverage`)
        || coverages.find((s: any) => codeOf(s).startsWith(`${prefix}_`))
        || null
}


const query = async () => {
    const code = String(props.imei || '').trim()
    if (!code) {
        ElMessage.warning('请先填写设备 IMEI / SN 再查保修(IMEI 为 15 位数字,也可能是 SN 号)')
        return
    }
    const prefix = resolveBrandPrefix(props.brand || '')
    if (!prefix) {
        ElMessage.error('无法识别设备品牌,请先选择型号再查保修')
        return
    }
    loading.value = true
    try {
        const list = await loadServices()
        const svc = pickCoverageService(list, prefix)
        if (!svc) {
            ElMessage.error(`未配置该品牌(${prefix})的保修查询服务,请到「设备查询」中配置`)
            return
        }
        const res: any = await queryDeviceByService({
            service_code: svc.code || svc.service_code,
            query_code: code,
            query_type: svc.query_type || 'imei',
        })
        const data = res?.data?.data || res?.data || {}
        if (code !== String(props.imei || '').trim()) {
            ElMessage.warning('设备串号已变化，本次查询结果未回填，请重新核对设备')
            return
        }
        if (res?.data?.query_record_id) {
            emit('query-result', { ...res.data })
        } else {
            ElMessage.warning('查询已返回，但原始查询记录未保存成功，请联系管理员检查查询记录')
        }
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
