/** Keep the framework DIY data identity, visibility and content intact. */
export function applyCooperationDiyTitle(data: any): void {
    const global = data?.global
    const header = global?.topStatusBar
    if (!header || typeof header !== 'object' || Array.isArray(header)) return
    global.topStatusBar = {
        ...header,
        titleFontSize: 20,
        titleFontWeight: 700,
        titleLineHeight: 28,
        h5Visible: true,
        h5Height: 44,
    }
}
