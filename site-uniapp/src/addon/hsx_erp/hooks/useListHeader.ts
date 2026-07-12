import { computed, ref } from 'vue'
import { getErpListHeaderHeightRpx } from '@/addon/hsx_erp/utils/navbar'

type ListHeaderOptions = {
    title?: boolean
    search?: boolean
    tabs?: boolean
    h5TopRpx?: number
}

export const useListHeader = (options: ListHeaderOptions | number = {}, legacyH5Top = 186) => {
    const isMp = ref(false)

    // #ifdef MP
    isMp.value = true
    // #endif

    const normalized = typeof options === 'number'
        ? { title: false, search: true, tabs: true, mpTopRpx: options, h5TopRpx: legacyH5Top }
        : { title: false, search: true, tabs: true, h5TopRpx: 186, ...options }
    const mpTopRpx = 'mpTopRpx' in normalized
        ? Number(normalized.mpTopRpx || 0)
        : getErpListHeaderHeightRpx(normalized)
    // 小程序 viewport 已位于原生导航栏下方，此处只计算列表工具区，不能再加 navbarHeightRpx。
    const pagingTop = computed(() => `${ isMp.value ? mpTopRpx : Number(normalized.h5TopRpx || mpTopRpx) }rpx`)
    const pagingStyle = computed(() => ({ top: pagingTop.value }))

    return {
        isMp,
        pagingTop,
        pagingStyle
    }
}
