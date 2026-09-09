import { ref } from 'vue'
import { onShow, onUnload } from '@dcloudio/uni-app'
import { memberCardVersion, errorText } from '../utils/presentation'

/** 普通返回保留列表和位置；插件内写操作成功后，回到列表刷新已加载页。 */
export function useMemberCardList(fetcher: (params: any) => Promise<any>, params: () => Record<string, any>) {
    const paging = ref<any>()
    const rows = ref<any[]>([])
    const total = ref(0)
    const listError = ref('')
    let loaded = false
    let seen = memberCardVersion()
    let sequence = 0
    const query = async (page: number, limit: number) => {
        const ticket = ++sequence
        const version = memberCardVersion()
        listError.value = ''
        try {
            // z-paging 保位刷新会一次请求所有已加载行；接口单页最多 100 条，分段取齐。
            const filters = { ...params() }
            const chunkSize = limit > 100 ? 100 : limit
            const offset = (page - 1) * limit
            const withinChunk = offset % chunkSize
            let data: any[] = []
            let count = 0
            for (let index = 0; index < Math.ceil((withinChunk + limit) / chunkSize); index++) {
                const result = await fetcher({
                    ...filters,
                    page: Math.floor(offset / chunkSize) + index + 1,
                    limit: chunkSize
                })
                if (ticket !== sequence) return
                count = Number(result?.data?.total || 0)
                const part = result?.data?.data || []
                data = data.concat(part)
                if (part.length < chunkSize || offset - withinChunk + data.length >= count) break
            }
            if (ticket !== sequence) return
            total.value = count
            paging.value?.complete(data.slice(withinChunk, withinChunk + limit))
            loaded = true
            seen = version
        } catch (e) {
            if (ticket === sequence) {
                listError.value = errorText(e, '列表加载失败，请重试')
                paging.value?.complete(false)
            }
        }
    }
    const reload = () => {
        paging.value?.reload()?.catch?.(() => {})
    }
    const refresh = () => {
        paging.value?.refresh()?.catch?.(() => {})
    }
    onShow(() => {
        if (loaded && seen !== memberCardVersion()) refresh()
    })
    onUnload(() => {
        sequence++
    })
    return { paging, rows, total, listError, query, reload, refresh }
}
