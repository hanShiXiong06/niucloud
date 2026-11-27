export interface HsxCacheOptions { namespace?: string, version?: string, defaultTtl?: number }
export interface HsxCacheRememberOptions { ttl?: number, force?: boolean }
interface CacheEntry<T> { version: string, createdAt: number, expiresAt: number, value: T }
const memory = new Map<string, any>()

export function createHsxCache(options: HsxCacheOptions = {}) {
    const namespace = options.namespace || 'default'
    const version = options.version || '1'
    const defaultTtl = Math.max(0, options.defaultTtl || 0)
    const prefix = `hsx:${namespace}:`
    const inflight = new Map<string, Promise<any>>()
    const fullKey = (key: string) => `${prefix}${key}`
    function read(key: string) { try { const value = uni.getStorageSync(key); return value === '' || value === undefined ? memory.get(key) : value } catch (_) { return memory.get(key) } }
    function write(key: string, value: any) { try { uni.setStorageSync(key, value) } catch (_) { memory.set(key, value) } }
    function removeRaw(key: string) { memory.delete(key); try { uni.removeStorageSync(key) } catch (_) {} }
    function get<T>(key: string, fallback?: T): T | undefined {
        const entry = read(fullKey(key)) as CacheEntry<T> | undefined
        if (!entry || typeof entry !== 'object') return fallback
        if (entry.version !== version || (entry.expiresAt > 0 && entry.expiresAt <= Date.now())) { removeRaw(fullKey(key)); return fallback }
        return entry.value
    }
    function set<T>(key: string, value: T, setOptions: { ttl?: number } = {}) {
        const ttl = Math.max(0, setOptions.ttl ?? defaultTtl); const now = Date.now()
        write(fullKey(key), { version, createdAt: now, expiresAt: ttl ? now + ttl : 0, value } satisfies CacheEntry<T>)
        return value
    }
    function remove(key: string) { removeRaw(fullKey(key)); inflight.delete(key) }
    function has(key: string) { return get(key, undefined) !== undefined }
    function keys() {
        const result = new Set<string>()
        memory.forEach((_, key) => key.startsWith(prefix) && result.add(key.slice(prefix.length)))
        try { uni.getStorageInfoSync().keys.forEach((key) => key.startsWith(prefix) && result.add(key.slice(prefix.length))) } catch (_) {}
        return [...result]
    }
    function clear() { keys().forEach(remove) }
    async function remember<T>(key: string, loader: () => Promise<T>, rememberOptions: HsxCacheRememberOptions = {}): Promise<T> {
        if (!rememberOptions.force) {
            const cached = get<T>(key); if (cached !== undefined) return cached
            const pending = inflight.get(key); if (pending) return pending
        }
        const task = Promise.resolve().then(loader).then((value) => set(key, value, rememberOptions)).finally(() => inflight.delete(key))
        inflight.set(key, task); return task
    }
    return { namespace, version, get, set, has, remove, clear, keys, remember }
}

