<template>
    <HsxDrawer v-model="visible" title="商城库存对账" size="xl" show-footer :confirm-loading="saving"
        :confirm-disabled="loading || confirming || !selected.length" :confirm-text="`确认处理 ${selected.length} 台`" @confirm="confirm">
        <HsxNotice type="info" title="先核对，再关联；不清库、不重复记账" :closable="false" default-expanded>
            <template #default>仅处理本站自营单台设备。已有 ERP 资产保留原成本、采购应付和付款记录；找不到的设备需明确确认期初成本及仓库。商城售价、图片和分类不会被覆盖。</template>
        </HsxNotice>
        <div v-if="receivableId" class="scope-note">正在核对这笔商城应收。确认只补设备、成本及出库台账，不改变应收金额、已收金额和结算记录。旧订单没有串号快照时，请先核对当前商城串号。</div>
        <div v-else class="query-bar">
            <el-radio-group v-model="scope" :disabled="saving || loading" @change="search">
                <el-radio-button label="stock">有库存待关联</el-radio-button>
                <el-radio-button label="sold">已成交待核对</el-radio-button>
            </el-radio-group>
            <el-input v-model.trim="keyword" :disabled="saving" clearable :placeholder="scope === 'sold' ? '串号 / 商品名称 / 商城订单号' : '完整串号 / 商品名称'" @keyup.enter="search" />
            <el-checkbox v-if="scope === 'stock'" v-model="includeLinked" :disabled="saving">包含已关联</el-checkbox>
            <el-button :loading="loading" :disabled="saving" @click="search">查询 / 重新预览</el-button>
        </div>
        <div class="summary-row">
            <span>待查商品 {{ total }} 条</span>
            <span>本页可关联 {{ summary.match || 0 }}</span>
            <span>本页待期初 {{ summary.opening || 0 }}</span>
            <span>本页需处理 {{ summary.conflict || 0 }}</span>
        </div>
        <HsxNotice v-if="loadError" type="error" title="预览未完成" :description="loadError" :closable="false" default-expanded />
        <el-table v-loading="loading" :data="rows" :row-key="rowKey" @selection-change="selected = $event" empty-text="未发现待关联设备">
            <el-table-column type="selection" width="46" :selectable="selectable" />
            <el-table-column label="设备 / 身份来源" min-width="240">
                <template #default="{ row }">
                    <div class="device-name">{{ row.goods_name }} {{ row.sku_name }}</div>
                    <div class="serial">{{ row.imei || row.sn || row.device?.sku_no || '缺少完整串号' }}</div>
                    <div class="muted">{{ row.identity_source }}</div>
                    <div v-if="row.source_no" class="muted">商城订单 {{ row.source_no }}</div>
                </template>
            </el-table-column>
            <el-table-column label="成本核对" width="158">
                <template #default="{ row }">
                    <div>商城 {{ money(row.cost_price) }}</div>
                    <div>ERP {{ row.erp_cost == null ? (row.state === 'opening' ? '未建账' : '待核对') : money(row.erp_cost) }}</div>
                </template>
            </el-table-column>
            <el-table-column label="处理结果 / 下一步" min-width="300">
                <template #default="{ row }">
                    <el-tag :type="stateType(row.state)">{{ stateLabel(row.state) }}</el-tag>
                    <div class="reason">{{ row.message }}</div>
                </template>
            </el-table-column>
        </el-table>
        <div v-if="!receivableId" class="pagination">
            <el-pagination v-model:current-page="page" :disabled="saving || loading" :page-size="20" :total="total" layout="prev, pager, next" @current-change="load" />
        </div>
        <div v-if="openingRows.length" class="opening-box">
            <div class="device-name">确认 {{ openingRows.length }} 台自有期初库存 · 成本合计 {{ money(openingTotal) }}</div>
            <p class="reason">成本取当前页面展示值。这里只登记启用前已拥有的库存，不代表向供应商付款，也不会生成新的采购应付；原来欠供应商的钱仍按原账处理。</p>
            <HsxNotice v-if="warehouseError" type="warning" title="期初仓库暂不可用" :description="warehouseError" :closable="false" default-expanded>
                <template #actions><el-button size="small" :loading="warehouseLoading" @click="loadWarehouses">重新获取仓库</el-button></template>
            </HsxNotice>
            <el-form label-position="top" class="opening-form" :disabled="saving">
                <el-form-item label="期初仓库" required>
                    <el-select v-model="form.warehouse_id" placeholder="选择实际存放仓库" @change="form.location_id = undefined">
                        <el-option v-for="w in warehouses" :key="w.id" :label="w.warehouse_name" :value="w.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="库位" required>
                    <el-select v-model="form.location_id" placeholder="选择库位">
                        <el-option v-for="l in locations" :key="l.id" :label="l.location_name" :value="l.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="期初日期（不得晚于成交时间）" required>
                    <el-date-picker v-model="form.opening_at" type="date" value-format="X" :disabled-date="futureDate" />
                </el-form-item>
            </el-form>
        </div>
        <HsxNotice v-if="result" class="result-notice" :type="result.failed ? 'warning' : 'success'" :closable="false"
            :title="`本次成功 ${result.success} 台，失败 ${result.failed} 台`" default-expanded>
            <div v-for="(item, index) in result.results" :key="index" class="reason">{{ item.sku_id ? `商品 SKU ${item.sku_id}：` : '' }}{{ item.message }}</div>
        </HsxNotice>
        <HsxNotice v-if="saveError" class="result-notice" type="warning" title="暂未获取处理结果" :description="saveError" :closable="false" default-expanded />
    </HsxDrawer>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { HsxDrawer, HsxNotice, useFeedback } from '@/addon/hsx_components/core'
import { previewErpMallInventory, confirmErpMallInventory } from '@/addon/hsx_erp/api/erp'
import { getErpWarehouseOptions } from '@/addon/hsx_erp/api/warehouse'

const props = withDefaults(defineProps<{ modelValue: boolean; receivableId?: number }>(), { receivableId: 0 })
const emit = defineEmits<{ (e: 'update:modelValue', value: boolean): void; (e: 'saved'): void }>()
const visible = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) })
const feedback = useFeedback()
const loading = ref(false), saving = ref(false), keyword = ref(''), includeLinked = ref(false), page = ref(1), total = ref(0)
const confirming = ref(false), loadError = ref(''), saveError = ref('')
const warehouseLoading = ref(false), warehouseError = ref('')
const scope = ref('stock')
const rows = ref<any[]>([]), selected = ref<any[]>([]), warehouses = ref<any[]>([]), summary = ref<Record<string, number>>({}), result = ref<any>(null)
const form = reactive<{ warehouse_id?: number; location_id?: number; opening_at: string }>({ opening_at: '' })
const locations = computed(() => warehouses.value.find(w => w.id === form.warehouse_id)?.locations || [])
const openingRows = computed(() => selected.value.filter(row => row.state === 'opening'))
const openingTotal = computed(() => openingRows.value.reduce((total, row) => total + Number(row.cost_price || 0), 0))
const selectable = (row: any) => ['match', 'opening'].includes(row.state) && !saving.value
const rowKey = (row: any) => row.sale_item_id ? `sale:${row.sale_item_id}` : `sku:${row.sku_id}`
const money = (value: any) => `¥${Number(value || 0).toFixed(2)}`
const futureDate = (date: Date) => date.getTime() > Date.now()
const stateLabels: Record<string, string> = { match: '可关联原库存', opening: '需确认期初', conflict: '需人工核对', linked: '已关联' }
const stateLabel = (state: string) => stateLabels[state] || state
const stateType = (state: string): 'success' | 'warning' | 'info' => state === 'match' || state === 'linked' ? 'success' : state === 'opening' ? 'info' : 'warning'
async function load() {
    if (loading.value) return
    loading.value = true
    loadError.value = ''
    selected.value = []
    try {
        const res: any = await previewErpMallInventory({ scope: scope.value, keyword: keyword.value, include_linked: includeLinked.value ? 1 : 0, page: page.value, limit: 20, receivable_id: props.receivableId })
        rows.value = res.data?.data || []
        total.value = res.data?.total || 0
        summary.value = res.data?.summary || {}
    } catch (error: any) {
        rows.value = []
        total.value = 0
        summary.value = {}
        loadError.value = error?.msg || error?.message || '暂时无法获取商城库存，请重新预览；如果提示权限不足，请联系管理员开通商城库存对账权限。'
    } finally { loading.value = false }
}
function search() { if (saving.value) return; page.value = 1; void load() }
async function loadWarehouses() {
    if (warehouseLoading.value) return
    warehouseLoading.value = true
    warehouseError.value = ''
    try {
        const res: any = await getErpWarehouseOptions()
        warehouses.value = (res.data || []).filter((w: any) => Number(w.status) === 1 && Number(w.allow_direct_sale) === 1)
        if (!warehouses.value.length) warehouseError.value = '没有已启用且允许销售的仓库，请先在仓库配置中核对；关联已有设备不受此提示影响。'
    } catch (error: any) {
        warehouses.value = []
        warehouseError.value = `${error?.msg || error?.message || '无法获取仓库列表'}。请重试，或联系管理员核对仓库查询权限。`
    } finally { warehouseLoading.value = false }
}
async function confirm() {
    if (!selected.value.length || saving.value || confirming.value) return
    if (openingRows.value.length && (!form.warehouse_id || !form.location_id || !form.opening_at)) {
        feedback.warning('期初建账请先选择仓库、库位和期初日期')
        return
    }
    const items = selected.value.map(row => ({ sku_id: row.sku_id, sale_order_id: row.sale_order_id, sale_item_id: row.sale_item_id, preview_token: row.preview_token, action: row.state === 'opening' ? 'opening' : 'link' }))
    confirming.value = true
    const confirmed = await feedback.confirm({ title: '确认库存对账', message: `本次处理 ${items.length} 台，其中 ${openingRows.value.length} 台建立自有期初库存。将复用或创建设备并关联商城${props.receivableId || scope.value === 'sold' ? '，同时补齐原成交出库' : ''}；不删除原记录，不重复收付款或创建采购应付。请确认设备和成本无误。`, type: 'warning', confirmText: '确认处理', cancelText: '再核对一下' })
    confirming.value = false
    if (!confirmed) return
    saving.value = true
    saveError.value = ''
    try {
        const res: any = await confirmErpMallInventory({ ...form, opening_at: Number(form.opening_at || 0), receivable_id: props.receivableId, items })
        result.value = res.data
        emit('saved')
        await load()
    } catch (error: any) {
        saveError.value = `${error?.msg || error?.message || '请求中断'}。请重新预览核对结果；已成功关联的设备不会重复建账，请勿另行新建采购或补收款。`
    } finally { saving.value = false }
}
watch(() => props.modelValue, value => {
    if (!value) return
    result.value = null
    saveError.value = ''
    page.value = 1
    void load()
    void loadWarehouses()
})
</script>

<style scoped>
.scope-note, .opening-box { margin-top: 16px; padding: 14px 16px; border: 1px solid #e6e8ed; border-radius: 10px; background: #f8fafc; color: #475569; line-height: 1.7; font-size: 13px; }
.query-bar { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; margin: 18px 0; }
.query-bar .el-input { width: 300px; max-width: 100%; }
.summary-row { display: flex; flex-wrap: wrap; gap: 10px 22px; margin: 18px 0; color: #475569; font-size: 13px; }
.device-name { font-weight: 600; color: #1e293b; line-height: 1.6; }
.serial { margin-top: 4px; font-family: monospace; overflow-wrap: anywhere; }
.muted { color: #64748b; font-size: 12px; margin-top: 4px; }
.reason { margin: 6px 0; color: #475569; font-size: 12px; line-height: 1.7; }
.pagination { display: flex; justify-content: flex-end; margin-top: 16px; }
.opening-form { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-top: 14px; }
.opening-form :deep(.el-select), .opening-form :deep(.el-date-editor) { width: 100%; }
.result-notice { margin-top: 18px; }
@media (max-width: 850px) { .opening-form { grid-template-columns: 1fr; gap: 0; } }
</style>
