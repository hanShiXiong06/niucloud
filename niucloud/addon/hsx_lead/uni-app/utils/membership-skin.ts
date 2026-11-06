/** Display only. Never grants membership, changes ranking or determines a fee. */
export const membershipSkinPresets = {
    neutral: { start: '#F4F5F7', end: '#E9ECF0', text: '#334155', accent: '#64748B' },
    bronze: { start: '#F3E8D9', end: '#DDC2A0', text: '#64452F', accent: '#A47743' },
    silver: { start: '#F4F6FA', end: '#D9E0EA', text: '#435269', accent: '#7C8EA5' },
    gold: { start: '#FFF1C8', end: '#E5C581', text: '#6E4C18', accent: '#AF7B25' },
    sapphire: { start: '#EAF1FE', end: '#AFC8F0', text: '#264D7F', accent: '#3769AD' },
    amethyst: { start: '#F0EAF9', end: '#CDBBE5', text: '#65477F', accent: '#8665A9' },
    obsidian: { start: '#354153', end: '#192230', text: '#F4E6BC', accent: '#C9A967' },
} as const

function hex(value: unknown, fallback: string): string {
    return typeof value === 'string' && /^#(?:[a-f\d]{3}|[a-f\d]{6})$/i.test(value) ? value : fallback
}

function imagePath(value: unknown): string {
    if (typeof value !== 'string' || value.length > 1000 || /[\x00-\x20'"<>\\]/.test(value)) return ''
    if (value === '') return ''
    if (/^https?:\/\//i.test(value)) return value
    return !/^(?:[a-z][a-z\d+.-]*:|\/\/)/i.test(value) && !value.split(/[/?#]/).includes('..') ? value : ''
}

export function membershipSkin(value: any = {}, rank = 0) {
    const data = value && typeof value === 'object' && !Array.isArray(value) ? value : {}
    const automatic = ['neutral', 'bronze', 'silver', 'gold', 'sapphire', 'amethyst', 'obsidian'][Math.min(6, Math.max(0, Number.isSafeInteger(rank) ? rank : 0))]
    const requested = typeof data.preset === 'string' ? data.preset : 'auto'
    const preset = Object.prototype.hasOwnProperty.call(membershipSkinPresets, requested) ? requested : automatic
    const base = membershipSkinPresets[preset as keyof typeof membershipSkinPresets]
    const start = hex(data.start_color, base.start), end = hex(data.end_color, base.end)
    const text = hex(data.text_color, base.text), accent = hex(data.accent_color, base.accent)
    const candidate = Number(data.angle)
    const angle = data.angle !== undefined && Number.isFinite(candidate) ? Math.max(0, Math.min(360, candidate)) : 135
    return { preset: requested === 'custom' ? 'custom' : preset, start, end, text, accent, angle,
        gradient: `linear-gradient(${angle}deg,${start},${end})`, cardImage: imagePath(data.card_image), avatarEffect: data.avatar_effect === undefined ? true : data.avatar_effect === true }
}

export function membershipSkinVars(value: any = {}, rank = 0) {
    const skin = membershipSkin(value, rank)
    return { '--lead-member-start': skin.start, '--lead-member-end': skin.end,
        '--lead-member-text': skin.text, '--lead-member-accent': skin.accent, '--lead-member-gradient': skin.gradient }
}
