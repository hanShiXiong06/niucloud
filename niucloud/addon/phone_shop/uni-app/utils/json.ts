/**
 * 兼容接口与 postMessage 返回 JSON 字符串或结构化对象两种形态。
 * 部分浏览器/小程序调试环境会自动对 postMessage 数据执行结构化克隆，
 * 此时再次 JSON.parse 会把对象转成 "[object Object]" 并抛出异常。
 */
export function parseJsonValue<T>(value: unknown, fallback: T): T {
    if (value !== null && typeof value === 'object') return value as T;
    if (typeof value !== 'string') return fallback;

    let current: unknown = value.trim();
    if (!current || current === '[object Object]') return fallback;

    // 同时兼容极少数被重复 JSON.stringify 的历史数据。
    for (let index = 0; index < 2 && typeof current === 'string'; index++) {
        const text = current.trim();
        if (!text || text === '[object Object]') return fallback;
        try {
            current = JSON.parse(text);
        } catch (error) {
            return fallback;
        }
    }

    return current !== null && typeof current === 'object' ? current as T : fallback;
}
