<template>
    <HsxDrawer
        v-model="visible"
        title="ERP 登录渠道"
        subtitle="设置商户看到的渠道名称及本站开关，不修改 ERP 账号密码。"
        size="md"
        :body-loading="loading"
        :confirm-loading="saving"
        :before-close="beforeClose"
    >
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="lead-error-banner" />
        <el-alert
            title="关闭渠道会同时暂停该渠道已有连接的采集"
            :description="policy || defaultPolicy"
            type="info"
            :closable="false"
            show-icon
            class="lead-banner"
        />
        <HsxTable :columns="columns" :data="rows" row-key="key" empty-text="暂无可管理的 ERP 渠道">
            <template #channel="{ row }"
                ><div class="lead-cell lead-channel-name"
                    ><el-input
                        v-model="row.display_name"
                        maxlength="24"
                        show-word-limit
                        :disabled="!canEdit || loading || saving || awaitingReadback"
                        :aria-label="`${row.name}的商户端展示名称`"
                        placeholder="填写商户端展示名称"
                    /><details class="lead-channel-technical"
                        ><summary>接口识别信息</summary><span>{{ row.technical_name }} · {{ row.key }}</span
                        ><small
                            >{{ row.renewal?.automatic === true ? '支持账号密码自动续登' : '按渠道要求完成登录验证' }}</small
                        ></details
                    ></div
                ></template
            >
            <template #connections="{ row }"
                ><div class="lead-cell"
                    ><span>{{ row.connection_count }} 个已接入连接</span
                    ><small>{{ row.automatic_sync_count }} 个原本开启自动同步</small></div
                ></template
            >
            <template #enabled="{ row }"
                ><el-switch
                    v-model="row.enabled"
                    :disabled="!canEdit || loading || saving || awaitingReadback"
                    :aria-label="`${row.name}登录渠道`"
                /><span class="lead-channel-state">{{ row.enabled ? '开启' : '关闭' }}</span></template
            >
        </HsxTable>
        <el-alert
            v-if="closing.length"
            :title="`本次将关闭：${closing.map(item => item.display_name).join('、')}`"
            :description="`涉及 ${impact.connections} 个连接，其中 ${impact.automatic} 个原本开启自动同步。保存后暂停采集，历史数据与已保存账号仍保留。`"
            type="warning"
            show-icon
            :closable="false"
            class="lead-channel-impact"
        />
        <p v-if="!rows.some(item => item.enabled) && ready" class="lead-muted"
            >全部关闭后，商户将没有可新增接入的 ERP 渠道；仍可查看历史资料。</p
        >
        <template #footer
            ><el-button
                :disabled="saving"
                @click="reload"
                >{{ awaitingReadback ? '核对保存结果' : '重新读取' }}</el-button
            ><el-button :disabled="saving" @click="requestClose">关闭</el-button
            ><el-button
                v-if="canEdit"
                type="primary"
                :loading="saving"
                :disabled="loading || !ready || !dirty || awaitingReadback"
                @click="save"
                >保存渠道设置</el-button
            ></template
        >
    </HsxDrawer>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import HsxDrawer from '@/addon/hsx_components/components/HsxDrawer/index.vue'
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import { getProviderChannels, saveProviderChannels } from '../../api/cooperation'
import { coopError, type CoopRow } from '../../composables/cooperation'
const props = withDefaults(defineProps<{ modelValue: boolean; canEdit?: boolean }>(), { canEdit: false })
const emit = defineEmits<{ (event: 'update:modelValue', value: boolean): void; (event: 'changed'): void }>()
const visible = computed({ get: () => props.modelValue, set: value => { if (!saving.value) emit('update:modelValue', value) } })
const rows = ref<CoopRow[]>([]), baseline = ref<CoopRow[]>([]), revision = ref(''), policy = ref(''), loading = ref(false), saving = ref(false), ready = ref(false), error = ref(''), awaitingReadback = ref(false)
const defaultPolicy = '新授权和手动、自动采集均暂停，历史与账号不删除。在途响应不会发布。重新开启时，仅按商户原来的自动同步开关恢复。'
const values = (items: CoopRow[]) => JSON.stringify(items.map(item => ({ key: item.key, enabled: item.enabled, display_name: item.display_name })).sort((a, b) => a.key.localeCompare(b.key)))
const dirty = computed(() => values(rows.value) !== values(baseline.value))
const closing = computed(() => rows.value.filter(item => !item.enabled && baseline.value.some(old => old.key === item.key && old.enabled)))
const impact = computed(() => closing.value.reduce((sum, row) => ({ connections: sum.connections + row.connection_count, automatic: sum.automatic + row.automatic_sync_count }), { connections: 0, automatic: 0 }))
const columns = [{ prop: 'display_name', label: '商户端展示名称', slot: 'channel', minWidth: 280 }, { prop: 'connection_count', label: '当前影响范围', slot: 'connections', minWidth: 180 }, { prop: 'enabled', label: '本站开关', slot: 'enabled', width: 130 }]
let sequence = 0, disposed = false
function normalize(data: CoopRow) {
    if (!data || typeof data.revision !== 'string' || !data.revision || !Array.isArray(data.items)) throw new Error('渠道配置返回不完整，未覆盖当前修改')
    const keys = new Set<string>()
    const items = data.items.map((item: CoopRow) => {
        if (typeof item.key !== 'string' || !item.key || keys.has(item.key) || typeof item.name !== 'string' || typeof item.display_name !== 'string' || typeof item.technical_name !== 'string' || typeof item.enabled !== 'boolean') throw new Error('渠道配置格式异常，未覆盖当前修改')
        keys.add(item.key)
        return { ...item, connection_count: Math.max(0, Number(item.connection_count) || 0), automatic_sync_count: Math.max(0, Number(item.automatic_sync_count) || 0) }
    })
    return { items, revision: data.revision, policy: String(data.policy_message || '') }
}
async function read(keepDraft = false) {
    const token = ++sequence; loading.value = true; error.value = ''
    try {
        const result = await getProviderChannels(), value = normalize(result.data || {})
        if (disposed || token !== sequence || !props.modelValue) return
        const draft = new Map(rows.value.map(item => [item.key, { enabled: item.enabled, display_name: item.display_name }])), equal = values(rows.value) === values(value.items)
        baseline.value = value.items.map(item => ({ ...item })); revision.value = value.revision; policy.value = value.policy; ready.value = true; awaitingReadback.value = false
        rows.value = keepDraft && !equal ? value.items.map(item => ({ ...item, ...(draft.get(item.key) || {}) })) : value.items
        if (keepDraft && !equal) error.value = '服务器设置与本次草稿不同，草稿已保留；请核对后再保存。若其他管理员刚改过配置，请先确认最新设置。'
        else if (keepDraft) { ElMessage.success('渠道设置已保存并核对'); emit('changed') }
    } catch (cause) { if (!disposed && token === sequence) error.value = coopError(cause, '渠道配置读取失败，请重试；当前修改已保留') }
    finally { if (!disposed && token === sequence) loading.value = false }
}
async function reload() {
    if (saving.value || loading.value) return
    if (awaitingReadback.value) return read(true)
    if (dirty.value) { try { await ElMessageBox.confirm('重新读取会替换尚未保存的展示名称与渠道开关，是否继续？', '保留修改或重新读取', { type: 'warning', confirmButtonText: '放弃修改并读取', cancelButtonText: '继续编辑' }) } catch { return } }
    await read()
}
async function save() {
    if (!props.canEdit || loading.value || saving.value || !ready.value || !dirty.value || awaitingReadback.value) return
    for (const item of rows.value) {
        if (typeof item.display_name !== 'string' || /[<>\p{C}]/u.test(item.display_name) || !item.display_name.trim() || Array.from(item.display_name.trim()).length > 24) { error.value = '请为每个渠道填写1—24字的纯文本展示名称，不允许HTML或控制字符'; return }
        item.display_name = item.display_name.trim()
    }
    saving.value = true; error.value = ''
    try {
        if (closing.value.length) {
            try { await ElMessageBox.confirm(`将关闭 ${closing.value.map(item => item.display_name).join('、')}，暂停 ${impact.value.connections} 个连接的采集（其中 ${impact.value.automatic} 个原本开启自动同步）。历史和账号保留；重新开启不会替商户打开其自行关闭的同步。确认保存？`, '确认关闭 ERP 渠道', { type: 'warning', confirmButtonText: '确认关闭并保存', cancelButtonText: '继续编辑' }) } catch { return }
        }
        awaitingReadback.value = true
        await saveProviderChannels({ revision: revision.value, items: rows.value.map(({ key, enabled, display_name }) => ({ key, enabled, display_name })) })
        await read(true)
    } catch (cause) { error.value = coopError(cause, '保存结果尚未确认，当前草稿已保留'); error.value += '；请先点击“核对保存结果”，不要重复提交。' }
    finally { saving.value = false }
}
async function beforeClose(done: () => void) { if (saving.value) return; if (dirty.value || awaitingReadback.value) { try { await ElMessageBox.confirm(awaitingReadback.value ? '保存结果尚未核对，关闭后请重新打开确认。是否关闭？' : '有未保存的渠道修改，关闭后将不保留本次草稿。是否关闭？', '关闭渠道设置', { type: 'warning', confirmButtonText: '确认关闭', cancelButtonText: '继续查看' }) } catch { return } }; done() }
function requestClose() { void beforeClose(() => { visible.value = false }) }
watch(() => props.modelValue, value => { sequence++; if (value) { rows.value = []; baseline.value = []; revision.value = ''; error.value = ''; ready.value = false; awaitingReadback.value = false; void read() } }, { immediate: true })
onBeforeUnmount(() => { disposed = true; sequence++ })
</script>
<style scoped>
.lead-channel-state { margin-left: 8px; font-size: 12px; color: var(--el-text-color-secondary); }.lead-channel-impact { margin-top: 16px; }
.lead-channel-name { max-width: 340px; gap: 8px; }.lead-channel-technical { font-size: 12px; color: var(--el-text-color-secondary); }.lead-channel-technical summary { cursor: pointer; width: fit-content; }.lead-channel-technical span,.lead-channel-technical small { display: block; margin-top: 5px; overflow-wrap: anywhere; }
</style>
