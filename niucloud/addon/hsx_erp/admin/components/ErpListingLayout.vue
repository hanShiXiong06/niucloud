<template>
    <el-popover v-model:visible="visible" placement="bottom-end" :width="320" trigger="click">
        <template #reference><el-button link type="primary" size="small" aria-label="调整表单区块顺序"><span class="listing-layout__icon" aria-hidden="true">☷</span>调整顺序</el-button></template>
        <section class="listing-layout" aria-label="表单区块排序">
            <div class="listing-layout__head"><strong>调整显示顺序</strong><el-button link @click="visible = false" aria-label="关闭排序">关闭</el-button></div>
            <p class="listing-layout__hint">拖动句柄或点击上下箭头，仅保存在本设备，不影响他人。</p>
            <div class="listing-layout__rows">
                <div v-for="(id, index) in order" :key="id" class="listing-layout__row" :class="{ 'is-target': dropId === id && dragId !== id, 'is-dragging': dragId === id }" draggable="true" @dragstart="event => startDrag(id, event)" @dragover.prevent="dropId = id" @drop.prevent="dropAt(id)" @dragend="endDrag">
                    <button type="button" class="listing-layout__handle" :aria-label="labels[id] + '，可用上下方向键移动'" @keydown.up.prevent="move(id, -1)" @keydown.down.prevent="move(id, 1)">⠿</button>
                    <span class="listing-layout__name">{{ labels[id] }}</span>
                    <el-button text size="small" :disabled="index === 0" :aria-label="'上移' + labels[id]" @click="move(id, -1)">↑</el-button>
                    <el-button text size="small" :disabled="index === order.length - 1" :aria-label="'下移' + labels[id]" @click="move(id, 1)">↓</el-button>
                </div>
            </div>
            <div class="listing-layout__foot"><el-button link @click="reset">恢复默认</el-button><el-button size="small" type="primary" @click="visible = false">完成</el-button></div>
        </section>
    </el-popover>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { defaultListingOrder, listingSectionLabels, moveListingSection, normalizeListingOrder } from '@/addon/hsx_erp/hooks/useErpListingLayout'
const props = defineProps<{ modelValue: string[] }>()
const emit = defineEmits<{ (event: 'update:modelValue', value: string[]): void }>()
const visible = ref(false), dragId = ref(''), dropId = ref('')
const order = computed(() => normalizeListingOrder(props.modelValue))
const labels = listingSectionLabels
function move(id: string, delta: number) { emit('update:modelValue', moveListingSection(order.value, id, order.value.indexOf(id as any) + delta)) }
function reset() { emit('update:modelValue', [...defaultListingOrder]) }
function startDrag(id: string, event?: DragEvent) {
    if (!order.value.includes(id as any)) return
    dragId.value = id; dropId.value = id
    if (event?.dataTransfer) { event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', id) }
}
function dropAt(id: string) {
    if (dragId.value && order.value.includes(id as any)) emit('update:modelValue', moveListingSection(order.value, dragId.value, order.value.indexOf(id as any)))
    endDrag()
}
function endDrag() { dragId.value = ''; dropId.value = '' }
</script>

<style scoped>
.listing-layout{color:var(--el-text-color-primary)}.listing-layout__icon{margin-right:5px;font-size:16px}.listing-layout__head{display:flex;align-items:center;justify-content:space-between;gap:12px}.listing-layout__hint{margin:5px 0 12px;font-size:12px;line-height:1.6;color:var(--el-text-color-secondary)}.listing-layout__rows{display:flex;flex-direction:column;gap:8px}.listing-layout__row{display:flex;align-items:center;gap:8px;min-height:44px;padding:4px 8px;border:1px solid var(--el-border-color-lighter);border-radius:8px;background:var(--el-fill-color-blank);transition:border-color .15s}.listing-layout__row.is-target{border-color:var(--el-color-primary);background:var(--el-color-primary-light-9)}.listing-layout__row.is-dragging{opacity:.55}.listing-layout__handle{display:flex;align-items:center;justify-content:center;width:32px;height:32px;border:0;border-radius:4px;background:transparent;color:var(--el-text-color-secondary);cursor:grab;font-size:20px}.listing-layout__handle:focus-visible{outline:2px solid var(--el-color-primary)}.listing-layout__name{flex:1;min-width:0;font-size:13px}.listing-layout__row .el-button+.el-button{margin-left:0}.listing-layout__foot{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:12px}
</style>

