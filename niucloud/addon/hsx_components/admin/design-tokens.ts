export const hsxBreakpoints = Object.freeze({
    xs: 0,
    sm: 640,
    md: 768,
    lg: 1024,
    xl: 1280
})

export const hsxDesignTokens = Object.freeze({
    spacing: { 0: 0, 1: 4, 2: 8, 3: 12, 4: 16, 5: 20, 6: 24, 8: 32, 10: 40, 12: 48, 16: 64 },
    radius: { xs: 4, sm: 6, md: 10, lg: 14, xl: 20, full: 9999 },
    typography: {
        caption: { size: 12, lineHeight: 18 },
        body: { size: 14, lineHeight: 22 },
        bodyStrong: { size: 14, lineHeight: 22, weight: 600 },
        subtitle: { size: 16, lineHeight: 24, weight: 600 },
        title: { size: 20, lineHeight: 28, weight: 600 },
        pageTitle: { size: 28, lineHeight: 38, weight: 650 }
    },
    motion: {
        fast: 120,
        normal: 200,
        slow: 320,
        easingStandard: 'cubic-bezier(.2, 0, 0, 1)',
        easingEmphasized: 'cubic-bezier(.2, .8, .2, 1)'
    },
    zIndex: { base: 1, sticky: 20, dropdown: 1000, overlay: 2000, toast: 3000 }
})

export type HsxBreakpoint = keyof typeof hsxBreakpoints
export type HsxSpacingToken = keyof typeof hsxDesignTokens.spacing

