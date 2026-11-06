<template>
    <view class="coop-business-icon" :class="['is-' + actualSize, { 'is-pressed': pressed === true && disabled !== true, 'is-disabled': disabled === true }]" :style="iconStyle" :hover-class="disabled === true ? 'none' : 'coop-business-icon--pressed'" :hover-start-time="0" :hover-stay-time="120" aria-hidden="true">
        <view class="coop-business-icon__fill"><u-icon :name="symbol.fill" :size="symbolSize" color="currentColor" /></view>
        <view class="coop-business-icon__line"><u-icon :name="symbol.line" :size="symbolSize" color="currentColor" /></view>
    </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'

/** Decorative business symbol only. The surrounding labelled control owns navigation and permissions. */
type BusinessIconName = 'store' | 'customer' | 'order' | 'stock' | 'team' | 'wallet' | 'member' | 'invite' | 'connection'
const props = withDefaults(defineProps<{ name?: BusinessIconName | string; size?: 'small' | 'default' | 'large'; color?: string; pressed?: boolean; disabled?: boolean }>(), { name: 'order', size: 'default', color: '', pressed: false, disabled: false })
// Every name exists in the installed uview-plus icon dictionary. Fill and line
// share the same geometry; two opacities add depth without new artwork or fonts.
const symbols: Record<BusinessIconName, { line: string; fill: string }> = {
    store: { line: 'home', fill: 'home-fill' },
    customer: { line: 'account', fill: 'account-fill' },
    order: { line: 'file-text', fill: 'file-text-fill' },
    stock: { line: 'grid', fill: 'grid-fill' },
    team: { line: 'man-add', fill: 'man-add-fill' },
    wallet: { line: 'rmb-circle', fill: 'rmb-circle-fill' },
    member: { line: 'integral', fill: 'integral-fill' },
    invite: { line: 'gift', fill: 'gift-fill' },
    connection: { line: 'attach', fill: 'attach' }
}
const symbol = computed(() => Object.prototype.hasOwnProperty.call(symbols, props.name) ? symbols[props.name as BusinessIconName] : symbols.order)
const actualSize = computed(() => ['small', 'default', 'large'].includes(props.size) ? props.size : 'default')
const symbolSize = computed(() => actualSize.value === 'small' ? 21 : actualSize.value === 'large' ? 27 : 23)
const iconStyle = computed(() => ({ color: (typeof props.color === 'string' ? props.color.trim() : '') || 'var(--coop-icon-color, var(--primary-color, #526d8e))' }))
</script>
<style scoped lang="scss">
.coop-business-icon { position:relative;display:inline-flex;align-items:center;justify-content:center;flex:none;box-sizing:border-box;width:36px;height:36px;border-radius:10px;background:var(--coop-icon-bg,#f3f5f8);vertical-align:middle;user-select:none;transition:transform 120ms ease-out,opacity 120ms ease-out; }
.coop-business-icon__fill,.coop-business-icon__line { position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none; }
.coop-business-icon__fill { opacity:.15;transform:translate(1px,1px); }
.coop-business-icon__line { opacity:.94; }
.coop-business-icon.is-small { width:32px;height:32px;border-radius:9px; }
.coop-business-icon.is-large { width:44px;height:44px;border-radius:12px; }
.coop-business-icon.is-pressed,.coop-business-icon--pressed { transform:scale(.96); }
.coop-business-icon.is-disabled { opacity:.4; }
@media(prefers-reduced-motion:reduce) { .coop-business-icon { transition:none; }.coop-business-icon.is-pressed,.coop-business-icon--pressed { transform:none; } }
</style>
