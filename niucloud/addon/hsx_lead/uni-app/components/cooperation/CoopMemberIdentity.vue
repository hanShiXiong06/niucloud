<template>
    <view class="coop-member-identity">
        <view class="coop-member-identity__top">
            <CoopMerchantAvatar
                :image="profile.storefront_image || member.headimg"
                :name="name"
                :active="profile.membership?.active === true"
                :rank="rank"
                :appearance="profile.membership?.level_visual"
                :highlighted="rank >= 3"
                size="large"
            />
            <view class="coop-member-identity__body">
                <view class="coop-member-identity__name-row"
                    ><text class="coop-member-identity__name">{{ name }}</text
                    ><CoopLevelBadge
                        v-if="shown('showGrade') && profile.id"
                        :level="rank"
                        :label="profile.membership?.level_name || '普通会员'"
                        :appearance="profile.membership?.level_visual"
                        :state="membershipState(profile.membership || {})"
                        variant="inline"
                        size="small"
                /></view>
                <button
                    v-if="shown('showMemberNo') && memberNo"
                    class="coop-member-identity__uid"
                    aria-label="复制用户编号"
                    @tap="copyNo"
                    ><text>{{ memberIdentifier.label }}：{{ memberNo }}</text
                    ><u-icon name="file-text" size="12" :color="muted"
                /></button>
                <text
                    v-if="shown('showRegion') && profile.region_text"
                    class="coop-member-identity__region"
                    >{{ profile.region_text }}</text
                >
            </view>
            <button
                v-if="shown('showSettings')"
                class="coop-member-identity__settings"
                aria-label="设置与退出登录"
                @tap="emit('action', 'settings')"
                ><u-icon name="setting" size="23" :color="ink"
            /></button>
        </view>
        <view v-if="profile.blocked_at" class="coop-member-identity__blocked">已停止合作</view>
        <view class="coop-member-identity__links"
            ><text
                class="coop-member-identity__account"
                >{{ member.nickname && member.nickname !== name ? member.nickname : '' }}</text
            ><button v-if="profile.permissions?.can_manage_billing !== false" @tap="emit('action', 'profile')"
                ><text>{{ profile.id ? '门店资料' : '创建门店' }}</text
                ><u-icon name="arrow-right" size="11" :color="muted" /></button
        ></view>
        <view v-if="shown('showBalance') || shown('showStats') && profile.id" class="coop-member-identity__metrics">
            <button v-if="shown('showBalance')" class="coop-member-identity__metric" @tap="emit('action', 'balance')"
                ><text class="coop-member-identity__number">{{ balance }}</text
                ><text>余额（元）</text></button
            >
            <template v-if="shown('showStats') && profile.id"
                ><button class="coop-member-identity__metric" @tap="emit('action', 'outgoing')"
                    ><text class="coop-member-identity__number">{{ count('outgoing') }}</text
                    ><text>{{ appearance.outgoingText || '找门店' }}</text></button
                ><button class="coop-member-identity__metric" @tap="emit('action', 'incoming')"
                    ><text class="coop-member-identity__number">{{ count('incoming') }}</text
                    ><text>{{ appearance.incomingText || '找客户' }}</text></button
                ><button class="coop-member-identity__metric" @tap="emit('action', 'members')"
                    ><text class="coop-member-identity__number">{{ profile.membership?.used_accounts ?? '—' }}</text
                    ><text>{{ appearance.secondaryText || '门店成员' }}</text></button
                ></template
            >
        </view>
    </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import CoopMerchantAvatar from './CoopMerchantAvatar.vue'
import CoopLevelBadge from './CoopLevelBadge.vue'
import { membershipBadgeRank, membershipState } from '../../utils/entity-membership'
import { profileMemberIdentifier, profileMemberBalance } from '../../utils/profile-identity'
const props = withDefaults(defineProps<{ profile: Record<string, any>; member: Record<string, any>; appearance?: Record<string, any>; preview?: boolean }>(), { appearance: () => ({}), preview: false })
const emit = defineEmits<{ (event: 'action', key: string): void }>()
const shown = (key: string) => ![false, 0, '0'].includes(props.appearance[key])
const name = computed(() => props.profile.name || props.member.nickname || '会员')
const rank = computed(() => membershipBadgeRank(props.profile.membership))
const memberIdentifier = computed(() => profileMemberIdentifier(props.member)), memberNo = computed(() => memberIdentifier.value.value), balance = computed(() => profileMemberBalance(props.member))
const muted = 'var(--lead-diy-subtle,#909399)', ink = 'var(--lead-diy-icon,#333333)'
function count(key: string) { const value = props.profile.cooperation_stats?.[key]; return Number.isSafeInteger(Number(value)) && Number(value) >= 0 && value !== null && value !== undefined ? value : '—' }
function copyNo() { if (!props.preview && memberNo.value) uni.setClipboardData({ data: memberNo.value }) }
</script>
<style scoped lang="scss">
.coop-member-identity { padding:var(--lead-diy-content-padding,16px);color:var(--lead-diy-text,#606266); }
.coop-member-identity__top { display:flex;align-items:center;gap:12px; }
.coop-member-identity__body { flex:1;min-width:0; }
.coop-member-identity__name-row { display:flex;align-items:center;gap:6px;min-width:0;flex-wrap:wrap; }
.coop-member-identity__name { min-width:0;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:var(--lead-diy-title-size,18px);font-weight:650;line-height:1.5;color:var(--lead-diy-title,#202124); }
.coop-member-identity__uid { display:flex;align-items:center;gap:6px;max-width:100%;min-height:32px;margin:0;padding:0;text-align:left;font-size:12px;line-height:18px;color:var(--lead-diy-text,#606266);background:transparent; }
.coop-member-identity__uid>text { min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
.coop-member-identity__region { display:block;font-size:12px;line-height:18px;color:var(--lead-diy-subtle,#909399);overflow-wrap:anywhere; }
.coop-member-identity__settings { flex:none;display:flex;align-items:center;justify-content:center;align-self:flex-start;width:44px;min-height:44px;margin:-6px -8px 0 0;padding:0;background:transparent; }
.coop-member-identity__links { display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:32px;margin-top:6px; }
.coop-member-identity__account { flex:1;min-width:0;color:var(--lead-diy-subtle,#909399);font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
.coop-member-identity__links>button { display:flex;align-items:center;gap:4px;flex:none;min-height:44px;margin:-4px 0;padding:0;color:var(--lead-diy-text,#606266);font-size:12px;background:transparent; }
.coop-member-identity__metrics { display:flex;align-items:stretch;border-top:1px solid var(--lead-diy-border,#e4e8ef);padding-top:16px;margin-top:4px;gap:4px; }
.coop-member-identity__metric { display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;flex:1;min-width:0;min-height:56px;padding:0;margin:0;background:transparent;color:var(--lead-diy-text,#606266);font-size:12px;line-height:18px; }
.coop-member-identity__number { max-width:100%;overflow-wrap:anywhere;font-size:21px;font-weight:650;line-height:27px;color:var(--lead-diy-title,#202124);font-variant-numeric:tabular-nums; }
.coop-member-identity__blocked { margin-top:10px;font-size:12px;color:#a85b48; }
.coop-member-identity button::after { border:0; }
</style>
