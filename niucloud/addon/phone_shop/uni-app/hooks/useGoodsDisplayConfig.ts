import { computed, ref } from 'vue'
import { getSiteId } from '@/utils/common'
import { getGoodsCategoryConfig } from '@/addon/phone_shop/api/goods'

/** 商品展示配置在插件内共享，避免首页每个商品/装修组件各发一次请求。 */
const config = ref({ imei_tail_only: 0 })
const error = ref('')
const ready = ref(false)
let owner = '', updatedAt = 0, generation = 0
let pending: Promise<{ imei_tail_only: number }> | null = null
function currentSite(): string {
    try { return String(getSiteId('') || uni.getStorageSync('wap_site_id') || 'default') } catch { return 'default' }
}
function ensureScope() {
    const site = currentSite()
    if (owner !== site) { owner = site; config.value = { imei_tail_only: 0 }; ready.value = false; error.value = ''; updatedAt = 0; generation++; pending = null }
    return site
}
/** 分类/列表/详情访问校验已有此接口响应，直接复用，不重复请求。 */
export function rememberGoodsDisplayConfig(value: any) {
    ensureScope(); generation++
    config.value = { imei_tail_only: Number(value?.imei_tail_only) === 1 ? 1 : 0 }
    ready.value = true
    updatedAt = Date.now(); error.value = ''
}
export function ensureGoodsDisplayConfig(force = false): Promise<{ imei_tail_only: number }> {
    const site = ensureScope()
    if (!force && updatedAt > 0 && Date.now() - updatedAt < 60000) return Promise.resolve(config.value)
    if (pending) return pending
    const token = ++generation
    const task = (async () => {
        try {
            const response: any = await getGoodsCategoryConfig()
            if (site !== owner || token !== generation) return config.value
            if (!response?.data || typeof response.data !== 'object' || Array.isArray(response.data)) throw new Error('商品展示配置不完整')
            config.value = { imei_tail_only: Number(response.data.imei_tail_only) === 1 ? 1 : 0 }
            ready.value = true
            updatedAt = Date.now(); error.value = ''
        } catch (exception: any) {
            // 未知配置默认关闭；请求失败保留上次配置和错误，不改任何商品原数据。
            if (site === owner && token === generation) error.value = exception?.message || exception?.msg || '商品展示配置加载失败'
        }
        return config.value
    })()
    pending = task
    void task.finally(() => { if (pending === task) pending = null })
    return task
}
export function useGoodsDisplayConfig() {
    ensureScope()
    return { imeiTailOnly: computed(() => Number(config.value.imei_tail_only) === 1), ready, error, ensure: ensureGoodsDisplayConfig }
}
