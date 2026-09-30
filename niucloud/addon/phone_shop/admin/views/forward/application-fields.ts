export interface ApplicationField {
    id?: number | string
    field_key?: string
    field_name?: string
    field_type?: string
    handle_field_value?: unknown
    field_value?: unknown
    render_value?: unknown
}

function parseValue(value: unknown): unknown {
    // 表单接口同时提供原始 JSON 字符串和转换后的数组；不要渲染为 JSON 文本。
    for (let depth = 0; depth < 2 && typeof value === 'string'; depth++) {
        const text = value.trim()
        if (!text || !['[', '{', '"'].includes(text[0])) break
        try { value = JSON.parse(text) } catch { break }
    }
    return value
}

function hasValue(value: unknown): boolean {
    return value !== null && value !== undefined && value !== '' &&
        (!Array.isArray(value) || value.length > 0)
}

function firstValue(...values: unknown[]): unknown {
    return values.map(parseValue).find(hasValue)
}

function imagePaths(value: unknown): string[] {
    const parsed = parseValue(value)
    if (Array.isArray(parsed)) return parsed.flatMap(imagePaths)
    if (parsed && typeof parsed === 'object') {
        const file = parsed as Record<string, unknown>
        return imagePaths(file.url ?? file.path ?? file.src)
    }
    if (typeof parsed !== 'string') return []
    const path = parsed.trim()
    // 只接受上传路径或 HTTP(S) 图片，不把脚本、HTML 或任意字段当成图片。
    return /^(https?:\/\/|\/(?!\/)|(?:upload|attachment|addon|static)\/)/i.test(path) &&
        !/[<>\u0000-\u001f]/.test(path) ? [path] : []
}

function textValue(value: unknown): string {
    const parsed = parseValue(value)
    if (!hasValue(parsed)) return ''
    if (Array.isArray(parsed)) return parsed.map(textValue).filter(Boolean).join('、')
    if (typeof parsed === 'boolean') return parsed ? '是' : '否'
    if (typeof parsed === 'object') {
        const option = parsed as Record<string, unknown>
        for (const key of ['text', 'label', 'name', 'title']) {
            if (hasValue(option[key])) return textValue(option[key])
        }
        return JSON.stringify(parsed, null, 2)
    }
    return String(parsed)
}

export function displayApplicationField(field: ApplicationField) {
    const isImage = String(field.field_type || '').toLowerCase() === 'formimage'
    return {
        key: field.id ?? field.field_key,
        label: field.field_name || '未命名资料',
        isImage,
        images: isImage ? [...new Set(imagePaths(firstValue(field.handle_field_value, field.field_value)))] : [],
        text: isImage ? '' : textValue(firstValue(field.render_value, field.handle_field_value, field.field_value)) || '未填写'
    }
}
