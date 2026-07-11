<template>
    <div v-if="images.length" class="erp-image-gallery">
        <el-image
            v-for="(url, index) in visibleImages"
            :key="`${url}-${index}`"
            :src="url"
            :preview-src-list="images"
            :initial-index="index"
            fit="cover"
            :style="{ width: `${size}px`, height: `${size}px` }"
            preview-teleported
            hide-on-click-modal
        />
        <span v-if="images.length > limit" class="more-count">+{{ images.length - limit }}</span>
    </div>
    <span v-else class="empty-text">{{ emptyText }}</span>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{ value?: string | string[] | null; size?: number; limit?: number; emptyText?: string }>(), {
    value: '', size: 64, limit: 6, emptyText: '暂无图片'
})

const images = computed(() => normalizeImages(props.value))
const visibleImages = computed(() => images.value.slice(0, props.limit))

function normalizeImages(value: string | string[] | null | undefined): string[] {
    if (Array.isArray(value)) return value.map(String).map(item => item.trim()).filter(Boolean)
    const text = String(value || '').trim()
    if (!text) return []
    try {
        const parsed = JSON.parse(text)
        if (Array.isArray(parsed)) return parsed.map(String).map(item => item.trim()).filter(Boolean)
    } catch (_) {}
    return text.split(/[，,\n]/).map(item => item.trim()).filter(Boolean)
}
</script>

<style scoped>
.erp-image-gallery { display:flex; flex-wrap:wrap; align-items:center; gap:8px; }
.erp-image-gallery :deep(.el-image) { overflow:hidden; cursor:zoom-in; border:1px solid #e5e7eb; border-radius:6px; background:#f8fafc; }
.more-count { display:flex; min-width:38px; height:38px; align-items:center; justify-content:center; border-radius:6px; color:#64748b; background:#f1f5f9; font-size:12px; }
.empty-text { color:#9ca3af; font-size:13px; }
</style>
