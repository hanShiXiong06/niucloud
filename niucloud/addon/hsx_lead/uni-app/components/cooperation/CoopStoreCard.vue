<template>
    <view class="coop-store" :class="{ selected }" @tap="$emit('select', store)">
        <view class="coop-store__head">
            <CoopMerchantAvatar
                :image="store.storefront_image"
                :name="store.name"
                :active="store.membership?.active===true"
                :rank="membershipBadgeRank(store.membership)"
                :appearance="store.membership?.level_visual"
                :highlighted="membershipBadgeRank(store.membership)>=3"
            />
            <view class="coop-store__name"
                ><view>{{ store.name || '合作门店' }}</view
                ><view class="coop-store__badges"
                    ><CoopLevelBadge
                        v-if="hasSubjectLevel"
                        :level="membershipBadgeRank(store.membership)"
                        :label="store.membership.level_name"
                        :appearance="store.membership.level_visual"
                        :state="subjectLevelState"
                        variant="inline"
                        size="small"
                    /><text v-else class="coop-store__grade">普通会员</text></view
                ></view
            >
            <CoopSelectionMark v-if="selectable" :selected="selected" />
        </view>
        <view class="coop-store__region"
            ><u-icon name="map" size="13" color="#89939e" /><text>{{ store.region_text || '地区未填写' }}</text></view
        >
        <view v-if="store.is_internal" class="coop-store__capability"
            ><text>本店内部交付</text
            ><view class="coop-store__source"><text>由本店成员共同处理，不收协作费</text></view></view
        >
        <view v-else class="coop-store__capability"
            ><text>{{ capabilityText }}</text
            ><view class="coop-store__source"
                ><text>{{ capabilitySourceText }}</text
                ><view
                    v-if="modelDimension && store.erp_visible !== false"
                    class="coop-store__stock-link"
                    role="button"
                    aria-label="查看门店货盘"
                    @tap.stop="$emit('inventory', store)"
                    ><text>{{ Number(store.inventory_count) > 0 ? '查看库存' : '查看货盘' }}</text
                    ><u-icon name="arrow-right" size="11" color="var(--primary-color, #3478cc)" /></view></view
        ></view>
    </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import CoopMerchantAvatar from './CoopMerchantAvatar.vue'
import CoopSelectionMark from './CoopSelectionMark.vue'
import CoopLevelBadge from './CoopLevelBadge.vue'
import type { CoopRow } from '../../api/cooperation'
import { entityLevel, membershipBadgeRank } from '../../utils/entity-membership'
const props = withDefaults(defineProps<{ store: CoopRow; selected?: boolean; selectable?: boolean }>(), { selected: false, selectable: false })
const hasSubjectLevel = computed(() => !!entityLevel(props.store.membership?.level_code))
const subjectLevelState = computed(() => props.store.membership?.active === true ? 'active' : Number(props.store.membership?.expires_at) > 0 ? 'expired' : 'locked')
const modelDimension = computed(() => !['brand', 'operator'].includes(props.store.capability_dimension))
const dimensionLabel = computed(() => props.store.capability_dimension === 'brand' ? '品牌' : props.store.capability_dimension === 'operator' ? '运营商' : '型号')
const matchedDemands = computed(() => props.store.match_demands || props.store.match_models || [])
const capabilityCount = computed(() => {
    if (matchedDemands.value.length) return new Set(matchedDemands.value.map((item: any) => typeof item === 'string' ? item : String(item.id))).size
    const capability = (props.store.business_capabilities || []).find((item: CoopRow) => item.business_type === props.store.business_type)
    return new Set(capability?.option_ids || (capability?.items || []).map((item: CoopRow) => String(item.id))).size
})
const capabilityText = computed(() => {
    if (props.store.erp_visible === false && modelDimension.value) return props.store.all_models ? '全型号交付能力' : capabilityCount.value ? `已登记 ${capabilityCount.value} 个可交付型号` : '交付能力请向门店确认'
    if (!modelDimension.value) return capabilityCount.value ? `${props.store.business_name || '该业务'} · 可承接 ${capabilityCount.value} 个${dimensionLabel.value}` : `${props.store.business_name || '该业务'}交付能力待门店确认`
    return props.store.all_models ? '全型号交付能力' : matchedDemands.value.length ? `可承接 ${capabilityCount.value} 个意向型号` : Number(props.store.manual_model_count) > 0 ? `已登记 ${props.store.manual_model_count} 个可交付型号` : Number(props.store.inventory_count) > 0 ? `${props.store.inventory_count} 条在售库存参考` : '交付能力待门店确认'
})
const capabilitySourceText = computed(() => props.store.erp_visible === false ? '交付能力以门店确认为准' : !modelDimension.value ? '门店自主登记 · 以实际受理为准' : props.store.capability_source === 'manual' ? '门店自主登记 · 非现货承诺' : props.store.capability_source === 'mixed' ? 'ERP 库存 + 自主登记' : props.store.all_models ? '平台已授权' : '库存需向门店核对')
defineEmits<{ (event: 'select' | 'inventory', row: CoopRow): void }>()
</script>
<style scoped lang="scss">
.coop-store { padding: 27rpx 25rpx; margin-bottom: 18rpx; background: #fff; border: 2rpx solid transparent; border-radius: 16rpx; }.coop-store.selected { border-color: var(--primary-color, #d93833); }.coop-store__head { display: flex; align-items: center; gap: 23rpx; }.coop-store__name { flex: 1; min-width: 0; font-size: 29rpx; font-weight: 600; line-height: 1.5; word-break: break-word; }.coop-store__badges { display: flex; flex-wrap: wrap; gap: 9rpx; margin-top: 11rpx; font-size: 20rpx; font-weight: 400; }.coop-store__grade { color: #61738c; background: #edf1f7; border-radius: 4rpx; padding: 1rpx 7rpx; }.coop-store__region { display: flex; align-items: center; gap: 7rpx; font-size: 23rpx; color: #8b939d; margin-top: 23rpx; line-height: 1.7; }.coop-store__capability { font-size: 23rpx; color: #687483; padding-top: 17rpx; margin-top: 18rpx; border-top: 1rpx solid #eff1f4; line-height: 1.7; word-break: break-word; }
</style>
<style scoped lang="scss">
.coop-store__source { display: flex; justify-content: space-between; gap: 15rpx; align-items: center; margin-top: 13rpx; color: #8994a3; font-size: 21rpx; }.coop-store__source>text:first-child { min-width: 0; flex: 1; }.coop-store__stock-link { display: flex; align-items: center; gap: 4rpx; min-height: 62rpx; color: var(--primary-color, #d93833); font-size: 23rpx; flex: none; }
.coop-store { border-color: #e3eaf3; transition: border-color 160ms ease, background-color 160ms ease; }.coop-store.selected { background: #fff; box-shadow: 0 0 0 1px rgba(52,120,204,.04); }.coop-store__head { gap: 22rpx; align-items: center; min-height: 106rpx; }.coop-store__name>view:first-child { color: #2d3d52; line-height: 1.45; }.coop-store__badges { align-items: center; gap: 9rpx; }.coop-store__grade { line-height: 1.55; white-space: nowrap; padding: 2rpx 9rpx; }.coop-store__region { align-items: flex-start; color: #78879a; }.coop-store__region>text { min-width: 0; flex: 1; }.coop-store__region>.u-icon { flex: none; padding-top: 4rpx; }.coop-store__source { flex-wrap: nowrap; align-items: center; gap: 12rpx; }.coop-store__source>text:first-child { line-height: 1.65; overflow-wrap: anywhere; }.coop-store__stock-link { display: inline-flex; flex-direction: row; align-items: center; justify-content: flex-end; flex-shrink: 0; gap: 6rpx; min-width: 90px; min-height: 44px; white-space: nowrap; }.coop-store__stock-link>text { display: block; flex: none; white-space: nowrap; }.coop-store__stock-link>.u-icon { flex: none; }@media(prefers-reduced-motion:reduce){.coop-store{transition:none;}}
</style>
