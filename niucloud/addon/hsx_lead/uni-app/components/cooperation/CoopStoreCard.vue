<template>
    <view class="coop-store" :class="{ selected }" @tap="$emit('select', store)">
        <view class="coop-store__head">
            <CoopMerchantAvatar :image="store.storefront_image" :name="store.name" :reliable="store.is_reliable" />
            <view class="coop-store__name"><view>{{ store.name || '合作门店' }}</view><view class="coop-store__badges"><text v-if="reliable" class="coop-store__trust">靠谱认证</text><text class="coop-store__grade">{{ store.grade_name || '等级未设置' }}</text></view></view>
            <CoopSelectionMark v-if="selectable" :selected="selected" />
        </view>
        <view class="coop-store__region"><u-icon name="map" size="13" color="#89939e" /><text>{{ store.region_text || '地区未填写' }}</text></view>
        <view class="coop-store__capability"><text>{{ store.all_models ? '全型号交付能力' : (store.match_models || []).length ? '可承接：' + store.match_models.map((item: any) => typeof item === 'string' ? item : item.title).join('、') : Number(store.manual_model_count) > 0 ? `已登记 ${store.manual_model_count} 个可交付型号` : Number(store.inventory_count) > 0 ? `${store.inventory_count} 条在售库存参考` : '交付能力待门店确认' }}</text><view class="coop-store__source"><text>{{ store.capability_source === 'manual' ? '门店自主登记 · 非现货承诺' : store.capability_source === 'mixed' ? 'ERP 库存 + 自主登记' : store.all_models ? '平台已授权' : '库存需向门店核对' }}</text><view class="coop-store__stock-link" role="button" aria-label="查看门店货盘" @tap.stop="$emit('inventory', store)"><text>{{ Number(store.inventory_count) > 0 ? '查看库存' : '查看货盘' }}</text><u-icon name="arrow-right" size="11" color="var(--primary-color, #3478cc)" /></view></view></view>
    </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import CoopMerchantAvatar from './CoopMerchantAvatar.vue'
import CoopSelectionMark from './CoopSelectionMark.vue'
import type { CoopRow } from '../../api/cooperation'
const props = withDefaults(defineProps<{ store: CoopRow; selected?: boolean; selectable?: boolean }>(), { selected: false, selectable: false })
const reliable = computed(() => props.store.is_reliable === true || props.store.is_reliable === 1 || props.store.is_reliable === '1')
defineEmits<{ (event: 'select' | 'inventory', row: CoopRow): void }>()
</script>
<style scoped lang="scss">
.coop-store { padding: 27rpx 25rpx; margin-bottom: 18rpx; background: #fff; border: 2rpx solid transparent; border-radius: 16rpx; }.coop-store.selected { border-color: var(--primary-color, #d93833); }.coop-store__head { display: flex; align-items: center; gap: 23rpx; }.coop-store__name { flex: 1; min-width: 0; font-size: 29rpx; font-weight: 600; line-height: 1.5; word-break: break-word; }.coop-store__badges { display: flex; flex-wrap: wrap; gap: 9rpx; margin-top: 11rpx; font-size: 20rpx; font-weight: 400; }.coop-store__trust { color: #826331; background: #f8f1df; border: 1rpx solid #eaddb7; padding: 1rpx 7rpx; border-radius: 4rpx; }.coop-store__grade { color: #61738c; background: #edf1f7; border-radius: 4rpx; padding: 1rpx 7rpx; }.coop-store__region { display: flex; align-items: center; gap: 7rpx; font-size: 23rpx; color: #8b939d; margin-top: 23rpx; line-height: 1.7; }.coop-store__capability { font-size: 23rpx; color: #687483; padding-top: 17rpx; margin-top: 18rpx; border-top: 1rpx solid #eff1f4; line-height: 1.7; word-break: break-word; }
</style>
<style scoped lang="scss">
.coop-store__source { display: flex; justify-content: space-between; gap: 15rpx; align-items: center; margin-top: 13rpx; color: #8994a3; font-size: 21rpx; }.coop-store__source>text:first-child { min-width: 0; flex: 1; }.coop-store__stock-link { display: flex; align-items: center; gap: 4rpx; min-height: 62rpx; color: var(--primary-color, #d93833); font-size: 23rpx; flex: none; }
.coop-store { border-color: #e3eaf3; transition: border-color 160ms ease, background-color 160ms ease; }.coop-store.selected { background: #fff; box-shadow: 0 0 0 1px rgba(52,120,204,.04); }.coop-store__head { gap: 22rpx; align-items: center; min-height: 106rpx; }.coop-store__name>view:first-child { color: #2d3d52; line-height: 1.45; }.coop-store__badges { align-items: center; gap: 9rpx; }.coop-store__trust,.coop-store__grade { line-height: 1.55; white-space: nowrap; padding: 2rpx 9rpx; }.coop-store__region { align-items: flex-start; color: #78879a; }.coop-store__region>text { min-width: 0; flex: 1; }.coop-store__region>.u-icon { flex: none; padding-top: 4rpx; }.coop-store__source { flex-wrap: nowrap; align-items: center; gap: 12rpx; }.coop-store__source>text:first-child { line-height: 1.65; overflow-wrap: anywhere; }.coop-store__stock-link { display: inline-flex; flex-direction: row; align-items: center; justify-content: flex-end; flex-shrink: 0; gap: 6rpx; min-width: 90px; min-height: 44px; white-space: nowrap; }.coop-store__stock-link>text { display: block; flex: none; white-space: nowrap; }.coop-store__stock-link>.u-icon { flex: none; }@media(prefers-reduced-motion:reduce){.coop-store{transition:none;}}</style>
