export const hsxMobileDesignTokens = Object.freeze({
    unit: 'px',
    breakpoints: { medium: 600, expanded: 840, large: 1200, extraLarge: 1600 },
    spacing: { 1: 4, 2: 8, 3: 12, 4: 16, 5: 20, 6: 24, 8: 32 },
    radius: { sm: 6, md: 10, lg: 14, xl: 20, full: 9999 },
    typography: {
        compact: { caption: 12, body: 14, control: 14, subtitle: 15, title: 18, pageTitle: 22 },
        medium: { caption: 12, body: 14, control: 15, subtitle: 16, title: 20, pageTitle: 26 },
        expanded: { caption: 13, body: 15, control: 16, subtitle: 17, title: 22, pageTitle: 28 }
    },
    motion: { fast: 120, normal: 200, slow: 320, easing: 'cubic-bezier(.2, 0, 0, 1)' },
    touch: { minimum: 44 }
})
