<template>
    <div class="device-identity" @click.stop>
        <el-tooltip :content="identity.source || '原订单设备资料'" placement="top">
            <div>
                <div v-if="identity.imei">IMEI <span>{{ identity.imei }}</span></div>
                <div v-if="identity.sn">SN <span>{{ identity.sn }}</span></div>
                <div v-if="!identity.imei && !identity.sn && identity.sku_no">商品编码 <span>{{ identity.sku_no }}</span></div>
                <div v-if="!identity.imei && !identity.sn && !identity.sku_no">设备串号未记录</div>
            </div>
        </el-tooltip>
        <div v-if="row.return_state === 'returned'" class="return-note">已退回 · 原单记录保留<span v-if="row.return_receiver"> · 收回 {{ row.return_receiver }}</span></div>
        <div v-if="row.can_confirm_received" class="receipt-action">
            <span>退款后需核对实物</span>
            <el-button type="primary" link :loading="busy" @click="confirmReceipt">确认设备已收回</el-button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { ElMessageBox, ElMessage } from 'element-plus'
import { confirmOrderDeviceReceived } from '@/addon/phone_shop/api/order'
const props = defineProps<{ row: any }>()
const emit = defineEmits(['complete'])
const busy = ref(false)
const identity = computed(() => props.row.device_identity || props.row.extend?.erp_device || { sku_no: props.row.sku?.sku_no || '' })
async function confirmReceipt() {
    if (busy.value) return
    try {
        await ElMessageBox.confirm(`请核对 ${identity.value.imei || identity.value.sn || identity.value.sku_no || '该设备'} 已经实际收回。确认后恢复一台库存并保持待上架；不会再收款或新增应收，原退款记录保留。`, '确认实物收回', { type: 'warning', confirmButtonText: '已核对并收回', cancelButtonText: '尚未收回' })
    } catch { return }
    busy.value = true
    try {
        await confirmOrderDeviceReceived(props.row.order_goods_id)
        emit('complete')
    } catch (error: any) {
        ElMessage.error(error?.msg || error?.message || '暂未确认收回结果，请刷新订单核对后重试，不要重复入库')
    } finally { busy.value = false }
}
</script>

<style scoped>
.device-identity { margin-top: 5px; color: #64748b; font-size: 12px; line-height: 19px; overflow-wrap: anywhere; }
.device-identity span { user-select: text; font-variant-numeric: tabular-nums; }
.return-note { color: #64748b; margin-top: 3px; }
.receipt-action { display: flex; flex-wrap: wrap; gap: 3px 8px; align-items: center; color: #b45309; }
</style>
