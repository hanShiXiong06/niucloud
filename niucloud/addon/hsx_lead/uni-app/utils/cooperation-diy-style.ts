/** Presentation JSON only; backgrounds, margins and outer corners remain owned by the framework DIY template. */
const color = (value: unknown, fallback: string) => {
    if (typeof value !== 'string' || value.length > 80) return fallback
    const text = value.trim()
    return /^(?:#[\da-f]{3}|#[\da-f]{4}|#[\da-f]{6}|#[\da-f]{8}|rgba?\([\d\s.,%]+\)|hsla?\([\d\s.,%]+\)|transparent)$/i.test(
        text
    )
        ? text
        : fallback
}
const pixels = (value: unknown, fallback: number, min: number, max: number) => {
    const number = value === '' || value === undefined || value === null ? fallback : Number(value)
    return `${Math.max(min, Math.min(max, Number.isFinite(number) ? Math.round(number) : fallback))}px`
}
/** Shared by home/profile renderers and their genuinely reusable entry cards. */
export function cooperationDiyVars(value: Record<string, any> = {}) {
    const primary = color(value.primaryColor, 'var(--primary-color, #ff4444)')
    return {
        '--lead-diy-primary': primary,
        '--lead-diy-title': color(value.titleColor, '#202124'),
        '--lead-diy-text': color(value.textColor, '#606266'),
        '--lead-diy-subtle': color(value.subtleColor, '#909399'),
        '--lead-diy-panel': color(value.panelColor, 'transparent'),
        '--lead-diy-icon': color(value.iconColor, '#333333'),
        '--lead-diy-border': color(value.borderColor, '#e4e8ef'),
        '--lead-diy-button': color(value.buttonColor, primary),
        '--lead-diy-button-text': color(value.buttonTextColor, '#ffffff'),
        '--lead-diy-title-size': pixels(value.titleSize, 18, 14, 28),
        '--lead-diy-text-size': pixels(value.textSize, 14, 12, 20),
        '--lead-diy-content-padding': pixels(value.contentPadding, 16, 0, 32),
        '--lead-diy-row-gap': pixels(value.rowGap, 12, 0, 32),
        '--lead-diy-item-radius': pixels(value.itemRadius, 8, 0, 24),
        '--lead-diy-button-radius': pixels(value.buttonRadius, 6, 0, 28)
    }
}
