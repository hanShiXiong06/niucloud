/** Keep the framework DIY data identity, visibility and content intact. */
export function applyCooperationDiyTitle(data: any): void {
    const global = data?.global
    const header = global?.topStatusBar
    if (!header || typeof header !== 'object' || Array.isArray(header)) return
    global.topStatusBar = {
        ...header,
        titleFontSize: bounded(header.titleFontSize, 12, 32, 20),
        titleFontWeight: Number.isInteger(Number(header.titleFontWeight)) && Number(header.titleFontWeight) % 100 === 0 ? bounded(header.titleFontWeight, 100, 900, 700) : 700,
        titleLineHeight: bounded(header.titleLineHeight, 16, 48, 28),
        h5Visible: typeof header.h5Visible === 'boolean' ? header.h5Visible : true,
        h5Height: bounded(header.h5Height, 32, 80, 44),
    }
}
function bounded(value: unknown, min: number, max: number, fallback: number): number {
    const number = typeof value === 'number' || typeof value === 'string' && value.trim() !== '' ? Number(value) : NaN
    return Number.isFinite(number) && number >= min && number <= max ? number : fallback
}
