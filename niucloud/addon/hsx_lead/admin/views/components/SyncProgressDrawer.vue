<template>
    <HsxDrawer v-model="visible" title="库存同步进度" subtitle="只有完整读取成功才发布库存快照；不会锁货。" size="sm" :body-loading="loading">
        <el-alert v-if="error" :title="error" type="error" :closable="false" class="lead-error-banner" />
        <div class="lead-status-line"><el-tag :type="run.status === 'failed' ? 'danger' : run.status === 'completed' ? 'success' : 'info'">{{ syncLabel(run.status) }}</el-tag><span class="lead-muted">任务 #{{ run.id || run.run_id || runId }}</span></div>
        <p>{{ run.message || run.error_message || '正在读取任务状态…' }}</p>
        <el-descriptions :column="1" border><el-descriptions-item label="已取分页">{{ run.page_count ?? 0 }} 页</el-descriptions-item><el-descriptions-item label="已取记录">{{ run.record_count ?? 0 }} 条</el-descriptions-item><el-descriptions-item label="开始时间">{{ time(run.started_at || run.create_time) }}</el-descriptions-item><el-descriptions-item label="结束时间">{{ time(run.finished_at) }}</el-descriptions-item></el-descriptions>
        <el-alert title="本页面逐页推进；离开后由已启用的牛云计划任务继续。若未启用计划任务，请回到本页继续。网络失败只重试本任务，不从头创建。" type="info" :closable="false" show-icon style="margin-top: 18px" />
        <template #footer><el-button @click="visible = false">关闭</el-button><el-button :loading="loading" @click="poll">继续 / 刷新进度</el-button></template>
    </HsxDrawer>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import HsxDrawer from '@/addon/hsx_components/components/HsxDrawer/index.vue'
import { advanceSync, getSync } from '../../api'
import { errorText, syncLabel, time, type Row } from '../../composables/useLeadUi'
const props = defineProps<{ modelValue: boolean, runId: number }>()
const emit = defineEmits<{ (e: 'update:modelValue', value: boolean): void, (e: 'changed'): void }>()
const visible = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) })
const run = ref<Row>({}), loading = ref(false), error = ref('')
let timer: ReturnType<typeof setTimeout> | undefined, sequence = 0
function stop() { if (timer) clearTimeout(timer); timer = undefined }
async function poll() {
    stop(); if (!visible.value || !props.runId || loading.value) return
    const token = ++sequence; loading.value = true; error.value = ''
    try { let result = await getSync(props.runId); if (token !== sequence || !visible.value) return; run.value = result.data; if (['pending', 'running', 'queued'].includes(run.value.status)) { result = await advanceSync(props.runId); if (token !== sequence || !visible.value) return; run.value = result.data }; if (['pending', 'running', 'queued'].includes(run.value.status)) timer = setTimeout(poll, 1500); else emit('changed') }
    catch (cause) { if (token === sequence) error.value = errorText(cause, '读取失败；任务可能仍在执行，请继续当前任务。') }
    finally { if (token === sequence) loading.value = false }
}
watch(() => [props.modelValue, props.runId], () => { stop(); sequence++; loading.value = false; if (visible.value) { run.value = {}; void poll() } }, { immediate: true })
onBeforeUnmount(() => { stop(); sequence++ })
</script>
