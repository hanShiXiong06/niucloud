import request from '@/utils/request'
import { getToken } from '@/utils/common'
import storage from '@/utils/storage'

export const getAiSection = (section: string) => request.get(section === 'playground' ? 'ai/playground/config' : `ai/config/${section}`)
export const saveAiSection = (section: string, data: Record<string, any>) => request.post(`ai/config/${section}`, data)
export const testAiSpeech = (speech: Record<string, any>) => request.post('ai/speech/test', { speech })
export const testAiProvider = (provider: Record<string, any>) => request.post('ai/provider/test', { provider })
export const syncAiModels = (provider: Record<string, any>) => request.post('ai/provider/models', { provider })
export const executeAi = (data: Record<string, any>) => request.post('ai/execute', data)
export const getAiLogs = (params: Record<string, any>) => request.get('ai/logs', { params })
export const recognizePlaygroundSpeech = (audio: Blob) => {
    const formData = new FormData()
    formData.append('audio', audio, 'hsx_ai_recording.wav')
    return request.post('ai/playground/speech/stt', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
    })
}
export const synthesizePlaygroundSpeech = (text: string) => request.post('ai/playground/speech/tts', { text })

export type AiStreamEvent = {
    type: 'meta' | 'reasoning' | 'content' | 'done' | 'error'
    delta?: string
    message?: string
    [key: string]: any
}

export const streamAi = async (
    data: Record<string, any>,
    onEvent: (event: AiStreamEvent) => void,
    signal?: AbortSignal
) => {
    const configuredBase = String(import.meta.env.VITE_APP_BASE_URL || '').replace(/\/$/, '')
    const url = `${configuredBase}/ai/stream`
    const headers: Record<string, string> = {
        'Content-Type': 'application/json',
        Accept: 'text/event-stream',
        lang: storage.get('lang') ?? 'zh-cn'
    }
    const token = getToken()
    if (token) headers[import.meta.env.VITE_REQUEST_HEADER_TOKEN_KEY] = String(token)
    headers[import.meta.env.VITE_REQUEST_HEADER_SITEID_KEY] = String(storage.get('siteId') || 0)
    const response = await fetch(url, { method: 'POST', headers, body: JSON.stringify(data), signal })
    if (!response.ok || !response.body) {
        const text = await response.text()
        let message = text || `流式请求失败（HTTP ${response.status}）`
        try {
            const parsed = JSON.parse(text)
            message = parsed.msg || parsed.message || message
        } catch (_) {}
        throw new Error(message)
    }

    const reader = response.body.getReader()
    const decoder = new TextDecoder('utf-8')
    let buffer = ''
    let streamError = ''
    const consume = (block: string) => {
        const dataLines = block.split('\n')
            .filter((line) => line.startsWith('data:'))
            .map((line) => line.slice(5).trimStart())
        if (!dataLines.length) return
        const event = JSON.parse(dataLines.join('\n')) as AiStreamEvent
        onEvent(event)
        if (event.type === 'error') streamError = event.message || '模型流式调用失败'
    }
    while (true) {
        const { done, value } = await reader.read()
        buffer += decoder.decode(value || new Uint8Array(), { stream: !done }).replace(/\r\n/g, '\n')
        let separator = buffer.indexOf('\n\n')
        while (separator >= 0) {
            const block = buffer.slice(0, separator)
            buffer = buffer.slice(separator + 2)
            if (block.trim()) consume(block)
            separator = buffer.indexOf('\n\n')
        }
        if (done) break
    }
    if (buffer.trim()) consume(buffer)
    if (streamError) throw new Error(streamError)
}
