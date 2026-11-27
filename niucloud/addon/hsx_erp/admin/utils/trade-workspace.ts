/** Only nonsensitive directory data lives in this per-tab cache. Never pass a transaction, balance, price or inventory query here. */
export type TradeDirectory = 'warehouse-options' | 'spec-options' | 'dicts'
export function tradeScope(site: unknown, uid: unknown) {
    return Number(site) > 0 && Number(uid) > 0 ? Number(site) + ':' + Number(uid) : ''
}
export function makeTradeDirectoryCache(ttl = 60_000, now = () => Date.now()) {
    const map = new Map<string, { until: number; pending: boolean; value: Promise<any> }>()
    return {
        read<T>(scope: string, kind: TradeDirectory, query: () => Promise<T>): Promise<T> {
            if (!scope) return query()
            const key = scope + '/' + kind, old = map.get(key)
            if (old && (old.pending || old.until > now())) return old.value
            for (const [k, v] of map) if (!v.pending && v.until <= now()) map.delete(k)
            const item = { pending: true, until: 0, value: Promise.resolve().then(query) }
            map.set(key, item)
            item.value.then(() => { item.pending = false; item.until = now() + ttl }, () => { if (map.get(key) === item) map.delete(key) })
            return item.value
        },
        clear(scope: string) { if (scope) for (const key of map.keys()) if (key.startsWith(scope + '/')) map.delete(key) }
    }
}
export const tradeDirectory = makeTradeDirectoryCache()
type LocalPreference = Pick<Storage, 'getItem' | 'setItem'>
export function tradePreferences(storage: LocalPreference | undefined, scope: string, page: string) {
    const key = 'hsx_erp:trade-view:v1:' + scope + ':' + page + ':'
    return {
        get(which: 'filters'|'summary') { try { return Boolean(scope && storage?.getItem(key + which) === '1') } catch { return false } },
        set(which: 'filters'|'summary', open: boolean) { try { if (scope) storage?.setItem(key + which, open ? '1' : '0') } catch { /* View still works without browser storage. */ } }
    }
}
