/** Short-lived public presentation projection only; never retain bootstrap data. */
type BusinessEntry = { key: string; name: string }
type BusinessReader = () => Promise<any>
let owner = '',
    pending: Promise<BusinessEntry[]> | null = null,
    rows: BusinessEntry[] = [],
    loadedAt = 0,
    generation = 0
function copy(items: BusinessEntry[]): BusinessEntry[] {
    return items.map((item) => ({ key: item.key, name: item.name }))
}
export function clearCooperationBusinessCache() {
    owner = ''
    pending = null
    rows = []
    loadedAt = 0
    generation++
}
export function readCooperationBusinessEntries(identity: string, read: BusinessReader): Promise<BusinessEntry[]> {
    if (!identity) {
        clearCooperationBusinessCache()
        return Promise.resolve([])
    }
    if (owner !== identity) {
        clearCooperationBusinessCache()
        owner = identity
    }
    if (pending) return pending.then(copy)
    if (loadedAt && Date.now() - loadedAt < 1000) return Promise.resolve(copy(rows))
    const turn = generation
    let request: Promise<any>
    try {
        request = read()
    } catch (error) {
        return Promise.reject(error)
    }
    const work = Promise.resolve(request)
        .then((result) => {
            const list = result?.data?.config?.businesses
            if (!Array.isArray(list)) throw new Error('业务入口返回格式不完整，请重新加载')
            const projected = list
                .filter(
                    (item) =>
                        item &&
                        [true, 1, '1'].includes(item.enabled) &&
                        typeof item.key === 'string' &&
                        typeof item.name === 'string'
                )
                .map((item) => ({ key: item.key, name: item.name }))
            if (turn === generation && owner === identity) {
                rows = projected
                loadedAt = Date.now()
            }
            return projected
        })
        .finally(() => {
            if (pending === work) pending = null
        })
    pending = work
    return work.then(copy)
}
