<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">出库管理 · 同行出货</div>
                    <div class="mt-1 text-sm text-gray-500">
                        把在库设备卖给同行并出库。可现结(出库即填价)或先出库、价格未来回填。同行销售出库会按往来单位生成应收。
                    </div>
                </div>
                <el-button type="primary" @click="openCreate">新建出库</el-button>
            </div>

            <el-form :inline="true" class="mt-4" @submit.prevent>
                <el-form-item label="出库类型">
                    <el-select v-model="search.outbound_type" placeholder="全部" clearable @change="loadList" class="!w-[140px]">
                        <el-option label="同行销售" value="peer_sale" />
                        <el-option label="报废出库" value="scrap" />
                        <el-option label="其他出库" value="other" />
                    </el-select>
                </el-form-item>
                <el-form-item label="价格状态">
                    <el-select v-model="search.price_status" placeholder="全部" clearable @change="loadList" class="!w-[120px]">
                        <el-option label="待回填" value="pending" />
                        <el-option label="已定价" value="filled" />
                    </el-select>
                </el-form-item>
                <el-form-item label="关键词">
                    <el-input v-model.trim="search.keyword" placeholder="出库单号 / 往来单位" clearable class="!w-[200px]" @keyup.enter="loadList" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadList" :loading="loading">查询</el-button>
                </el-form-item>
            </el-form>

            <el-table class="mt-4" :data="list" v-loading="loading" size="large" empty-text="暂无出库单">
                <el-table-column prop="outbound_no" label="出库单号" min-width="170" />
                <el-table-column prop="type_text" label="类型" width="100" />
                <el-table-column label="往来单位" min-width="140">
                    <template #default="{ row }">{{ row.counterparty_name || (row.counterparty_id ? '#' + row.counterparty_id : '-') }}</template>
                </el-table-column>
                <el-table-column prop="qty" label="台数" width="80" align="center" />
                <el-table-column label="出货总额" width="120" align="right">
                    <template #default="{ row }">{{ money(row.total_amount) }}</template>
                </el-table-column>
                <el-table-column label="价格状态" width="110" align="center">
                    <template #default="{ row }">
                        <el-tag v-if="row.price_status === 'pending'" type="warning" effect="light">待回填</el-tag>
                        <el-tag v-else type="success" effect="light">已定价</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="出库时间" width="170">
                    <template #default="{ row }">{{ row.out_at ? formatTime(row.out_at) : '-' }}</template>
                </el-table-column>
                <el-table-column label="操作" width="150" align="center" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="openInfo(row)">详情</el-button>
                        <el-button v-if="row.price_status === 'pending'" type="warning" link @click="openFill(row)">回填价格</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div class="mt-4 flex justify-end">
                <el-pagination layout="total, prev, pager, next" :total="total" :page-size="search.limit"
                    :current-page="search.page" @current-change="onPageChange" />
            </div>
        </el-card>

        <!-- 新建出库 -->
        <el-dialog v-model="createVisible" title="新建出库" width="900px" @closed="resetCreate">
            <el-form :model="form" label-width="90px">
                <el-form-item label="出库类型">
                    <el-radio-group v-model="form.outbound_type" @change="onTypeChange">
                        <el-radio label="peer_sale">同行销售</el-radio>
                        <el-radio label="scrap">报废出库</el-radio>
                        <el-radio label="other">其他出库</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item v-if="form.outbound_type === 'peer_sale'" label="对接人/交易人">
                    <el-select v-model="form.counterparty_id" filterable remote clearable :remote-method="searchContacts"
                        :loading="cpLoading" placeholder="按姓名/手机检索交易人" class="!w-[320px]" @change="onContactChange">
                        <el-option v-for="c in contacts" :key="c.member_id"
                            :label="(c.nickname || c.username || ('会员#' + c.member_id)) + (c.mobile ? ('·' + c.mobile) : '') + (c.counterparty_name ? ('（' + c.counterparty_name + '）') : '')"
                            :value="c.member_id" />
                    </el-select>
                    <el-button class="!ml-2" link type="primary" @click="openQuickContact">+ 快速建档</el-button>
                    <div class="mt-1 text-xs text-gray-400">出库按"交易人"记账(与回收同口径,可折账)。同行没建档点"快速建档"一步搞定。</div>
                </el-form-item>
                <el-form-item v-if="form.outbound_type === 'peer_sale'" label="结算方式">
                    <el-radio-group v-model="form.settle_mode">
                        <el-radio label="now">现结(出库即填价)</el-radio>
                        <el-radio label="later">价格未来回填</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="选择设备">
                    <div class="w-full">
                        <div class="mb-2 flex items-center gap-2">
                            <el-select v-model="assetWarehouseId" placeholder="按仓库筛选" clearable class="!w-[200px]" @change="loadAvailableAssets">
                                <el-option v-for="w in warehouseOptions" :key="w.id"
                                    :label="w.warehouse_name + '（' + businessTypeLabel(w.business_type) + '）'" :value="w.id" />
                            </el-select>
                            <el-input v-model.trim="assetKeyword" placeholder="资产号/IMEI/型号" clearable class="!w-[200px]" @keyup.enter="loadAvailableAssets" />
                            <el-button @click="loadAvailableAssets" :loading="assetLoading">筛选</el-button>
                            <span class="text-xs text-gray-400">先按仓库性质筛选，再勾选要出库的设备</span>
                        </div>
                        <el-table :data="availableAssets" size="small" max-height="300" @selection-change="onAssetSelect"
                            v-loading="assetLoading" empty-text="无可出库设备（试试切换仓库）">
                            <el-table-column type="selection" width="40" />
                            <el-table-column prop="asset_no" label="资产号" min-width="120" show-overflow-tooltip />
                            <el-table-column label="所在仓库" min-width="130" show-overflow-tooltip>
                                <template #default="{ row }">{{ warehouseName(row.warehouse_id) }}</template>
                            </el-table-column>
                            <el-table-column prop="model" label="型号" min-width="120" show-overflow-tooltip />
                            <el-table-column prop="imei" label="IMEI" min-width="120" show-overflow-tooltip />
                            <el-table-column v-if="showPrice" label="出货价" width="140">
                                <template #default="{ row }">
                                    <el-input-number v-model="priceInput[row.id]" :min="0" :controls="false" size="small" class="w-28" />
                                </template>
                            </el-table-column>
                            <el-table-column v-if="showConsignorCol" label="应付寄卖人" width="150">
                                <template #default="{ row }">
                                    <el-input-number v-if="isConsign(row)" v-model="consignorInput[row.id]" :min="0" :precision="2" :controls="false" size="small" class="w-28" />
                                    <span v-else class="text-xs text-gray-300">非代卖</span>
                                </template>
                            </el-table-column>
                        </el-table>
                        <div class="mt-1 text-xs text-gray-400">已选 {{ selectedAssets.length }} 台</div>
                    </div>
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model="form.remark" type="textarea" :rows="2" maxlength="200" show-word-limit />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="createVisible = false">取消</el-button>
                <el-button type="primary" :loading="submitting" :disabled="selectedAssets.length === 0" @click="doCreate">确认出库</el-button>
            </template>
        </el-dialog>

        <!-- 回填价格 -->
        <el-dialog v-model="fillVisible" title="回填出货价" width="640px" @closed="fillItems = []">
            <el-table :data="fillItems" size="small" empty-text="无明细">
                <el-table-column prop="model" label="型号" min-width="120" show-overflow-tooltip />
                <el-table-column prop="imei" label="IMEI" min-width="120" show-overflow-tooltip />
                <el-table-column label="出货价" width="160">
                    <template #default="{ row }">
                        <el-input-number v-model="row.sale_price" :min="0" :controls="false" size="small" class="w-32" />
                    </template>
                </el-table-column>
            </el-table>
            <template #footer>
                <el-button @click="fillVisible = false">取消</el-button>
                <el-button type="primary" :loading="submitting" @click="doFill">确认回填</el-button>
            </template>
        </el-dialog>

        <!-- 详情 -->
        <el-dialog v-model="infoVisible" title="出库单详情" width="720px">
            <div v-if="infoData" v-loading="infoLoading">
                <div class="mb-3 grid grid-cols-2 gap-2 text-sm">
                    <div>单号：{{ infoData.outbound_no }}</div>
                    <div>类型：{{ infoData.type_text }}</div>
                    <div>往来单位：{{ infoData.counterparty_name || ('#' + infoData.counterparty_id) }}</div>
                    <div>总额：{{ money(infoData.total_amount) }}</div>
                </div>
                <el-table :data="infoData.items || []" size="small" empty-text="无明细">
                    <el-table-column prop="model" label="型号" min-width="120" show-overflow-tooltip />
                    <el-table-column prop="imei" label="IMEI" min-width="120" show-overflow-tooltip />
                    <el-table-column label="出货价" width="110" align="right">
                        <template #default="{ row }">{{ money(row.sale_price) }}</template>
                    </el-table-column>
                    <el-table-column label="应收" width="80" align="center">
                        <template #default="{ row }">
                            <el-tag size="small" :type="row.receivable_emitted ? 'success' : 'info'">{{ row.receivable_emitted ? '已生成' : '未生成' }}</el-tag>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
        </el-dialog>

        <!-- 快速建档:对接人+主体一步建 -->
        <el-dialog v-model="quickContact.visible" title="快速建档(对接人)" width="420px" append-to-body>
            <el-form label-width="84px">
                <el-form-item label="对接人" required>
                    <el-input v-model.trim="quickContact.name" placeholder="交易人姓名,如 张三 / 某同行老板" />
                </el-form-item>
                <el-form-item label="手机号" required>
                    <el-input v-model.trim="quickContact.mobile" placeholder="用于建会员档,必填" />
                </el-form-item>
                <el-form-item label="所属主体">
                    <el-input v-model.trim="quickContact.entity_name" placeholder="留空则以对接人姓名作主体名" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="quickContact.visible = false">取消</el-button>
                <el-button type="primary" :loading="quickContact.submitting" @click="submitQuickContact">建档并选中</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { getErpOutboundList, getErpOutboundInfo, createErpOutbound, fillErpOutboundPrice } from '@/addon/hsx_erp/api/outbound'
import { getErpAssetList } from '@/addon/hsx_erp/api/asset'
import { getErpMemberOptions, quickCreateErpContact } from '@/addon/hsx_erp/api/counterparty'
import { getErpWarehouseOptions } from '@/addon/hsx_erp/api/warehouse'

const money = (v: any) => '¥' + Number(v || 0).toFixed(2)
const formatTime = (t: number) => new Date(t * 1000).toLocaleString()

const loading = ref(false)
const list = ref<any[]>([])
const total = ref(0)
const search = reactive({ outbound_type: '', price_status: '', keyword: '', page: 1, limit: 15 })

function onPageChange(p: number) {
    search.page = p
    loadList()
}

async function loadList() {
    loading.value = true
    try {
        const res: any = await getErpOutboundList(search)
        list.value = res.data?.data || []
        total.value = res.data?.total || 0
    } finally {
        loading.value = false
    }
}

// 新建出库
const createVisible = ref(false)
const submitting = ref(false)
const form = reactive({ outbound_type: 'peer_sale', counterparty_id: 0, counterparty_name: '', settle_mode: 'now', remark: '' })
const contacts = ref<any[]>([])
const cpLoading = ref(false)
const warehouseOptions = ref<any[]>([])
const assetWarehouseId = ref<number | ''>('')
const assetKeyword = ref('')
const businessTypeMap: Record<string, string> = { mall: '商城', peer: '同行', consignment: '代卖', hold: '暂存', scrap: '报废' }
const businessTypeLabel = (v: string) => businessTypeMap[v] || '商城'
const warehouseName = (id: number) => warehouseOptions.value.find((w: any) => Number(w.id) === Number(id))?.warehouse_name || '-'
const availableAssets = ref<any[]>([])
const assetLoading = ref(false)
const selectedAssets = ref<any[]>([])
const priceInput = reactive<Record<number, number>>({})
const consignorInput = reactive<Record<number, number>>({})
const showPrice = computed(() => form.outbound_type === 'peer_sale' && form.settle_mode === 'now')
// 代卖设备卖出需填"应付寄卖人"金额（人手填）
const isConsign = (row: any) => String(row?.ownership_type) === 'consign'
const showConsignorCol = computed(() => form.outbound_type === 'peer_sale' && availableAssets.value.some((a) => isConsign(a)))

async function openCreate() {
    createVisible.value = true
    await Promise.all([searchContacts(''), loadWarehouses(), loadAvailableAssets()])
}
async function loadWarehouses() {
    try {
        const res: any = await getErpWarehouseOptions()
        warehouseOptions.value = res.data || []
    } catch { warehouseOptions.value = [] }
}
async function searchContacts(keyword: string) {
    cpLoading.value = true
    try {
        const res: any = await getErpMemberOptions({ keyword: keyword || '' })
        contacts.value = res.data || []
    } catch { contacts.value = [] } finally {
        cpLoading.value = false
    }
}
async function loadAvailableAssets() {
    assetLoading.value = true
    try {
        const params: any = { sellable: 1, page: 1, limit: 200 }
        if (assetWarehouseId.value) params.warehouse_id = assetWarehouseId.value
        if (assetKeyword.value) params.keyword = assetKeyword.value
        const res: any = await getErpAssetList(params)
        availableAssets.value = res.data?.data || []
    } finally {
        assetLoading.value = false
    }
}
function onTypeChange() {
    if (form.outbound_type !== 'peer_sale') form.settle_mode = 'none'
    else if (form.settle_mode === 'none') form.settle_mode = 'now'
}
function onContactChange(id: number) {
    const c = contacts.value.find((x) => x.member_id === id)
    form.counterparty_name = c ? (c.nickname || c.username || '') : ''
}

// 快速建档:一步建对接人+主体
const quickContact = reactive<any>({ visible: false, submitting: false, name: '', mobile: '', entity_name: '' })
function openQuickContact() {
    Object.assign(quickContact, { visible: true, submitting: false, name: '', mobile: '', entity_name: '' })
}
async function submitQuickContact() {
    if (!quickContact.name) return ElMessage.warning('请填写对接人姓名')
    if (!quickContact.mobile) return ElMessage.warning('请填写手机号(用于建档)')
    quickContact.submitting = true
    try {
        const res: any = await quickCreateErpContact({
            name: quickContact.name, mobile: quickContact.mobile, entity_name: quickContact.entity_name,
        })
        const d = res.data || {}
        // 选中刚建好的对接人(锚=member_id)
        contacts.value.unshift({ member_id: d.member_id, nickname: d.member_name, username: d.member_name, mobile: d.mobile, counterparty_name: d.counterparty_name })
        form.counterparty_id = d.member_id
        form.counterparty_name = d.member_name
        ElMessage.success('已建档并选中:' + d.member_name + '（' + d.counterparty_name + '）')
        quickContact.visible = false
    } catch (e: any) {
        ElMessage.error(e?.message || '建档失败')
    } finally {
        quickContact.submitting = false
    }
}
function onAssetSelect(rows: any[]) {
    selectedAssets.value = rows
}
async function doCreate() {
    if (selectedAssets.value.length === 0) return
    const items = selectedAssets.value.map((a) => ({
        asset_id: a.id,
        sale_price: showPrice.value ? (priceInput[a.id] || 0) : 0,
        consignor_payable: isConsign(a) ? (consignorInput[a.id] || 0) : 0,
    }))
    if (showPrice.value && items.some((i) => !i.sale_price)) {
        ElMessage.warning('现结出库请为每台填写出货价')
        return
    }
    submitting.value = true
    try {
        await createErpOutbound({
            outbound_type: form.outbound_type,
            counterparty_id: form.counterparty_id,
            counterparty_name: form.counterparty_name,
            settle_mode: form.outbound_type === 'peer_sale' ? form.settle_mode : 'none',
            remark: form.remark,
            items,
        })
        ElMessage.success('出库成功')
        createVisible.value = false
        loadList()
    } finally {
        submitting.value = false
    }
}
function resetCreate() {
    form.outbound_type = 'peer_sale'
    form.counterparty_id = 0
    form.counterparty_name = ''
    form.settle_mode = 'now'
    form.remark = ''
    selectedAssets.value = []
    assetWarehouseId.value = ''
    assetKeyword.value = ''
    Object.keys(priceInput).forEach((k) => delete priceInput[Number(k)])
    Object.keys(consignorInput).forEach((k) => delete consignorInput[Number(k)])
}

// 回填价格
const fillVisible = ref(false)
const fillItems = ref<any[]>([])
const fillOrderId = ref(0)
async function openFill(row: any) {
    fillOrderId.value = row.id
    const res: any = await getErpOutboundInfo(row.id)
    fillItems.value = (res.data?.items || []).map((it: any) => ({ ...it, sale_price: Number(it.sale_price || 0) }))
    fillVisible.value = true
}
async function doFill() {
    const items = fillItems.value.map((it) => ({ item_id: it.id, sale_price: it.sale_price }))
    if (items.some((i) => !i.sale_price)) {
        ElMessage.warning('请为每台填写出货价')
        return
    }
    submitting.value = true
    try {
        await fillErpOutboundPrice(fillOrderId.value, items)
        ElMessage.success('回填成功，已生成应收')
        fillVisible.value = false
        loadList()
    } finally {
        submitting.value = false
    }
}

// 详情
const infoVisible = ref(false)
const infoLoading = ref(false)
const infoData = ref<any>(null)
async function openInfo(row: any) {
    infoVisible.value = true
    infoLoading.value = true
    try {
        const res: any = await getErpOutboundInfo(row.id)
        infoData.value = res.data
    } finally {
        infoLoading.value = false
    }
}

loadList()
</script>
