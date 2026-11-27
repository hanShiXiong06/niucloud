export interface HsxCacheOptions {
    namespace?: string
    version?: string
    defaultTtl?: number
    storage?: 'local' | 'session' | 'memory'
}

export interface HsxCacheSetOptions { ttl?: number }
export interface HsxCacheRememberOptions extends HsxCacheSetOptions { force?: boolean }

interface CacheEntry<T> {
    version: string
    createdAt: number
    expiresAt: number
    value: T
}

const memoryStorage = new Map<string, string>()

export function createHsxCache(options: HsxCacheOptions = {}) {
    const namespace = options.namespace || 'default'
    const version = options.version || '1'
    const defaultTtl = Math.max(0, options.defaultTtl || 0)
    const storageType = options.storage || 'local'
    const prefix = `hsx:${namespace}:`
    const inflight = new Map<string, Promise<any>>()

    function webStorage(): Storage | undefined {
        if (typeof window === 'undefined') return undefined
        try { return storageType === 'session' ? window.sessionStorage : window.localStorage } catch (_) { return undefined }
    }
    function readRaw(key: string) { return storageType === 'memory' ? memoryStorage.get(key) : webStorage()?.getItem(key) ?? memoryStorage.get(key) }
    function writeRaw(key: string, value: string) {
        if (storageType === 'memory') memoryStorage.set(key, value)
        else {
            try {
                const storage = webStorage()
                if (storage) storage.setItem(key, value)
                else memoryStorage.set(key, value)
            } catch (_) { memoryStorage.set(key, value) }
        }
    }
    function removeRaw(key: string) { memoryStorage.delete(key); try { webStorage()?.removeItem(key) } catch (_) {} }
    function fullKey(key: string) { return `${prefix}${key}` }

    function get<T>(key: string, fallback?: T): T | undefined {
        const raw = readRaw(fullKey(key))
        if (!raw) return fallback
        try {
            const entry = JSON.parse(raw) as CacheEntry<T>
            if (entry.version !== version || (entry.expiresAt > 0 && entry.expiresAt <= Date.now())) {
                removeRaw(fullKey(key))
                return fallback
            }
            return entry.value
        } catch (_) {
            removeRaw(fullKey(key))
            return fallback
        }
    }

    function set<T>(key: string, value: T, setOptions: HsxCacheSetOptions = {}) {
        const ttl = Math.max(0, setOptions.ttl ?? defaultTtl)
        const now = Date.now()
        writeRaw(fullKey(key), JSON.stringify({ version, createdAt: now, expiresAt: ttl ? now + ttl : 0, value } satisfies CacheEntry<T>))
        return value
    }

    function remove(key: string) { removeRaw(fullKey(key)); inflight.delete(key) }
    function has(key: string) { return get(key, undefined) !== undefined }
    function keys() {
        const result = new Set<string>()
        memoryStorage.forEach((_, key) => key.startsWith(prefix) && result.add(key.slice(prefix.length)))
        const storage = webStorage()
        if (storage) for (let index = 0; index < storage.length; index += 1) {
            const key = storage.key(index)
            if (key?.startsWith(prefix)) result.add(key.slice(prefix.length))
        }
        return [...result]
    }
    function clear() { keys().forEach(remove) }

    async function remember<T>(key: string, loader: () => Promise<T>, rememberOptions: HsxCacheRememberOptions = {}): Promise<T> {
        if (!rememberOptions.force) {
            const cached = get<T>(key)
            if (cached !== undefined) return cached
            const pending = inflight.get(key)
            if (pending) return pending
        }
        const task = Promise.resolve().then(loader).then((value) => set(key, value, rememberOptions)).finally(() => inflight.delete(key))
        inflight.set(key, task)
        return task
    }

    return { namespace, version, get, set, has, remove, clear, keys, remember }
}
