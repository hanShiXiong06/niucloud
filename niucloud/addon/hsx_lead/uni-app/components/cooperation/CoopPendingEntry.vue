<template>
    <view class="coop-pending-mount">
        <u-popup :show="pending.visible.value" mode="bottom" :round="20" :z-index="10090" :safe-area-inset-bottom="false" @close="later">
            <view class="coop-pending" :style="themeColor()" role="dialog" aria-label="待接交付提醒" @touchmove.prevent.stop>
                <CooperationNav title="有交付等待你处理" :safe-area-inset-top="false" :actions="[{ key: 'close', icon: 'close', ariaLabel: '稍后处理待接交付' }]" @action="later" />
                <scroll-view scroll-y class="coop-pending__body" @touchmove.stop>
                    <view class="coop-pending__summary"><view class="coop-pending__icon"><u-icon name="order" size="24" color="var(--primary-color,#007aff)" /></view><view><text class="coop-pending__count">{{ pending.total.value }} 笔待接交付</text><text class="coop-pending__hint">先查看需求，再决定是否接单</text></view></view>
                    <view class="coop-pending__list">
                        <button v-for="item in pending.items.value" :key="item.id + ':' + item.assignmentVersion" class="coop-pending__item" :class="{ 'is-selected': pending.selectedId.value === item.id }" :disabled="opening" :aria-label="'选择查看' + item.businessName" @tap="select(item.id)">
                            <view class="coop-pending__item-copy"><text class="coop-pending__business">{{ item.businessName }}</text><text class="coop-pending__meta">{{ item.publisherName }}{{ item.region ? ' · ' + item.region : '' }}</text></view>
                            <u-icon :name="pending.selectedId.value === item.id ? 'checkmark-circle-fill' : 'arrow-right'" size="19" :color="pending.selectedId.value === item.id ? 'var(--primary-color,#007aff)' : '#9a9fa8'" />
                        </button>
                    </view>
                    <text class="coop-pending__note">稍后处理不会拒单，交付仍保留在「找客户」。</text>
                </scroll-view>
                <view class="coop-pending__footer">
                    <u-button type="primary" shape="circle" :custom-style="cooperationButtonStyle" :loading="opening" :disabled="opening" text="查看并处理" @click="viewSelected" />
                    <view class="coop-pending__secondary"><button :disabled="opening" @tap="later">稍后处理</button><button :disabled="opening" @tap="publish">我先发布需求 <text>›</text></button></view>
                </view>
            </view>
        </u-popup>
    </view>
</template>
<script lang="ts">export default { options: { virtualHost: true } }</script>
<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { getSiteId, getToken } from '@/utils/common'
import useMemberStore from '@/stores/member'
import CooperationNav from './CooperationNav.vue'
import { usePendingCooperationEntry } from '../../composables/usePendingCooperationEntry'
import { cooperationButtonStyle } from '../../utils/mobile-ui'
import { openCooperationTask } from '../../utils/cooperation-navigation'
const props = withDefaults(defineProps<{ active: boolean; memberId?: number }>(), { memberId: 0 })
const emit = defineEmits<{ (event: 'publish'): void }>()
const member = useMemberStore(), opening = ref(false), entryToken = ref('')
let openingGeneration = 0
const viewer = computed(() => props.memberId || (member.token && String(member.token) === String(getToken() || '') ? Number(member.info?.member_id || 0) : 0))
function siteId() {
    const configured = import.meta.env.VITE_SITE_ID
    const fallback = process.env.NODE_ENV === 'development' ? configured || uni.getStorageSync('wap_site_id') || '' : configured
    return String(getSiteId(fallback) || '')
}
// A token change while this page is visible invalidates its old bootstrap id.
// Only the next page-entry activation can establish the new private context.
watch(() => props.active, active => { entryToken.value = active ? String(getToken() || '') : '' }, { immediate: true })
const pending = usePendingCooperationEntry({ active: () => props.active, memberId: () => viewer.value, siteId, token: () => member.token && String(member.token) === entryToken.value && String(getToken() || '') === entryToken.value ? entryToken.value : '' })
watch(() => [props.active, viewer.value, member.token], () => { openingGeneration++; opening.value = false })
function select(id: number) { if (!opening.value) pending.selectedId.value = id }
function later() { if (!opening.value) pending.dismiss() }
function publish() { if (opening.value) return; pending.dismiss(); emit('publish') }
function viewSelected() {
    const item = pending.selected.value
    if (opening.value || !item) return
    const turn = ++openingGeneration
    opening.value = true
    const started = openCooperationTask('detail', { collaboration_id: item.id }, opened => {
        if (turn !== openingGeneration) return
        opening.value = false
        if (opened && props.active && pending.visible.value) pending.opened()
    })
    if (!started) opening.value = false
}
</script>
<style scoped lang="scss">
.coop-pending-mount { flex:none;height:0;width:100%;overflow:visible; }
.coop-pending { --cds-surface-panel:#fff;--cds-surface-1:#f7f8fa;--cds-text-primary:#202124;--cds-text-secondary:#6c7480;--cds-border:#e9edf2;--cds-fill-accent:var(--primary-color,#007aff);display:flex;flex-direction:column;width:100%;max-height:90vh;overflow:hidden;background:var(--cds-surface-panel);color:var(--cds-text-primary);border-radius:20px 20px 0 0; }
.coop-pending__body { flex:1 1 auto;min-height:0;max-height:52vh;padding:20px 20px 0;box-sizing:border-box; }
.coop-pending__summary { display:flex;align-items:center;gap:12px;margin-bottom:20px; }
.coop-pending__icon { width:48px;height:48px;border-radius:14px;background:var(--cds-surface-1);display:flex;align-items:center;justify-content:center;flex:none; }
.coop-pending__count { display:block;font-size:19px;font-weight:650;line-height:28px; }
.coop-pending__hint { display:block;font-size:13px;line-height:20px;color:var(--cds-text-secondary);margin-top:3px; }
.coop-pending__list { border:1px solid var(--cds-border);border-radius:14px;overflow:hidden; }
.coop-pending__item { display:flex;align-items:center;gap:12px;width:100%;min-height:72px;margin:0;padding:13px 14px;text-align:left;background:var(--cds-surface-panel);border-radius:0;border:0;border-bottom:1px solid var(--cds-border);line-height:1.5; }
.coop-pending__item:last-child { border-bottom:0; }
.coop-pending__item::after,.coop-pending__secondary button::after { border:0; }
.coop-pending__item.is-selected { background:var(--cds-surface-1); }
.coop-pending__item-copy { flex:1;min-width:0; }
.coop-pending__business { display:block;font-size:15px;font-weight:600;color:var(--cds-text-primary); }
.coop-pending__meta { display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:12px;color:var(--cds-text-secondary);margin-top:4px; }
.coop-pending__note { display:block;font-size:12px;line-height:20px;color:var(--cds-text-secondary);margin:14px 0 0; }
.coop-pending__footer { flex:none;padding:18px 20px calc(12px + env(safe-area-inset-bottom)); }
.coop-pending__secondary { display:flex;justify-content:space-between;gap:12px;margin-top:7px; }
.coop-pending__secondary button { background:transparent;border:0;font-size:14px;line-height:44px;min-height:44px;margin:0;padding:0 4px;color:var(--cds-text-secondary); }
.coop-pending__secondary button:last-child { color:var(--cds-fill-accent); }
.coop-pending__secondary button[disabled] { opacity:.5; }
/* #ifdef H5 */
.coop-pending button:focus-visible { outline:2px solid var(--cds-fill-accent);outline-offset:-2px; }
@media (prefers-reduced-motion:reduce) { .coop-pending { scroll-behavior:auto;animation:none;transition:none; } }
/* #endif */
</style>
