<template>
    <PremiumTheme class="check-severity-page">
        <el-card class="box-card" shadow="never">
            <template #header>
                <PageHeader title="选项级别" description="导出全部质检选项，由人工或 Codex 标注，预检无误后一次确认更新。">
                    <template #actions>
                        <el-button :loading="exportLoading" @click="handleExport">导出协作表</el-button>
                        <el-button type="primary" @click="openImport">导入更新</el-button>
                        <el-button :loading="loading" @click="loadList">刷新</el-button>
                    </template>
                </PageHeader>
            </template>

            <HsxNotice default-expanded class="source-alert" type="warning" :closable="false" show-icon>
                <template #title>级别由人工确认，系统不会自动判断</template>
                当前版本以选项字典作为实时数据源。确认导入后，历史订单、ERP 和商城重新读取质检报告时也会使用新级别，请先预检再确认。
            </HsxNotice>

            <el-row :gutter="12" class="summary-row">
                <el-col :xs="12" :sm="8" :md="4"><div class="metric metric--pending"><span>待人工确认</span><strong>{{ summary.pending }}</strong></div></el-col>
                <el-col :xs="12" :sm="8" :md="4"><div class="metric metric--confirmed"><span>已确认</span><strong>{{ summary.confirmed }}</strong></div></el-col>
                <el-col :xs="12" :sm="8" :md="4"><div class="metric metric--normal"><span>正常</span><strong>{{ summary.normal }}</strong></div></el-col>
                <el-col :xs="12" :sm="8" :md="4"><div class="metric metric--general"><span>一般</span><strong>{{ summary.general }}</strong></div></el-col>
                <el-col :xs="12" :sm="8" :md="4"><div class="metric metric--abnormal"><span>异常</span><strong>{{ summary.abnormal }}</strong></div></el-col>
            </el-row>

            <HsxSearchPanel>
                <el-form :inline="true" class="filter-form" @submit.prevent>
                    <el-form-item label="确认状态">
                        <el-select v-model="query.confirm_status" clearable class="!w-[130px]" placeholder="全部" @change="handleSearch">
                            <el-option label="待确认" value="pending" />
                            <el-option label="已确认" value="confirmed" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="级别">
                        <el-select v-model="query.severity" clearable class="!w-[120px]" placeholder="全部" @change="handleSearch">
                            <el-option label="正常" value="normal" />
                            <el-option label="一般" value="general" />
                            <el-option label="异常" value="abnormal" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="关键字">
                        <el-input v-model.trim="query.keyword" clearable class="!w-[220px]" placeholder="选项文本，如 碎屏" @keyup.enter="handleSearch" @clear="handleSearch" />
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="handleSearch">查询</el-button>
                        <el-button @click="handleReset">重置</el-button>
                    </el-form-item>
                </el-form>
            </HsxSearchPanel>

            <el-collapse class="quick-tools">
                <el-collapse-item title="少量数据快速处理（单项、批量或关键字）" name="quick">
                    <div class="batch-bar">
                        <span class="batch-bar__tip">按关键字：</span>
                        <el-input v-model.trim="kw.keyword" clearable class="!w-[210px]" placeholder="如 碎、裂、缺失" />
                        <el-select v-model="kw.severity" class="!w-[110px]">
                            <el-option label="异常" value="abnormal" />
                            <el-option label="一般" value="general" />
                            <el-option label="正常" value="normal" />
                        </el-select>
                        <el-button type="warning" :disabled="!kw.keyword" :loading="kwLoading" @click="applyKeyword">应用</el-button>
                        <el-divider direction="vertical" />
                        <span class="batch-bar__tip">已选 {{ selected.length }} 项：</span>
                        <el-button size="small" :disabled="!selected.length" @click="batchSet('normal')">正常</el-button>
                        <el-button size="small" type="info" :disabled="!selected.length" @click="batchSet('general')">一般</el-button>
                        <el-button size="small" type="danger" :disabled="!selected.length" @click="batchSet('abnormal')">异常</el-button>
                    </div>
                </el-collapse-item>
            </el-collapse>

            <el-table :data="list" v-loading="loading" border @selection-change="selected = $event" empty-text="暂无选项，请先导入质检模板">
                <el-table-column type="selection" width="46" />
                <el-table-column prop="text" label="选项文本" min-width="190" show-overflow-tooltip />
                <el-table-column prop="context" label="所属质检项" min-width="300" show-overflow-tooltip>
                    <template #default="{ row }"><span :class="{ muted: !row.context }">{{ row.context || '完整上下文请在导出表查看' }}</span></template>
                </el-table-column>
                <el-table-column label="确认状态" width="110" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.confirm_status === 'confirmed' ? 'success' : 'warning'" effect="plain">
                            {{ row.confirm_status === 'confirmed' ? '已确认' : '待确认' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="当前级别" width="100" align="center">
                    <template #default="{ row }"><el-tag :type="sevType(row.severity)" effect="light">{{ sevLabel(row.severity) }}</el-tag></template>
                </el-table-column>
                <el-table-column label="快速设置" width="240" align="center">
                    <template #default="{ row }">
                        <el-radio-group :model-value="row.severity" size="small" @change="(v: string) => setOne(row, v)">
                            <el-radio-button label="normal">正常</el-radio-button>
                            <el-radio-button label="general">一般</el-radio-button>
                            <el-radio-button label="abnormal">异常</el-radio-button>
                        </el-radio-group>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pager">
                <el-pagination v-model:current-page="page.page" v-model:page-size="page.limit" layout="total, sizes, prev, pager, next, jumper" :total="page.total" @size-change="loadList" @current-change="loadList" />
            </div>
        </el-card>

        <HsxDialog :confirm-loading="importState.confirmLoading" v-model="importState.visible" title="导入质检选项级别" width="min(920px, 92vw)" :close-on-click-modal="false" :destroy-on-close="false">
            <el-steps :active="importStep" align-center finish-status="success" class="import-steps">
                <el-step title="选择文件" description="上传填写后的协作表" />
                <el-step title="系统预检" description="核对 ID、文本和级别" />
                <el-step title="确认更新" description="事务写入并记录日志" />
            </el-steps>

            <HsxNotice default-expanded type="info" :closable="false" show-icon class="dialog-alert">
                只有“确认状态=已确认”且“建议级别”为正常、一般或异常的行才会更新；待确认行自动跳过。
            </HsxNotice>

            <div class="upload-panel">
                <el-upload :auto-upload="false" :show-file-list="false" accept=".xls,.xlsx" :on-change="handleFileChange">
                    <el-button>选择 Excel</el-button>
                </el-upload>
                <span class="file-name">{{ importState.fileName || '尚未选择文件' }}</span>
                <el-button type="primary" :disabled="!importState.file" :loading="importState.previewLoading" @click="handlePreview">开始预检</el-button>
            </div>

            <template v-if="importState.token">
                <div class="preview-metrics">
                    <div><span>读取</span><strong>{{ importState.summary.total }}</strong></div>
                    <div class="success"><span>将更新</span><strong>{{ importState.summary.will_update }}</strong></div>
                    <div><span>无变化</span><strong>{{ importState.summary.unchanged }}</strong></div>
                    <div class="warning"><span>待确认跳过</span><strong>{{ importState.summary.pending }}</strong></div>
                    <div class="danger"><span>错误</span><strong>{{ importState.summary.errors }}</strong></div>
                </div>

                <HsxNotice default-expanded v-if="importState.summary.errors" type="error" :closable="false" show-icon class="dialog-alert">
                    文件存在错误，系统不会执行任何更新。请按下方错误修正 Excel 后重新上传。
                </HsxNotice>

                <el-tabs v-model="importState.activeTab">
                    <el-tab-pane label="预检明细" name="rows">
                        <el-table :data="importState.rows" max-height="300" border size="small">
                            <el-table-column prop="line" label="Excel 行" width="85" />
                            <el-table-column prop="text" label="选项文本" min-width="180" show-overflow-tooltip />
                            <el-table-column label="原级别" width="90"><template #default="{ row }">{{ sevLabel(row.old) }}</template></el-table-column>
                            <el-table-column label="新级别" width="90"><template #default="{ row }">{{ row.new ? sevLabel(row.new) : '-' }}</template></el-table-column>
                            <el-table-column prop="message" label="处理结果" min-width="150" />
                        </el-table>
                    </el-tab-pane>
                    <el-tab-pane :label="`错误 (${importState.errors.length})`" name="errors">
                        <el-table :data="importState.errors" max-height="300" border size="small" empty-text="没有错误">
                            <el-table-column prop="line" label="Excel 行" width="85" />
                            <el-table-column prop="text" label="选项文本" min-width="180" show-overflow-tooltip />
                            <el-table-column prop="message" label="错误原因" min-width="260" />
                        </el-table>
                    </el-tab-pane>
                </el-tabs>
            </template>

            <template #footer>
                <el-button :disabled="importState.confirmLoading" @click="importState.visible = false">取消</el-button>
                <el-button type="primary" :disabled="(!canConfirmImport) || (importState.confirmLoading)" :loading="importState.confirmLoading" @click="handleConfirmImport">确认更新 {{ importState.summary.will_update }} 项</el-button>
            </template>
        </HsxDialog>
    </PremiumTheme>
</template>

<script lang="ts" setup>
import { HsxSearchPanel, HsxDialog, HsxNotice, useFeedback } from '@/addon/hsx_components/core'
import PremiumTheme from '@/addon/hsx_recycle/components/PremiumTheme.vue'
import PageHeader from '@/addon/hsx_recycle/components/PageHeader.vue'
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessageBox } from 'element-plus'
import {
    getCheckSeverityList,
    setCheckSeverity,
    batchSetCheckSeverity,
    setCheckSeverityByKeyword,
    exportCheckSeverityExcel,
    previewCheckSeverityImport,
    confirmCheckSeverityImport,
} from '@/addon/hsx_recycle/api/check_catalog'
const hsxFeedback = useFeedback()


const loading = ref(false)
const exportLoading = ref(false)
const kwLoading = ref(false)
const list = ref<any[]>([])
const selected = ref<any[]>([])
const summary = reactive({ normal: 0, general: 0, abnormal: 0, pending: 0, confirmed: 0 })
const query = reactive({ severity: '', confirm_status: '', keyword: '' })
const kw = reactive({ keyword: '', severity: 'abnormal' })
const page = reactive({ page: 1, limit: 50, total: 0 })
const emptyImportSummary = () => ({ total: 0, will_update: 0, unchanged: 0, pending: 0, errors: 0 })
const importState = reactive({
    visible: false,
    file: null as File | null,
    fileName: '',
    previewLoading: false,
    confirmLoading: false,
    token: '',
    summary: emptyImportSummary(),
    rows: [] as any[],
    errors: [] as any[],
    activeTab: 'rows',
})

const importStep = computed(() => importState.token ? (importState.summary.errors ? 1 : 2) : (importState.file ? 1 : 0))
const canConfirmImport = computed(() => !!importState.token && importState.summary.errors === 0 && importState.summary.will_update > 0)
const sevType = (s: string) => (s === 'abnormal' ? 'danger' : s === 'general' ? 'info' : 'success')
const sevLabel = (s: string) => (s === 'abnormal' ? '异常' : s === 'general' ? '一般' : s === 'normal' ? '正常' : '-')

async function loadList() {
    loading.value = true
    try {
        const res: any = await getCheckSeverityList({ ...query, page: page.page, limit: page.limit })
        const data = res.data || {}
        Object.assign(summary, data.summary || {})
        list.value = data.page?.data || []
        page.total = Number(data.page?.total || 0)
    } finally {
        loading.value = false
    }
}

function handleSearch() { page.page = 1; loadList() }
function handleReset() { query.severity = ''; query.confirm_status = ''; query.keyword = ''; handleSearch() }

async function handleExport() {
    exportLoading.value = true
    try {
        const response: any = await exportCheckSeverityExcel()
        const blob = response instanceof Blob ? response : new Blob([response])
        const url = window.URL.createObjectURL(blob)
        const link = document.createElement('a')
        link.href = url
        link.download = `质检选项级别_${new Date().toISOString().slice(0, 10)}.xlsx`
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)
        hsxFeedback.success('协作表已导出')
    } catch (error) {
        hsxFeedback.error('协作表导出失败，请检查权限或稍后重试')
    } finally {
        exportLoading.value = false
    }
}

function openImport() {
    Object.assign(importState, { visible: true, file: null, fileName: '', token: '', summary: emptyImportSummary(), rows: [], errors: [], activeTab: 'rows' })
}

function handleFileChange(uploadFile: any) {
    importState.file = uploadFile.raw || null
    importState.fileName = uploadFile.name || ''
    importState.token = ''
    importState.summary = emptyImportSummary()
    importState.rows = []
    importState.errors = []
}

async function handlePreview() {
    if (!importState.file) return
    importState.previewLoading = true
    try {
        const data = new FormData()
        data.append('file', importState.file)
        const res: any = await previewCheckSeverityImport(data)
        const result = res.data || {}
        importState.token = result.token || ''
        importState.summary = { ...emptyImportSummary(), ...(result.summary || {}) }
        importState.rows = result.rows || []
        importState.errors = result.errors || []
        importState.activeTab = importState.errors.length ? 'errors' : 'rows'
        ElMessage[importState.errors.length ? 'warning' : 'success'](importState.errors.length ? '预检发现错误，请修正后重新上传' : '预检通过，可以确认更新')
    } finally {
        importState.previewLoading = false
    }
}

async function handleConfirmImport() {
    if (!canConfirmImport.value) return
    await ElMessageBox.confirm(
        `将确认更新 ${importState.summary.will_update} 个选项级别。当前版本历史报告重新读取时也会使用新级别，是否继续？`,
        '确认批量更新',
        { type: 'warning', confirmButtonText: '确认更新', cancelButtonText: '再检查一下' },
    )
    importState.confirmLoading = true
    try {
        const res: any = await confirmCheckSeverityImport(importState.token)
        hsxFeedback.success(`已更新 ${Number(res.data?.updated || 0)} 项`)
        importState.visible = false
        loadList()
    } finally {
        importState.confirmLoading = false
    }
}

async function setOne(row: any, severity: string) {
    await setCheckSeverity(row.id, severity)
    hsxFeedback.success('已更新并确认')
    loadList()
}

async function batchSet(severity: string) {
    if (!selected.value.length) return
    await batchSetCheckSeverity(selected.value.map((row) => row.id), severity)
    hsxFeedback.success(`已设置并确认 ${selected.value.length} 项`)
    loadList()
}

async function applyKeyword() {
    if (!kw.keyword) return
    kwLoading.value = true
    try {
        const res: any = await setCheckSeverityByKeyword(kw.keyword, kw.severity)
        hsxFeedback.success(`已更新并确认 ${res.data || 0} 项`)
        kw.keyword = ''
        loadList()
    } finally {
        kwLoading.value = false
    }
}

onMounted(loadList)
</script>

<style lang="scss" scoped>
.source-alert { margin-bottom: 18px; }
.summary-row { margin-bottom: 18px; row-gap: 12px; }
.metric { height: 76px; padding: 13px 16px; border: 1px solid var(--el-border-color-lighter); border-radius: 9px; background: var(--el-bg-color); display: flex; flex-direction: column; justify-content: space-between; }
.metric span { color: var(--el-text-color-secondary); font-size: 13px; }
.metric strong { font-size: 24px; line-height: 1; color: var(--el-text-color-primary); }
.metric--pending { border-left: 3px solid var(--el-color-warning); }
.metric--confirmed, .metric--normal { border-left: 3px solid var(--el-color-success); }
.metric--general { border-left: 3px solid var(--el-color-info); }
.metric--abnormal { border-left: 3px solid var(--el-color-danger); }
.filter-form { margin-bottom: 4px; }
.quick-tools { margin-bottom: 14px; }
.batch-bar { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; padding-bottom: 4px; }
.batch-bar__tip { font-size: 13px; color: var(--el-text-color-secondary); }
.pager { margin-top: 16px; display: flex; justify-content: flex-end; }
.muted { color: var(--el-text-color-placeholder); }
.import-steps { margin: 4px 0 22px; }
.dialog-alert { margin: 14px 0; }
.upload-panel { display: flex; align-items: center; gap: 12px; padding: 16px; border: 1px dashed var(--el-border-color); border-radius: 8px; background: var(--el-fill-color-lighter); }
.file-name { min-width: 0; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--el-text-color-secondary); }
.preview-metrics { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 10px; margin: 16px 0; }
.preview-metrics > div { padding: 10px 12px; border-radius: 8px; background: var(--el-fill-color-light); display: flex; flex-direction: column; gap: 6px; }
.preview-metrics span { color: var(--el-text-color-secondary); font-size: 12px; }
.preview-metrics strong { font-size: 20px; }
.preview-metrics .success strong { color: var(--el-color-success); }
.preview-metrics .warning strong { color: var(--el-color-warning); }
.preview-metrics .danger strong { color: var(--el-color-danger); }
@media (max-width: 768px) { .preview-metrics { grid-template-columns: repeat(2, 1fr); } }
</style>
