<template>
    <div class="main-container opening-page">
        <el-card class="!border-none" shadow="never">
            <div class="page-head">
                <div>
                    <div class="text-page-title">期初建账</div>
                    <div class="mt-1 text-sm text-gray-500">
                        把启用 ERP 前已有的设备库存、应收应付、资金余额和客户资料一次迁入；上传只校验，确认后才正式入账。
                    </div>
                </div>
                <div class="head-actions">
                    <el-button :icon="Download" @click="downloadTemplate">下载完整模板</el-button>
                    <el-button :icon="Refresh" :loading="loading" @click="loadBatches">刷新</el-button>
                    <el-button type="primary" :icon="Upload" @click="openUpload">上传并校验</el-button>
                </div>
            </div>

            <div class="guide-grid mt-5">
                <div class="guide-card">
                    <div class="guide-index">1</div>
                    <div>
                        <div class="guide-title">准备基础资料</div>
                        <div class="guide-desc">先维护仓库、库位和商品目录，再下载模板。表格内名称必须与系统一致。</div>
                    </div>
                </div>
                <div class="guide-card">
                    <div class="guide-index">2</div>
                    <div>
                        <div class="guide-title">上传并检查</div>
                        <div class="guide-desc">系统检查重复串号、账户重复、手机号冲突和必填项，不会立即改账。</div>
                    </div>
                </div>
                <div class="guide-card">
                    <div class="guide-index">3</div>
                    <div>
                        <div class="guide-title">确认正式入账</div>
                        <div class="guide-desc">确认后写入库存和财务账；已存在手机号复用账号，新手机号自动创建账号。</div>
                    </div>
                </div>
            </div>

            <el-alert class="mt-4" type="warning" :closable="false" show-icon>
                <template #title>
                    新账号用户名为手机号，初始密码统一为 123456。用户以后用相同手机号微信授权登录时，可由现有登录流程绑定 openid。
                </template>
            </el-alert>

            <div class="filter-row mt-5">
                <el-select v-model="query.status" clearable placeholder="全部状态" class="!w-[160px]" @change="loadBatches">
                    <el-option v-for="item in statusOptions" :key="item.value" :label="item.label" :value="item.value" />
                </el-select>
            </div>

            <el-table v-loading="loading" :data="batches" class="mt-3" empty-text="暂无期初建账批次">
                <el-table-column label="批次" min-width="220">
                    <template #default="{ row }">
                        <div class="font-medium text-gray-800">{{ row.batch_no }}</div>
                        <div class="mt-1 text-xs text-gray-400">{{ row.file_name }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="期初日期" width="120" prop="opening_date_text" />
                <el-table-column label="校验结果" min-width="220">
                    <template #default="{ row }">
                        <div class="count-line">
                            <span>共 {{ row.total_rows || 0 }}</span>
                            <span class="text-green-600">可入账 {{ row.valid_rows || 0 }}</span>
                            <span :class="row.error_rows ? 'text-red-500' : 'text-gray-400'">错误 {{ row.error_rows || 0 }}</span>
                        </div>
                        <el-progress
                            v-if="['queued', 'parsing', 'posting'].includes(row.status)"
                            class="mt-2"
                            :percentage="row.progress || 0"
                            :stroke-width="5"
                            :show-text="false"
                        />
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="130">
                    <template #default="{ row }">
                        <el-tag :type="row.status_type || 'info'" effect="light">{{ row.status_name }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作人 / 时间" min-width="180">
                    <template #default="{ row }">
                        <div>{{ row.operator_name || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-400">{{ row.create_at_text }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="说明" min-width="230" show-overflow-tooltip>
                    <template #default="{ row }">
                        <span :class="row.status === 'failed' ? 'text-red-500' : 'text-gray-500'">
                            {{ row.error_message || row.message || '-' }}
                        </span>
                    </template>
                </el-table-column>
                <el-table-column label="操作" fixed="right" width="270">
                    <template #default="{ row }">
                        <el-button link type="primary" @click="openDetail(row)">查看结果</el-button>
                        <el-button
                            v-if="row.status === 'ready'"
                            link
                            type="success"
                            @click="confirmBatch(row)"
                        >确认入账</el-button>
                        <el-button
                            v-if="['failed', 'invalid', 'pending'].includes(row.status)"
                            link
                            type="warning"
                            @click="retryBatch(row)"
                        >重新校验</el-button>
                        <el-button
                            v-if="!['posting', 'completed', 'partial'].includes(row.status)"
                            link
                            type="danger"
                            @click="removeBatch(row)"
                        >删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div class="flex justify-end pt-4">
                <el-pagination
                    v-model:current-page="query.page"
                    :page-size="query.limit"
                    :total="total"
                    layout="total, prev, pager, next"
                    @current-change="loadBatches"
                />
            </div>
        </el-card>

        <el-dialog v-model="uploadVisible" title="上传期初建账表" width="560px" append-to-body destroy-on-close>
            <el-form label-position="top">
                <el-form-item label="统一期初日期">
                    <el-date-picker
                        v-model="uploadForm.openingDate"
                        type="date"
                        value-format="X"
                        placeholder="选择启用 ERP 前一天或正式启用日"
                        class="!w-full"
                    />
                    <div class="form-tip">没有单独填写日期的设备、应收和应付，将使用这个日期。</div>
                </el-form-item>
                <el-form-item label="Excel 文件">
                    <div class="upload-box" @click="fileInput?.click()">
                        <el-icon class="upload-icon"><UploadFilled /></el-icon>
                        <div class="mt-2 font-medium">{{ uploadForm.file?.name || '点击选择 xls / xlsx 文件' }}</div>
                        <div class="mt-1 text-xs text-gray-400">最多 10000 行、30MB；请保留模板工作表和表头</div>
                    </div>
                    <input ref="fileInput" class="hidden" type="file" accept=".xls,.xlsx" @change="onFileChange" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="uploadVisible = false">取消</el-button>
                <el-button type="primary" :loading="uploading" @click="submitUpload">上传并开始校验</el-button>
            </template>
        </el-dialog>

        <el-drawer v-model="detailVisible" title="期初建账结果" size="78%" append-to-body>
            <template v-if="detail">
                <div class="detail-hero">
                    <div>
                        <div class="text-lg font-semibold">{{ detail.batch_no }}</div>
                        <div class="mt-1 text-sm text-gray-500">{{ detail.file_name }} · 期初日期 {{ detail.opening_date_text }}</div>
                    </div>
                    <el-tag :type="detail.status_type || 'info'" size="large">{{ detail.status_name }}</el-tag>
                </div>

                <div class="summary-grid mt-4">
                    <div class="summary-item"><span>总行数</span><strong>{{ detail.total_rows || 0 }}</strong></div>
                    <div class="summary-item success"><span>有效</span><strong>{{ detail.valid_rows || 0 }}</strong></div>
                    <div class="summary-item danger"><span>错误</span><strong>{{ detail.error_rows || 0 }}</strong></div>
                    <div class="summary-item primary"><span>已入账</span><strong>{{ detail.posted_rows || 0 }}</strong></div>
                </div>

                <el-alert
                    v-if="detail.error_message"
                    class="mt-4"
                    type="error"
                    :title="detail.error_message"
                    :closable="false"
                    show-icon
                />

                <el-tabs v-model="detailTab" class="mt-4" @tab-change="onDetailTabChange">
                    <el-tab-pane label="数据明细" name="items">
                        <div class="detail-filter">
                            <el-select v-model="itemQuery.status" clearable placeholder="全部校验状态" class="!w-[150px]" @change="loadItems">
                                <el-option label="可入账" value="valid" />
                                <el-option label="错误" value="error" />
                                <el-option label="已入账" value="posted" />
                            </el-select>
                            <el-select v-model="itemQuery.item_type" clearable placeholder="全部资料类型" class="!w-[150px]" @change="loadItems">
                                <el-option v-for="(label, key) in typeNames" :key="key" :label="label" :value="key" />
                            </el-select>
                            <el-input v-model="itemQuery.keyword" clearable placeholder="手机号 / 名称 / 错误原因" class="!w-[260px]" @keyup.enter="loadItems" />
                            <el-button :icon="Search" @click="loadItems">查询</el-button>
                            <el-button :icon="Download" @click="exportItems(detail.error_rows ? 'error' : '')">
                                {{ detail.error_rows ? '下载错误明细' : '导出明细' }}
                            </el-button>
                        </div>
                        <el-table v-loading="itemLoading" :data="items" class="mt-3" height="470">
                            <el-table-column label="来源" width="150">
                                <template #default="{ row }">{{ row.sheet_name }} 第{{ row.row_no }}行</template>
                            </el-table-column>
                            <el-table-column label="类型" width="110">
                                <template #default="{ row }">{{ typeNames[row.item_type] || row.item_type }}</template>
                            </el-table-column>
                            <el-table-column label="名称 / 手机号" min-width="190">
                                <template #default="{ row }">
                                    <div>{{ row.display_name || '-' }}</div>
                                    <div class="text-xs text-gray-400">{{ row.mobile || '-' }}</div>
                                </template>
                            </el-table-column>
                            <el-table-column label="账号处理" width="130">
                                <template #default="{ row }">
                                    <el-tag v-if="row.member_action === 'create'" type="success" size="small">将新建</el-tag>
                                    <el-tag v-else-if="row.member_action === 'reuse'" type="primary" size="small">复用已有</el-tag>
                                    <el-tag v-else-if="row.member_action === 'conflict'" type="danger" size="small">冲突</el-tag>
                                    <span v-else class="text-gray-400">无需账号</span>
                                </template>
                            </el-table-column>
                            <el-table-column label="状态" width="100">
                                <template #default="{ row }">
                                    <el-tag :type="row.status === 'error' ? 'danger' : row.status === 'posted' ? 'success' : 'info'" size="small">
                                        {{ row.status === 'error' ? '错误' : row.status === 'posted' ? '已入账' : '可入账' }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column label="错误原因" min-width="300" show-overflow-tooltip>
                                <template #default="{ row }">
                                    <span :class="row.error_message ? 'text-red-500' : 'text-gray-400'">{{ row.error_message || '-' }}</span>
                                </template>
                            </el-table-column>
                        </el-table>
                        <div class="flex justify-end pt-3">
                            <el-pagination
                                v-model:current-page="itemQuery.page"
                                :page-size="itemQuery.limit"
                                :total="itemTotal"
                                layout="total, prev, pager, next"
                                @current-change="loadItems"
                            />
                        </div>
                    </el-tab-pane>

                    <el-tab-pane label="账号归并预览" name="accounts">
                        <el-alert type="info" :closable="false" show-icon>
                            <template #title>
                                “复用”是按手机号把本次多行资料归到已有账号，不会擅自合并两个既有账号；发现一号多账号时会阻止入账。
                            </template>
                        </el-alert>
                        <el-alert
                            v-if="nameWarnings.length"
                            class="mt-3"
                            type="warning"
                            :closable="false"
                            show-icon
                            :title="`发现 ${nameWarnings.length} 条姓名差异，系统不会覆盖已有昵称；请在确认入账前核对下表说明。`"
                        />
                        <el-table :data="previewAccounts" class="mt-3" height="480" empty-text="本批次没有需要关联的用户">
                            <el-table-column label="手机号 / 姓名" min-width="180">
                                <template #default="{ row }">
                                    <div>{{ row.mobile }}</div>
                                    <div class="text-xs text-gray-400">{{ row.name || '-' }}</div>
                                    <div v-if="row.existing_name && row.existing_name !== row.name" class="text-xs text-amber-600">
                                        已有昵称：{{ row.existing_name }}
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column label="处理方式" width="120">
                                <template #default="{ row }">
                                    <el-tag :type="row.action === 'create' ? 'success' : row.action === 'reuse' ? 'primary' : 'danger'">
                                        {{ row.action === 'create' ? '新建账号' : row.action === 'reuse' ? '复用账号' : '冲突' }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column label="会员 / 主体ID" width="160">
                                <template #default="{ row }">{{ row.member_id || '-' }} / {{ row.party_id || '-' }}</template>
                            </el-table-column>
                            <el-table-column label="涉及资料" min-width="320" show-overflow-tooltip>
                                <template #default="{ row }">{{ (row.source_rows || []).join('、') || '-' }}</template>
                            </el-table-column>
                            <el-table-column label="说明" min-width="200">
                                <template #default="{ row }">{{ row.reason || (row.action === 'create' ? '用户名为手机号，初始密码123456' : '保留已有账号资料') }}</template>
                            </el-table-column>
                        </el-table>
                    </el-tab-pane>

                    <el-tab-pane v-if="detail.status === 'completed'" label="正式入账结果" name="result">
                        <div class="result-actions">
                            <el-button type="primary" :icon="Download" @click="exportAccountResult">导出账号处理结果</el-button>
                        </div>
                        <div class="account-result-grid mt-4">
                            <div>
                                <span>手机号引用</span>
                                <strong>{{ result.account_summary?.source_reference_count || 0 }}</strong>
                            </div>
                            <div>
                                <span>唯一用户</span>
                                <strong>{{ result.account_summary?.unique_mobile_count || 0 }}</strong>
                            </div>
                            <div>
                                <span>合并重复资料</span>
                                <strong>{{ result.account_summary?.consolidated_row_count || 0 }}</strong>
                            </div>
                            <div>
                                <span>新建 / 复用</span>
                                <strong>{{ result.account_summary?.created_count || 0 }} / {{ result.account_summary?.reused_count || 0 }}</strong>
                            </div>
                        </div>
                        <div class="result-section">
                            <div class="result-title">新建账号 {{ result.new_accounts?.length || 0 }} 个</div>
                            <el-table :data="result.new_accounts || []" max-height="230" empty-text="没有新建账号">
                                <el-table-column prop="mobile" label="登录用户名" min-width="140" />
                                <el-table-column prop="name" label="姓名" min-width="120" />
                                <el-table-column prop="member_no" label="会员号" min-width="120" />
                                <el-table-column prop="member_id" label="会员ID" width="100" />
                                <el-table-column prop="party_id" label="主体ID" width="100" />
                                <el-table-column prop="initial_password" label="初始密码" width="110" />
                            </el-table>
                        </div>
                        <div class="result-section">
                            <div class="result-title">复用已有账号 {{ result.matched_accounts?.length || 0 }} 个</div>
                            <el-table :data="result.matched_accounts || []" max-height="230" empty-text="没有复用账号">
                                <el-table-column prop="mobile" label="手机号" min-width="140" />
                                <el-table-column prop="name" label="本次姓名" min-width="120" />
                                <el-table-column prop="account_name" label="账号现有昵称" min-width="130" />
                                <el-table-column prop="member_no" label="会员号" min-width="120" />
                                <el-table-column prop="member_id" label="会员ID" width="100" />
                                <el-table-column prop="party_id" label="主体ID" width="100" />
                                <el-table-column label="涉及资料" min-width="250" show-overflow-tooltip>
                                    <template #default="{ row }">{{ (row.source_rows || []).join('、') }}</template>
                                </el-table-column>
                            </el-table>
                        </div>
                    </el-tab-pane>
                </el-tabs>
            </template>
        </el-drawer>
    </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Download, Refresh, Search, Upload, UploadFilled } from '@element-plus/icons-vue'
import * as XLSX from 'xlsx'
import {
    confirmErpOpeningBatch,
    deleteErpOpeningBatch,
    getErpOpeningBatch,
    getErpOpeningBatches,
    getErpOpeningItems,
    retryErpOpeningBatch,
    uploadErpOpening
} from '@/addon/hsx_erp/api/erp'

const loading = ref(false)
const uploading = ref(false)
const batches = ref<any[]>([])
const total = ref(0)
const query = reactive({ status: '', page: 1, limit: 15 })
const uploadVisible = ref(false)
const fileInput = ref<HTMLInputElement>()
const uploadForm = reactive<{ openingDate: string; file: File | null }>({
    openingDate: String(Math.floor(new Date().setHours(0, 0, 0, 0) / 1000)),
    file: null
})

const detailVisible = ref(false)
const detail = ref<any>(null)
const detailTab = ref('items')
const itemLoading = ref(false)
const items = ref<any[]>([])
const itemTotal = ref(0)
const itemQuery = reactive({ status: '', item_type: '', keyword: '', page: 1, limit: 50 })
let pollTimer: number | undefined

const statusOptions = [
    { label: '待确认入账', value: 'ready' },
    { label: '校验未通过', value: 'invalid' },
    { label: '执行中', value: 'parsing' },
    { label: '已完成', value: 'completed' },
    { label: '失败', value: 'failed' }
]
const typeNames: Record<string, string> = {
    member: '客户资料',
    device: '设备库存',
    receivable: '应收余额',
    payable: '应付余额',
    capital: '资金账户'
}
const previewAccounts = computed(() => detail.value?.summary_json?.accounts || [])
const nameWarnings = computed(() => detail.value?.summary_json?.name_warnings || [])
const result = computed(() => detail.value?.result_json || {})

function responseList(res: any) {
    const payload = res?.data || {}
    const list = payload.data || payload.list || []
    return { list, total: Number(payload.total || 0) }
}

async function loadBatches() {
    loading.value = true
    try {
        const res: any = await getErpOpeningBatches(query)
        const parsed = responseList(res)
        batches.value = parsed.list
        total.value = parsed.total
        schedulePolling()
    } finally {
        loading.value = false
    }
}

function schedulePolling() {
    if (pollTimer) window.clearTimeout(pollTimer)
    const active = batches.value.some((row: any) => ['queued', 'parsing', 'posting'].includes(row.status))
    if (active) pollTimer = window.setTimeout(loadBatches, 2500)
}

function openUpload() {
    uploadForm.file = null
    uploadVisible.value = true
}

function onFileChange(event: Event) {
    uploadForm.file = (event.target as HTMLInputElement).files?.[0] || null
}

async function submitUpload() {
    if (!uploadForm.file) return ElMessage.warning('请选择期初建账 Excel 文件')
    const data = new FormData()
    data.append('file', uploadForm.file)
    data.append('opening_date', uploadForm.openingDate)
    uploading.value = true
    try {
        const res: any = await uploadErpOpening(data)
        ElMessage.success(res?.data?.message || '文件已上传，正在校验')
        uploadVisible.value = false
        query.page = 1
        await loadBatches()
    } finally {
        uploading.value = false
    }
}

async function openDetail(row: any) {
    detailVisible.value = true
    detailTab.value = 'items'
    const res: any = await getErpOpeningBatch(row.id)
    detail.value = res?.data || {}
    itemQuery.page = 1
    await loadItems()
}

async function loadItems() {
    if (!detail.value?.id) return
    itemLoading.value = true
    try {
        const res: any = await getErpOpeningItems(detail.value.id, itemQuery)
        const parsed = responseList(res)
        items.value = parsed.list
        itemTotal.value = parsed.total
    } finally {
        itemLoading.value = false
    }
}

function onDetailTabChange(name: string | number) {
    if (name === 'items') loadItems()
}

async function confirmBatch(row: any) {
    await ElMessageBox.confirm(
        `确认将批次 ${row.batch_no} 的 ${row.valid_rows} 行数据正式写入库存和财务账吗？入账后不能直接删除批次。`,
        '确认期初入账',
        { type: 'warning', confirmButtonText: '确认正式入账' }
    )
    await confirmErpOpeningBatch(row.id)
    ElMessage.success('已提交期初入账任务')
    await loadBatches()
}

async function retryBatch(row: any) {
    await retryErpOpeningBatch(row.id)
    ElMessage.success('已重新开始校验')
    await loadBatches()
}

async function removeBatch(row: any) {
    await ElMessageBox.confirm('确认删除该未入账批次及校验明细吗？', '删除批次', { type: 'warning' })
    await deleteErpOpeningBatch(row.id)
    await loadBatches()
}

function sheetFromRows(headers: string[], rows: any[][] = []) {
    const sheet = XLSX.utils.aoa_to_sheet([headers, ...rows])
    sheet['!cols'] = headers.map((header) => ({ wch: Math.max(14, Math.min(28, header.length * 2 + 4)) }))
    return sheet
}

function downloadTemplate() {
    const workbook = XLSX.utils.book_new()
    const guideRows = [
        ['步骤', '操作说明', '重要提醒'],
        ['1', '先在 ERP 中维护仓库、库位和商品目录', '表格中的仓库/库位名称必须完全一致'],
        ['2', '按实际业务填写下方工作表；不需要的工作表可只保留表头', '不要修改工作表名称和表头'],
        ['3', '上传后查看错误和账号归并预览', '上传只校验，不会直接入账'],
        ['4', '所有错误修正后重新上传，确认无误再点击“确认入账”', '确认后才写入库存和财务账'],
        ['用户规则', '手机号优先复用已有账号；不存在则创建', '用户名=手机号，初始密码=123456'],
        ['微信登录', '新用户以后使用相同手机号微信授权登录', '现有登录流程可将 openid 绑定到此账号'],
        ['限制', '当前模板支持一物一码设备库存、应收、应付、资金账户', '普通标品数量库存需等标品进销存模块完成后再导入']
    ]
    const guide = XLSX.utils.aoa_to_sheet(guideRows)
    guide['!cols'] = [{ wch: 14 }, { wch: 54 }, { wch: 48 }]
    XLSX.utils.book_append_sheet(workbook, guide, '使用说明')
    XLSX.utils.book_append_sheet(workbook, sheetFromRows(
        ['客户姓名*', '手机号*', '客户身份', '备注'],
    ), '客户资料')
    XLSX.utils.book_append_sheet(workbook, sheetFromRows(
        ['IMEI', 'SN', '商品型号*', '商品目录型号', '分类路径', '规格', '仓库*', '库位', '物权', '物权客户姓名', '物权客户手机号', '来源主体姓名', '来源主体手机号', '期初成本*', '销售底价', '零售价', '入库日期', '图片URL', '备注'],
    ), '设备库存')
    XLSX.utils.book_append_sheet(workbook, sheetFromRows(
        ['客户姓名*', '手机号*', '期初未收余额*', '原单号', '发生日期', '业务说明'],
    ), '应收余额')
    XLSX.utils.book_append_sheet(workbook, sheetFromRows(
        ['供应商姓名*', '手机号*', '期初未付余额*', '原单号', '发生日期', '业务说明'],
    ), '应付余额')
    XLSX.utils.book_append_sheet(workbook, sheetFromRows(
        ['账户名称*', '账户类型*', '开户行', '账号', '户名', '期初余额*', '设为默认', '备注'],
    ), '资金账户')
    XLSX.utils.book_append_sheet(workbook, sheetFromRows(
        ['工作表', '示例字段1', '示例字段2', '示例字段3', '说明'],
        [
            ['客户资料', '张三', '13800138000', '客户兼供应商', '示例只用于参考，请不要复制本页上传'],
            ['设备库存', 'IMEI 860000000000001', '苹果 iPhone 15 Pro', '二手机仓 / A01', '自有设备可不填来源主体'],
            ['应收余额', '李四 / 13900139000', '1200.00', '2026-07-01', '只填正式启用 ERP 时仍未收的余额'],
            ['资金账户', '公司微信', '微信', '5000.00', '期初余额不会伪装为真实收款流水']
        ],
    ), '填写示例（请勿导入）')
    XLSX.writeFile(workbook, `ERP期初建账完整模板_${new Date().toISOString().slice(0, 10)}.xlsx`)
}

async function exportItems(status = '') {
    if (!detail.value?.id) return
    const res: any = await getErpOpeningItems(detail.value.id, {
        status,
        page: 1,
        limit: 10000
    })
    const rows = responseList(res).list.map((row: any) => ({
        工作表: row.sheet_name,
        行号: row.row_no,
        类型: typeNames[row.item_type] || row.item_type,
        名称: row.display_name,
        手机号: row.mobile,
        状态: row.status === 'error' ? '错误' : row.status === 'posted' ? '已入账' : '可入账',
        账号处理: row.member_action,
        错误原因: row.error_message || '',
        原始数据: JSON.stringify(row.raw_json || {}, null, 0)
    }))
    const workbook = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(workbook, XLSX.utils.json_to_sheet(rows), status === 'error' ? '错误明细' : '期初明细')
    XLSX.writeFile(workbook, `${detail.value.batch_no}_${status === 'error' ? '错误明细' : '全部明细'}.xlsx`)
}

function exportAccountResult() {
    const rows = [
        ...(result.value.new_accounts || []).map((row: any) => ({ ...row, 处理结果: '新建账号', 初始密码: row.initial_password || '123456' })),
        ...(result.value.matched_accounts || []).map((row: any) => ({ ...row, 处理结果: '复用已有账号', 初始密码: '' }))
    ].map((row: any) => ({
        处理结果: row.处理结果,
        姓名: row.name,
        账号现有昵称: row.account_name || row.name,
        手机号及登录用户名: row.mobile,
        会员号: row.member_no,
        会员ID: row.member_id,
        往来主体ID: row.party_id,
        初始密码: row.初始密码,
        涉及资料: (row.source_rows || []).join('、'),
        本批次合并重复资料数: result.value.account_summary?.consolidated_row_count || 0
    }))
    const workbook = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(workbook, XLSX.utils.json_to_sheet(rows), '账号处理结果')
    XLSX.writeFile(workbook, `${detail.value.batch_no}_账号处理结果.xlsx`)
}

onMounted(loadBatches)
onBeforeUnmount(() => {
    if (pollTimer) window.clearTimeout(pollTimer)
})
</script>

<style scoped>
.opening-page { min-height: 100%; }
.page-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; }
.head-actions, .filter-row, .detail-filter, .result-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }
.guide-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
.guide-card { display: flex; gap: 13px; min-height: 96px; padding: 18px; border: 1px solid #e8edf5; border-radius: 12px; background: linear-gradient(135deg, #fbfdff, #f6f9ff); }
.guide-index { display: flex; flex: 0 0 32px; width: 32px; height: 32px; align-items: center; justify-content: center; border-radius: 10px; color: #fff; font-weight: 700; background: #3b6ef5; box-shadow: 0 6px 16px rgb(59 110 245 / 20%); }
.guide-title { color: #172033; font-size: 15px; font-weight: 600; }
.guide-desc { margin-top: 7px; color: #718096; font-size: 13px; line-height: 1.6; }
.count-line { display: flex; flex-wrap: wrap; gap: 12px; font-size: 13px; }
.upload-box { width: 100%; padding: 28px 16px; border: 1px dashed #cbd5e1; border-radius: 12px; text-align: center; cursor: pointer; background: #f8fafc; transition: .2s; }
.upload-box:hover { border-color: #3b6ef5; background: #f4f7ff; }
.upload-icon { color: #3b6ef5; font-size: 34px; }
.form-tip { margin-top: 6px; color: #94a3b8; font-size: 12px; }
.detail-hero { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; padding: 18px; border-radius: 12px; background: #f6f8fc; }
.summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.summary-item { display: flex; align-items: center; justify-content: space-between; padding: 16px 18px; border: 1px solid #edf0f5; border-radius: 10px; color: #64748b; background: #fff; }
.summary-item strong { color: #172033; font-size: 22px; }
.summary-item.success strong { color: #16a34a; }
.summary-item.danger strong { color: #ef4444; }
.summary-item.primary strong { color: #2563eb; }
.result-section { margin-top: 16px; padding: 16px; border: 1px solid #edf0f5; border-radius: 12px; }
.result-title { margin-bottom: 12px; color: #172033; font-weight: 600; }
.account-result-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.account-result-grid > div { display: flex; flex-direction: column; gap: 8px; padding: 14px 16px; border: 1px solid #edf0f5; border-radius: 10px; background: #f8fafc; }
.account-result-grid span { color: #64748b; font-size: 13px; }
.account-result-grid strong { color: #172033; font-size: 20px; }
@media (max-width: 1000px) {
    .page-head { flex-direction: column; }
    .guide-grid { grid-template-columns: 1fr; }
    .summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .account-result-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>
