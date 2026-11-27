<template>
    <HsxDrawer v-model="visible" title="自动采集报价" subtitle="关联一次报价栏目，之后按天同步原图" size="lg"
        :body-loading="loading" :destroy-on-close="false" :before-close="beforeClose">
        <div class="quote-sync">
            <HsxNotice v-if="loadError" type="error" :title="loadError" :closable="false">
                <template #actions><el-button link type="primary" @click="loadConfig">重试加载</el-button></template>
            </HsxNotice>
            <template v-if="loaded">
                <HsxNotice title="只同步已关联栏目的报价图片" description="不会修改分类名称、封面、会员权限或业务价格；抓图失败保留旧报价，成功更新会留历史版本。原水印保留，请确认已获图片使用授权。" />
                <HsxNotice v-if="!queueEnabled" type="warning" title="采集队列尚未开启，暂不能发起采集"
                    description="配置可以先保存，请联系平台管理员开启队列并保持工作进程运行。任务排队不等于已经完成采集。" :closable="false" />
                <HsxNotice v-if="operationError" type="error" :title="operationError" :reset-key="operationError" />

                <section class="quote-sync__section">
                    <div class="quote-sync__section-head"><h3>1. 读取来源栏目</h3><span>每天自动下载最新原图</span></div>
                    <div class="quote-sync__source">
                        <el-input v-model="form.source_url" aria-label="报价来源地址" placeholder="https://13303016666.huishoubaojia.com/" :disabled="busy || active" />
                        <el-button :loading="discovering" :disabled="busyExceptDiscover || active" @click="discoverSources">读取栏目</el-button>
                    </div>
                    <p v-if="sourceChanged" class="quote-sync__hint is-warning">来源地址已改变，请重新读取栏目后再保存映射。</p>
                    <p v-else class="quote-sync__hint">填报价网站首页地址，不需要逐个复制图片链接。</p>
                </section>

                <section class="quote-sync__section">
                    <div class="quote-sync__section-head"><h3>2. 关联本站报价分类</h3><span>已关联 {{ enabledMappings.length }} 项</span></div>
                    <el-table :data="mappingRows" size="small" max-height="390" row-key="source_id" class="quote-sync__mapping">
                        <el-table-column label="对方报价栏目" min-width="185">
                            <template #default="{ row }">
                                <div class="quote-sync__source-name">{{ row.name }}</div>
                                <div class="quote-sync__muted">{{ row.group || '报价栏目' }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column label="本站报价分类" min-width="235">
                            <template #default="{ row }">
                                <el-select :model-value="row.mapping.category_id || undefined" filterable clearable placeholder="不关联则跳过"
                                    :disabled="busy || active || sourceChanged" class="quote-sync__category" :aria-label="row.name + '对应的本站分类'"
                                    @update:model-value="value => row.mapping.category_id = Number(value || 0)">
                                    <el-option v-for="option in categoryOptions" :key="option.value" :value="option.value" :label="option.label" />
                                </el-select>
                            </template>
                        </el-table-column>
                        <el-table-column label="同步" width="70" align="center">
                            <template #default="{ row }"><el-switch :model-value="row.mapping.category_id ? row.mapping.enabled : 0" :active-value="1" :inactive-value="0"
                                :disabled="!row.mapping.category_id || busy || active || sourceChanged" :aria-label="'同步' + row.group + row.name"
                                @update:model-value="value => row.mapping.enabled = Number(value)" /></template>
                        </el-table-column>
                        <template #empty><div class="quote-sync__empty">先点击“读取栏目”，再选择要同步的本站分类。</div></template>
                    </el-table>
                    <p class="quote-sync__hint">同一个本站分类只关联一个来源栏目。暂停某一项后，已采集的图片仍保留。</p>
                </section>

                <section class="quote-sync__schedule">
                    <div><strong>每天自动采集</strong><div class="quote-sync__muted">关闭后停止后续自动采集，仍可手动采集。</div></div>
                    <div class="quote-sync__schedule-controls">
                        <el-time-select v-model="form.run_time" start="00:00" step="00:30" end="23:30" :editable="false"
                            :clearable="false" :disabled="busy || active" placeholder="每日时间" aria-label="每日采集时间" />
                        <el-switch v-model="form.enabled" :active-value="1" :inactive-value="0" :disabled="busy || active" aria-label="每天自动采集" />
                    </div>
                </section>
                <p class="quote-sync__hint">北京时间；{{ form.enabled ? '保存后生效' : '自动采集已关闭' }}<template v-if="nextRunAt && form.enabled"> · 下次计划 {{ timeText(nextRunAt) }}</template></p>

                <section v-if="run.status !== 'idle'" class="quote-sync__result">
                    <div class="quote-sync__section-head">
                        <h3>本次采集 <el-tag :type="statusType(run.status)" size="small">{{ statusText(run.status) }}</el-tag></h3>
                        <div><el-button link type="primary" :loading="statusLoading" @click="refreshStatus(true)">刷新状态</el-button>
                            <el-button v-if="active" link type="danger" :loading="cancelling" @click="stopRun">停止本次</el-button></div>
                    </div>
                    <el-progress :percentage="progress" :status="run.status === 'failed' ? 'exception' : run.status === 'success' ? 'success' : undefined" :stroke-width="7" />
                    <div class="quote-sync__result-summary"><span>已处理 {{ run.done }} / {{ run.total }}</span><span>更新 {{ run.updated }}</span><span>未变化 {{ run.unchanged }}</span><span :class="{ 'is-warning': run.failed > 0 }">失败 {{ run.failed }}</span></div>
                    <p class="quote-sync__hint">{{ run.message || '等待处理' }}<template v-if="run.started_at"> · {{ timeText(run.started_at) }}</template></p>
                    <HsxNotice v-if="statusError" type="warning" :title="statusError" :closable="false">
                        <template #actions><el-button link type="primary" @click="refreshStatus(true)">重新查询</el-button></template>
                    </HsxNotice>
                    <el-collapse v-if="run.items.length" class="quote-sync__details">
                        <el-collapse-item title="查看逐项结果" name="items">
                            <div v-for="item in run.items" :key="item.source_id" class="quote-sync__item-result">
                                <span>{{ item.source_name }}</span><el-tag size="small" :type="item.status === 'failed' ? 'danger' : item.status === 'updated' ? 'success' : 'info'">{{ itemStatusText(item.status) }}</el-tag>
                                <span class="quote-sync__muted">{{ item.message }}</span>
                            </div>
                        </el-collapse-item>
                    </el-collapse>
                </section>

                <el-collapse v-if="recentRuns.length" class="quote-sync__history">
                    <el-collapse-item title="最近采集记录" name="history">
                        <div v-for="item in recentRuns.slice(0, 10)" :key="item.run_id" class="quote-sync__history-row">
                            <span>{{ timeText(item.finished_at || item.started_at) }}</span>
                            <el-tag size="small" :type="statusType(item.status)">{{ statusText(item.status) }}</el-tag>
                            <span>更新 {{ item.updated || 0 }} · 失败 {{ item.failed || 0 }}</span>
                            <span class="quote-sync__muted">{{ item.message }}</span>
                        </div>
                    </el-collapse-item>
                </el-collapse>
            </template>
        </div>
        <template #footer>
            <div class="quote-sync__footer">
                <span class="quote-sync__muted">{{ dirty ? '有未保存的配置，采集前请先保存' : active ? '关闭抽屉不会停止后台采集' : '仅影响已关联分类的报价图片' }}</span>
                <div><el-button @click="closeDrawer">关闭</el-button>
                    <el-button :loading="saving" :disabled="!loaded || !dirty || busyExceptSave || active" @click="saveConfig">保存配置</el-button>
                    <el-tooltip :disabled="!runDisabledReason" :content="runDisabledReason" placement="top">
                        <span><el-button type="primary" :loading="starting" :disabled="!!runDisabledReason" @click="startRun">立即采集</el-button></span>
                    </el-tooltip>
                </div>
            </div>
        </template>
    </HsxDrawer>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { HsxDrawer, HsxNotice, useFeedback } from '@/addon/hsx_components/core'
import {
    cancelQuoteImageSync, discoverQuoteImageSources, getQuoteImageSyncConfig, getQuoteImageSyncStatus,
    saveQuoteImageSyncConfig, startQuoteImageSync,
    type QuoteImageRun, type QuoteImageSourceItem, type QuoteImageSyncConfig, type QuoteImageSyncInput
} from '@/addon/hsx_recycle/api/quote_image_sync'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{ (event: 'update:modelValue', value: boolean): void; (event: 'updated'): void }>()
const feedback = useFeedback()
const visible = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) })
const form = reactive<QuoteImageSyncInput>({ enabled: 0, source_url: 'https://13303016666.huishoubaojia.com/', run_time: '09:30', mappings: [] })
const catalog = ref<QuoteImageSourceItem[]>([])
const catalogSource = ref('')
const categoryOptions = ref<Array<{ value: number; label: string }>>([])
const snapshot = ref('')
const queueEnabled = ref(false)
const loaded = ref(false)
const loading = ref(false)
const discovering = ref(false)
const saving = ref(false)
const starting = ref(false)
const cancelling = ref(false)
const statusLoading = ref(false)
const loadError = ref('')
const operationError = ref('')
const statusError = ref('')
const nextRunAt = ref(0)
const recentRuns = ref<QuoteImageRun[]>([])
const emptyRun = (): QuoteImageRun => ({ run_id: '', status: 'idle', message: '', total: 0, done: 0, updated: 0, unchanged: 0, failed: 0, started_at: 0, finished_at: 0, items: [] })
const run = ref<QuoteImageRun>(emptyRun())
let epoch = 0
let pollTimer: ReturnType<typeof setTimeout> | undefined
let pollFailures = 0
let notifiedRun = ''
const active = computed(() => ['queued', 'running'].includes(run.value.status))
const busy = computed(() => loading.value || discovering.value || saving.value || starting.value || cancelling.value)
const busyExceptSave = computed(() => loading.value || discovering.value || starting.value || cancelling.value)
const busyExceptDiscover = computed(() => loading.value || saving.value || starting.value || cancelling.value)
const enabledMappings = computed(() => form.mappings.filter(item => item.category_id > 0 && item.enabled === 1))
const formInput = (): QuoteImageSyncInput => ({ enabled: Number(form.enabled), source_url: form.source_url.trim(), run_time: form.run_time,
    mappings: form.mappings.map(item => ({ source_id: String(item.source_id), source_name: String(item.source_name), category_id: Number(item.category_id || 0), enabled: Number(item.enabled) })) })
const dirty = computed(() => loaded.value && JSON.stringify(formInput()) !== snapshot.value)
function sourceKey(value: string): string { try { return new URL(value).origin.toLowerCase() } catch { return value.trim() } }
const sourceChanged = computed(() => !!catalogSource.value && sourceKey(form.source_url) !== sourceKey(catalogSource.value))
const mappingRows = computed(() => form.mappings.map(mapping => {
    const source = catalog.value.find(item => String(item.id) === String(mapping.source_id))
    return { source_id: String(mapping.source_id), name: source?.name || mapping.source_name,
        group: source?.group || '来源栏目暂未找到，请核对或暂停', mapping }
}))
const progress = computed(() => run.value.total > 0 ? Math.min(100, Math.round(run.value.done / run.value.total * 100)) : 0)
const runDisabledReason = computed(() => {
    if (!loaded.value || loading.value) return '请先加载配置'
    if (active.value) return '本次采集尚未结束，请查看进度'
    if (busy.value) return '请等待当前操作完成'
    if (dirty.value) return '请先保存配置，再发起采集'
    if (!enabledMappings.value.length) return '请至少关联并启用一个报价栏目'
    if (!queueEnabled.value) return '请先由平台管理员开启采集队列'
    return ''
})
const errorText = (error: any, fallback: string) => String(error?.msg || error?.message || fallback).slice(0, 300)
const timeText = (value: number) => value ? new Date(value * 1000).toLocaleString('zh-CN', { timeZone: 'Asia/Shanghai', hour12: false }) : '—'
const statusLabels: Record<string, string> = { idle: '尚未采集', queued: '排队中', running: '采集中', success: '采集完成', partial: '部分失败', failed: '采集失败', cancelled: '已停止' }
const statusText = (value: string) => statusLabels[value] || value
const statusType = (value: string): 'success' | 'warning' | 'danger' | 'info' => value === 'success' ? 'success' : value === 'failed' ? 'danger' : value === 'partial' ? 'warning' : 'info'
const itemStatusLabels: Record<string, string> = { pending: '等待', running: '采集中', updated: '已更新', unchanged: '未变化', failed: '失败', skipped: '已跳过' }
const itemStatusText = (value: string) => itemStatusLabels[value] || value

function stopPolling() { if (pollTimer) clearTimeout(pollTimer); pollTimer = undefined }
function schedulePoll() {
    stopPolling()
    if (visible.value && active.value && pollFailures < 3) pollTimer = setTimeout(() => refreshStatus(), 2500)
}
function setStatus(data: any, notify = true) {
    const value = data?.status && typeof data.status === 'object' ? data.status : data
    if (value && typeof value.status === 'string') run.value = { ...emptyRun(), ...value, items: Array.isArray(value.items) ? value.items : [] }
    if (Array.isArray(data?.recent_runs)) recentRuns.value = data.recent_runs
    if (data?.queue_enabled !== undefined) queueEnabled.value = Boolean(data.queue_enabled)
    if (data?.next_run_at !== undefined || value?.next_run_at !== undefined) nextRunAt.value = Number(data.next_run_at ?? value.next_run_at ?? 0)
    if (notify && run.value.run_id && !active.value && notifiedRun !== run.value.run_id && run.value.updated > 0) {
        notifiedRun = run.value.run_id
        emit('updated')
    }
}
function applyConfig(data: QuoteImageSyncConfig) {
    form.enabled = Number(data.enabled || 0)
    form.source_url = data.source_url || 'https://13303016666.huishoubaojia.com/'
    form.run_time = data.run_time || '09:30'
    catalog.value = Array.isArray(data.source_catalog) ? data.source_catalog : []
    catalogSource.value = form.source_url
    const mappings = Array.isArray(data.mappings) ? data.mappings : []
    form.mappings = catalog.value.map(item => {
        const saved = mappings.find(mapping => String(mapping.source_id) === String(item.id))
        return { source_id: String(item.id), source_name: item.group ? `${item.group} / ${item.name}` : item.name,
            category_id: Number(saved?.category_id || 0), enabled: saved ? Number(saved.enabled) : 1 }
    })
    // 已关联的来源若暂未返回，也保留配置，避免一次读取异常就丢失映射。
    for (const item of mappings) if (!form.mappings.some(mapping => mapping.source_id === String(item.source_id))) form.mappings.push({ ...item })
    categoryOptions.value = Array.isArray(data.category_options) ? data.category_options : []
    queueEnabled.value = Boolean(data.queue_enabled)
    setStatus(data, false)
    notifiedRun = active.value ? '' : run.value.run_id
    snapshot.value = JSON.stringify(formInput())
}
async function loadConfig() {
    const ticket = epoch
    loading.value = true; loadError.value = ''; operationError.value = ''
    try {
        const response: any = await getQuoteImageSyncConfig()
        if (ticket !== epoch || !visible.value) return
        applyConfig(response.data || {})
        loaded.value = true
        schedulePoll()
    } catch (error) {
        if (ticket === epoch && visible.value) loadError.value = errorText(error, '配置加载失败，请重试')
    } finally { if (ticket === epoch) loading.value = false }
}
async function discoverSources() {
    if (busy.value || active.value) return
    const ticket = epoch
    const source = form.source_url.trim()
    discovering.value = true; operationError.value = ''
    try {
        const response: any = await discoverQuoteImageSources(source)
        if (ticket !== epoch || !visible.value) return
        const items: QuoteImageSourceItem[] = response.data?.items || []
        if (!items.length) throw new Error('未读到报价栏目，原来的映射未改动，请检查来源地址后重试')
        const sameSource = sourceKey(source) === sourceKey(catalogSource.value)
        const old = sameSource ? form.mappings : []
        catalog.value = items
        form.source_url = response.data?.source_url || source
        catalogSource.value = form.source_url
        form.mappings = items.map(item => {
            const saved = old.find(mapping => String(mapping.source_id) === String(item.id))
            return { source_id: String(item.id), source_name: item.group ? `${item.group} / ${item.name}` : item.name,
                category_id: Number(saved?.category_id || 0), enabled: saved ? Number(saved.enabled) : 1 }
        })
        for (const item of old) if (item.category_id && !form.mappings.some(mapping => mapping.source_id === String(item.source_id))) form.mappings.push({ ...item })
        feedback.success(`已读取 ${items.length} 个报价栏目，请关联本站分类并保存`)
    } catch (error) { if (ticket === epoch && visible.value) operationError.value = errorText(error, '读取栏目失败，原配置已保留') }
    finally { if (ticket === epoch) discovering.value = false }
}
function validateConfig(): string {
    if (sourceChanged.value) return '来源地址已改变，请先重新读取报价栏目'
    if (form.enabled && !enabledMappings.value.length) return '请先关联并启用至少一个报价栏目'
    const ids = enabledMappings.value.map(item => item.category_id)
    if (new Set(ids).size !== ids.length) return '同一个本站分类只能关联一个启用中的来源栏目'
    if (form.mappings.filter(item => item.category_id > 0).length > 32) return '最多关联 32 个报价栏目，请取消多余的分类关联'
    return ''
}
async function saveConfig() {
    if (busy.value || active.value || !loaded.value) return
    const problem = validateConfig()
    if (problem) { feedback.warning(problem); return }
    const ticket = epoch
    saving.value = true; operationError.value = ''
    try {
        const response: any = await saveQuoteImageSyncConfig(formInput())
        if (ticket !== epoch || !visible.value) return
        applyConfig(response.data || {})
        feedback.success('采集配置已保存')
    } catch (error) { if (ticket === epoch && visible.value) operationError.value = errorText(error, '保存失败，表单内容已保留') }
    finally { if (ticket === epoch) saving.value = false }
}
async function startRun() {
    if (runDisabledReason.value) return
    const ticket = epoch
    starting.value = true; operationError.value = ''; statusError.value = ''; pollFailures = 0
    try {
        const confirmed = await feedback.confirm({ title: '立即采集报价？', message: `将更新 ${enabledMappings.value.length} 个已关联分类的报价图片，旧报价会留在历史记录中；未关联的分类不受影响。`, confirmText: '开始采集', type: 'warning' })
        if (!confirmed || ticket !== epoch || !visible.value) return
        const response: any = await startQuoteImageSync()
        if (ticket !== epoch || !visible.value) return
        setStatus(response.data || {})
        schedulePoll()
    } catch (error) { if (ticket === epoch && visible.value) operationError.value = errorText(error, '未能发起采集，请检查队列状态后重试') }
    finally { if (ticket === epoch) starting.value = false }
}
async function refreshStatus(manual = false) {
    if (!visible.value || statusLoading.value) return
    const ticket = epoch
    if (manual) pollFailures = 0
    statusLoading.value = true
    try {
        const response: any = await getQuoteImageSyncStatus()
        if (ticket !== epoch || !visible.value) return
        const wasActive = active.value
        setStatus(response.data || {})
        statusError.value = ''; pollFailures = 0
        if ((wasActive || manual) && !active.value && !dirty.value) await reloadFinishedConfig(ticket)
    } catch (error) {
        if (ticket === epoch && visible.value) {
            pollFailures++
            statusError.value = pollFailures >= 3 ? '暂时无法取得进度，已暂停自动查询。任务可能仍在执行，请点击重新查询，不要重复发起。' : errorText(error, '进度查询失败，稍后重试')
        }
    } finally {
        if (ticket === epoch) { statusLoading.value = false; schedulePoll() }
    }
}
async function reloadFinishedConfig(ticket: number) {
    const response: any = await getQuoteImageSyncConfig()
    if (ticket === epoch && visible.value && !dirty.value) applyConfig(response.data || {})
}
async function stopRun() {
    if (!active.value || busy.value) return
    const expectedEpoch = epoch
    const expectedRun = run.value.run_id
    const confirmed = await feedback.confirm({ title: '停止本次采集？', message: '只停止尚未完成的栏目，已经更新成功的报价图片不会撤回。每天自动采集的开关不会改变。', confirmText: '停止本次', type: 'warning' })
    if (!confirmed || expectedEpoch !== epoch || expectedRun !== run.value.run_id || !visible.value || !active.value) return
    const ticket = epoch
    cancelling.value = true; operationError.value = ''
    try {
        const response: any = await cancelQuoteImageSync(run.value.run_id)
        if (ticket !== epoch || !visible.value) return
        setStatus(response.data || {})
        if (!active.value && !dirty.value) await reloadFinishedConfig(ticket)
        schedulePoll()
    } catch (error) { if (ticket === epoch && visible.value) operationError.value = errorText(error, '停止请求未确认，请刷新状态核实') }
    finally { if (ticket === epoch) cancelling.value = false }
}
async function beforeClose(done: () => void) {
    if (saving.value || starting.value || cancelling.value) return
    if (dirty.value && !await feedback.confirm({ title: '关闭采集设置？', message: '尚有未保存的配置，关闭后本次修改不会生效。', confirmText: '放弃修改并关闭', type: 'warning' })) return
    done()
}
function closeDrawer() { beforeClose(() => { visible.value = false }) }
watch(() => props.modelValue, value => {
    epoch++; stopPolling(); pollFailures = 0
    loading.value = false; saving.value = false; discovering.value = false; starting.value = false; cancelling.value = false; statusLoading.value = false
    if (value) { loaded.value = false; run.value = emptyRun(); loadConfig() }
}, { immediate: true })
onBeforeUnmount(() => { epoch++; stopPolling() })
</script>

<style scoped lang="scss">
.quote-sync { display: flex; flex-direction: column; gap: 16px; color: var(--hsx-text-primary); }
.quote-sync__section { border-bottom: 1px solid var(--hsx-border-color); padding-bottom: 16px; }
.quote-sync__section-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 10px; }
.quote-sync__section-head h3 { display: flex; align-items: center; gap: 8px; font-size: 14px; margin: 0; }
.quote-sync__section-head > span, .quote-sync__muted, .quote-sync__hint { color: var(--hsx-text-secondary); font-size: 12px; line-height: 1.6; }
.quote-sync__source { display: flex; gap: 8px; }
.quote-sync__source .el-input { flex: 1; min-width: 0; }
.quote-sync__hint { margin: 8px 0 0; }
.quote-sync__category { width: 100%; }
.quote-sync__source-name { font-weight: 500; line-height: 1.6; }
.quote-sync__empty { padding: 16px; font-size: 13px; }
.quote-sync__schedule { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.quote-sync__schedule strong { font-size: 14px; }
.quote-sync__schedule-controls { display: flex; align-items: center; gap: 12px; }
.quote-sync__schedule-controls .el-select { width: 115px; }
.quote-sync__result { border: 1px solid var(--hsx-border-color); background: var(--hsx-bg-muted); border-radius: 8px; padding: 14px; }
.quote-sync__result-summary { display: flex; flex-wrap: wrap; gap: 8px 18px; font-size: 12px; margin-top: 10px; }
.quote-sync__result .hsx-notice { margin-top: 10px; }
.quote-sync__details { margin-top: 8px; }
.quote-sync__item-result { display: grid; grid-template-columns: minmax(140px, 1fr) 72px minmax(180px, 2fr); gap: 10px; align-items: start; padding: 7px 0; }
.quote-sync__history-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; padding: 7px 0; font-size: 12px; border-bottom: 1px solid var(--hsx-border-color); }
.quote-sync__footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.quote-sync__footer > div { display: flex; align-items: center; gap: 8px; }
.quote-sync__footer .el-button + .el-button { margin-left: 0; }
.is-warning { color: var(--el-color-warning-dark-2); }
@media (max-width: 860px) {
    .quote-sync__section-head { align-items: flex-start; }
    .quote-sync__footer { flex-wrap: wrap; }
    .quote-sync__footer > div { margin-left: auto; }
}
@media (max-width: 520px) {
    .quote-sync__source, .quote-sync__schedule { flex-wrap: wrap; }
    .quote-sync__source .el-input { flex-basis: 100%; }
    .quote-sync__item-result { grid-template-columns: 1fr 72px; }
    .quote-sync__item-result > .quote-sync__muted { grid-column: 1 / -1; }
}
</style>
