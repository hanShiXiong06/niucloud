<template>
    <el-form class="agent-display-settings" label-position="top" @submit.prevent="save">
        <el-form-item label="主站显示名称">
            <div class="display-controls">
                <el-input v-model="name" aria-label="主站显示名称" :placeholder="config.default_agent_name || '代理仓'" :disabled="saving" maxlength="12" show-word-limit clearable />
                <el-button type="primary" native-type="submit" :icon="Check" :loading="saving" :disabled="!dirty">保存名称</el-button>
            </div>
        </el-form-item>
    </el-form>
</template>

<script lang="ts" setup>
import { computed, ref, watch } from 'vue'
import { Check } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { setAgentDisplayConfig } from '@/addon/phone_shop/api/agent'

const props = defineProps<{ config: { agent_name?: string; default_agent_name?: string } }>()
const emit = defineEmits<{ saved: [config: any] }>()
const name = ref('')
const saving = ref(false)
const dirty = computed(() => name.value.trim() !== (props.config.agent_name || ''))
watch(() => props.config.agent_name, value => { name.value = value || '' }, { immediate: true })

const save = async () => {
    if (!dirty.value || saving.value) return
    saving.value = true
    try {
        const res: any = await setAgentDisplayConfig({ agent_name: name.value.trim() })
        emit('saved', res.data)
        ElMessage.success('显示名称已保存')
    } catch {
        // 请求层展示接口错误，保留输入供重试。
    } finally {
        saving.value = false
    }
}
</script>

<style lang="scss" scoped>
.agent-display-settings {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--el-border-color-lighter);
    :deep(.el-form-item) { margin-bottom: 0; }
}
.display-controls {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    width: 100%;
    :deep(.el-input) { width: 260px; max-width: 100%; }
    :deep(.el-button) { flex-shrink: 0; margin-left: 0; }
}
</style>
