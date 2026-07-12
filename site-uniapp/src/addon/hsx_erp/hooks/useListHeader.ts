import { computed, ref } from 'vue'
import { pxToRpx } from '@/utils/common'
import { getErpNavbarMetrics } from '@/addon/hsx_erp/utils/navbar'

export const useListHeader = (statusTabsHeight = 82, h5Top = 186) => {
    const isMp = ref(false)

    // #ifdef MP
    isMp.value = true
    // #endif

    const navbarMetrics = getErpNavbarMetrics()
    const navbarHeightPx = navbarMetrics.navbarHeightPx
    const navbarHeightRpx = pxToRpx(navbarHeightPx)

    const pageHeaderStyle = computed(() => isMp.value ? `top: ${ navbarHeightPx }px;` : '')
    const pagingTop = computed(() => `${ isMp.value ? navbarHeightRpx + statusTabsHeight : h5Top }rpx`)
    const pagingStyle = computed(() => ({ top: pagingTop.value }))

    return {
        isMp,
        navbarMetrics,
        navbarHeightPx,
        navbarHeightRpx,
        pageHeaderStyle,
        pagingTop,
        pagingStyle
    }
}
