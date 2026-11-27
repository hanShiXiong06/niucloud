import { nextTick, ref } from 'vue'
import { onHide, onLoad, onPullDownRefresh, onReachBottom, onShow } from '@dcloudio/uni-app'
import { useShare } from '@/hooks/useShare'

/** Native pages share lifecycle wiring; the two managed role lists keep their own route role. */
export function useCooperationPage(defaultDirection: 'incoming' | 'outgoing' = 'outgoing', managedPaging = false) {
    const cooperationRef = ref<any>(null),
        collaborationId = ref(0),
        promotionInviter = ref(0),
        promotionRequested = ref(false),
        initialDirection = ref<'incoming' | 'outgoing'>(defaultDirection)
    useShare().onShareAppMessage()
    const positiveId = (value: unknown) => {
        const id = Number(value)
        return Number.isSafeInteger(id) && id > 0 && id <= 4294967295 ? id : 0
    }
    onLoad((options) => {
        // Each bottom-navigation list has one role. A query parameter must not
        // turn “找客户” into the same publishing page as “找门店”.
        if (!managedPaging && (options?.direction === 'incoming' || options?.direction === 'outgoing'))
            initialDirection.value = options.direction
        const id = positiveId(options?.collaboration_id)
        if (id) collaborationId.value = id
        if (String(options?.promotion_invite || '') === '1') {
            promotionRequested.value = true
            promotionInviter.value = positiveId(options?.mid)
        }
    })
    onShow(async () => {
        await nextTick()
        cooperationRef.value?.resume?.()
    })
    onHide(() => cooperationRef.value?.pause?.())
    if (!managedPaging) {
        onPullDownRefresh(async () => {
            try {
                await cooperationRef.value?.refresh?.()
            } finally {
                uni.stopPullDownRefresh()
            }
        })
        onReachBottom(() => cooperationRef.value?.loadMore?.())
    }
    return { cooperationRef, collaborationId, promotionInviter, promotionRequested, initialDirection }
}
