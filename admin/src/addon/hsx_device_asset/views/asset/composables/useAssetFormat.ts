import { ref } from 'vue'
import { img } from '@/utils/common'

/**
 * 资产中台 UI 共享格式化/判定工具（从 list.vue 抽出，供列表页与各弹窗复用）。
 * 全部为纯函数 + 一份摘要折叠状态，无业务副作用。
 */
export function useAssetFormat() {
    const money = (value: any) => Number(value || 0).toFixed(2)
    const imgUrl = (url: string) => img(url || '')

    const statusType = (status: string) => {
        if (status === 'exported') return 'success'
        if (status === 'ready_export' || status === 'wait_price') return 'warning'
        if (status === 'photo_rejected') return 'danger'
        return 'info'
    }
    const photoStatusType = (status: string) => {
        if (status === 'approved') return 'success'
        if (status === 'rejected') return 'danger'
        if (status === 'review') return 'warning'
        return 'info'
    }
    const priceStatusType = (status: string) => status === 'completed' ? 'success' : (status === 'pending' ? 'warning' : 'info')
    const mediaStatusType = (status: string) => status === 'approved' ? 'success' : (status === 'rejected' ? 'danger' : 'warning')

    const splitImages = (value: any) => {
        if (Array.isArray(value)) return value.filter(Boolean)
        return String(value || '').split(',').map((item: string) => item.trim()).filter(Boolean)
    }

    const normalizeObject = (value: any) => {
        if (!value) return {}
        if (typeof value === 'object') return value
        try {
            const parsed = JSON.parse(value)
            return typeof parsed === 'object' && parsed ? parsed : { 原始质检: value }
        } catch {
            return { 原始质检: value }
        }
    }

    const normalizeCheckResult = (value: any, label: string) => {
        if (!value) return {}
        if (typeof value === 'object') return value
        try {
            const parsed = JSON.parse(value)
            return typeof parsed === 'object' && parsed ? parsed : { [label]: value }
        } catch {
            return { [label]: value }
        }
    }

    const checkSummaryEntries = (asset: any) => {
        const device = asset?.recycle_device || asset?.recycleDevice || {}
        const summary = asset?.check_summary || {}
        const data: Record<string, any> = {
            ...normalizeCheckResult(device.check_result_seller || '', '卖家质检'),
            // ...normalizeCheckResult(device.check_result_buyer || '', '买家质检'),
            ...normalizeObject(summary),
        }
        if (!data['容量'] && (asset?.ext_json?.capacity || device.capacity)) data['容量'] = asset?.ext_json?.capacity || device.capacity
        if (!data['颜色'] && (asset?.ext_json?.color || device.color)) data['颜色'] = asset?.ext_json?.color || device.color
        return Object.entries(data)
            .filter(([, value]) => value !== '' && value !== null && value !== undefined)
            .map(([key, value]) => ({
                key,
                value: typeof value === 'object' ? JSON.stringify(value) : String(value)
            }))
    }

    const recycleCheckImages = (asset: any) => {
        const device = asset?.recycle_device || asset?.recycleDevice || {}
        return [
            ...splitImages(asset?.ext_json?.check_images_buyer),
            ...splitImages(asset?.ext_json?.check_images_seller),
            ...splitImages(asset?.ext_json?.check_images),
            ...splitImages(device.check_images_buyer),
            ...splitImages(device.check_images_seller),
            ...splitImages(device.check_images)
        ].filter((url, index, arr) => url && arr.indexOf(url) === index)
    }

    const assetImages = (asset: any) => {
        return (asset?.media || [])
            .filter((item: any) => item.media_type !== 'video' && item.status !== 'rejected')
            .map((item: any) => item.url)
            .filter(Boolean)
    }

    const priceCompareImages = (asset: any) => {
        return [...recycleCheckImages(asset).slice(0, 4), ...assetImages(asset).slice(0, 4)]
    }

    const deviceSpecText = (asset: any) => {
        const device = asset?.recycle_device || asset?.recycleDevice || {}
        return [asset?.ext_json?.capacity || device.capacity, asset?.ext_json?.color || device.color].filter(Boolean).join(' / ')
    }

    const canConfirmPhotos = (asset: any) => {
        if (!asset) return false
        if (asset.photo_status === 'approved') return false
        if (asset.status === 'exported' || asset.status === 'archived') return false
        return Number(asset.image_count || 0) > 0
    }

    const canUploadMedia = (asset: any) => {
        if (!asset) return false
        if (asset.status === 'exported' || asset.status === 'archived') return false
        return ['wait_photo', 'photoing', 'photo_review', 'photo_rejected'].includes(asset.status)
    }

    const canPrice = (asset: any) => {
        if (!asset) return false
        if (asset.status === 'exported' || asset.status === 'archived') return false
        return asset.photo_status === 'approved' || ['wait_price', 'ready_export'].includes(asset.status)
    }

    const nextActionLabel = (asset: any) => {
        if (!asset) return '-'
        if (asset.status === 'exported') return '已导出，可归档'
        if (asset.photo_status === 'rejected' || asset.status === 'photo_rejected') return '下一步：补拍退回图片'
        if (['wait_photo', 'photoing'].includes(asset.photo_status) || Number(asset.image_count || 0) <= 0) return '下一步：拍照/补图'
        if (asset.photo_status !== 'approved') return '下一步：复检确认照片'
        if (asset.price_status !== 'completed') return '下一步：销售定价'
        return '下一步：导出销售资料'
    }

    // 质检摘要里卖家质检等可能很长，超过阈值默认折叠、可展开
    const expandedSummaryKeys = ref<Set<string>>(new Set())
    const isLongSummary = (v: any) => String(v ?? '').length > 50
    const toggleSummary = (key: string) => {
        const s = new Set(expandedSummaryKeys.value)
        s.has(key) ? s.delete(key) : s.add(key)
        expandedSummaryKeys.value = s
    }

    return {
        money, imgUrl,
        statusType, photoStatusType, priceStatusType, mediaStatusType,
        splitImages, normalizeObject, normalizeCheckResult,
        checkSummaryEntries, recycleCheckImages, assetImages, priceCompareImages, deviceSpecText,
        canConfirmPhotos, canUploadMedia, canPrice, nextActionLabel,
        expandedSummaryKeys, isLongSummary, toggleSummary
    }
}
