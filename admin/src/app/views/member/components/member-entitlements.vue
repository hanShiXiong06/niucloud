<template>
    <div v-if="entitlements.length" class="member-entitlements">
        <section v-for="item in entitlements" :key="item.key" class="member-entitlements__item">
            <div v-if="showTitle" class="member-entitlements__source">{{ item.title }}</div>
            <div class="member-entitlements__heading">
                <strong>{{ item.label }}</strong>
                <el-tag v-if="item.status !== 'not_joined'" class="member-entitlements__status" :type="memberEntitlementTone(item.status)" effect="plain" size="small">{{ item.status_text }}</el-tag>
            </div>
            <div v-if="item.context" class="member-entitlements__context">{{ item.context }}</div>
            <div v-if="expiry(item)" class="member-entitlements__expiry">{{ expiry(item) }}</div>
            <div v-if="item.reason" class="member-entitlements__reason" :class="{'is-error':item.status==='unavailable'}">{{ item.reason }}</div>
        </section>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { timeStampTurnTime } from '@/utils/common'
import { normalizeMemberEntitlements, memberEntitlementTone, memberEntitlementExpiry, type MemberEntitlement } from '@/app/utils/member-entitlements'

const props = withDefaults(defineProps<{ items?: unknown; sources?: unknown; sourceKey?: string; showTitle?: boolean }>(), { sourceKey: '', showTitle: false })
const entitlements = computed(() => normalizeMemberEntitlements(props.items, props.sources, props.sourceKey))
const expiry = (item: MemberEntitlement) => memberEntitlementExpiry(item, timeStampTurnTime)
</script>

<style scoped>
.member-entitlements{display:flex;flex-direction:column;gap:12px;min-width:0;line-height:1.6}
.member-entitlements__item{min-width:0}
.member-entitlements__source{margin-bottom:5px;font-size:12px;color:var(--el-text-color-secondary)}
.member-entitlements__heading{display:flex;align-items:center;flex-wrap:wrap;gap:6px 8px;min-width:0}
.member-entitlements__heading>strong{font-size:13px;font-weight:600;overflow-wrap:anywhere;color:var(--el-text-color-primary)}
.member-entitlements__status{display:inline-flex;flex:0 0 auto;align-self:center;width:auto;max-width:100%;white-space:nowrap}
.member-entitlements__context,.member-entitlements__expiry,.member-entitlements__reason{margin-top:3px;font-size:12px;color:var(--el-text-color-secondary);overflow-wrap:anywhere}
.member-entitlements__reason.is-error{color:var(--el-color-danger)}
</style>
