<template>
    <el-dialog v-model="visible" :title="`开单 · 卖出（${devices.length} 台）`" width="720px" :close-on-click-modal="false" class="sell-dialog" destroy-on-close>
        <el-form label-width="84px" v-loading="submitting">
            <!-- 设备清单 -->
            <el-form-item label="出售设备" required>
                <div class="w-full">
                    <el-table :data="devices" size="small" border max-height="260" class="dev-table">
                        <el-table-column label="设备" min-width="200">
                            <template #default="{ row }">
                                <div class="flex items-center gap-2">
                                    <el-image v-if="row.cover" :src="row.cover" fit="cover" class="dev-cover" />
                                    <div class="min-w-0">
                                        <div class="dev-name" :title="row.name">{{ row.name }}</div>
                                        <div class="dev-sub">
                                            <span v-if="row.memory">{{ row.memory }}</span>
                                            <span v-if="row.condition"> · {{ row.condition }}</span>
                                            <span class="text-gray-300"> · 资产{{ row.erp_asset_id }}</span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column label="成交价" width="150" align="right">
                            <template #default="{ row }">
                                <el-input-number v-model="row.price" :min="0" :precision="2" :controls="false" size="small" class="!w-[110px]" />
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="110" align="center">
                            <template #default="{ row, $index }">
                                <el-popover placement="left" :width="280" trigger="click">
                                    <template #reference><el-button link type="primary" size="small">详情</el-button></template>
                                    <div class="dev-detail">
                                        <el-image v-if="row.cover" :src="row.cover" fit="cover" class="dd-cover" />
                                        <div class="dd-line"><span>型号</span>{{ row.name }}</div>
                                        <div class="dd-line"><span>内存</span>{{ row.memory || '—' }}</div>
                                        <div class="dd-line"><span>成色</span>{{ row.condition || '—' }}</div>
                                        <div class="dd-line"><span>IMEI</span>{{ row.imei || '—' }}</div>
                                        <div class="dd-line"><span>资产ID</span>{{ row.erp_asset_id }}</div>
                                        <div class="dd-line"><span>挂牌价</span>¥{{ row.list_price }}</div>
                                    </div>
                                </el-popover>
                                <el-button v-if="devices.length > 1" link type="danger" size="small" @click="devices.splice($index, 1)">移除</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="dev-foot">
                        <span>共 <b>{{ devices.length }}</b> 台</span>
                        <div class="flex items-center gap-2">
                            <el-input-number v-model="batchPrice" :min="0" :precision="2" :controls="false" size="small" class="!w-[110px]" placeholder="统一价" />
                            <el-button link type="primary" size="small" @click="applyBatchPrice">统一改价</el-button>
                        </div>
                    </div>
                </div>
            </el-form-item>

            <el-form-item label="客户" required>
                <counterparty-select v-model="counterpartyId" value-field="member_id" role-type="customer"
                                     placeholder="搜索姓名/手机号选择买家（无主体自动建）" class="w-full" @resolved="onCustomer" />
            </el-form-item>

            <el-form-item label="结算方式" required>
                <div class="settle-cards">
                    <div class="settle-card" :class="{ active: settleMode === 'now' }" @click="settleMode = 'now'">
                        <div class="settle-icon">💰</div><div class="settle-title">现结</div><div class="settle-desc">当场收款入账</div>
                    </div>
                    <div class="settle-card" :class="{ active: settleMode === 'later' }" @click="settleMode = 'later'">
                        <div class="settle-icon">🧾</div><div class="settle-title">挂账</div><div class="settle-desc">先出货，后结款（应收）</div>
                    </div>
                </div>
            </el-form-item>

            <el-form-item v-if="settleMode === 'now'" label="收款账户" required>
                <div class="w-full">
                    <div v-for="(pm, i) in payments" :key="i" class="pay-row">
                        <el-select v-model="pm.account_id" placeholder="账户" filterable class="flex-1">
                            <el-option v-for="a in accountOptions" :key="a.id" :value="a.id"
                                       :label="`${a.account_name || a.name}（余额 ¥${a.balance ?? 0}）`" />
                        </el-select>
                        <el-input-number v-model="pm.amount" :min="0" :precision="2" :controls="false" class="!w-[120px]" placeholder="金额" />
                        <el-button v-if="payments.length > 1" type="danger" link @click="payments.splice(i, 1)">删</el-button>
                    </div>
                    <div class="pay-foot">
                        <el-button link type="primary" @click="addPayment">+ 添加账户（多账户收款）</el-button>
                        <span class="pay-sum" :class="{ bad: payDiff !== 0 }">已分配 ¥{{ paySum }} / 应收 ¥{{ total }}</span>
                    </div>
                </div>
            </el-form-item>

            <el-form-item label="快递单号">
                <el-input v-model.trim="expressNo" placeholder="打包发货填物流单号，便于追踪（选填）" maxlength="64" class="w-full" />
            </el-form-item>

            <el-form-item label="备注">
                <el-input v-model="remark" type="textarea" :autosize="{ minRows: 2, maxRows: 5 }" maxlength="200" show-word-limit placeholder="选填，如客户讲价等" class="w-full" />
            </el-form-item>
        </el-form>

        <template #footer>
            <div class="foot-bar">
                <div class="foot-total">合计 <b>¥{{ total }}</b> <span>· {{ devices.length }} 台</span></div>
                <div>
                    <el-button @click="visible = false">取消</el-button>
                    <el-button type="primary" :loading="submitting" @click="submit">确认开单出库</el-button>
                </div>
            </div>
        </template>
    </el-dialog>
</template>

<script lang="ts" setup>
import { computed, ref, watch } from 'vue'
import { img } from '@/utils/common'
import { ElMessage } from 'element-plus'
import CounterpartySelect from '@/addon/phone_shop/components/CounterpartySelect.vue'
import { getErpCapitalAccounts, erpOutboundCreate } from '@/addon/phone_shop/api/erp_outbound'

const props = defineProps<{ modelValue: boolean; goodsList: any[] }>()
const emit = defineEmits(['update:modelValue', 'done'])

const visible = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) })

const submitting = ref(false)
const accountOptions = ref<any[]>([])
const devices = ref<any[]>([])
const counterpartyId = ref<number | string>('')
const settleMode = ref('now')
const payments = ref<{ account_id: number | string; amount: number }[]>([{ account_id: '', amount: 0 }])
const expressNo = ref('')
const remark = ref('')
const batchPrice = ref<number>(0)

const toDevice = (g: any) => {
    const sku = g.goodsSku || g.goods_sku || {}
    return {
        goods_id: g.goods_id,
        sku_id: Number(sku.sku_id) || 0,
        erp_asset_id: Number(sku.erp_asset_id) || 0,
        name: g.goods_name || '',
        memory: g.memory_group || '',
        condition: g.condition_grade || '',
        imei: sku.sku_no || '',
        cover: g.goods_cover_thumb_small || (g.goods_cover ? img(g.goods_cover) : ''),
        list_price: Number(sku.price) || 0,
        price: Number(sku.price) || 0
    }
}

const total = computed(() => Number(devices.value.reduce((s, d) => s + (Number(d.price) || 0), 0).toFixed(2)))
const paySum = computed(() => Number(payments.value.reduce((s, p) => s + (Number(p.amount) || 0), 0).toFixed(2)))
const payDiff = computed(() => Number((total.value - paySum.value).toFixed(2)))

watch(() => props.modelValue, (v) => {
    if (!v) return
    devices.value = (props.goodsList || []).map(toDevice).filter(d => d.erp_asset_id > 0)
    counterpartyId.value = ''
    settleMode.value = 'now'
    payments.value = [{ account_id: '', amount: total.value }]
    expressNo.value = ''
    remark.value = ''
    batchPrice.value = 0
    loadAccounts()
})

// 现结单账户时，总额变化自动同步唯一那行
watch(total, (t) => { if (settleMode.value === 'now' && payments.value.length === 1) payments.value[0].amount = t })

const applyBatchPrice = () => {
    if (!(batchPrice.value > 0)) return
    devices.value.forEach(d => { d.price = batchPrice.value })
}
const addPayment = () => payments.value.push({ account_id: '', amount: payDiff.value > 0 ? payDiff.value : 0 })
const onCustomer = (_d: any) => { /* 解析后的主体信息，预留 */ }

const loadAccounts = () => {
    getErpCapitalAccounts().then((res: any) => {
        accountOptions.value = res.data?.list || (Array.isArray(res.data) ? res.data : [])
    }).catch(() => { accountOptions.value = [] })
}

const submit = () => {
    if (!devices.value.length) return ElMessage.warning('没有可出库的设备')
    if (!counterpartyId.value) return ElMessage.warning('请选择客户')
    if (devices.value.some(d => !(Number(d.price) > 0))) return ElMessage.warning('请为每台填写成交价')

    const params: Record<string, any> = {
        outbound_type: 'peer_sale',
        sale_channel: 'mall',
        counterparty_id: counterpartyId.value,
        settle_mode: settleMode.value,
        express_no: expressNo.value,
        remark: remark.value,
        items: devices.value.map(d => ({ asset_id: d.erp_asset_id, sale_price: Number(d.price) || 0 }))
    }
    if (settleMode.value === 'now') {
        const pays = payments.value.filter(p => p.account_id && Number(p.amount) > 0)
        if (!pays.length) return ElMessage.warning('现结请填写收款账户与金额')
        if (payDiff.value !== 0) return ElMessage.warning(`收款合计 ¥${paySum.value} 与应收 ¥${total.value} 不一致`)
        params.payments = pays
    }

    submitting.value = true
    erpOutboundCreate(params).then(() => {
        submitting.value = false
        ElMessage.success(devices.value.length > 1 ? `已开单出库 ${devices.value.length} 台` : '已开单出库，商品将置为已售')
        visible.value = false
        emit('done')
    }).catch(() => { submitting.value = false })
}
</script>

<style lang="scss" scoped>
.dev-table { width: 100%; }
.dev-cover { width: 40px; height: 40px; border-radius: 4px; flex-shrink: 0; }
.dev-name { font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 160px; }
.dev-sub { font-size: 11px; color: #94a3b8; }
.dev-foot { display: flex; align-items: center; justify-content: space-between; margin-top: 6px; font-size: 12px; color: #64748b; }
.dev-detail .dd-cover { width: 100%; height: 140px; border-radius: 6px; margin-bottom: 6px; }
.dd-line { font-size: 12px; padding: 2px 0; color: #334155; }
.dd-line span { display: inline-block; width: 52px; color: #94a3b8; }
.settle-cards { display: flex; gap: 12px; }
.settle-card { flex: 1; border: 1.5px solid #e5e7eb; border-radius: 8px; padding: 10px; text-align: center; cursor: pointer; transition: all .15s; }
.settle-card:hover { border-color: #93c5fd; }
.settle-card.active { border-color: var(--el-color-primary); background: var(--el-color-primary-light-9); }
.settle-icon { font-size: 22px; }
.settle-title { font-weight: 600; font-size: 13px; margin-top: 2px; }
.settle-desc { font-size: 11px; color: #94a3b8; }
.pay-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.pay-foot { display: flex; align-items: center; justify-content: space-between; }
.pay-sum { font-size: 12px; color: #64748b; }
.pay-sum.bad { color: var(--el-color-danger); font-weight: 600; }
.foot-bar { display: flex; align-items: center; justify-content: space-between; }
.foot-total { font-size: 13px; color: #64748b; }
.foot-total b { font-size: 18px; color: var(--el-color-danger); }
.foot-total span { font-size: 12px; }
</style>
