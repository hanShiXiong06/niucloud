<template>
  <section v-if="!hidden" class="inbound-status" aria-label="入库与应付">
    <header><strong>入库与应付</strong><el-button link type="primary" :loading="loading" @click="load">刷新状态</el-button></header>
    <p v-if="error" role="alert">{{ error }}</p>
    <p v-else-if="loading">正在核对本单设备…</p>
    <template v-else>
      <article v-for="r in rows" :key="r.device_id">
        <div><b>{{ r.model || '设备' }}</b> <span>{{ r.serial || ('设备 #' + r.device_id) }}</span></div>
        <div><b>{{ r.step.label }}</b><p>{{ r.step.next }}</p></div>
        <el-button v-if="r.step.action === 'payable' && canSeePayables" type="primary" link
          @click="router.push({name:'hsx_erp_payable',query:{payable_id:String(r.erp?.purchase_payable_id)}})">查看本机应付</el-button>
        <el-button v-else-if="r.step.action === 'inbound' && r.serial && router.hasRoute('recycle_device_export')" type="primary" link
          @click="router.push({name:'recycle_device_export',query:{imei:r.serial}})">核对入库</el-button>
      </article>
    </template>
  </section>
</template>
<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { getCapitalAccountOptions } from '@/addon/hsx_recycle/api/recycle_order'
import { inboundHandoff, type InboundScope } from '@/addon/hsx_recycle/utils/inbound-handoff'
const props = defineProps<{ orderId?: number | string; deviceId?: number | string; initialScope?: InboundScope }>()
const router = useRouter(), scope = ref<InboundScope>(), loading = ref(false), error = ref('')
const hidden = computed(() => !error.value && !loading.value && scope.value?.payment_owner === 'local')
const canSeePayables = computed(() => router.hasRoute('hsx_erp_payable'))
const rows = computed(() => (scope.value?.devices || []).map(d => ({ ...d, step: inboundHandoff(d) })))
let seq = 0
async function load() {
  const n = ++seq
  scope.value = undefined; error.value = ''; loading.value = true
  try {
    const r = await getCapitalAccountOptions(props.orderId, props.deviceId ? [props.deviceId] : undefined, true)
    if (!r?.data?.devices?.length) throw new Error()
    if (n === seq) scope.value = r.data
  } catch { if (n === seq) error.value = '未能核对本单入库和应付。请刷新或请有权限的财务查看原应付；不要重新下单或付款。' }
  finally { if (n === seq) loading.value = false }
}
watch(() => [props.orderId, props.deviceId, props.initialScope], () => {
  if (props.initialScope?.devices?.length) { ++seq; scope.value = props.initialScope; loading.value = false; error.value = '' }
  else load()
}, { immediate: true })
</script>
<style scoped>
.inbound-status{padding:14px 18px;border:1px solid var(--el-border-color-light);border-radius:8px;background:var(--el-bg-color)}
header,article{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
article{border-top:1px solid var(--el-border-color-lighter);padding:10px 0}article:last-child{padding-bottom:0}
article>div:first-child{flex:1;min-width:150px}article>div:nth-child(2){flex:2;min-width:180px}article b{font-size:13px}span,p{font-size:12px;color:var(--el-text-color-regular)}p{margin:3px 0}
</style>
