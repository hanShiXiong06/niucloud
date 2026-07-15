import { computed, ref } from 'vue'
import { pxToRpx } from '@/utils/common'
import { getErpListHeaderHeightRpx, getErpNavbarMetrics } from '@/addon/hsx_erp/utils/navbar'

type ListHeaderOptions = {
    title?: boolean
    search?: boolean
    tabs?: boolean
    h5TopRpx?: number
    compactMp?: boolean
    compactToolsHeightRpx?: number
}

export const useListHeader = (options: ListHeaderOptions | number = {}, legacyH5Top = 186) => {
    const isMp = ref(false)

    // #ifdef MP
    isMp.value = true
    // #endif

    const normalized: ListHeaderOptions & { mpTopRpx?: number } = typeof options === 'number'
        ? { title: false, search: true, tabs: true, mpTopRpx: options, h5TopRpx: legacyH5Top }
        : { title: false, search: true, tabs: true, h5TopRpx: 186, compactMp: false, compactToolsHeightRpx: 74, ...options }
    const compactMpTopRpx = pxToRpx(getErpNavbarMetrics().navbarHeightPx) + Number(normalized.compactToolsHeightRpx || 74)
    const mpTopRpx = normalized.compactMp
        ? compactMpTopRpx
        : 'mpTopRpx' in normalized
        ? Number(normalized.mpTopRpx || 0)
        : getErpListHeaderHeightRpx(normalized)
    // 普通模式的 viewport 已位于原生导航栏下；紧凑模式使用自定义导航，需要把导航栏高度计入列表 top。
    const pagingTop = computed(() => `${ isMp.value ? mpTopRpx : Number(normalized.h5TopRpx || mpTopRpx) }rpx`)
    const pagingStyle = computed(() => ({ top: pagingTop.value }))

    return {
        isMp,
        pagingTop,
        pagingStyle
    }
}
