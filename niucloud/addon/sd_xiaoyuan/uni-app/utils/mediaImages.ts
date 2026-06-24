import { img } from '@/utils/common'

const PLACEHOLDER = '/static/resource/images/empty.png'

function pushOne(out: string[], v: any) {
    if (v == null) return
    if (typeof v === 'string') {
        const s = v.trim()
        if (s) out.push(s)
        return
    }
    if (typeof v === 'object' && (v as any).url) {
        const s = String((v as any).url).trim()
        if (s) out.push(s)
        return
    }
}

function walk(out: string[], raw: any) {
    if (raw == null || raw === '') return
    if (Array.isArray(raw)) {
        raw.forEach((x) => walk(out, x))
        return
    }
    if (typeof raw === 'object') {
        pushOne(out, raw)
        return
    }
    if (typeof raw === 'string') {
        const t = raw.trim()
        if (!t) return
        if (t.startsWith('[') || t.startsWith('{')) {
            try {
                walk(out, JSON.parse(t))
                return
            } catch {
                //
            }
        }
        t.split(',').forEach((x) => pushOne(out, x.trim()))
    }
}

export function normalizeMediaImages(images: any): string[] {
    const out: string[] = []
    walk(out, images)
    return out
}

export function firstMediaImageSrc(images: any): string {
    const list = normalizeMediaImages(images)
    const u = list[0]
    return u ? img(u) : img(PLACEHOLDER)
}
