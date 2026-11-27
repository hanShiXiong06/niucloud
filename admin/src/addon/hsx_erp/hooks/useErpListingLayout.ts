import { ref } from 'vue'

export const defaultListingOrder = Object.freeze(['category', 'details', 'price', 'images', 'extras'] as const)
export type ErpListingSection = typeof defaultListingOrder[number]
export const listingSectionLabels: Record<ErpListingSection, string> = {
    category: '商城分类', details: '规格与标签', price: '销售价格', images: '商品图片', extras: '视频与说明',
}

/** 只保存五个固定区块的顺序；坏数据、重复项和新版本新增项均安全补齐。 */
export function normalizeListingOrder(value: unknown): ErpListingSection[] {
    const rows = Array.isArray(value) ? value : []
    const valid = rows.filter((id): id is ErpListingSection => typeof id === 'string' && (defaultListingOrder as readonly string[]).includes(id))
    return [...new Set(valid), ...defaultListingOrder.filter(id => !valid.includes(id))]
}

export function moveListingSection(value: unknown, id: string, toIndex: number): ErpListingSection[] {
    const order = normalizeListingOrder(value), from = order.indexOf(id as ErpListingSection)
    if (from < 0 || !Number.isFinite(toIndex)) return order
    const target = Math.max(0, Math.min(order.length - 1, Math.trunc(toIndex)))
    order.splice(from, 1); order.splice(target, 0, id as ErpListingSection)
    return order
}

function readLocal(key: string): unknown {
    if (typeof uni !== 'undefined' && typeof uni.getStorageSync === 'function') return uni.getStorageSync(key)
    if (typeof localStorage !== 'undefined') {
        const value = localStorage.getItem(key)
        return value == null ? null : JSON.parse(value)
    }
    return null
}
function localIdentity() {
    const mobile = typeof uni !== 'undefined' && typeof uni.getStorageSync === 'function'
    const prefix = mobile ? '' : 'site.'
    const info: any = readLocal(prefix + 'userinfo') || {}
    const site: any = readLocal(prefix + 'siteInfo') || {}
    const siteId = Number(readLocal(prefix + 'siteId') || site.site_id || info.site_id || 0)
    const uid = Number(info.uid || info.user_id || 0)
    return Number.isSafeInteger(siteId) && siteId > 0 && Number.isSafeInteger(uid) && uid > 0
        ? `erp:listing-layout:v1:${siteId}:${uid}` : ''
}

/** 本设备 + 本站点 + 当前员工的展示偏好，不保存业务草稿、不访问接口或数据库。 */
export function useErpListingLayout() {
    const sectionOrder = ref<ErpListingSection[]>([...defaultListingOrder])
    const storageError = ref('')
    let key = ''
    try {
        key = localIdentity()
        if (key) sectionOrder.value = normalizeListingOrder(readLocal(key))
    } catch {
        storageError.value = '原排序偏好无法读取，已使用默认顺序。'
        if (typeof console !== 'undefined') console.warn('[ERP layout] 无法读取本设备的排序偏好，已安全回退')
    }
    const sectionStyle = (id: string) => ({ order: sectionOrder.value.indexOf(id as ErpListingSection) + 1 || defaultListingOrder.length + 1 })
    function setSectionOrder(value: unknown): boolean {
        sectionOrder.value = normalizeListingOrder(value)
        try {
            key = localIdentity()
            if (!key) throw new Error('identity-unavailable')
            if (typeof uni !== 'undefined' && typeof uni.setStorageSync === 'function') uni.setStorageSync(key, [...sectionOrder.value])
            else if (typeof localStorage !== 'undefined') localStorage.setItem(key, JSON.stringify(sectionOrder.value))
            else throw new Error('storage-unavailable')
            storageError.value = ''
            return true
        } catch {
            storageError.value = '本次顺序已临时应用，但未能保存在本设备，下次打开可能恢复默认。'
            if (typeof uni !== 'undefined' && typeof uni.showToast === 'function') uni.showToast({ title: '顺序未保存，本次临时生效', icon: 'none' })
            else if (typeof console !== 'undefined') console.warn('[ERP layout] ' + storageError.value)
            return false
        }
    }
    return { sectionOrder, sectionStyle, setSectionOrder, storageError }
}

