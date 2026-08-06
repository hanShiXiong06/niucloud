<template>
    <el-dialog
        :model-value="modelValue"
        title="补录商城成交资料"
        width="980px"
        append-to-body
        destroy-on-close
        @close="close"
    >
        <el-alert
            type="warning"
            :closable="false"
            show-icon
            title="补录后会生成可追踪的 ERP 已售资产和出入库台账；不会重复生成应收，也不会创建采购应付。"
            class="mb-4"
        />

        <el-form label-width="88px">
            <div class="grid grid-cols-1 gap-x-5 md:grid-cols-2">
                <el-form-item label="成交客户">
                    <ErpPartySelect
                        v-model="form.party_id"
                        v-model:party-name="form.party_name"
                        party-type="customer"
                        placeholder="选择客户；无主体时可保留商城客户"
                    />
                </el-form-item>
                <el-form-item label="销售人员">
                    <el-select v-model="form.salesman_uid" clearable filterable class="w-full" placeholder="选择实际销售人员" @change="syncSalesmanName">
                        <el-option v-for="item in staffOptions" :key="item.uid" :label="staffName(item)" :value="Number(item.uid)" />
                    </el-select>
                </el-form-item>
            </div>
            <el-form-item label="补录说明">
                <el-input v-model.trim="form.remark" maxlength="180" show-word-limit placeholder="例如：历史商城订单未进入ERP库存，本次由财务补齐成交设备与成本" />
            </el-form-item>
        </el-form>

        <div class="mb-3 flex items-center justify-between">
            <div>
                <div class="font-medium text-gray-900">成交设备</div>
                <div class="mt-1 text-xs text-gray-500">成交价按设备填写；合计必须与本笔应收一致，后续退货与利润均按这里的实际金额计算。</div>
            </div>
            <el-button type="primary" plain @click="addItem()">添加设备</el-button>
        </div>

        <el-table :data="form.items" border size="small" max-height="420">
            <el-table-column type="index" label="#" width="48" />
            <el-table-column label="IMEI / 序列号" min-width="165">
                <template #default="{ row }"><el-input v-model.trim="row.imei" maxlength="64" placeholder="必填" /></template>
            </el-table-column>
            <el-table-column label="设备型号" min-width="210">
                <template #default="{ row }"><el-input v-model.trim="row.model" maxlength="255" placeholder="必填，如 iPhone 15 Pro 256G" /></template>
            </el-table-column>
            <el-table-column label="成本" width="145">
                <template #default="{ row }"><el-input-number v-model="row.cost" :min="0" :precision="2" :controls="false" class="!w-full" /></template>
            </el-table-column>
            <el-table-column label="实际成交价" width="155">
                <template #default="{ row }"><el-input-number v-model="row.sale_price" :min="0" :precision="2" :controls="false" class="!w-full" /></template>
            </el-table-column>
            <el-table-column label="设备备注" min-width="170">
                <template #default="{ row }"><el-input v-model.trim="row.remark" maxlength="180" placeholder="选填" /></template>
            </el-table-column>
            <el-table-column label="操作" width="72" fixed="right">
                <template #default="{ row, $index }">
                    <el-tooltip :content="row.id ? '已有成交行不能删除，可直接修正内容' : '删除本行'">
                        <span><el-button type="danger" link :disabled="Boolean(row.id)" @click="removeItem($index)">删除</el-button></span>
                    </el-tooltip>
                </template>
            </el-table-column>
        </el-table>

        <div class="mt-4 flex flex-wrap items-center justify-end gap-5 rounded bg-gray-50 px-4 py-3 text-sm">
            <span class="text-gray-500">应收金额 <b class="ml-1 text-gray-900">{{ money(targetAmount) }}</b></span>
            <span class="text-gray-500">成交价合计 <b class="ml-1 text-gray-900">{{ money(saleTotal) }}</b></span>
            <span :class="amountDiffOk ? 'text-green-600' : 'text-red-600'">{{ amountDiffOk ? '金额已对平' : `相差 ${money(Math.abs(amountDiff))}` }}</span>
            <span class="text-gray-500">成本合计 <b class="ml-1 text-gray-900">{{ money(costTotal) }}</b></span>
            <span class="text-gray-500">预计毛利 <b :class="profitTotal >= 0 ? 'ml-1 text-green-600' : 'ml-1 text-red-600'">{{ money(profitTotal) }}</b></span>
        </div>

        <template #footer>
            <el-button @click="close">取消</el-button>
            <el-button type="primary" :loading="saving" @click="submit">确认补录并留痕</el-button>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import ErpPartySelect from '@/addon/hsx_erp/components/ErpPartySelect.vue'
import { supplementErpReceivableSaleDetails } from '@/addon/hsx_erp/api/erp'

const props = withDefaults(defineProps<{
    modelValue: boolean
    receivable?: any
    items?: any[]
    staffOptions?: any[]
}>(), {
    receivable: null,
    items: () => [],
    staffOptions: () => [],
})

const emit = defineEmits<{
    (event: 'update:modelValue', value: boolean): void
    (event: 'saved', value: any): void
}>()

const saving = ref(false)
const form = reactive<any>({ party_id: 0, party_name: '', salesman_uid: 0, salesman_name: '', remark: '', items: [] })
const targetAmount = computed(() => Number(props.receivable?.amount || 0))
const saleTotal = computed(() => form.items.reduce((sum: number, item: any) => sum + Number(item.sale_price || 0), 0))
const costTotal = computed(() => form.items.reduce((sum: number, item: any) => sum + Number(item.cost || 0), 0))
const profitTotal = computed(() => saleTotal.value - costTotal.value)
const amountDiff = computed(() => Number((saleTotal.value - targetAmount.value).toFixed(2)))
const amountDiffOk = computed(() => Math.abs(amountDiff.value) <= 0.01)

watch(() => props.modelValue, visible => {
    if (visible) resetForm()
})

function resetForm() {
    const order = props.receivable?.source_order || {}
    form.party_id = Number(props.receivable?.party_id || order.party_id || 0)
    form.party_name = String(props.receivable?.party_name || order.party_name || '')
    form.salesman_uid = Number(order.salesman_uid || props.receivable?.business_operator_uid || 0)
    form.salesman_name = String(order.salesman_name || props.receivable?.business_operator_name || '')
    form.remark = ''
    form.items = (props.items || []).map((item: any) => ({
        id: Number(item.id || item.sale_item_id || 0),
        imei: String(item.imei || ''),
        model: String(item.model || ''),
        cost: Number(item.cost || 0),
        sale_price: Number(item.sale_price || 0),
        remark: String(item.remark || '').replace(/^商城补录资产[；;]?/, ''),
    }))
    if (!form.items.length) addItem(targetAmount.value)
}

function addItem(defaultPrice = 0) {
    form.items.push({ id: 0, imei: '', model: '', cost: 0, sale_price: Number(defaultPrice || 0), remark: '' })
}

function removeItem(index: number) {
    if (form.items[index]?.id) return
    form.items.splice(index, 1)
}

function syncSalesmanName(uid: number | string) {
    const selected = props.staffOptions.find((item: any) => Number(item.uid) === Number(uid))
    form.salesman_name = selected ? staffName(selected) : ''
}

function staffName(user: any) {
    return user?.name || user?.real_name || user?.username || `员工#${user?.uid || '-'}`
}

async function submit() {
    if (!props.receivable?.id) return ElMessage.warning('应收记录不存在，请刷新后重试')
    if (!form.items.length) return ElMessage.warning('请至少填写一台成交设备')
    const invalidIndex = form.items.findIndex((item: any) => !String(item.imei || '').trim() || !String(item.model || '').trim() || Number(item.cost) < 0 || Number(item.sale_price) <= 0)
    if (invalidIndex >= 0) return ElMessage.warning(`请完整填写第 ${invalidIndex + 1} 台设备的 IMEI、型号、成本和成交价`)
    if (!amountDiffOk.value) return ElMessage.warning(`成交价合计必须等于应收金额，当前相差 ${money(Math.abs(amountDiff.value))}`)
    saving.value = true
    try {
        const res: any = await supplementErpReceivableSaleDetails(Number(props.receivable.id), {
            party_id: Number(form.party_id || 0),
            party_name: form.party_name,
            salesman_uid: Number(form.salesman_uid || 0),
            salesman_name: form.salesman_name,
            remark: form.remark,
            items: form.items,
        })
        ElMessage.success('商城成交资料、ERP 已售资产和台账已补全')
        emit('saved', res?.data || {})
        close()
    } finally {
        saving.value = false
    }
}

function close() {
    emit('update:modelValue', false)
}

function money(value: number) {
    return `¥${Number(value || 0).toFixed(2)}`
}
</script>
