<template>
    <el-dialog v-model="visible" title="商品批量导入与导出" width="780px" destroy-on-close @closed="stopPolling">
        <el-tabs v-model="activeTab" @tab-change="onTabChange">
            <el-tab-pane label="批量导入" name="import">
                <div class="transfer-guide">
                    <div class="guide-step"><b>1</b><div><strong>下载二手机模板</strong><span>分类、成色、内存、价格和 IMEI 一次整理</span></div></div>
                    <div class="guide-step"><b>2</b><div><strong>填写商品与图片</strong><span>一行一台，图片可填 URL 或直接嵌入 Excel</span></div></div>
                    <div class="guide-step"><b>3</b><div><strong>后台校验并导入</strong><span>失败结果会明确标记行号、字段和原因</span></div></div>
                </div>
                <el-alert class="mb-[16px]" type="info" :closable="false" show-icon>
                    <template #title>默认库存 1 件、全配送、包邮；同行价会生成 fixed_price 指定会员价。内嵌图片会自动上传。</template>
                </el-alert>
                <div class="image-mode-card">
                    <div class="image-mode-title">外链图片处理方式</div>
                    <el-radio-group v-model="imageMode" class="image-mode-options">
                        <el-radio value="direct" border>
                            <span class="font-medium">直接使用原 URL</span>
                            <span class="image-mode-desc">推荐，导入快，不受本机或服务器下载环境影响</span>
                        </el-radio>
                        <el-radio value="store" border>
                            <span class="font-medium">下载并转存到当前存储</span>
                            <span class="image-mode-desc">避免外链失效，但服务器必须能访问图片地址</span>
                        </el-radio>
                    </el-radio-group>
                    <div class="image-mode-tip">直接使用 URL 时不会采集或重复上传；只有 Excel 内嵌图片仍会上传到当前存储。</div>
                </div>
                <div class="import-defaults">
                    <div>
                        <div class="image-mode-title">导入后的默认状态</div>
                        <el-radio-group v-model="defaultStatus">
                            <el-radio-button :label="0">默认下架</el-radio-button>
                            <el-radio-button :label="1">默认上架</el-radio-button>
                        </el-radio-group>
                        <p>模板不再读取上架状态，整批商品使用这里的选择。</p>
                    </div>
                    <div>
                        <div class="image-mode-title">轮播图同步到详情</div>
                        <el-switch v-model="imagesToDesc" inline-prompt active-text="开" inactive-text="关" />
                        <p>开启后自动生成图片详情；关闭后只保留轮播图。</p>
                    </div>
                </div>
                <div class="flex items-center gap-[12px] mb-[16px]">
                    <el-button @click="downloadTemplate" :loading="templateLoading">下载导入模板</el-button>
                    <span class="text-[13px] text-[#94a3b8]">请保留“商品数据”工作表及表头</span>
                </div>
                <el-upload ref="uploadRef" drag :auto-upload="false" :limit="1" accept=".xlsx,.xls" :on-change="onFileChange" :on-remove="onFileRemove">
                    <div class="upload-title">拖拽 Excel 到这里，或点击选择文件</div>
                    <div class="upload-tip">支持 .xlsx / .xls，上传后进入后台任务，不影响继续管理商品</div>
                </el-upload>
                <div class="mt-[16px] flex justify-end">
                    <el-button type="primary" :disabled="!selectedFile" :loading="importLoading" @click="submitImport">创建导入任务</el-button>
                </div>
            </el-tab-pane>

            <el-tab-pane label="批量导出" name="export">
                <div class="export-card">
                    <div class="font-medium text-[15px] text-[#1e293b] mb-[12px]">选择导出范围</div>
                    <el-radio-group v-model="exportScope">
                        <el-radio label="filter">当前筛选结果</el-radio>
                        <el-radio label="selected" :disabled="selectedCount === 0">已选商品（{{ selectedCount }} 件）</el-radio>
                    </el-radio-group>
                    <p class="mt-[10px] text-[13px] text-[#94a3b8]">导出一行代表一个 SKU，商品多规格会展开为多行；图片以可再次导入的 URL 输出。</p>
                </div>
                <div class="mt-[18px] flex justify-end">
                    <el-button type="primary" :loading="exportLoading" @click="submitExport">创建导出任务</el-button>
                </div>
            </el-tab-pane>

            <el-tab-pane label="任务记录" name="tasks">
                <div class="flex justify-between items-center mb-[12px]">
                    <span class="text-[13px] text-[#64748b]">任务在后台执行，完成后可直接下载结果。</span>
                    <el-button size="small" @click="loadTasks" :loading="taskLoading">刷新</el-button>
                </div>
                <el-table :data="tasks" v-loading="taskLoading" max-height="390" empty-text="暂无任务">
                    <el-table-column label="类型" width="74">
                        <template #default="{ row }"><el-tag size="small" :type="row.task_type === 'import' ? 'success' : 'primary'">{{ row.task_type === 'import' ? '导入' : '导出' }}</el-tag></template>
                    </el-table-column>
                    <el-table-column label="进度" min-width="170">
                        <template #default="{ row }">
                            <div class="text-[13px] text-[#334155] mb-[5px]">{{ row.message || statusText(row.status) }}</div>
                            <el-progress :percentage="Number(row.progress || 0)" :status="progressStatus(row.status)" :stroke-width="6" />
                        </template>
                    </el-table-column>
                    <el-table-column label="结果" width="150">
                        <template #default="{ row }">
                            <div class="text-[12px] text-[#64748b]">成功 {{ row.success_count || 0 }} · 跳过 {{ row.skipped_count || 0 }}</div>
                            <el-popover v-if="row.error_count" placement="left" :width="420" trigger="hover">
                                <template #reference><div class="text-[12px] text-danger cursor-help">失败 {{ row.error_count }} · 查看行号</div></template>
                                <div class="error-preview-title">错误预览（完整内容请下载错误表）</div>
                                <div v-for="item in (row.result_json?.error_samples || []).slice(0, 8)" :key="`${item.row}-${item.field}`" class="error-preview-row">
                                    <b>第 {{ item.row }} 行 · {{ item.field || '整行数据' }}</b>
                                    <span>{{ item.message }}</span>
                                </div>
                            </el-popover>
                        </template>
                    </el-table-column>
                    <el-table-column prop="create_time" label="创建时间" width="155" />
                    <el-table-column label="操作" width="126" fixed="right">
                        <template #default="{ row }">
                            <el-button v-if="row.can_download" link type="primary" @click="downloadResult(row)">下载</el-button>
                            <el-button v-if="row.can_retry" link type="primary" @click="retryTask(row)">重试</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div class="mt-[12px] flex justify-end"><el-pagination small layout="prev, pager, next" :total="taskTotal" :page-size="8" v-model:current-page="taskPage" @current-change="loadTasks" /></div>
            </el-tab-pane>
        </el-tabs>
    </el-dialog>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { ElMessage } from 'element-plus'
import type { UploadFile, UploadInstance } from 'element-plus'
import {
    createGoodsExportTask, createGoodsImportTask, downloadGoodsImportTemplate,
    downloadGoodsTransferResult, getGoodsTransferTasks, retryGoodsTransferTask
} from '@/addon/phone_shop/api/goods'

const props = defineProps<{ batchPayload?: Record<string, any> }>()
const emit = defineEmits(['completed'])
const visible = ref(false)
const activeTab = ref('import')
const selectedFile = ref<File | null>(null)
const imageMode = ref<'direct' | 'store'>('direct')
const defaultStatus = ref(0)
const imagesToDesc = ref(true)
const uploadRef = ref<UploadInstance>()
const templateLoading = ref(false)
const importLoading = ref(false)
const exportLoading = ref(false)
const exportScope = ref('filter')
const tasks = ref<any[]>([])
const taskLoading = ref(false)
const taskPage = ref(1)
const taskTotal = ref(0)
let timer: ReturnType<typeof setInterval> | null = null
const watchedTaskIds = new Set<number>()

const selectedCount = computed(() => {
    const payload = props.batchPayload || {}
    if (Number(payload.is_all) === 1) return Number(payload.total || 0) - (payload.ids || []).length
    return (payload.ids || []).length
})

const show = (tab = 'import') => {
    visible.value = true
    activeTab.value = tab
    if (tab === 'tasks') loadTasks()
}
defineExpose({ show })

const saveBlob = (blob: Blob, filename: string) => {
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    link.click()
    setTimeout(() => URL.revokeObjectURL(url), 1000)
}
const responseBlob = (response: any): Blob => {
    const data = response?.data ?? response
    return data instanceof Blob ? data : new Blob([data])
}
const downloadTemplate = async () => {
    templateLoading.value = true
    try { saveBlob(responseBlob(await downloadGoodsImportTemplate()), '商城商品批量导入模板.xlsx') } finally { templateLoading.value = false }
}
const onFileChange = (file: UploadFile) => { selectedFile.value = file.raw || null }
const onFileRemove = () => { selectedFile.value = null }
const submitImport = async () => {
    if (!selectedFile.value) return
    importLoading.value = true
    try {
        const res: any = await createGoodsImportTask(selectedFile.value, imageMode.value, defaultStatus.value, imagesToDesc.value)
        ElMessage.success(res.data?.message || '导入任务已创建')
        if (res.data?.task_id) watchedTaskIds.add(Number(res.data.task_id))
        selectedFile.value = null
        uploadRef.value?.clearFiles()
        activeTab.value = 'tasks'
        await loadTasks()
        startPolling()
    } finally { importLoading.value = false }
}
const submitExport = async () => {
    const payload: any = { ...(props.batchPayload || {}), scope: exportScope.value }
    if (exportScope.value === 'filter') { payload.is_all = 1; payload.ids = [] }
    exportLoading.value = true
    try {
        const res: any = await createGoodsExportTask(payload)
        ElMessage.success(res.data?.message || '导出任务已创建')
        if (res.data?.task_id) watchedTaskIds.add(Number(res.data.task_id))
        activeTab.value = 'tasks'
        await loadTasks()
        startPolling()
    } finally { exportLoading.value = false }
}
const loadTasks = async () => {
    taskLoading.value = true
    try {
        const res: any = await getGoodsTransferTasks({ page: taskPage.value, limit: 8 })
        tasks.value = res.data?.data || []
        taskTotal.value = Number(res.data?.total || 0)
        let imported = false
        tasks.value.forEach(row => {
            const id = Number(row.id)
            if (watchedTaskIds.has(id) && ['completed', 'partial', 'failed'].includes(row.status)) {
                watchedTaskIds.delete(id)
                if (row.task_type === 'import' && ['completed', 'partial'].includes(row.status)) imported = true
            }
        })
        if (imported) emit('completed')
        if (!tasks.value.some(row => ['queued', 'processing'].includes(row.status))) stopPolling()
    } finally { taskLoading.value = false }
}
const retryTask = async (row: any) => { await retryGoodsTransferTask(row.id); watchedTaskIds.add(Number(row.id)); ElMessage.success('任务已重新提交'); await loadTasks(); startPolling() }
const downloadResult = async (row: any) => {
    const response: any = await downloadGoodsTransferResult(row.id)
    saveBlob(responseBlob(response), row.task_type === 'export' ? `商城商品导出_${row.id}.xlsx` : `商品导入错误明细_${row.id}.xlsx`)
}
const onTabChange = (name: string | number) => { if (name === 'tasks') { loadTasks(); startPolling() } else stopPolling() }
const startPolling = () => { stopPolling(); timer = setInterval(loadTasks, 3000) }
const stopPolling = () => { if (timer) clearInterval(timer); timer = null }
const statusText = (status: string) => ({ pending: '等待执行', queued: '排队中', processing: '处理中', completed: '已完成', partial: '部分成功', failed: '失败' } as any)[status] || status
const progressStatus = (status: string) => status === 'failed' ? 'exception' : (['completed', 'partial'].includes(status) ? 'success' : undefined)
onBeforeUnmount(stopPolling)
</script>

<style scoped lang="scss">
.transfer-guide { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 16px; }
.guide-step { display: flex; gap: 10px; padding: 14px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; }
.guide-step b { flex: none; display: grid; place-items: center; width: 24px; height: 24px; border-radius: 50%; color: #fff; background: var(--el-color-primary); }
.guide-step div { min-width: 0; display: flex; flex-direction: column; }
.guide-step strong { color: #1e293b; font-size: 14px; }
.guide-step span { margin-top: 3px; color: #94a3b8; font-size: 12px; line-height: 1.5; }
.upload-title { color: #334155; font-size: 15px; font-weight: 500; }
.upload-tip { margin-top: 6px; color: #94a3b8; font-size: 12px; }
.image-mode-card { margin-bottom: 16px; padding: 14px 16px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; }
.image-mode-title { margin-bottom: 10px; color: #334155; font-size: 14px; font-weight: 600; }
.image-mode-options { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); width: 100%; gap: 10px; }
.image-mode-options :deep(.el-radio) { width: 100%; height: auto; min-height: 58px; margin: 0; padding: 10px 12px; align-items: flex-start; }
.image-mode-options :deep(.el-radio__label) { display: flex; min-width: 0; flex-direction: column; white-space: normal; line-height: 1.5; }
.image-mode-desc { margin-top: 2px; color: #94a3b8; font-size: 12px; }
.image-mode-tip { margin-top: 9px; color: #64748b; font-size: 12px; }
.import-defaults { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
.import-defaults > div { padding: 14px 16px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; }
.import-defaults p { margin: 8px 0 0; color: #94a3b8; font-size: 12px; line-height: 1.5; }
.export-card { padding: 18px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; }
.error-preview-title { margin-bottom: 8px; color: #334155; font-size: 13px; font-weight: 600; }
.error-preview-row { display: flex; flex-direction: column; gap: 2px; padding: 7px 0; border-top: 1px solid #f1f5f9; }
.error-preview-row b { color: #dc2626; font-size: 12px; }
.error-preview-row span { color: #64748b; font-size: 12px; line-height: 1.5; }
</style>
