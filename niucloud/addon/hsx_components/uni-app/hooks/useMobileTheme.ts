import { computed, inject, provide, type ComputedRef } from 'vue'

export type HsxMobileThemeMode = 'light' | 'dark'

const hsxMobileThemeKey = Symbol('hsx-mobile-theme')

const lightPalette = {
    '--hsx-mobile-bg-page': '#f2f5fa',
    '--hsx-mobile-bg-surface': '#ffffff',
    '--hsx-mobile-bg-muted': '#f5f7fa',
    '--hsx-mobile-text-primary': '#172033',
    '--hsx-mobile-text-regular': '#596579',
    '--hsx-mobile-text-secondary': '#8a94a5',
    '--hsx-mobile-border': '#e6ebf2',
    '--hsx-mobile-primary': '#2563eb',
    '--hsx-mobile-primary-soft': '#eaf1ff',
    '--hsx-mobile-primary-border': '#9bb8ff',
    '--hsx-mobile-danger': '#f56c6c',
    '--hsx-mobile-success': '#16a34a',
    '--hsx-mobile-warning': '#d97706',
    '--hsx-mobile-price': '#ff5a36',
    '--hsx-mobile-border-strong': '#cbd5e1',
    '--hsx-mobile-skeleton': '#edf1f6'
}

const darkPalette = {
    ...lightPalette,
    '--hsx-mobile-bg-page': '#080d17',
    '--hsx-mobile-bg-surface': '#121b2a',
    '--hsx-mobile-bg-muted': '#172235',
    '--hsx-mobile-text-primary': '#eff5ff',
    '--hsx-mobile-text-regular': '#bdc8d8',
    '--hsx-mobile-text-secondary': '#8290a5',
    '--hsx-mobile-border': '#26354c',
    '--hsx-mobile-primary': '#5b8cff',
    '--hsx-mobile-primary-soft': '#1d3157',
    '--hsx-mobile-primary-border': '#496fb7',
    '--hsx-mobile-price': '#ff8a66',
    '--hsx-mobile-success': '#4ade80',
    '--hsx-mobile-warning': '#fbbf24',
    '--hsx-mobile-danger': '#fb7185',
    '--hsx-mobile-border-strong': '#40516c',
    '--hsx-mobile-skeleton': '#1f2c42'
}

export function resolveMobileThemeVariables(mode: HsxMobileThemeMode): Record<string, string> {
    return mode === 'dark' ? darkPalette : lightPalette
}

export function provideMobileTheme(mode: ComputedRef<HsxMobileThemeMode>) {
    provide(hsxMobileThemeKey, mode)
    return mode
}

export function useMobileTheme() {
    return inject<ComputedRef<HsxMobileThemeMode>>(hsxMobileThemeKey, computed(() => 'light'))
}
