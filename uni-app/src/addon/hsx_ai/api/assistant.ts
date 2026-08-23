import request from '@/utils/request'
import { getAppChannel, getSiteId, getToken } from '@/utils/common'

export type AiAssistantEvent = { type: string; delta?: string; message?: string; items?: any[]; [key: string]: any }

export const getAiAssistantCapability = () => request.get('ai/assistant/capability', {}, { showErrorMessage: false })
export const getAiConversations = (params: Record<string, any> = {}) => request.get('ai/assistant/conversations', params, { showErrorMessage: false })
export const getAiConversationMessages = (conversationId: number, params: Record<string, any> = {}) => request.get(`ai/assistant/conversations/${conversationId}/messages`, params, { showErrorMessage: false })
export const archiveAiConversation = (conversationId: number) => request.post(`ai/assistant/conversations/${conversationId}/archive`, {}, { showErrorMessage: false })
export const chatWithAiAssistant = (data: Record<string, any>) => request.post('ai/assistant/chat', data, { showErrorMessage: false })
export const speechToText = (filePath: string) => request.upload('ai/assistant/speech/stt', { filePath, name: 'audio' }, { showErrorMessage: false })
export const textToSpeech = (text: string) => request.post('ai/assistant/speech/tts', { text }, { showErrorMessage: false })
export const getProjectAiCapability = (projectId: number, groupNo = '') => request.get(`ai/project-assistant/${projectId}/capability`, { group_no: groupNo }, { showErrorMessage: false })
export const chatWithProjectAiAssistant = (projectId: number, data: Record<string, any>) => request.post(`ai/project-assistant/${projectId}/chat`, data, { showErrorMessage: false })
export const projectAiTextToSpeech = (projectId: number, text: string, groupNo = '') => request.post(`ai/project-assistant/${projectId}/speech/tts`, { text, group_no: groupNo }, { showErrorMessage: false })

const apiUrl = (path: string) => {
    let base = String(import.meta.env.VITE_APP_BASE_URL || '').replace(/\/$/, '')
    // #ifdef H5
    if (!base) base = `${location.origin}/api`
    // #endif
    return `${base}/${path}`
}
const headers = () => {
    const value: Record<string, string> = { 'Content-Type': 'application/json', Accept: 'text/event-stream' }
    const token = getToken()
    if (token) value[import.meta.env.VITE_REQUEST_HEADER_TOKEN_KEY] = String(token)
    value[import.meta.env.VITE_REQUEST_HEADER_SITEID_KEY] = String(getSiteId(import.meta.env.VITE_SITE_ID || uni.getStorageSync('wap_site_id')))
    value[import.meta.env.VITE_REQUEST_HEADER_CHANNEL_KEY] = getAppChannel()
    return value
}

export const speechBlobToText = async (audio: Blob) => {
    const body = new FormData()
    body.append('audio', audio, 'voice.wav')
    const uploadHeaders = headers()
    delete uploadHeaders['Content-Type']
    uploadHeaders.Accept = 'application/json'
    const response = await fetch(apiUrl('ai/assistant/speech/stt'), {
        method: 'POST', headers: uploadHeaders, body, credentials: 'same-origin'
    })
    const result = await response.json()
    if (!response.ok || Number(result?.code) !== 1) throw new Error(result?.msg || `语音识别失败（HTTP ${response.status}）`)
    return result
}

const createParser = (onEvent: (event: AiAssistantEvent) => void) => {
    let buffer = ''
    let streamError = ''
    const consume = (block: string) => {
        const lines = block.split('\n').filter((line) => line.startsWith('data:')).map((line) => line.slice(5).trimStart())
        if (!lines.length) return
        const event = JSON.parse(lines.join('\n')) as AiAssistantEvent
        onEvent(event)
        if (event.type === 'error') streamError = event.message || 'AI 响应失败'
    }
    return {
        push(text: string) {
            buffer += text.replace(/\r\n/g, '\n')
            let index = buffer.indexOf('\n\n')
            while (index >= 0) {
                const block = buffer.slice(0, index)
                buffer = buffer.slice(index + 2)
                if (block.trim()) consume(block)
                index = buffer.indexOf('\n\n')
            }
        },
        finish() { if (buffer.trim()) consume(buffer); if (streamError) throw new Error(streamError) }
    }
}

const streamAssistantAt = async (path: string, data: Record<string, any>, onEvent: (event: AiAssistantEvent) => void, signal?: AbortSignal) => {
    // #ifdef H5
    const parser = createParser(onEvent)
    const response = await fetch(apiUrl(path), { method: 'POST', headers: headers(), body: JSON.stringify(data), signal })
    if (!response.ok || !response.body) throw new Error(`AI 流式请求失败（HTTP ${response.status}）`)
    const reader = response.body.getReader()
    const decoder = new TextDecoder('utf-8')
    while (true) {
        const { done, value } = await reader.read()
        parser.push(decoder.decode(value || new Uint8Array(), { stream: !done }))
        if (done) break
    }
    parser.finish()
    return
    // #endif

    // #ifdef MP-WEIXIN
    await new Promise<void>((resolve, reject) => {
        const parser = createParser(onEvent)
        const decoder = typeof TextDecoder !== 'undefined' ? new TextDecoder('utf-8') : null
        const task = wx.request({
            url: apiUrl(path), method: 'POST', header: headers(), data, enableChunked: true,
            success: () => { try { parser.finish(); resolve() } catch (error) { reject(error) } },
            fail: reject
        } as any)
        if (signal) signal.addEventListener('abort', () => task.abort(), { once: true })
        task.onChunkReceived((chunk: any) => {
            const bytes = new Uint8Array(chunk.data)
            parser.push(decoder ? decoder.decode(bytes, { stream: true }) : decodeURIComponent(Array.from(bytes).map((value) => `%${value.toString(16).padStart(2, '0')}`).join('')))
        })
    })
    return
    // #endif

    // #ifdef APP-PLUS
    if (signal?.aborted) { const error = new Error('Aborted'); error.name = 'AbortError'; throw error }
    const response: any = await request.post(path.replace(/\/stream$/, '/chat'), data, { showErrorMessage: false })
    const result = response?.data || {}
    if (result.resources?.length) onEvent({ type: 'resources', items: result.resources })
    if (result.blocks?.length) onEvent({ type: 'blocks', items: result.blocks })
    onEvent({ type: 'content', delta: result.content || '' })
    onEvent({ type: 'done', ...result })
    // #endif
}

export const streamAiAssistant = (data: Record<string, any>, onEvent: (event: AiAssistantEvent) => void, signal?: AbortSignal) => streamAssistantAt('ai/assistant/stream', data, onEvent, signal)
export const streamProjectAiAssistant = (projectId: number, data: Record<string, any>, onEvent: (event: AiAssistantEvent) => void, signal?: AbortSignal) => streamAssistantAt(`ai/project-assistant/${projectId}/stream`, data, onEvent, signal)
