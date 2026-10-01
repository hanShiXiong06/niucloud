import { ref } from 'vue'
import { onShow, onHide, onUnload } from '@dcloudio/uni-app'
import { goodsRecentTag } from '@/addon/phone_shop/utils/goods-recent-activity'

/** 一页共用一个时钟，校准服务器时间；离页停止，避免每张卡片创建定时器。 */
export function useGoodsRecentActivity() {
    const now = ref(Math.floor(Date.now() / 1000))
    let anchor = { server: now.value, local: Date.now() }
    let timer: ReturnType<typeof setInterval> | undefined
    const tick = () => { now.value = anchor.server + Math.max(0, Math.floor((Date.now() - anchor.local) / 1000)) }
    const stop = () => { if (timer !== undefined) clearInterval(timer); timer = undefined }
    const sync = (value: unknown) => {
        const server = Number(value)
        if (Number.isFinite(server) && server > 0) anchor = { server: Math.floor(server), local: Date.now() }
        tick()
    }
    onShow(() => { stop(); tick(); timer = setInterval(tick, 1000) })
    onHide(stop)
    onUnload(stop)
    return { now, sync, tag: (goods: Record<string, any>) => goodsRecentTag(goods, now.value) }
}
