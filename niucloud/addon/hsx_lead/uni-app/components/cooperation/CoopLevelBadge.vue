<template>
    <view class="coop-level-badge" :class="['level-' + badge.level, 'state-' + badge.state, 'size-' + badge.size, 'variant-' + badge.variant, { 'has-skin': hasSkin, 'is-glowing': showEdgeLight, 'is-animated': showEdgeLight }]" :style="skinStyle" role="img" :aria-label="badge.ariaLabel">
        <view class="coop-level-badge__surface" aria-hidden="true">
            <text class="coop-level-badge__mark">{{ mark }}</text>
            <view v-if="showEdgeLight" class="coop-level-badge__edge" />
        </view>
        <view v-if="badge.variant === 'emblem' && (showLabel || badge.stateLabel)" class="coop-level-badge__caption"><text v-if="showLabel" class="coop-level-badge__name">{{ badge.name }}</text><text v-if="badge.stateLabel" class="coop-level-badge__state">{{ badge.stateLabel }}</text></view>
        <text v-else-if="badge.stateLabel" class="coop-level-badge__state">{{ badge.stateLabel }}</text>
    </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { membershipBadgeAppearance, type MembershipBadgeSize, type MembershipBadgeState, type MembershipBadgeVariant } from '../../utils/membership-visual'
import { membershipSkin, membershipSkinVars } from '../../utils/membership-skin'

const props = withDefaults(defineProps<{ level?: number; label?: string; state?: MembershipBadgeState; size?: MembershipBadgeSize; variant?: MembershipBadgeVariant; animated?: boolean; showLabel?: boolean; appearance?: Record<string, any> }>(), { level: 0, label: '', state: 'locked', size: 'default', variant: 'emblem', animated: false, showLabel: false, appearance: () => ({}) })
const badge = computed(() => membershipBadgeAppearance(props.level, props.state, props.size, props.variant, props.animated, props.label))
const skin = computed(() => membershipSkin(props.appearance, badge.value.level))
// Unowned plans may preview their configured palette, never an active effect.
const hasSkin = computed(() => badge.value.level > 0 && badge.value.state !== 'expired')
const skinStyle = computed(() => hasSkin.value ? membershipSkinVars(props.appearance, badge.value.level) : {})
const mark = computed(() => props.label || badge.value.level === 0 ? badge.value.name : badge.value.code)
const showEdgeLight = computed(() => badge.value.level > 0 && badge.value.state === 'active' && props.animated === true && skin.value.avatarEffect === true)
</script>
<style scoped>
.coop-level-badge { --badge-color:#65748a;--badge-light:#f1f4f7;--badge-edge:#dce3ea;display:inline-flex;flex:none;align-items:center;justify-content:center;gap:6px;max-width:100%;vertical-align:middle;box-sizing:border-box; }
.coop-level-badge__surface { position:relative;flex:none;display:flex;align-items:center;justify-content:center;box-sizing:border-box;max-width:100%;padding:0 9px;border:1px solid var(--badge-edge);border-radius:6px;background:var(--badge-light); }
.has-skin .coop-level-badge__surface { background:var(--lead-member-gradient);border-color:var(--lead-member-accent); }
.has-skin .coop-level-badge__mark { color:var(--lead-member-text); }
.coop-level-badge__mark { position:relative;display:block;min-width:0;overflow:hidden;text-overflow:ellipsis;color:var(--badge-mark-color,var(--badge-color));font-weight:700;line-height:1.25;white-space:nowrap;font-family:-apple-system,BlinkMacSystemFont,"Arial","PingFang SC",sans-serif; }
.coop-level-badge__caption { display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:4px;min-width:0; }
.coop-level-badge__name { color:var(--badge-name-color,#43516a);font-size:12px;line-height:18px;font-weight:600; }
.coop-level-badge__state { flex:none;color:var(--badge-state-color,#778295);font-size:10px;line-height:16px;font-weight:400;white-space:nowrap; }
.variant-emblem { flex-direction:column;gap:4px; }
.variant-emblem .coop-level-badge__surface { min-width:48px;height:40px; }
.variant-emblem .coop-level-badge__mark { font-size:18px; }
.variant-emblem.size-small .coop-level-badge__surface { min-width:38px;height:32px;padding:0 7px; }
.variant-emblem.size-small .coop-level-badge__mark { font-size:15px; }
.variant-emblem.size-small .coop-level-badge__name { font-size:11px;line-height:16px; }
.variant-emblem.size-large .coop-level-badge__surface { min-width:60px;height:48px;padding:0 11px; }
.variant-emblem.size-large .coop-level-badge__mark { font-size:22px; }
.variant-emblem.size-large .coop-level-badge__name { font-size:13px;line-height:20px; }
.variant-inline { gap:5px; }
.variant-inline .coop-level-badge__surface { min-width:36px;height:24px;padding:0 7px;border-radius:5px; }
.variant-inline .coop-level-badge__mark { font-size:13px;line-height:20px; }
.variant-inline.size-small .coop-level-badge__surface { min-width:32px;height:22px;padding:0 6px; }
.variant-inline.size-small .coop-level-badge__mark { font-size:12px;line-height:18px; }
.variant-inline.size-large .coop-level-badge__surface { min-width:42px;height:28px;padding:0 8px; }
.variant-inline.size-large .coop-level-badge__mark { font-size:15px;line-height:22px; }
.level-0 { --badge-color:#718096;--badge-light:#f3f5f8;--badge-edge:#e4e8ef; }
.level-0 .coop-level-badge__mark { font-weight:600; }
.state-expired { --badge-color:#7c8795;--badge-light:#f3f4f6;--badge-edge:#e0e4ea; }
.state-expired .coop-level-badge__name,.state-locked .coop-level-badge__name { color:var(--badge-name-color,#7c8795); }
.coop-level-badge__edge { position:absolute;inset:-1px;box-sizing:border-box;border:1px solid var(--lead-member-accent,var(--badge-color));border-radius:inherit;pointer-events:none;opacity:0;animation:coop-level-edge 1400ms ease-out 1; }
@keyframes coop-level-edge { 0%,100% { opacity:0; } 35% { opacity:.38; } }
@media (prefers-reduced-motion: reduce) { .coop-level-badge__edge { animation: none;display:none; } }
</style>
