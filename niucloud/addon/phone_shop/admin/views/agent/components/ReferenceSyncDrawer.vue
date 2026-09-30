<template>
    <el-drawer v-model="visible" title="基础资料同步" size="720px" class="reference-sync-drawer" destroy-on-close @close="stop">
        <div v-loading="loading" class="reference-sync">
            <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon />
            <el-button v-if="!info && !loading" :icon="Refresh" @click="load(true)">重新加载</el-button>
            <template v-if="info">
                <div class="reference-sync__relation">
                    <span>主站 <strong>{{ info.master_site_id }}</strong></span>
                    <el-icon><Right /></el-icon>
                    <span>子站 <strong>{{ info.agent_site_id }}</strong></span>
                    <el-tag v-if="info.relation_status !== 1" type="danger">跟随已停用</el-tag>
                </div>
                <el-form :model="form" label-position="top" class="reference-sync__form" :disabled="saving || processing">
                    <el-form-item label="同步方式">
                        <el-radio-group v-model="form.ref_sync_mode">
                            <el-radio-button label="manual">仅手动</el-radio-button>
                            <el-radio-button label="interval">定时同步</el-radio-button>
                            <el-radio-button label="realtime">实时跟随</el-radio-button>
                        </el-radio-group>
                    </el-form-item>
                    <el-form-item v-if="form.ref_sync_mode === 'interval'" label="同步周期">
                        <el-select v-model="form.ref_sync_interval" class="reference-sync__interval">
                            <el-option v-for="item in intervals" :key="item.value" :label="item.label" :value="item.value" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="自动新增并关联缺失分类">
                        <el-switch v-model="form.ref_auto_create_category" :active-value="1" :inactive-value="0" />
                    </el-form-item>
                    <div class="reference-sync__policy">已关联分类：跟随主站名称、层级和商品归属。未关联的本地分类：保留。</div>
                    <el-button v-permission="'phone_shop_agent_edit'" type="primary" :icon="Check" :loading="saving" :disabled="!dirty" @click="save">保存设置</el-button>
                    <span v-if="dirty" class="reference-sync__unsaved">设置未保存</span>
                </el-form>

                <section class="reference-sync__result">
                    <div class="reference-sync__result-head">
                        <h3>同步结果</h3>
                        <el-tag :type="statusMeta.type">{{ statusMeta.label }}</el-tag>
                    </div>
                    <dl class="reference-sync__times">
                        <div><dt>最近完成</dt><dd>{{ formatTime(info.last_time) }}</dd></div>
                        <div><dt>下次定时</dt><dd>{{ formatTime(info.next_time) }}</dd></div>
                    </dl>
                    <div v-if="active" class="reference-sync__progress" role="status" aria-live="polite">
                        {{ info.status === 'queued' ? '等待队列执行' : processing ? '正在同步' : '同步进行中' }} · 已处理 {{ info.result?.scanned || 0 }} 条
                    </div>
                    <el-alert v-if="info.result?.error_message" :title="info.result.error_message" type="error" :closable="false" show-icon />
                    <el-table :data="resultRows" empty-text="尚未同步" size="default">
                        <el-table-column prop="name" label="资料" min-width="150" />
                        <el-table-column prop="scanned" label="已处理" width="85" align="right" />
                        <el-table-column prop="success" label="成功" width="75" align="right" />
                        <el-table-column label="待处理" width="85" align="right">
                            <template #default="{ row }"><span :class="{ 'reference-sync__failed': row.failed > 0 }">{{ row.failed }}</span></template>
                        </el-table-column>
                    </el-table>
                    <el-collapse v-if="info.result?.errors?.length" class="reference-sync__errors">
                        <el-collapse-item title="待处理原因（最多展示 20 条）" name="errors">
                            <ul><li v-for="(item, index) in info.result.errors" :key="index">
                                <strong>{{ info.result.types?.[item.type]?.name || item.type }} · {{ item.source_id }}</strong>
                                <p>{{ item.message }}</p>
                            </li></ul>
                            <el-button type="primary" link @click="emit('open-category', agentSiteId)">处理分类映射</el-button>
                        </el-collapse-item>
                    </el-collapse>
                </section>
            </template>
        </div>
        <template #footer>
            <div class="reference-sync__footer">
                <el-tooltip content="刷新同步结果" placement="top"><el-button :icon="Refresh" circle :loading="loading" :disabled="processing || saving" aria-label="刷新同步结果" @click="load(false)" /></el-tooltip>
                <el-button @click="visible = false">关闭</el-button>
                <el-button v-permission="'phone_shop_agent_reference_start'" type="primary" :loading="processing" :disabled="!info || loading || saving || dirty || info.relation_status !== 1 || (active && !canResume)" @click="run">
                    {{ canResume && !processing ? '继续同步' : '立即同步基础资料' }}
                </el-button>
            </div>
        </template>
    </el-drawer>
</template>

<script lang="ts" setup>
import { computed, onBeforeUnmount, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Check, Refresh, Right } from '@element-plus/icons-vue'
import { editAgent, getAgentReferenceSync, startAgentReferenceSync, stepAgentReferenceSync } from '@/addon/phone_shop/api/agent'

const emit = defineEmits<{ (event: 'changed'): void; (event: 'open-category', id: number): void }>()
const visible = ref(false)
const loading = ref(false)
const saving = ref(false)
const processing = ref(false)
const error = ref('')
const agentSiteId = ref(0)
const info = ref<any>(null)
const form = reactive({ ref_sync_mode: 'manual', ref_sync_interval: 60, ref_auto_create_category: 1 })
const intervals = [15, 30, 60, 180, 360, 720, 1440].map(value => ({ value, label: value < 60 ? `每 ${value} 分钟` : value < 1440 ? `每 ${value / 60} 小时` : '每天' }))
const active = computed(() => ['queued', 'running'].includes(info.value?.status))
const canResume = computed(() => active.value && info.value?.result?.trigger === 'manual')
const dirty = computed(() => !!info.value && Object.entries(form).some(([key, value]) => value !== info.value.settings?.[key]))
const resultRows = computed(() => Object.values(info.value?.result?.types || {}))
const statuses = {
    idle: { label: '尚未同步', type: 'info' }, queued: { label: '等待执行', type: 'warning' },
    running: { label: '同步中', type: 'primary' }, success: { label: '已完成', type: 'success' },
    partial: { label: '部分完成', type: 'warning' }, failed: { label: '未完成', type: 'danger' }
} as const
const statusMeta = computed(() => statuses[info.value?.status as keyof typeof statuses] || statuses.idle)
const formatTime = (value: number) => value ? new Date(value * 1000).toLocaleString('zh-CN', { hour12: false }) : '暂无'
const message = (e: any) => String(e?.msg || e?.message || '同步请求失败，请重试')
let generation = 0
let poll: ReturnType<typeof setTimeout> | undefined

function stop() {
    generation++
    if (poll) clearTimeout(poll)
    poll = undefined
    processing.value = false
    saving.value = false
}
function scheduleRefresh() {
    if (poll) clearTimeout(poll)
    if (visible.value && active.value && !canResume.value && !processing.value) poll = setTimeout(() => load(false), 4000)
}
async function load(resetForm = false) {
    const current = generation
    loading.value = true
    try {
        const res: any = await getAgentReferenceSync(agentSiteId.value)
        if (current !== generation) return
        info.value = res.data
        if (resetForm) Object.assign(form, res.data.settings)
        error.value = ''
    } catch (e) {
        if (current === generation) error.value = message(e)
    } finally {
        if (current === generation) { loading.value = false; scheduleRefresh() }
    }
}
async function open(siteId: number) {
    stop()
    info.value = null
    error.value = ''
    agentSiteId.value = siteId
    visible.value = true
    await load(true)
}
async function save() {
    const current = generation
    saving.value = true
    try {
        await editAgent(info.value.id, { ...form })
        if (current !== generation) return
        ElMessage.success('同步设置已保存')
        emit('changed')
        await load(true)
    } catch (e) {
        if (current === generation) error.value = message(e)
    } finally { if (current === generation) saving.value = false }
}
async function run() {
    if (processing.value || dirty.value) return
    const current = generation
    processing.value = true
    if (poll) clearTimeout(poll)
    error.value = ''
    try {
        if (!canResume.value) {
            const res: any = await startAgentReferenceSync(agentSiteId.value)
            if (current !== generation) return
            info.value = res.data
        }
        while (!info.value.result?.done && current === generation) {
            const res: any = await stepAgentReferenceSync({ agent_site_id: agentSiteId.value, token: info.value.token, revision: info.value.result?.revision || 0 })
            if (current !== generation) return
            info.value = res.data
        }
        if (current === generation) {
            if (info.value.status === 'success') ElMessage.success('基础资料及商品分类关联已同步')
            else ElMessage.warning('同步有待处理项，请查看原因')
            emit('changed')
        }
    } catch (e) {
        if (current === generation) {
            await load(false)
            if (current === generation) error.value = message(e)
        }
    } finally {
        if (current === generation) processing.value = false
    }
}
onBeforeUnmount(stop)
defineExpose({ open })
</script>

<style lang="scss" scoped>
:global(.reference-sync-drawer) { max-width: 100vw; }
.reference-sync { min-height: 180px; color: #303133; }
.reference-sync__relation { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 0 0 20px; border-bottom: 1px solid #e4e7ed; }
.reference-sync__form { padding: 22px 0; border-bottom: 1px solid #e4e7ed; }
.reference-sync__interval { width: 240px; max-width: 100%; }
.reference-sync__policy { color: #606266; font-size: 13px; line-height: 1.7; margin-bottom: 18px; }
.reference-sync__unsaved { margin-left: 12px; color: #b45309; font-size: 13px; }
.reference-sync__result { padding-top: 24px; }
.reference-sync__result-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; h3 { margin: 0; font-size: 16px; font-weight: 600; } }
.reference-sync__times { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin: 18px 0; font-size: 13px; dt { color: #909399; margin-bottom: 6px; } dd { margin: 0; overflow-wrap: anywhere; } }
.reference-sync__progress { padding: 10px 0; font-size: 13px; color: var(--el-color-primary); }
.reference-sync__failed { color: #d97706; }
.reference-sync__errors { margin-top: 16px; ul { list-style: none; margin: 0; padding: 0; } li { padding: 8px 0; border-bottom: 1px solid #ebeef5; } p { margin: 4px 0; overflow-wrap: anywhere; color: #606266; } }
.reference-sync__footer { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; :deep(.el-button + .el-button) { margin-left: 0; } }
@media (max-width: 480px) { .reference-sync__times { grid-template-columns: 1fr; } }
</style>
