import { getToken } from '@/utils/common'
import storage from '@/utils/storage'

export interface StreamHandlers {
    onStatus?: (text: string) => void
    onDelta?: (text: string) => void
    onDone?: (data: any) => void
    onError?: (msg: string) => void
}

/**
 * 以 SSE 方式调用 erp/ai/stream。
 * 后端事件：status(进度) / delta(逐字答案) / done(最终) / error / end
 */
export async function streamAi(
    payload: Record<string, any>,
    handlers: StreamHandlers,
    signal?: AbortSignal,
) {
    let base = (import.meta.env.VITE_APP_BASE_URL as string) || ''
    if (!base.endsWith('/')) base += '/'

    const headers: Record<string, string> = { 'Content-Type': 'application/json' }
    const token = getToken()
    if (token) headers[import.meta.env.VITE_REQUEST_HEADER_TOKEN_KEY as string] = token
    headers[import.meta.env.VITE_REQUEST_HEADER_SITEID_KEY as string] = String(storage.get('siteId') || 0)
    headers['lang'] = storage.get('lang') ?? 'zh-cn'

    let res: Response
    try {
        res = await fetch(base + 'erp/ai/stream', {
            method: 'POST',
            headers,
            body: JSON.stringify(payload),
            signal,
        })
    } catch (e: any) {
        if (e?.name !== 'AbortError') handlers.onError?.('网络连接失败')
        return
    }

    if (!res.ok || !res.body) {
        handlers.onError?.('请求失败（' + res.status + '）')
        return
    }

    const reader = res.body.getReader()
    const decoder = new TextDecoder('utf-8')
    let buf = ''

    while (true) {
        let chunk: ReadableStreamReadResult<Uint8Array>
        try {
            chunk = await reader.read()
        } catch (e: any) {
            if (e?.name !== 'AbortError') handlers.onError?.('连接中断')
            return
        }
        if (chunk.done) break
        buf += decoder.decode(chunk.value, { stream: true })

        let sep: number
        while ((sep = buf.indexOf('\n\n')) !== -1) {
            const block = buf.slice(0, sep)
            buf = buf.slice(sep + 2)

            let event = 'message'
            let data = ''
            for (const line of block.split('\n')) {
                if (line.startsWith('event:')) event = line.slice(6).trim()
                else if (line.startsWith('data:')) data += line.slice(5).trim()
            }
            if (!data) continue

            let parsed: any = data
            try { parsed = JSON.parse(data) } catch { /* 保留原文 */ }

            if (event === 'status') handlers.onStatus?.(parsed?.text ?? '')
            else if (event === 'delta') handlers.onDelta?.(typeof parsed === 'string' ? parsed : (parsed?.text ?? ''))
            else if (event === 'done') handlers.onDone?.(parsed)
            else if (event === 'error') handlers.onError?.(parsed?.message ?? '出错了')
        }
    }
}
