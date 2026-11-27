<script lang="ts">export default { name: 'HsxProductCard' }</script>

<script setup lang="ts">
import { computed, ref, watch, type PropType } from 'vue'
import HsxActionBar from '../HsxActionBar/index.vue'
import HsxIcon from '../HsxIcon/index.vue'
import HsxTag from '../HsxTag/index.vue'
import type { AnyRecord, HsxActionItem, HsxProductFieldMap, HsxTagTone } from '../../types'
import { getPathValue } from '../../utils'

const props = defineProps({
    product: { type: Object as PropType<AnyRecord>, required: true },
    fieldMap: { type: Object as PropType<HsxProductFieldMap>, default: () => ({ key: 'id', title: 'title', subtitle: 'subtitle', image: 'image', price: 'price', originalPrice: 'originalPrice', status: 'status', description: 'description' }) },
    actions: { type: Array as PropType<HsxActionItem[]>, default: () => [] },
    layout: { type: String as PropType<'vertical' | 'horizontal'>, default: 'vertical' },
    imageHeight: { type: [String, Number] as PropType<string | number>, default: 180 },
    clickable: { type: Boolean, default: true },
    statusTone: { type: [String, Function] as PropType<HsxTagTone | ((status: any, product: AnyRecord) => HsxTagTone)>, default: 'success' },
    pricePrefix: { type: String, default: '¥' },
    showOriginalPrice: { type: Boolean, default: true }
})

const emit = defineEmits<{
    (event: 'click', product: AnyRecord): void
    (event: 'action', action: HsxActionItem, product: AnyRecord): void
    (event: 'image-error', product: AnyRecord): void
}>()

const imageFailed = ref(false)
const value = (name: keyof HsxProductFieldMap) => getPathValue(props.product, props.fieldMap[name] || String(name))
const image = computed(() => String(value('image') || ''))
const imageStyle = computed(() => ({ height: typeof props.imageHeight === 'number' ? `${props.imageHeight}px` : props.imageHeight }))
const tone = computed<HsxTagTone>(() => typeof props.statusTone === 'function' ? props.statusTone(value('status'), props.product) : props.statusTone)
watch(image, () => { imageFailed.value = false })

function click() { if (props.clickable) emit('click', props.product) }
function imageError() { imageFailed.value = true; emit('image-error', props.product) }
</script>

<template>
    <article class="hsx-product-card" :class="[`hsx-product-card--${layout}`, { 'is-clickable': clickable }]" @click="click">
        <div class="hsx-product-card__media" :style="imageStyle">
            <slot name="image" :product="product" :image="image">
                <el-image v-if="image && !imageFailed" :src="image" fit="cover" lazy @error="imageError" />
                <div v-else class="hsx-product-card__placeholder"><HsxIcon name="element Picture" :size="30" /><span>暂无图片</span></div>
            </slot>
            <div v-if="value('status') || $slots.status" class="hsx-product-card__status"><slot name="status" :product="product" :status="value('status')"><HsxTag :text="value('status')" :tone="tone" /></slot></div>
        </div>
        <div class="hsx-product-card__content">
            <slot name="content" :product="product">
                <div class="hsx-product-card__heading">
                    <h3>{{ value('title') }}</h3>
                    <p v-if="value('subtitle')">{{ value('subtitle') }}</p>
                </div>
                <p v-if="value('description')" class="hsx-product-card__description">{{ value('description') }}</p>
                <div class="hsx-product-card__price-row">
                    <slot name="price" :product="product" :price="value('price')">
                        <strong v-if="value('price') !== undefined && value('price') !== ''">{{ pricePrefix }}{{ Number(value('price')).toLocaleString() }}</strong>
                        <del v-if="showOriginalPrice && value('originalPrice')">{{ pricePrefix }}{{ Number(value('originalPrice')).toLocaleString() }}</del>
                    </slot>
                    <slot name="meta" :product="product" />
                </div>
            </slot>
            <HsxActionBar v-if="actions.length || $slots.actions" class="hsx-product-card__actions" :actions="actions" :context="product" size="small" :max-visible="3" @click.stop @action="emit('action', $event, product)">
                <template v-if="$slots.actions" #default><slot name="actions" :product="product" /></template>
            </HsxActionBar>
        </div>
    </article>
</template>

<style scoped>
.hsx-product-card { display: flex; min-width: 0; height: 100%; overflow: hidden; border: 1px solid var(--hsx-border-color); border-radius: var(--hsx-radius-lg); background: var(--hsx-bg-surface); box-shadow: var(--hsx-shadow-card); transition: transform var(--hsx-motion-normal) var(--hsx-ease-standard), box-shadow var(--hsx-motion-normal) var(--hsx-ease-standard); }
.hsx-product-card--vertical { flex-direction: column; }
.hsx-product-card--horizontal { flex-direction: row; }
.hsx-product-card.is-clickable { cursor: pointer; }
.hsx-product-card.is-clickable:hover { transform: translateY(-2px); box-shadow: 0 16px 34px rgba(15, 23, 42, .12); }
.hsx-product-card__media { position: relative; min-width: 0; overflow: hidden; background: var(--hsx-bg-muted); }
.hsx-product-card--horizontal .hsx-product-card__media { width: 180px; height: auto !important; flex: none; }
.hsx-product-card__media :deep(.el-image) { width: 100%; height: 100%; }
.hsx-product-card__placeholder { display: flex; width: 100%; height: 100%; min-height: 120px; flex-direction: column; align-items: center; justify-content: center; gap: 8px; color: var(--hsx-text-tertiary); background: linear-gradient(135deg, color-mix(in srgb, var(--hsx-color-primary) 9%, var(--hsx-bg-muted)), var(--hsx-bg-muted)); }
.hsx-product-card__status { position: absolute; top: 10px; right: 10px; }
.hsx-product-card__content { display: flex; min-width: 0; flex: 1; flex-direction: column; padding: 15px; }
.hsx-product-card__heading h3 { display: -webkit-box; margin: 0; overflow: hidden; color: var(--hsx-text-primary); font-size: 16px; line-height: 1.45; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
.hsx-product-card__heading p,
.hsx-product-card__description { margin: 5px 0 0; color: var(--hsx-text-secondary); font-size: 13px; }
.hsx-product-card__description { display: -webkit-box; overflow: hidden; line-height: 1.55; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
.hsx-product-card__price-row { display: flex; min-width: 0; align-items: baseline; gap: 9px; margin-top: auto; padding-top: 13px; }
.hsx-product-card__price-row strong { color: var(--hsx-color-danger, #ef4444); font-size: 20px; font-variant-numeric: tabular-nums; }
.hsx-product-card__price-row del { color: var(--hsx-text-tertiary); font-size: 12px; }
.hsx-product-card__actions { margin-top: 12px; padding-top: 11px; border-top: 1px solid var(--hsx-border-color); }
@media (max-width: 640px) { .hsx-product-card--horizontal .hsx-product-card__media { width: 128px; } }
</style>
