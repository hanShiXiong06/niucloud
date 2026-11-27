<template>
    <div class="credential" :data-testid="'credential-' + field">
        <div v-if="saved && !editing" class="credential-row">
            <div class="credential-saved" :aria-label="label + '已保存，内容已隐藏'">
                <span class="credential-mask" aria-hidden="true">******</span>
                <span class="credential-status">已保存</span>
            </div>
            <el-button :disabled="disabled" :aria-label="'更换' + label" @click="editing = true">更换</el-button>
        </div>
        <div v-else class="credential-row">
            <el-input
                :model-value="modelValue"
                :aria-label="label"
                :disabled="disabled"
                type="password"
                show-password
                autocomplete="new-password"
                :placeholder="saved ? '输入新值，留空保留原值' : '请输入' + label"
                @update:model-value="emit('update:modelValue', $event)"
            />
            <el-button v-if="saved" :disabled="disabled" :aria-label="'取消更换' + label" @click="cancel">取消</el-button>
        </div>
        <div v-if="saved && editing" class="credential-hint">保存后替换；取消则保留原密钥。</div>
    </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'

const props = withDefaults(defineProps<{
    modelValue: string
    label: string
    field: string
    saved: boolean
    disabled?: boolean
}>(), { disabled: false })
const emit = defineEmits<{ (event: 'update:modelValue', value: string): void }>()
const editing = ref(false)

// 掩码只用于展示，不进入 v-model；取消替换不会清除服务端已保存的凭据。
function cancel() { emit('update:modelValue', ''); editing.value = false }
watch(() => props.saved, () => { editing.value = false })
</script>

<style scoped>
.credential { width: 100%; min-width: 0; }
.credential-row { display: flex; align-items: center; gap: 8px; min-width: 0; }
.credential-row > .el-input { flex: 1; min-width: 0; }
.credential-row > .el-button { flex: none; margin-left: 0; padding-inline: 12px; }
.credential-saved { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; min-height: 32px; padding: 0 11px; border: 1px solid var(--el-border-color); border-radius: 4px; background: var(--el-fill-color-light); line-height: 30px; }
.credential-mask { color: var(--el-text-color-primary); font-family: monospace; letter-spacing: 2px; }
.credential-status { margin-left: auto; flex: none; color: var(--el-color-success-dark-2); font-size: 12px; }
.credential-hint { margin-top: 4px; color: var(--el-text-color-secondary); font-size: 12px; line-height: 1.5; }
</style>
