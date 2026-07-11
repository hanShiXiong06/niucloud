import { ref } from 'vue'
import { getErpSaleChannelOptions } from '@/addon/hsx_erp/api/erp'

export type ErpSaleChannelOption = {
    key: string
    name: string
    channel_type: string
    channel_type_name: string
    source_plugin: string
    source_plugin_name: string
    source_key: string
    is_default: number
    enabled: number
    [key: string]: any
}

// 渠道由 ERP 配置和插件 Hook 共同组装；移动端各页面共享同一份结果，避免重复请求和字段分叉。
const options = ref<ErpSaleChannelOption[]>([])
const loading = ref(false)
const error = ref('')
let pending: Promise<ErpSaleChannelOption[]> | null = null

export function useErpSaleChannels() {
    async function load(force = false): Promise<ErpSaleChannelOption[]> {
        if (!force && options.value.length) return options.value
        if (pending) return pending

        loading.value = true
        error.value = ''
        pending = getErpSaleChannelOptions()
            .then((res: any) => {
                options.value = normalizeChannels(res?.data || [])
                return options.value
            })
            .catch((reason: any) => {
                error.value = reason?.message || '销售渠道加载失败'
                throw reason
            })
            .finally(() => {
                loading.value = false
                pending = null
            })
        return pending
    }

    function find(key: string) {
        return options.value.find(row => row.key === String(key || ''))
    }

    function preferred() {
        return options.value.find(row => row.is_default === 1) || options.value[0]
    }

    return { options, loading, error, load, find, preferred }
}

function normalizeChannels(rows: any[]): ErpSaleChannelOption[] {
    return (Array.isArray(rows) ? rows : [])
        .filter(row => Number(row?.enabled ?? 1) === 1)
        .map(row => ({
            ...row,
            key: String(row?.key || row?.value || ''),
            name: String(row?.name || row?.label || row?.key || ''),
            channel_type: String(row?.channel_type || ''),
            channel_type_name: erpSaleChannelTypeLabel(String(row?.channel_type || '')),
            source_plugin: String(row?.source_plugin || ''),
            source_plugin_name: String(row?.source_plugin_name || ''),
            source_key: String(row?.source_key || ''),
            is_default: Number(row?.is_default || 0),
            enabled: Number(row?.enabled ?? 1),
        }))
        .filter(row => row.key && row.name)
}

export function erpSaleChannelTypeLabel(type: string) {
    return ({
        peer: '同行', retail: '零售', store: '门店', miniapp: '小程序',
        platform: '平台', ecommerce: '电商', plugin: '插件',
    } as Record<string, string>)[String(type || '')] || '其他渠道'
}
