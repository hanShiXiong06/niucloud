/** Native NiuCloud component skin. The group alone owns margins. */
type FrameSettings = Record<string, any>
type FrameStyle = Record<string, string | undefined>
type FrameImageResolver = (path: string) => string
type FrameResult = { style: FrameStyle; mask: FrameStyle; hasImage: boolean }

function radius(value: unknown): string {
    const pixels = Number(value)
    return (Number.isFinite(pixels) ? Math.max(0, pixels) : 0) * 2 + 'rpx'
}

function imageUrl(value: string): string {
    return value
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'")
        .replace(/[\r\n\f]/g, '')
}

export function cooperationDiyFrame(value: FrameSettings = {}, resolveImage?: FrameImageResolver): FrameResult {
    const start = typeof value.componentStartBgColor === 'string' ? value.componentStartBgColor.trim() : ''
    const end = typeof value.componentEndBgColor === 'string' ? value.componentEndBgColor.trim() : ''
    const angle =
        typeof value.componentGradientAngle === 'string' && value.componentGradientAngle.trim()
            ? value.componentGradientAngle.trim()
            : 'to bottom'
    const path = typeof value.componentBgUrl === 'string' ? value.componentBgUrl.trim() : ''
    const source = path ? (resolveImage ? resolveImage(path) : path) : ''
    const hasImage = typeof source === 'string' && source.length > 0
    const alphaValue = Number(value.componentBgAlpha)
    const alpha = (Number.isFinite(alphaValue) ? Math.min(10, Math.max(0, alphaValue)) : 0) / 10
    const top = radius(value.topRounded),
        bottom = radius(value.bottomRounded)
    const style: FrameStyle = {
        position: 'relative',
        overflow: 'hidden',
        background: start ? (end ? `linear-gradient(${angle},${start},${end})` : start) : undefined,
        ...(hasImage
            ? { backgroundImage: `url('${imageUrl(source)}')`, backgroundSize: 'cover', backgroundRepeat: 'no-repeat' }
            : {}),
        borderTopLeftRadius: top,
        borderTopRightRadius: top,
        borderBottomLeftRadius: bottom,
        borderBottomRightRadius: bottom
    }
    const mask: FrameStyle = {
        position: 'absolute',
        top: '0',
        left: '0',
        width: '100%',
        height: '100%',
        pointerEvents: 'none',
        backgroundColor: `rgba(0,0,0,${alpha})`
    }
    return { style, mask, hasImage }
}
