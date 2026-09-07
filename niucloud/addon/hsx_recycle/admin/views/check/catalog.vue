<template>
    <PremiumTheme class="check-catalog-page">
        <el-card class="box-card" shadow="never">
            <template #header>
                <PageHeader title="导入检测表" description="上传拍机堂 Excel/CSV，后端自动去重生成紧凑质检模板并按型号绑定。验机时按需展开，无需逐型号建表。">
                    <template #actions>
                        <el-button type="primary" @click="openImportDialog">导入 Excel</el-button>
                        <el-button :loading="loading" @click="loadSummary">刷新</el-button>
                        <input ref="fileInput" type="file" accept=".xlsx,.xls,.csv,.txt" style="display:none" @change="onFileChange" />
                    </template>
                </PageHeader>
            </template>

        <el-row :gutter="16" class="summary-row">
            <el-col :span="8"><el-statistic title="质检模板数（拍机堂）" :value="summary.templates" /></el-col>
            <el-col :span="8"><el-statistic title="已绑定型号" :value="summary.bindings" /></el-col>
            <el-col :span="8"><el-statistic title="选项字典" :value="summary.options" /></el-col>
        </el-row>

        <div class="section-title">最近导入</div>
        <el-table :data="batches" v-loading="loading" size="default" border empty-text="还没有导入记录，点右上角「导入 CSV」">
            <el-table-column label="文件" min-width="200" show-overflow-tooltip>
                <template #default="{ row }">{{ (row.file_name || '').split('|')[0] }}</template>
            </el-table-column>
            <el-table-column label="生成模板" width="100" align="right"><template #default="{ row }">{{ row.inserted }}</template></el-table-column>
            <el-table-column label="绑定型号" width="100" align="right"><template #default="{ row }">{{ row.updated }}</template></el-table-column>
            <el-table-column label="跳过已有" width="100" align="right"><template #default="{ row }">{{ row.skipped_user || 0 }}</template></el-table-column>
            <el-table-column label="已处理行" width="110" align="right"><template #default="{ row }">{{ row.skipped_same }}</template></el-table-column>
            <el-table-column label="导入方式" width="100" align="center">
                <template #default="{ row }">
                    <el-tag effect="light" :type="getBatchMode(row) === 'overwrite' ? 'warning' : 'success'">
                        {{ getBatchMode(row) === 'overwrite' ? '覆盖' : '追加' }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column label="状态" width="100" align="center">
                <template #default="{ row }">
                    <el-tag :type="row.status === 'completed' ? 'success' : (row.status === 'failed' ? 'danger' : 'warning')" effect="light">
                        {{ row.status === 'completed' ? '完成' : (row.status === 'failed' ? '失败' : '进行中') }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column label="时间" width="170">
                <template #default="{ row }">{{ formatTime(row.create_at) }}</template>
            </el-table-column>
        </el-table>
        </el-card>

        <HsxDialog v-model="importDialog.visible" title="选择导入方式" width="520px" class="" :destroy-on-close="false">
            <el-radio-group v-model="importDialog.mode" class="import-mode-group">
                <el-radio value="append" border class="import-mode-item">
                    <div class="mode-title">追加导入</div>
                    <div class="mode-desc">已有模板绑定的分类会跳过，只给未配置的分类创建模板。</div>
                </el-radio>
                <el-radio value="overwrite" border class="import-mode-item">
                    <div class="mode-title">最新覆盖</div>
                    <div class="mode-desc">清空旧拍机堂导入模板并按最新文件重建，人工调整过的导入规则可能失效。</div>
                </el-radio>
            </el-radio-group>
            <template #footer>
                <el-button @click="importDialog.visible = false">取消</el-button>
                <el-button type="primary" @click="triggerUpload">选择文件</el-button>
            </template>
        </HsxDialog>

        <HsxDialog v-model="imp.visible" title="导入检测表" width="460px" :close-on-click-modal="false" :show-close="!imp.running" class="" :destroy-on-close="false">
            <div class="imp-file">{{ imp.fileName }}</div>
            <el-progress :percentage="impPercent" :status="imp.done ? 'success' : undefined" />
            <div class="imp-line">已处理 {{ imp.rows }} / {{ imp.total }} 行{{ imp.running ? '（后端慢慢跑，请勿关闭）' : '' }}</div>
            <div class="imp-line imp-muted">
                {{ imp.mode === 'overwrite' ? '最新覆盖' : '追加导入' }} · 生成模板 {{ imp.templates }} · 绑定型号 {{ imp.bindings }} · 跳过已有 {{ imp.skipped }}
            </div>
            <template #footer>
                <el-button :disabled="imp.running" type="primary" @click="finishImport">{{ imp.done ? '完成' : '关闭' }}</el-button>
            </template>
        </HsxDialog>
    </PremiumTheme>
</template>

<script lang="ts" setup>
import { HsxDialog, useFeedback } from '@/addon/hsx_components/core'
import PremiumTheme from '@/addon/hsx_recycle/components/PremiumTheme.vue'
import PageHeader from '@/addon/hsx_recycle/components/PageHeader.vue'
import { computed, onMounted, reactive, ref } from 'vue'

import { getCheckCatalogList, uploadCheckCatalog, importChunkCheckCatalog } from '@/addon/hsx_recycle/api/check_catalog'
const hsxFeedback = useFeedback()


const loading = ref(false)
const summary = reactive({ templates: 0, bindings: 0, options: 0 })
const batches = ref<any[]>([])
const getBatchMode = (row: any) => {
    const mode = String(row?.file_name || '').split('|')[2] || 'append'
    return mode === 'overwrite' ? 'overwrite' : 'append'
}
const formatTime = (t: any) => {
    if (t === null || t === undefined || t === '' || t === 0) return '-'
    const n = Number(t)
    // 数字：秒(10位)→×1000，毫秒(13位)直接用；非数字：当作已格式化字符串解析
    const d = Number.isFinite(n) && n > 0
        ? new Date(n < 1e12 ? n * 1000 : n)
        : new Date(String(t).replace(/-/g, '/'))
    return isNaN(d.getTime()) ? String(t) : d.toLocaleString()
}

async function loadSummary() {
    loading.value = true
    try {
        const res: any = await getCheckCatalogList({})
        Object.assign(summary, res.data?.summary || {})
        batches.value = res.data?.batches || []
    } finally {
        loading.value = false
    }
}

// 上传一份 CSV → 后端分批慢慢跑
const fileInput = ref<HTMLInputElement>()
const importDialog = reactive({ visible: false, mode: 'append' })
const imp = reactive({
    visible: false, running: false, done: false, fileName: '',
    total: 0, rows: 0, templates: 0, bindings: 0, skipped: 0, batch_id: 0, token: '', mode: 'append',
})
const impPercent = computed(() => (imp.total ? Math.min(100, Math.floor((imp.rows / imp.total) * 100)) : (imp.done ? 100 : 0)))
function openImportDialog() {
    importDialog.visible = true
}
function triggerUpload() {
    importDialog.visible = false
    fileInput.value?.click()
}
async function onFileChange(e: Event) {
    const input = e.target as HTMLInputElement
    const f = input.files?.[0]
    input.value = ''
    if (!f) return
    const mode = importDialog.mode === 'overwrite' ? 'overwrite' : 'append'
    Object.assign(imp, { visible: true, running: true, done: false, fileName: f.name, total: 0, rows: 0, templates: 0, bindings: 0, skipped: 0, mode })
    try {
        const fd = new FormData()
        fd.append('file', f)
        fd.append('mode', mode)
        const up: any = await uploadCheckCatalog(fd)
        imp.batch_id = up.data.batch_id
        imp.token = up.data.token
        imp.total = Number(up.data.total_rows || 0)
        let offset = 0
        // eslint-disable-next-line no-constant-condition
        while (true) {
            const res: any = await importChunkCheckCatalog({ batch_id: imp.batch_id, token: imp.token, offset, limit: 1000, mode })
            const d = res.data
            imp.templates = d.templates
            imp.bindings = d.bindings
            imp.rows = d.rows_done
            imp.skipped = Number(d.skipped_exists || 0)
            offset = Number(d.next_offset || 0)
            if (d.done) break
        }
        imp.done = true
        hsxFeedback.success('导入完成')
    } catch (err) {
        hsxFeedback.error('导入中断，请重试')
    } finally {
        imp.running = false
    }
}
function finishImport() {
    imp.visible = false
    loadSummary()
}

onMounted(loadSummary)
</script>

<style lang="scss" scoped>
.summary-row { margin: 8px 0 20px; }
.section-title { margin: 6px 0 12px; font-size: 14px; font-weight: 600; color: var(--el-text-color-primary); }
.imp-file { margin-bottom: 12px; font-weight: 500; }
.imp-line { margin-top: 8px; font-size: 13px; }
.imp-muted { color: var(--el-text-color-secondary); }
.import-mode-group { width: 100%; display: grid; gap: 12px; }
.import-mode-item {
    width: 100%;
    height: auto;
    margin-right: 0;
    padding: 14px 16px;
    align-items: flex-start;
}
.mode-title { font-size: 14px; font-weight: 600; line-height: 20px; color: var(--el-text-color-primary); }
.mode-desc { margin-top: 4px; font-size: 12px; line-height: 18px; color: var(--el-text-color-secondary); white-space: normal; }
</style>
