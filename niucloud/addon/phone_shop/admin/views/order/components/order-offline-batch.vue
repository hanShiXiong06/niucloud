<template>
    <HsxDrawer v-model="visible" :title="action === 'batch_delivery' ? '批量登记发货' : '批量确认完成'" size="lg" show-footer
        :confirm-text="hasResult ? '重试未成功订单' : '确认处理所列订单'" :confirm-loading="busy"
        :confirm-disabled="!confirmed || !pending.length || loading" @confirm="submit">
        <div class="batch-form">
            <HsxNotice :closable="false" :title="action === 'batch_delivery' ? '按整单交付；物流订单填写已有的真实运单号' : '确认客户已收货后，结束所列订单'"
                :description="action === 'batch_delivery' ? '勾选设备代表选择所属整单。物流发货不申请面单、不调用付费配送；自提订单将确认当面交付并完成。同城配送请逐单办理。已退回设备不会再次发货。' : '本操作不新增收款，不代替财务结算；仅将已发货的订单确认完成。'" />
            <HsxNotice v-if="error" type="error" :title="error" :closable="false" />
            <div v-if="hasResult" class="batch-summary">成功 {{ rows.filter(row => row.success === true).length }} 笔 · 未成功 {{ pending.length }} 笔。可修改后仅重试未成功订单。</div>
            <el-table :data="rows" border v-loading="loading">
                <el-table-column label="订单" min-width="210"><template #default="{ row }"><strong>{{ row.order_no }}</strong><div>{{ row.taker_name || row.member?.nickname || '未记录收件人' }}</div></template></el-table-column>
                <el-table-column v-if="action === 'batch_delivery'" label="实际交付信息" min-width="300">
                    <template #default="{ row }">
                        <div v-if="row.delivery_type === 'express'" class="batch-delivery">
                            <el-select v-model="row.express_company_id" filterable placeholder="快递公司" :disabled="busy || row.success === true" aria-label="快递公司">
                                <el-option v-for="company in companies" :key="company.company_id" :label="company.company_name" :value="company.company_id" />
                            </el-select>
                            <el-input v-model="row.express_number" placeholder="填写实际运单号" maxlength="60" :disabled="busy || row.success === true" />
                        </div>
                        <span v-else-if="row.delivery_type === 'store'">门店自提：确认当面交付并完成</span>
                        <span v-else class="batch-warning">同城配送或特殊订单，请逐单办理</span>
                    </template>
                </el-table-column>
                <el-table-column label="处理结果" min-width="200"><template #default="{ row }"><span :class="row.success === false ? 'batch-warning' : ''">{{ row.message || '等待确认' }}</span></template></el-table-column>
            </el-table>
            <el-checkbox v-model="confirmed" :disabled="busy" class="batch-confirm">{{ action === 'batch_delivery' ? '我已实际交件 / 当面交付，所填运单号真实正确' : '我已核实所列订单的客户均已收货' }}</el-checkbox>
        </div>
    </HsxDrawer>
</template>
<script setup lang="ts">
import { computed, ref } from 'vue'
import { HsxDrawer, HsxNotice } from '@/addon/hsx_components/core'
import { processOfflineOrderAction } from '@/addon/phone_shop/api/order'
import { getCompanyList } from '@/addon/phone_shop/api/delivery'
const emit = defineEmits<{ (event: 'complete'): void }>()
const visible = ref(false), busy = ref(false), loading = ref(false), confirmed = ref(false), error = ref('')
const action = ref('batch_delivery'), rows = ref<any[]>([]), companies = ref<any[]>([])
const pending = computed(() => rows.value.filter(row => row.success !== true))
const hasResult = computed(() => rows.value.some(row => row.success !== undefined))
async function open(orders: any[], type: string) {
    action.value = type; confirmed.value = false; error.value = ''
    rows.value = orders.map(row => ({ ...row, success: undefined, message: '', express_company_id: undefined, express_number: '' }))
    visible.value = true
    if (type !== 'batch_delivery') return
    loading.value = true
    try { companies.value = (await getCompanyList({})).data || [] } catch (e: any) { error.value = e?.msg || e?.message || '快递公司加载失败，请关闭后重试' } finally { loading.value = false }
}
async function submit() {
    if (busy.value || !confirmed.value || !pending.value.length) return
    busy.value = true; error.value = ''
    try {
        const { data } = await processOfflineOrderAction({
            action: action.value, confirmed: true,
            items: pending.value.map(row => ({ order_id: row.order_id, express_company_id: row.express_company_id || 0, express_number: row.express_number.trim() }))
        })
        for (const result of data.items || []) {
            const row = rows.value.find(item => Number(item.order_id) === Number(result.order_id))
            if (row) { row.success = result.success; row.message = result.message }
        }
        emit('complete')
    } catch (e: any) { error.value = e?.msg || e?.message || '结果未确认，请核对订单状态后重试，不要重复申请运单' }
    finally { busy.value = false; confirmed.value = false }
}
defineExpose({ open })
</script>
<style scoped>
.batch-form { display: grid; gap: 16px; }
.batch-delivery { display: grid; grid-template-columns: 130px minmax(140px, 1fr); gap: 8px; }
.batch-warning { color: var(--el-color-warning); }
.batch-summary { font-size: 13px; line-height: 22px; padding: 10px 12px; background: var(--el-fill-color-light); border-radius: 6px; }
.batch-confirm { height: auto; white-space: normal; }
.batch-confirm :deep(.el-checkbox__label) { white-space: normal; line-height: 22px; }
</style>
