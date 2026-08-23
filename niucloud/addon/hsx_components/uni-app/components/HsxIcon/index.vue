<script lang="ts">
export default { name: 'HsxIcon' }
</script>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
    name?: string
    src?: string
    size?: string | number
    color?: string
    spin?: boolean
    label?: string
    clickable?: boolean
    hitSize?: string | number
}>(), {
    name: '',
    size: 20,
    color: 'currentColor',
    spin: false,
    label: '',
    clickable: false,
    hitSize: 36
})

const semanticIcons: Record<string, string> = {
    add: 'plus',
    back: 'arrow-left',
    next: 'arrow-right',
    close: 'close',
    search: 'search',
    filter: 'list-dot',
    scan: 'scan',
    list: 'list',
    grid: 'grid',
    refresh: 'reload',
    image: 'photo',
    camera: 'camera-fill',
    edit: 'edit-pen',
    delete: 'trash-fill',
    share: 'share',
    info: 'info-circle',
    success: 'checkmark-circle-fill',
    warning: 'error-circle-fill',
    more: 'more-dot-fill',
    upload: 'arrow-upward',
    download: 'download'
}
const parts = computed(() => props.name.trim().split(/\s+/).filter(Boolean))
const isFont = computed(() => parts.value.length > 1 && parts.value[0] !== 'uview')
const uviewName = computed(() => {
    const name = parts.value[0] === 'uview' ? parts.value.slice(1).join('-') : props.name
    return semanticIcons[name] || name
})
const actualSize = computed(() => typeof props.size === 'number' ? props.size : parseFloat(props.size) || 20)
const actualHitSize = computed(() => typeof props.hitSize === 'number' ? props.hitSize : parseFloat(props.hitSize) || 36)
const emit = defineEmits<{ (event: 'click', detail: any): void }>()
</script>

<template>
    <view
        class="hsx-icon"
        :class="{ 'is-spin': spin, 'is-clickable': clickable }"
        :style="clickable ? { width: `${actualHitSize}px`, height: `${actualHitSize}px` } : undefined"
        :role="clickable ? 'button' : undefined"
        :aria-label="label || undefined"
        @click="clickable && emit('click', $event)"
    >
        <image v-if="src" class="hsx-icon__image" :src="src" mode="aspectFit" :style="{ width: `${actualSize}px`, height: `${actualSize}px` }" />
        <text v-else-if="isFont" :class="parts" :style="{ color, fontSize: `${actualSize}px` }" />
        <u-icon v-else :name="uviewName" :size="actualSize" :color="color" />
    </view>
</template>

<style scoped lang="scss">
.hsx-icon { display: inline-flex; flex: none; align-items: center; justify-content: center; line-height: 1; }
.hsx-icon.is-clickable { box-sizing: border-box; border-radius: 50%; }
.hsx-icon.is-clickable:active { background: var(--hsx-mobile-bg-muted, rgba(15, 23, 42, .07)); }
.hsx-icon__image { display: block; }
.hsx-icon.is-spin { animation: hsx-icon-spin 1s linear infinite; }
@keyframes hsx-icon-spin { to { transform: rotate(360deg); } }
</style>
