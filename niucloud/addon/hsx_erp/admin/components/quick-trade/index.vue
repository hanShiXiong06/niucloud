<template>
    <el-dialog v-model="visible" title="快速出入库 · 一单成账" width="860px" :close-on-click-modal="false" destroy-on-close class="quick-trade">
        <el-alert type="info" :closable="false" show-icon class="mb-3"
                  title="只走个账：选好向谁进的货、卖给了谁，逐台填型号/IMEI 和 进价/出货价即可。每台自动生成「应付供货人」「应收买家」两笔账（默认挂账，后续在财务核销收/付款）。" />

        <el-form label-width="76px">
            <div class="grid grid-cols-2 gap-3">
                <el-form-item label="供货人" required>
                    <counterparty-select v-model="supplierId" value-field="counterparty_id" role-type="supplier"
                                         placeholder="向谁进的货（搜姓名/手机号，无则自动建）" class="w-full" @resolved="onSupplier" />
                </el-form-item>
                <el-form-item label="买家" required>
                    <counterparty-select v-model="buyerId" value-field="member_id" role-type="customer"
                                         placeholder="卖给了谁（搜姓名/手机号，无则自动建）" class="w-full" @resolved="onBuyer" />
                </el-form-item>
            </div>

            <el-form-item label="设备" required>
                <div class="w-full">
                    <el-table :data="rows" size="small" border class="dev-table">
                        <el-table-column type="index" label="#" width="42" align="center" />
                        <el-table-column label="型号" min-width="150">
                            <template #default="{ row }">
                                <el-input v-model="row.model" placeholder="如 iPhone 14 128G" size="small" />
                            </template>
                        </el-table-column>
                        <el-table-column label="IMEI / SN" min-width="150">
                            <template #default="{ row }">
                                <el-input v-model="row.imei" placeholder="IMEI 或 SN" size="small" />
                            </template>
                        </el-table-column>
                        <el-table-column label="进价" width="120" align="right">
                            <template #default="{ row }">
                                <el-input-number v-model="row.purchase_cost" :min="0" :precision="2" :controls="false" size="small" class="!w-[100px]" />
                            </template>
                        </el-table-column>
                        <el-table-column label="出货价" width="120" align="right">
                            <template #default="{ row }">
                                <el-input-number v-model="row.sale_price" :min="0" :precision="2" :controls="false" size="small" class="!w-[100px]" />
                            </template>
                        </el-table-column>
                        <el-table-column label="毛利" width="90" align="right">
                            <template #default="{ row }">
                                <span :class="rowProfit(row) >= 0 ? 'text-emerald-600' : 'text-red-500'">{{ rowProfit(row).toFixed(2) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="" width="48" align="center">
                            <template #default="{ $index }">
                                <el-button v-if="rows.length > 1" link type="danger" size="small" @click="rows.splice($index, 1)">删</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="mt-2 flex items-center justify-between">
                        <el-button link type="primary" size="small" @click="addRow">+ 加一台</el-button>
                        <div class="text-sm text-gray-500">
                            进货 <b class="text-gray-700">¥{{ totalBuy.toFixed(2) }}</b>
                            · 出货 <b class="text-gray-700">¥{{ totalSale.toFixed(2) }}</b>
                            · 毛利 <b :class="totalProfit >= 0 ? 'text-emerald-600' : 'text-red-500'">¥{{ totalProfit.toFixed(2) }}</b>
                        </div>
                    </div>
                </div>
            </el-form-item>

            <el-form-item label="备注">
                <el-input v-model="remark" placeholder="选填，整批备注（每台未单独填则用它）" maxlength="100" class="w-full" />
            </el-form-item>
        </el-form>

        <!-- 结果回执 -->
        <el-alert v-if="result" :type="result.fail_count ? 'warning' : 'success'" :closable="false" show-icon class="mt-1"
                  :title="`成功 ${result.success_count} 台，失败 ${result.fail_count} 台 · 应付合计 ¥${result.total_buy}，应收合计 ¥${result.total_sale}，毛利 ¥${result.total_profit}`">
            <template v-if="result.fail && result.fail.length" #default>
                <div class="text-xs mt-1">
                    <div v-for="f in result.fail" :key="f.no">第{{ f.no }}台 {{ f.model }}：{{ f.message }}</div>
                </div>
            </template>
        </el-alert>

        <template #footer>
            <div class="flex items-center justify-between">
                <span class="text-xs text-gray-400">{{ rows.length }} 台 · 默认挂账，钱到账后去财务核销</span>
                <div>
                    <el-button @click="visible = false">关闭</el-button>
                    <el-button type="primary" :loading="submitting" @click="submit">确认过账</el-button>
                </div>
            </div>
        </template>
    </el-dialog>
</template>

<script lang="ts" setup>
import { computed, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import CounterpartySelect from '@/addon/hsx_erp/components/counterparty-select/index.vue'
import { quickTradeCreate } from '@/addon/hsx_erp/api/quick_trade'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits(['update:modelValue', 'done'])

const visible = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) })

const supplierId = ref<number | string>('')      // 供货人 counterparty_id
const supplierMemberId = ref<number>(0)          // 供货人 member_id
const buyerId = ref<number | string>('')         // 买家 member_id
const remark = ref('')
const submitting = ref(false)
const result = ref<any>(null)

const blankRow = () => ({ model: '', imei: '', sn: '', purchase_cost: 0, sale_price: 0, remark: '' })
const rows = ref<any[]>([blankRow()])

const addRow = () => rows.value.push(blankRow())
const rowProfit = (r: any) => Number(((Number(r.sale_price) || 0) - (Number(r.purchase_cost) || 0)).toFixed(2))
const totalBuy = computed(() => Number(rows.value.reduce((s, r) => s + (Number(r.purchase_cost) || 0), 0).toFixed(2)))
const totalSale = computed(() => Number(rows.value.reduce((s, r) => s + (Number(r.sale_price) || 0), 0).toFixed(2)))
const totalProfit = computed(() => Number((totalSale.value - totalBuy.value).toFixed(2)))

const onSupplier = (d: any) => { supplierMemberId.value = Number(d?.member_id || 0) }
const onBuyer = (_d: any) => { /* 买家直接用 member_id（v-model） */ }

watch(() => props.modelValue, (v) => {
    if (!v) return
    supplierId.value = ''
    supplierMemberId.value = 0
    buyerId.value = ''
    remark.value = ''
    result.value = null
    rows.value = [blankRow()]
})

const submit = () => {
    if (!supplierId.value) return ElMessage.warning('请选择供货人')
    if (!buyerId.value) return ElMessage.warning('请选择买家')
    const items = rows.value.filter(r => String(r.model).trim() !== '')
    if (!items.length) return ElMessage.warning('请至少录入一台设备（型号必填）')
    if (items.some(r => !String(r.imei).trim())) return ElMessage.warning('每台请填 IMEI 或 SN')

    submitting.value = true
    quickTradeCreate({
        supplier_id: supplierId.value,
        supplier_member_id: supplierMemberId.value,
        buyer_id: buyerId.value,
        remark: remark.value,
        items: items.map(r => ({
            model: r.model, imei: r.imei, sn: r.sn,
            purchase_cost: Number(r.purchase_cost) || 0,
            sale_price: Number(r.sale_price) || 0,
            remark: r.remark
        }))
    }).then((res: any) => {
        submitting.value = false
        result.value = res.data
        if (!res.data?.fail_count) {
            ElMessage.success(`已过账 ${res.data?.success_count || 0} 台`)
            emit('done')
            // 全部成功则清空设备行，方便接着录下一批
            rows.value = [blankRow()]
        } else {
            ElMessage.warning(`成功 ${res.data?.success_count} 台，失败 ${res.data?.fail_count} 台`)
            emit('done')
        }
    }).catch(() => { submitting.value = false })
}
</script>

<style lang="scss" scoped>
.dev-table { width: 100%; }
</style>
