import { nextTick, ref } from 'vue'
import { onHide, onLoad, onPullDownRefresh, onReachBottom, onShow } from '@dcloudio/uni-app'
import { useShare } from '@/hooks/useShare'

/** Four real pages share lifecycle wiring, never an in-page tab router. */
export function useCooperationPage() {
    const cooperationRef = ref<any>(null), collaborationId = ref(0), promotionInviter = ref(0), promotionRequested = ref(false)
    useShare().onShareAppMessage()
    const positiveId = (value: unknown) => { const id = Number(value); return Number.isSafeInteger(id) && id > 0 && id <= 4294967295 ? id : 0 }
    onLoad(options => {
        const id = positiveId(options?.collaboration_id)
        if (id) collaborationId.value = id
        if (String(options?.promotion_invite || '') === '1') { promotionRequested.value = true; promotionInviter.value = positiveId(options?.mid) }
    })
    onShow(async () => { await nextTick(); cooperationRef.value?.resume?.() })
    onHide(() => cooperationRef.value?.pause?.())
    onPullDownRefresh(async () => { try { await cooperationRef.value?.refresh?.() } finally { uni.stopPullDownRefresh() } })
    onReachBottom(() => cooperationRef.value?.loadMore?.())
    return { cooperationRef, collaborationId, promotionInviter, promotionRequested }
}
