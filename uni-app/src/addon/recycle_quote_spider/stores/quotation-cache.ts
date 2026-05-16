import { defineStore } from 'pinia'

interface CacheEntry<T = any> {
    data: T
    timestamp: number
}

interface QuotationCacheState {
    entries: Record<string, CacheEntry>
}

const CACHE_TTL = 3 * 60 * 1000

function normalizeParams(params: Record<string, any> = {}) {
    const result: Record<string, any> = {}
    Object.keys(params)
        .sort()
        .forEach((key) => {
            const value = params[key]
            if (value === undefined || value === null || value === '') return
            result[key] = value
        })
    return result
}

export function buildQuotationCacheKey(scope: string, params: Record<string, any> = {}) {
    return `${ scope }:${ JSON.stringify(normalizeParams(params)) }`
}

const useQuotationCacheStore = defineStore('recycleQuoteSpiderQuotationCache', {
    state: (): QuotationCacheState => ({
        entries: {}
    }),
    actions: {
        get<T = any>(key: string, ttl = CACHE_TTL): T | null {
            const entry = this.entries[key]
            if (!entry) return null
            if (Date.now() - entry.timestamp > ttl) {
                delete this.entries[key]
                return null
            }
            return entry.data as T
        },
        set<T = any>(key: string, data: T) {
            this.entries[key] = {
                data,
                timestamp: Date.now()
            }
        },
        remove(key: string) {
            delete this.entries[key]
        },
        clearScope(scope: string) {
            const prefix = `${ scope }:`
            Object.keys(this.entries).forEach((key) => {
                if (key.startsWith(prefix)) delete this.entries[key]
            })
        }
    }
})

export default useQuotationCacheStore
