<template>
    <view class="coop-business-icon" :class="['is-' + actualSize, { 'is-pressed': pressed === true && disabled !== true, 'is-disabled': disabled === true }]" :style="iconStyle" :hover-class="disabled === true ? 'none' : 'coop-business-icon--pressed'" :hover-start-time="0" :hover-stay-time="120" aria-hidden="true">
        <u-icon :name="symbol" :size="symbolSize" color="currentColor" />
    </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'

/** Decorative business symbol only. The surrounding labelled control owns navigation and permissions. */
type BusinessIconName = 'store' | 'customer' | 'order' | 'stock' | 'team' | 'wallet' | 'member' | 'invite' | 'connection'
const props = withDefaults(defineProps<{ name?: BusinessIconName | string; size?: 'small' | 'default' | 'large'; color?: string; pressed?: boolean; disabled?: boolean }>(), { name: 'order', size: 'default', color: '', pressed: false, disabled: false })
// One familiar outline per action. No offset fill layer, improvised geometry
// or duplicate glyph competing with the surrounding text label.
const symbols: Record<BusinessIconName, string> = {
    store: 'home', customer: 'account', order: 'file-text', stock: 'grid',
    team: 'man-add', wallet: 'rmb-circle', member: 'level', invite: 'share', connection: 'attach'
}
const symbol = computed(() => Object.prototype.hasOwnProperty.call(symbols, props.name) ? symbols[props.name as BusinessIconName] : symbols.order)
const actualSize = computed(() => ['small', 'default', 'large'].includes(props.size) ? props.size : 'default')
const symbolSize = computed(() => actualSize.value === 'small' ? 21 : actualSize.value === 'large' ? 27 : 23)
const iconStyle = computed(() => ({ color: (typeof props.color === 'string' ? props.color.trim() : '') || 'var(--coop-icon-color, var(--primary-color, #526d8e))' }))
</script>
<style scoped lang="scss">
.coop-business-icon { display:inline-flex;align-items:center;justify-content:center;flex:none;box-sizing:border-box;width:36px;height:36px;border-radius:8px;background:var(--coop-icon-bg,#f5f7fa);vertical-align:middle;user-select:none;transition:transform 120ms ease-out,opacity 120ms ease-out; }
.coop-business-icon.is-small { width:32px;height:32px;border-radius:8px; }
.coop-business-icon.is-large { width:44px;height:44px;border-radius:10px; }
.coop-business-icon.is-pressed,.coop-business-icon--pressed { transform:scale(.96); }
.coop-business-icon.is-disabled { opacity:.4; }
@media(prefers-reduced-motion:reduce) { .coop-business-icon { transition:none; }.coop-business-icon.is-pressed,.coop-business-icon--pressed { transform:none; } }
</style>
