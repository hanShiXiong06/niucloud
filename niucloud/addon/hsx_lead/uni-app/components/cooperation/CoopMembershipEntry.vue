<template>
    <view
        class="entity-membership-entry"
        :class="{ 'is-embedded': embedded }"
        :style="appearanceStyle"
        role="button"
        aria-label="查看门店会员权益"
        @tap="$emit('open')"
    >
        <text
            v-if="appearance.showTitle !== 0 && appearance.showTitle !== false"
            class="entity-membership-entry__heading"
            >{{ appearance.title || '门店会员' }}</text
        >
        <view class="entity-membership-entry__body"
            ><image
                v-if="appearance.entryImage"
                class="entity-membership-entry__image"
                :src="img(appearance.entryImage)"
                mode="aspectFit" /><CoopBusinessIcon
                v-else
                name="member"
                size="default"
                color="var(--lead-diy-icon,#333)" /><view class="entity-membership-entry__identity"
                ><view class="entity-membership-entry__name"
                    ><text
                        v-if="!membershipBadgeRank(membership)"
                        class="entity-membership-entry__title"
                        >{{ membership.level_name || '查看会员权益' }}</text
                    ><CoopLevelBadge
                        v-if="membershipBadgeRank(membership)"
                        :level="membershipBadgeRank(membership)"
                        :label="membership.level_name"
                        :appearance="membership.level_visual"
                        :state="membershipState(membership)"
                        variant="inline"
                        size="small" /></view
                ><text
                    class="entity-membership-entry__hint"
                    >{{ membership.active ? '有效期至 ' + entityExpiry(membership.expires_at) : membership.level_code ? '已到期，负责人可续费' : appearance.subtitle || '等级、有效期与账号名额' }}</text
                ></view
            ><u-icon name="arrow-right" size="13" color="var(--lead-diy-subtle,#909399)"
        /></view>
        <view class="entity-membership-entry__bottom"
            ><text
                >{{ membership.account_limit || membership.unlimited ? '账号 ' + (membership.used_accounts ?? '—') + ' / ' + entityAccounts(membership) : '全店共享，不重复开通' }}</text
            ><text>{{ appearance.buttonText || (membership.can_manage ? '管理权益' : '查看权益') }}</text></view
        >
    </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { img } from '@/utils/common'
import type { CoopRow } from '../../api/cooperation'
import CoopLevelBadge from './CoopLevelBadge.vue'
import CoopBusinessIcon from './CoopBusinessIcon.vue'
import { entityAccounts, entityExpiry, membershipBadgeRank, membershipState } from '../../utils/entity-membership'
import { cooperationDiyVars } from '../../utils/cooperation-diy-style'
const props = withDefaults(defineProps<{ profile: CoopRow; embedded?: boolean; appearance?: CoopRow }>(), { embedded: false, appearance: () => ({}) })
defineEmits<{ (event: 'open'): void }>()
const membership = computed(() => props.profile.entity_membership || props.profile.membership || {})
const appearanceStyle = computed(() => cooperationDiyVars(props.appearance))
</script>
<style scoped>
.entity-membership-entry { background:var(--lead-diy-panel,#fff);color:var(--lead-diy-text,#606266);border-radius:var(--lead-diy-item-radius,8px);margin:12px 0;padding:16px;box-sizing:border-box; }
.entity-membership-entry.is-embedded { margin:0;border-radius:0;background:transparent; }
.entity-membership-entry__heading { display:block;color:var(--lead-diy-title,#202124);font-size:var(--lead-diy-title-size,16px);font-weight:600;line-height:24px;margin-bottom:12px; }
.entity-membership-entry__image { display:block;flex:none;width:36px;height:36px; }
.entity-membership-entry__body { display:flex;align-items:center;gap:12px;min-height:44px; }
.entity-membership-entry__identity { min-width:0;flex:1; }
.entity-membership-entry__name { display:flex;align-items:center;flex-wrap:wrap;gap:6px; }
.entity-membership-entry__title { min-width:0;font-size:var(--lead-diy-title-size,16px);line-height:1.5;font-weight:600;color:var(--lead-diy-title,#202124);overflow-wrap:break-word; }
.entity-membership-entry__hint { display:block;font-size:12px;line-height:20px;color:var(--lead-diy-subtle,#909399);margin-top:3px; }
.entity-membership-entry__bottom { display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:12px;padding-top:10px;border-top:1px solid #f0f0f0;font-size:var(--lead-diy-text-size,13px);line-height:20px; }
.entity-membership-entry__bottom>text:first-child { min-width:0;flex:1;color:var(--lead-diy-subtle,#909399); }
.entity-membership-entry__bottom>text:last-child { flex:none;background:var(--lead-diy-button,var(--primary-color,#ff4444));color:var(--lead-diy-button-text,#fff);padding:4px 10px;border-radius:var(--lead-diy-button-radius,6px); }
</style>
