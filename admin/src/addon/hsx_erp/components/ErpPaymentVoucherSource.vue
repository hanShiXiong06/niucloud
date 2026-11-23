<template>
    <div v-if="targets.length" class="voucher-origin">
        <el-button link type="primary" :aria-expanded="open" @click="toggle">{{ open ? '收起原应付' : '查看原应付与回执' }}</el-button>
        <template v-if="open">
            <el-select v-if="targets.length > 1" v-model="selected" aria-label="选择本凭证分摊的应付" placeholder="选择要核对的应付" @change="load">
                <el-option v-for="t in targets" :key="t.target_id" :value="Number(t.target_id)" :label="t.target_no || String(t.target_id)" />
            </el-select>
            <div v-if="current" v-loading="loading" class="voucher-origin__result" aria-live="polite">
                <span>本站原应付 · {{ current.target_no || '单号未记录' }}</span>
                <template v-if="!loading">
                    <p v-if="error">{{ error }} <el-button link type="primary" @click="load">重新读取</el-button></p>
                    <ErpDocumentSyncResult v-else-if="results.length" :results="results" operator-view="payable" @refresh="load" />
                    <p v-else>这张凭证没有可查看的外部回执。</p>
                </template>
            </div>
        </template>
    </div>
</template>
<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { getErpPayablePartyItems } from '../api/erp'
import { matchingVoucherResults, purchaseTargets, voucherId } from '../utils/payable-voucher'
import ErpDocumentSyncResult from './ErpDocumentSyncResult.vue'

const props = defineProps<{ voucher: any }>()
const targets = computed(() => purchaseTargets(props.voucher))
const open = ref(false), selected = ref(0), loading = ref(false), error = ref(''), results = ref<any[]>([])
const current = computed(() => targets.value.find((t: any) => Number(t.target_id) === selected.value))
let generation = 0
watch(() => [voucherId(props.voucher), props.voucher.settlement_no, props.voucher.party_id], () => {
    ++generation; open.value = false; selected.value = 0; results.value = []; error.value = ''; loading.value = false
})
function toggle() {
    open.value = !open.value
    if (!open.value) { ++generation; return }
    selected.value = targets.value.length === 1 ? Number(targets.value[0].target_id) : 0
    if (selected.value) load()
}
async function load() {
    const who = ++generation, v = props.voucher, t = current.value
    results.value = []; error.value = ''
    if (!open.value || !t) return
    loading.value = true
    try {
        // Use the existing permission-checked, read-only endpoint for the selected payable only.
        const res: any = await getErpPayablePartyItems(v.party_id, {source_type:'purchase', payable_id:t.target_id, page:1, limit:2})
        if (who !== generation) return
        const exact = matchingVoucherResults(res?.data?.data, t, v)
        if (!exact) throw new Error()
        results.value = exact
    } catch { if (who === generation) error.value = '未能核实本笔原应付，请核对查看权限或稍后重试；不要再次付款。' }
    finally { if (who === generation) loading.value = false }
}
</script>
<style scoped>
.voucher-origin { margin:0 18px 12px; }
.voucher-origin .el-select { width:260px; max-width:100%; display:block; margin:8px 0; }
.voucher-origin__result { padding:12px; min-height:46px; background:var(--el-fill-color-lighter); border-radius:6px; }
.voucher-origin__result > span,.voucher-origin__result p { color:var(--el-text-color-regular); font-size:13px; }
</style>
