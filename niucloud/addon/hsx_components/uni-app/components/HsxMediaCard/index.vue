<script lang="ts">
export default { name: 'HsxMediaCard' }
</script>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { resolveAdaptiveValue, useAdaptiveContext } from '../../hooks/useAdaptiveLayout'
import type { MobileResponsiveValue } from '../../types'
import HsxIcon from '../HsxIcon/index.vue'
import HsxTag from '../HsxTag/index.vue'

const props = withDefaults(
    defineProps<{
        image?: string
        title: string
        subtitle?: string
        description?: string
        price?: string | number
        pricePrefix?: string
        status?: string
        statusTone?: 'neutral' | 'primary' | 'info' | 'success' | 'warning' | 'danger'
        imageSize?: MobileResponsiveValue<number>
        radius?: number
        clickable?: boolean
        lazyLoad?: boolean
        placeholderIcon?: string
    }>(),
    {
        image: '',
        subtitle: '',
        description: '',
        pricePrefix: '¥',
        status: '',
        statusTone: 'success',
        imageSize: () => ({ compact: 86, medium: 96, expanded: 104 }),
        radius: 14,
        clickable: true,
        lazyLoad: true,
        placeholderIcon: 'image'
    }
)

const emit = defineEmits<{
    (event: 'click'): void
    (event: 'image-load', detail: any): void
    (event: 'image-error', detail: any): void
}>()
const layout = useAdaptiveContext()
const actualImageSize = computed(() => resolveAdaptiveValue(props.imageSize, layout.widthClass.value, 86))
const imageFailed = ref(false)

watch(() => props.image, () => (imageFailed.value = false))

function handleImageLoad(detail: any) {
    imageFailed.value = false
    emit('image-load', detail)
}

function handleImageError(detail: any) {
    imageFailed.value = true
    emit('image-error', detail)
}
</script>

<template>
    <view class="hsx-media-card" :class="{ 'is-clickable': clickable }" @click="emit('click')">
        <view
            class="hsx-media-card__media"
            :style="{ width: `${actualImageSize}px`, height: `${actualImageSize}px`, borderRadius: `${radius - 3}px` }"
        >
            <slot name="image" :image="image">
                <image
                    v-if="image && !imageFailed"
                    class="hsx-media-card__image"
                    :src="image"
                    mode="aspectFill"
                    :lazy-load="lazyLoad"
                    @load="handleImageLoad"
                    @error="handleImageError"
                />
                <slot v-else name="fallback">
                    <view class="hsx-media-card__placeholder"><HsxIcon :name="placeholderIcon" :size="28" /></view>
                </slot>
            </slot>
        </view>

        <view class="hsx-media-card__content">
            <view class="hsx-media-card__topline">
                <text class="hsx-media-card__title">{{ title }}</text>
                <slot name="status"><HsxTag v-if="status" :text="status" :tone="statusTone" /></slot>
            </view>
            <text v-if="subtitle" class="hsx-media-card__subtitle">{{ subtitle }}</text>
            <text v-if="description" class="hsx-media-card__description">{{ description }}</text>
            <view class="hsx-media-card__bottom">
                <slot name="meta">
                    <text v-if="price !== undefined && price !== ''" class="hsx-media-card__price">{{ pricePrefix }}{{ price }}</text>
                </slot>
                <view v-if="$slots.actions" class="hsx-media-card__actions"><slot name="actions" /></view>
                <HsxIcon v-else-if="clickable" name="next" :size="17" color="var(--hsx-mobile-text-tertiary, #a5adba)" />
            </view>
        </view>
    </view>
</template>

<style scoped lang="scss">
.hsx-media-card {
    display: flex;
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    align-items: stretch;
    gap: 12px;
    padding: var(--hsx-mobile-card-padding, 14px);
    border: 1px solid var(--hsx-mobile-border, #e6ebf2);
    border-radius: 14px;
    background: var(--hsx-mobile-bg-surface, #fff);
    box-shadow: 0 6px 16px rgba(22, 42, 83, .07);
}
.hsx-media-card.is-clickable:active { background: var(--hsx-mobile-bg-muted, #f7f8fa); transform: scale(.995); }
.hsx-media-card__media { flex: none; overflow: hidden; background: var(--hsx-mobile-bg-muted, #f3f5f8); }
.hsx-media-card__image,
.hsx-media-card__placeholder { display: flex; width: 100%; height: 100%; align-items: center; justify-content: center; }
.hsx-media-card__placeholder { color: var(--hsx-mobile-text-tertiary, #a5adba); background: linear-gradient(135deg, rgba(37, 99, 235, .08), rgba(14, 165, 233, .04)); }
.hsx-media-card__content { display: flex; min-width: 0; flex: 1; flex-direction: column; }
.hsx-media-card__topline { display: flex; min-width: 0; align-items: flex-start; gap: 8px; }
.hsx-media-card__title { display: -webkit-box; min-width: 0; flex: 1; overflow: hidden; color: var(--hsx-mobile-text-primary, #172033); font-size: var(--hsx-mobile-font-subtitle, 15px); font-weight: 650; line-height: 1.45; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
.hsx-media-card__subtitle { margin-top: 4px; overflow: hidden; color: var(--hsx-mobile-text-secondary, #8a8f99); font-size: var(--hsx-mobile-font-caption, 12px); text-overflow: ellipsis; white-space: nowrap; }
.hsx-media-card__description { display: -webkit-box; margin-top: 5px; overflow: hidden; color: var(--hsx-mobile-text-secondary, #697386); font-size: var(--hsx-mobile-font-caption, 12px); line-height: 1.45; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
.hsx-media-card__bottom { display: flex; min-width: 0; align-items: flex-end; justify-content: space-between; gap: 8px; margin-top: auto; padding-top: 8px; }
.hsx-media-card__price { color: var(--hsx-mobile-price, #ff5a36); font-size: var(--hsx-mobile-font-subtitle, 15px); font-weight: 700; }
.hsx-media-card__actions { display: flex; align-items: center; gap: 6px; }
</style>
