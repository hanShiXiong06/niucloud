<template>
    <view class="demand-models">
        <view class="demand-models__items">
            <view v-for="item in preview.items" :key="item.id" class="demand-models__tag">
                <text>{{ item.title || item.name || '未命名型号' }}</text>
                <button v-if="removable" class="demand-models__remove" :disabled="disabled" :aria-label="`移除${item.title || item.name || '型号'}`" @tap.stop="emit('remove', item.id)"><u-icon name="close" size="11" color="#76879c" /></button>
            </view>
            <text v-if="!preview.total" class="demand-models__empty">{{ empty }}</text>
        </view>
        <button v-if="preview.hidden" class="demand-models__toggle" :aria-expanded="expanded" @tap.stop="expanded = !expanded"><text>{{ expanded ? '收起' : `展开其余 ${preview.hidden} 个型号` }}</text><u-icon :name="expanded ? 'arrow-up' : 'arrow-down'" size="10" color="#6580a1" /></button>
    </view>
</template>
<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { demandModelPreview } from '../../utils/demand-models'
const props = withDefaults(defineProps<{ items: Record<string, any>[]; empty?: string; removable?: boolean; disabled?: boolean }>(), { empty: '未填写意向型号', removable: false, disabled: false })
const emit = defineEmits<{ (event: 'remove', id: string): void }>()
const expanded = ref(false)
const preview = computed(() => demandModelPreview(props.items, expanded.value))
watch(() => props.items.map(item => String(item.id ?? item.catalog_id ?? '')).join('|'), () => { if (!props.removable || preview.value.total <= 3) expanded.value = false })
</script>
<style scoped lang="scss">
.demand-models { min-width: 0; }.demand-models__items { display: flex; flex-wrap: wrap; gap: 10rpx; }.demand-models__tag { display: inline-flex; align-items: center; gap: 4rpx; max-width: 100%; box-sizing: border-box; border-radius: 7rpx; background: #f2f5f9; padding: 8rpx 13rpx; color: #52667f; font-size: 24rpx; line-height: 1.6; }.demand-models__tag>text { min-width: 0; overflow-wrap: anywhere; }.demand-models__remove { flex: none; display: flex; align-items: center; justify-content: center; min-width: 28px; min-height: 28px; margin: -4rpx -7rpx -4rpx 0; padding: 0; background: transparent; border: 0; }.demand-models__remove::after,.demand-models__toggle::after { border: 0; }.demand-models__remove[disabled] { opacity: .4; }.demand-models__toggle { display: inline-flex; align-items: center; gap: 9rpx; min-height: 44px; margin: 0; padding: 0 2rpx; border: 0; background: transparent; color: #56789f; font-family: inherit; font-size: 23rpx; line-height: 1.7; }.demand-models__empty { color: #8b98aa; font-size: 24rpx; line-height: 1.7; }
</style>
