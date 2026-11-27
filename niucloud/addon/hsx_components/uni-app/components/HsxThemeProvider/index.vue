<script lang="ts">
export default { name: 'HsxThemeProvider' }
</script>

<script setup lang="ts">
import { computed } from 'vue'
import { provideAdaptiveLayout, useAdaptiveLayout } from '../../hooks/useAdaptiveLayout'
import { provideMobileTheme, resolveMobileThemeVariables } from '../../hooks/useMobileTheme'

const props = withDefaults(defineProps<{ mode?: 'light' | 'dark' }>(), { mode: 'light' })
const themeMode = provideMobileTheme(computed(() => props.mode))
const layout = provideAdaptiveLayout(useAdaptiveLayout())
const scale = computed(() => {
    if (layout.isExpanded.value) return { caption: 13, body: 15, control: 16, subtitle: 17, title: 22, page: 28, controlHeight: 46, cardPadding: 20, sectionGap: 24 }
    if (layout.isMedium.value) return { caption: 12, body: 14, control: 15, subtitle: 16, title: 20, page: 26, controlHeight: 44, cardPadding: 18, sectionGap: 20 }
    return { caption: 12, body: 14, control: 14, subtitle: 15, title: 18, page: 22, controlHeight: 40, cardPadding: 14, sectionGap: 16 }
})
const themeStyle = computed<Record<string, string>>(() => ({
    ...resolveMobileThemeVariables(themeMode.value),
    '--hsx-mobile-font-caption': `${scale.value.caption}px`,
    '--hsx-mobile-font-body': `${scale.value.body}px`,
    '--hsx-mobile-font-control': `${scale.value.control}px`,
    '--hsx-mobile-font-subtitle': `${scale.value.subtitle}px`,
    '--hsx-mobile-font-title': `${scale.value.title}px`,
    '--hsx-mobile-font-page-title': `${scale.value.page}px`,
    '--hsx-mobile-line-caption': `${Math.round(scale.value.caption * 1.5)}px`,
    '--hsx-mobile-line-body': `${Math.round(scale.value.body * 1.5)}px`,
    '--hsx-mobile-line-title': `${Math.round(scale.value.title * 1.35)}px`,
    '--hsx-mobile-control-height': `${scale.value.controlHeight}px`,
    '--hsx-mobile-card-padding': `${scale.value.cardPadding}px`,
    '--hsx-mobile-section-gap': `${scale.value.sectionGap}px`
}))
</script>

<template>
    <view
        class="hsx-mobile-theme"
        :class="[`hsx-mobile-theme--${themeMode}`, `hsx-mobile-theme--${layout.widthClass.value}`]"
        :style="themeStyle"
    ><slot /></view>
</template>

<style lang="scss">
.hsx-mobile-theme {
    --hsx-mobile-bg-page: #f2f5fa;
    --hsx-mobile-bg-surface: #ffffff;
    --hsx-mobile-bg-muted: #f5f7fa;
    --hsx-mobile-text-primary: #172033;
    --hsx-mobile-text-regular: #596579;
    --hsx-mobile-text-secondary: #8a94a5;
    --hsx-mobile-border: #e6ebf2;
    --hsx-mobile-primary: #2563eb;
    --hsx-mobile-primary-soft: #eaf1ff;
    --hsx-mobile-primary-border: #9bb8ff;
    --hsx-mobile-danger: #f56c6c;
    --hsx-mobile-success: #16a34a;
    --hsx-mobile-warning: #d97706;
    --hsx-mobile-price: #ff5a36;
    --hsx-mobile-border-strong: #cbd5e1;
    --hsx-mobile-skeleton: #edf1f6;
    --hsx-mobile-motion-fast: 120ms;
    --hsx-mobile-motion-normal: 200ms;
    --hsx-mobile-motion-slow: 320ms;
    --hsx-mobile-ease: cubic-bezier(.2, 0, 0, 1);
    --hsx-mobile-font-caption: 12px;
    --hsx-mobile-font-body: 14px;
    --hsx-mobile-font-control: 14px;
    --hsx-mobile-font-subtitle: 15px;
    --hsx-mobile-font-title: 18px;
    --hsx-mobile-font-page-title: 22px;
    --hsx-mobile-line-caption: 18px;
    --hsx-mobile-line-body: 21px;
    --hsx-mobile-line-title: 24px;
    --hsx-mobile-control-height: 40px;
    --hsx-mobile-card-padding: 14px;
    --hsx-mobile-section-gap: 16px;
    color: var(--hsx-mobile-text-primary);
}
.hsx-mobile-theme {
    min-height: 100%;
    background: var(--hsx-mobile-bg-page);
}
.hsx-mobile-theme--dark {
    --hsx-mobile-bg-page: #080d17;
    --hsx-mobile-bg-surface: #121b2a;
    --hsx-mobile-bg-muted: #172235;
    --hsx-mobile-text-primary: #eff5ff;
    --hsx-mobile-text-regular: #bdc8d8;
    --hsx-mobile-text-secondary: #8290a5;
    --hsx-mobile-border: #26354c;
    --hsx-mobile-primary: #5b8cff;
    --hsx-mobile-primary-soft: #1d3157;
    --hsx-mobile-primary-border: #496fb7;
    --hsx-mobile-price: #ff8a66;
    --hsx-mobile-success: #4ade80;
    --hsx-mobile-warning: #fbbf24;
    --hsx-mobile-danger: #fb7185;
    --hsx-mobile-border-strong: #40516c;
    --hsx-mobile-skeleton: #1f2c42;
}
</style>
