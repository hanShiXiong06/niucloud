/**
 * 解析接口或 postMessage 中的结构化 JSON 数据。
 * 放在主包，公共装修入口不能反向依赖任何插件分包。
 */
export function parseJsonValue<T>(value: unknown, fallback: T): T {
    if (value !== null && typeof value === 'object') return value as T;
    if (typeof value !== 'string') return fallback;

    let current: unknown = value.trim();
    if (!current || current === '[object Object]') return fallback;

    for (let index = 0; index < 2 && typeof current === 'string'; index++) {
        const text = current.trim();
        if (!text || text === '[object Object]') return fallback;
        try {
            current = JSON.parse(text);
        } catch {
            return fallback;
        }
    }

    return current !== null && typeof current === 'object' ? current as T : fallback;
}
