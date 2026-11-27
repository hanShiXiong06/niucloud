<template>
    <div class="tier-price-input">
        <el-input v-if="!enabled" :model-value="modelValue" :disabled="disabled || loading || failed" placeholder="0.00" @update:model-value="value => emit('update:modelValue', value)" />
        <template v-else>
            <div class="tier-price-input__label">基准售价（最高等级会员价）</div>
            <el-input :model-value="basePrice" :disabled="disabled" placeholder="输入最低销售价" @update:model-value="changeBase" />
            <div class="tier-price-input__hint">只填一次，各等级自动加价；不影响成本和已有订单。</div>
            <div v-if="busy" class="tier-price-input__hint">正在计算…</div>
            <div v-else-if="error" class="tier-price-input__error">{{ error }}</div>
            <div v-else class="tier-price-input__preview"><span v-for="row in prices" :key="row.level_no">{{ row.name }} ¥{{ row.price }}</span></div>
        </template>
        <div v-if="failed" class="tier-price-input__error">价格规则加载失败，请刷新后再定价</div>
    </div>
</template>
<script setup lang="ts">
import { ref, watch, onBeforeUnmount } from 'vue'
import { getTierPricing, previewTierPricing } from '@/addon/phone_shop/api/tier_pricing'
const props = defineProps<{ modelValue?: any, basePrice?: any, disabled?: boolean }>()
const emit = defineEmits(['update:modelValue', 'update:basePrice', 'policy'])
const enabled = ref(false), loading = ref(true), failed = ref(false), busy = ref(false)
const prices = ref<any[]>([]), error = ref('')
let timer: any, sequence = 0, disposed = false
const changeBase = (value: any) => {
    emit('update:basePrice', value)
    // 先让必填校验感知输入；保存时后端按 basePrice 重新计算，不依赖异步试算。
    emit('update:modelValue', value)
}
function calculate() {
    clearTimeout(timer)
    const id = ++sequence
    prices.value = []; error.value = ''; busy.value = false
    if (!enabled.value || !(Number(props.basePrice) > 0)) return
    busy.value = true
    timer = setTimeout(async () => {
        try {
            const res = await previewTierPricing(Number(props.basePrice))
            if (disposed || id !== sequence) return
            prices.value = res.data.prices || []
            emit('update:modelValue', res.data.retail_price)
        } catch (e: any) { if (id === sequence) error.value = e?.msg || e?.message || '规则暂不可用，保存时将再次校验' }
        finally { if (id === sequence) busy.value = false }
    }, 250)
}
watch(() => props.basePrice, calculate)
getTierPricing().then(res => { if (disposed) return; enabled.value = Number(res.data.enabled) === 1; emit('policy', res.data); calculate() })
    .catch(() => { failed.value = true }).finally(() => { loading.value = false })
onBeforeUnmount(() => { disposed = true; ++sequence; clearTimeout(timer) })
</script>
<style scoped>
.tier-price-input{width:100%;min-width:150px}.tier-price-input__label{font-size:12px;color:#334155;line-height:20px;margin-bottom:4px}.tier-price-input__hint{font-size:12px;color:#64748b;line-height:18px;margin-top:6px}.tier-price-input__error{font-size:12px;color:#dc2626;line-height:18px;margin-top:6px}.tier-price-input__preview{display:flex;flex-wrap:wrap;gap:4px 12px;font-size:12px;color:#2563eb;line-height:20px;margin-top:6px}
</style>
