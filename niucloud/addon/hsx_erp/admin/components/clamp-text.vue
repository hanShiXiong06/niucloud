<template>
    <div class="clamp-text">
        <div ref="bodyRef" class="clamp-text__body" :class="{ 'is-clamp': !expanded }" :style="{ '--clamp-rows': rows }">
            <slot>{{ text }}</slot>
        </div>
        <span v-if="overflow" class="clamp-text__toggle" @click.stop="expanded = !expanded">
            {{ expanded ? '收起' : '展开' }}
            <el-icon class="clamp-text__icon" :class="{ 'is-up': expanded }"><ArrowDown /></el-icon>
        </span>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, nextTick, watch } from 'vue'
import { ArrowDown } from '@element-plus/icons-vue'

const props = withDefaults(defineProps<{ text?: string; rows?: number }>(), { text: '', rows: 3 })

const bodyRef = ref<HTMLElement>()
const overflow = ref(false)
const expanded = ref(false)

async function measure() {
    expanded.value = false            // 先回到收起态再测量，否则测不准
    await nextTick()
    const el = bodyRef.value
    if (!el) return
    overflow.value = el.scrollHeight > el.clientHeight + 1   // 内容真高 > 可见高 = 超行
}

onMounted(measure)
watch(() => props.text, measure)
</script>

<style scoped>
.clamp-text__body.is-clamp {
    display: -webkit-box;
    -webkit-line-clamp: var(--clamp-rows, 3);
    -webkit-box-orient: vertical;
    overflow: hidden;
    word-break: break-word;
}
.clamp-text__toggle {
    display: inline-flex;
    align-items: center;
    margin-top: 2px;
    font-size: 12px;
    color: var(--el-color-primary);
    cursor: pointer;
    user-select: none;
}
.clamp-text__icon {
    margin-left: 2px;
    transition: transform 0.2s;
}
.clamp-text__icon.is-up {
    transform: rotate(180deg);
}
</style>
