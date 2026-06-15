<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">库存盘点</div>
                    <div class="mt-1 text-sm text-gray-500">
                        选仓库/库位拉出应在库清单，逐台录入实物 IMEI，未盘到的判为盘亏、可据实核销离库。与「账实校验」互补：那个查数据，这个查实物。
                    </div>
                </div>
                <el-button type="primary" @click="openCreate">新建盘点</el-button>
            </div>

            <div class="mt-4 flex gap-3">
                <el-select v-model="search.warehouse_id" placeholder="仓库" clearable class="w-44" @change="loadList">
                    <el-option v-for="w in warehouses" :key="w.id" :label="w.warehouse_name" :value="w.id" />
                </el-select>
                <el-select v-model="search.status" placeholder="状态" clearable class="w-36" @change="loadList">
                    <el-option label="盘点中" value="counting" />
                    <el-option label="已完成" value="finished" />
                </el-select>
                <el-button @click="loadList" :loading="loading">查询</el-button>
            </div>

            <el-table class="mt-4" :data="list" v-loading="loading" size="large" empty-text="暂无盘点单">
                <el-table-column prop="stocktake_no" label="盘点单号" min-width="170" />
                <el-table-column label="范围" min-width="160">
                    <template #default="{ row }">{{ row.warehouse_name }}{{ row.location_name ? ' / ' + row.location_name : ' / 整仓' }}</template>
                </el-table-column>
                <el-table-column prop="system_count" label="应盘" width="80" align="right" />
                <el-table-column label="相符" width="80" align="right"><template #default="{ row }"><span class="text-gray-600">{{ row.match_count }}</span></template></el-table-column>
                <el-table-column label="盘亏" width="80" align="right"><template #default="{ row }"><span :class="row.loss_count>0?'text-red-600':'text-gray-400'">{{ row.loss_count }}</span></template></el-table-column>
                <el-table-column label="盘盈" width="80" align="right"><template #default="{ row }"><span :class="row.profit_count>0?'text-orange-600':'text-gray-400'">{{ row.profit_count }}</span></template></el-table-column>
                <el-table-column label="状态" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.status==='finished'?'success':'warning'" effect="light">{{ row.status_text }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="时间" width="170"><template #default="{ row }">{{ fmt(row.create_at) }}</template></el-table-column>
                <el-table-column label="操作" width="120" align="center" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="openDetail(row)">{{ row.status==='counting' ? '去盘点' : '查看' }}</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div class="mt-4 flex justify-end">
                <el-pagination layout="total, prev, pager, next" :total="total" :page-size="search.limit" :current-page="search.page" @current-change="onPage" />
            </div>
        </el-card>

        <!-- 新建盘点 -->
        <el-dialog v-model="createVisible" title="新建盘点" width="480px" @closed="resetCreate">
            <el-form label-width="80px">
                <el-form-item label="仓库" required>
                    <el-select v-model="form.warehouse_id" placeholder="选择仓库" class="w-full" @change="onWhChange">
                        <el-option v-for="w in warehouses" :key="w.id" :label="w.warehouse_name" :value="w.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="库位">
                    <el-select v-model="form.location_id" placeholder="整仓盘点(可不选库位)" clearable class="w-full">
                        <el-option v-for="l in formLocations" :key="l.id" :label="l.location_name" :value="l.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model="form.remark" type="textarea" :rows="2" maxlength="200" show-word-limit />
                </el-form-item>
                <div class="text-xs text-gray-400">创建后系统会把该范围当前“应在库”的设备拉成清单，开始逐台核对。</div>
            </el-form>
            <template #footer>
                <el-button @click="createVisible=false">取消</el-button>
                <el-button type="primary" :loading="creating" :disabled="!form.warehouse_id" @click="doCreate">创建并开始</el-button>
            </template>
        </el-dialog>

        <!-- 盘点详情 / 录入 -->
        <el-dialog v-model="detailVisible" :title="detailTitle" width="820px" @closed="detail=null">
            <div v-if="detail" v-loading="detailLoading">
                <div class="mb-3 grid grid-cols-5 gap-3 text-center">
                    <div class="rounded bg-gray-50 py-2"><div class="text-xs text-gray-500">应盘</div><div class="font-semibold">{{ detail.system_count }}</div></div>
                    <div class="rounded bg-gray-50 py-2"><div class="text-xs text-gray-500">已盘到</div><div class="font-semibold text-green-600">{{ countedNum }}</div></div>
                    <div class="rounded bg-gray-50 py-2"><div class="text-xs text-gray-500">待盘</div><div class="font-semibold text-gray-500">{{ uncountedNum }}</div></div>
                    <div class="rounded bg-gray-50 py-2"><div class="text-xs text-gray-500">盘盈</div><div class="font-semibold text-orange-600">{{ profitNum }}</div></div>
                    <div class="rounded bg-gray-50 py-2"><div class="text-xs text-gray-500">状态</div><div class="font-semibold">{{ detail.status_text }}</div></div>
                </div>

                <div v-if="detail.status==='counting'" class="mb-3 flex gap-2">
                    <el-input v-model="scanText" type="textarea" :rows="2" class="flex-1"
                        placeholder="扫码或粘贴 IMEI，多台用空格/逗号/换行分隔，然后点“录入”" />
                    <el-button type="primary" :loading="scanning" :disabled="!scanText.trim()" @click="doScan">录入</el-button>
                </div>

                <el-table :data="detail.items" size="small" max-height="320" empty-text="无清单">
                    <el-table-column prop="imei" label="IMEI" min-width="140" show-overflow-tooltip />
                    <el-table-column prop="model" label="型号" min-width="120" show-overflow-tooltip />
                    <el-table-column label="结果" width="90" align="center">
                        <template #default="{ row }">
                            <el-tag size="small" :type="resultType(row.result)" effect="light">{{ row.result_text }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="remark" label="说明" min-width="120" show-overflow-tooltip />
                </el-table>
            </div>
            <template #footer>
                <el-button @click="detailVisible=false">关闭</el-button>
                <el-button v-if="detail && detail.status==='counting'" type="danger" :loading="finishing" @click="doFinish">
                    完成盘点（未盘到{{ uncountedNum }}台判盘亏并核销）
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getStocktakeList, getStocktakeInfo, createStocktake, scanStocktake, finishStocktake } from '@/addon/hsx_erp/api/stocktake'
import { getErpWarehouseList } from '@/addon/hsx_erp/api/warehouse'

const fmt = (t: number) => (t ? new Date(t * 1000).toLocaleString() : '-')
const resultType = (r: string) => (r === 'loss' ? 'danger' : r === 'profit' ? 'warning' : r === 'matched' ? 'success' : 'info')

const loading = ref(false)
const list = ref<any[]>([])
const total = ref(0)
const search = reactive({ warehouse_id: undefined as any, status: '', page: 1, limit: 15 })
const warehouses = ref<any[]>([])

function onPage(p: number) { search.page = p; loadList() }
async function loadWarehouses() {
    try {
        const res: any = await getErpWarehouseList()
        warehouses.value = res.data?.data || res.data || []
    } catch { warehouses.value = [] }
}
async function loadList() {
    loading.value = true
    try {
        const res: any = await getStocktakeList(search)
        list.value = res.data?.data || []
        total.value = res.data?.total || 0
    } finally {
        loading.value = false
    }
}

// 新建
const createVisible = ref(false)
const creating = ref(false)
const form = reactive({ warehouse_id: undefined as any, location_id: undefined as any, remark: '' })
const formLocations = ref<any[]>([])
function openCreate() { createVisible.value = true }
function onWhChange(id: number) {
    form.location_id = undefined
    const w = warehouses.value.find((x) => x.id === id)
    formLocations.value = w?.locations || []
}
async function doCreate() {
    if (!form.warehouse_id) return
    creating.value = true
    try {
        const res: any = await createStocktake({ warehouse_id: form.warehouse_id, location_id: form.location_id || 0, remark: form.remark })
        ElMessage.success(`已创建，应盘 ${res.data?.system_count ?? 0} 台`)
        createVisible.value = false
        await loadList()
        if (res.data?.stocktake_id) openDetail({ id: res.data.stocktake_id, status: 'counting' })
    } finally {
        creating.value = false
    }
}
function resetCreate() {
    form.warehouse_id = undefined; form.location_id = undefined; form.remark = ''; formLocations.value = []
}

// 详情 / 录入
const detailVisible = ref(false)
const detailLoading = ref(false)
const detail = ref<any>(null)
const scanText = ref('')
const scanning = ref(false)
const finishing = ref(false)
const detailTitle = computed(() => '盘点 · ' + (detail.value?.stocktake_no || ''))
const countedNum = computed(() => (detail.value?.items || []).filter((i: any) => i.counted === 1 && i.result !== 'profit').length)
const uncountedNum = computed(() => (detail.value?.items || []).filter((i: any) => i.counted === 0 && i.result === 'uncounted').length)
const profitNum = computed(() => (detail.value?.items || []).filter((i: any) => i.result === 'profit').length)

async function openDetail(row: any) {
    detailVisible.value = true
    detailLoading.value = true
    try {
        const res: any = await getStocktakeInfo(row.id)
        detail.value = res.data
    } finally {
        detailLoading.value = false
    }
}
async function doScan() {
    const codes = scanText.value.split(/[\s,，;；]+/).map((x) => x.trim()).filter(Boolean)
    if (!codes.length || !detail.value) return
    scanning.value = true
    try {
        const res: any = await scanStocktake(detail.value.id, codes)
        ElMessage.success(`录入：相符 +${res.data?.matched_added || 0}，盘盈 +${res.data?.profit_added || 0}`)
        scanText.value = ''
        await openDetail(detail.value)
    } finally {
        scanning.value = false
    }
}
async function doFinish() {
    if (!detail.value) return
    try {
        await ElMessageBox.confirm(`未盘到的 ${uncountedNum.value} 台将判为盘亏并核销离库（写库存流水）。确认完成？`, '完成盘点', { type: 'warning' })
    } catch { return }
    finishing.value = true
    try {
        const res: any = await finishStocktake(detail.value.id, 1)
        ElMessage.success(`盘点完成：相符 ${res.data?.match || 0}，盘亏 ${res.data?.loss || 0}，盘盈 ${res.data?.profit || 0}`)
        detailVisible.value = false
        loadList()
    } finally {
        finishing.value = false
    }
}

loadWarehouses()
loadList()
</script>
