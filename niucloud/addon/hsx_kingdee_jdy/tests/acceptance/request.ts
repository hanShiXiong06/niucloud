import { ElMessage } from 'element-plus'

async function send(method: string, path: string, data?: any, params?: Record<string, any>) {
    const url = new URL('/api/' + path, window.location.origin)
    Object.entries(params || {}).forEach(([key, value]) => url.searchParams.set(key, String(value)))
    try {
        const response = await fetch(url, { method, headers: { 'Content-Type': 'application/json', 'X-Acceptance-UI': '1' }, body: method === 'POST' ? JSON.stringify(data || {}) : undefined })
        const result = await response.json()
        if (!response.ok || result.code !== 1) throw new Error(result.msg || '本地服务暂不可用')
        return result
    } catch (error) { ElMessage.error(error instanceof Error ? error.message : '本地请求失败'); throw error }
}
export default {
    get: (path: string, options?: { params?: Record<string, any> }) => send('GET', path, undefined, options?.params),
    post: (path: string, data?: any) => send('POST', path, data)
}
