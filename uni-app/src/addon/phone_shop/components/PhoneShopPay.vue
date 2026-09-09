<template>
    <FrameworkPay ref="cashier" :ignore-pay="ignorePay" :reformat="reformat" @close="onClose" @confirm="onConfirm" />
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import FrameworkPay from '@/components/pay/pay.vue'
import { usePhoneShopPaymentAmount } from '@/addon/phone_shop/hooks/usePhoneShopPaymentAmount'

withDefaults(defineProps<{ ignorePay?: string[], reformat?: string }>(), { ignorePay: () => [], reformat: '' })
const emit = defineEmits(['close', 'confirm'])
const cashier = ref<any>(null)
const payInfo = computed(() => cashier.value?.payInfo)
const { activate, deactivate } = usePhoneShopPaymentAmount(() => payInfo.value)
let current: { tradeType: string, tradeId: number } | null = null
const open = (tradeType: string, tradeId: number, payReturn = '', scene = '') => {
    current = { tradeType, tradeId }
    activate(tradeType, tradeId)
    cashier.value?.open(tradeType, tradeId, payReturn, scene)
}
// 从微信/其他应用回来继续付款时，恢复当前收银台的核对信息。
onShow(() => { if (current) activate(current.tradeType, current.tradeId) })
const onClose = () => { current = null; deactivate(); emit('close') }
const onConfirm = () => { current = null; deactivate(); emit('confirm') }
defineExpose({ open, payInfo })
</script>
