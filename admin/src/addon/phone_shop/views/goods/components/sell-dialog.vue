<template>
    <el-dialog v-model="visible" title="开单 · 卖出" width="560px" :close-on-click-modal="false" class="sell-dialog">
        <!-- 商品信息 -->
        <div class="goods-box">
            <el-image v-if="cover" :src="cover" fit="cover" class="goods-cover" />
            <div class="flex-1 min-w-0">
                <div class="goods-name">{{ form.goods_name }}</div>
                <div class="goods-meta">
                    <span v-for="(t, i) in metaTags" :key="i" class="meta-tag">{{ t }}</span>
                </div>
                <div class="goods-asset">资产ID {{ erpAssetId }}</div>
            </div>
        </div>

        <el-form :model="form" label-width="80px" class="mt-4" v-loading="submitting">
            <el-form-item label="客户" required>
                <el-select v-model="form.counterparty_id" placeholder="搜索交易人(名称/手机号)" filterable remote clearable
                           :remote-method="searchCounterparty" :loading="cpLoading" class="w-full">
                    <el-option v-for="c in counterpartyOptions" :key="c.id" :value="c.id"
                               :label="c.name + (c.mobile ? '（' + c.mobile + '）' : '')" />
                </el-select>
            </el-form-item>

            <el-form-item label="成交价" required>
                <el-input-number v-model="form.sale_price" :min="0" :precision="2" :step="100" controls-position="right" class="!w-[180px]" />
                <span class="text-xs text-gray-400 ml-2">挂牌价 ¥{{ listPrice }}（可改）</span>
            </el-form-item>

            <el-form-item label="结算方式" required>
                <div class="settle-cards">
                    <div class="settle-card" :class="{ active: form.settle_mode === 'now' }" @click="form.settle_mode = 'now'">
                        <div class="settle-icon">💰</div>
                        <div class="settle-title">现结</div>
                        <div class="settle-desc">当场收款入账</div>
                    </div>
                    <div class="settle-card" :class="{ active: form.settle_mode === 'later' }" @click="form.settle_mode = 'later'">
                        <div class="settle-icon">🧾</div>
                        <div class="settle-title">挂账</div>
                        <div class="settle-desc">先出货，后结款（应收）</div>
                    </div>
                </div>
            </el-form-item>

            <el-form-item v-if="form.settle_mode === 'now'" label="收款账户" required>
                <el-select v-model="form.capital_account_id" placeholder="款项打到哪个账户" clearable filterable class="w-full">
                    <el-option v-for="a in accountOptions" :key="a.id" :value="a.id" :label="a.name + (a.account_type_name ? '·' + a.account_type_name : '')" />
                </el-select>
            </el-form-item>

            <el-form-item label="备注">
                <el-input v-model="form.remark" type="textarea" :rows="2" placeholder="选填，如客户讲价等" />
            </el-form-item>
        </el-form>

        <template #footer>
            <el-button @click="visible = false">取消</el-button>
            <el-button type="primary" :loading="submitting" @click="submit">确认开单出库</el-button>
        </template>
    </el-dialog>
</template>

<script lang="ts" setup>
import { computed, reactive, ref, watch } from 'vue'
import { img } from '@/utils/common'
import { ElMessage } from 'element-plus'
import { getErpCounterpartyOptions, getErpCapitalAccounts, erpOutboundCreate } from '@/addon/phone_shop/api/erp_outbound'

const props = defineProps<{ modelValue: boolean; goods: any }>()
const emit = defineEmits(['update:modelValue', 'done'])

const visible = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v)
})

const submitting = ref(false)
const cpLoading = ref(false)
const counterpartyOptions = ref<any[]>([])
const accountOptions = ref<any[]>([])

const sku = computed(() => props.goods?.goodsSku || props.goods?.goods_sku || {})
const erpAssetId = computed(() => Number(sku.value?.erp_asset_id) || 0)
const listPrice = computed(() => Number(sku.value?.price) || 0)
const cover = computed(() => props.goods?.goods_cover_thumb_small || (props.goods?.goods_cover ? img(props.goods.goods_cover) : ''))
const metaTags = computed(() => [props.goods?.memory_group, props.goods?.condition_grade].filter(Boolean))

const form = reactive({
    goods_name: '',
    counterparty_id: '' as number | string,
    sale_price: 0,
    settle_mode: 'now',
    capital_account_id: '' as number | string,
    remark: ''
})

watch(() => props.modelValue, (v) => {
    if (!v) return
    form.goods_name = props.goods?.goods_name || ''
    form.counterparty_id = ''
    form.sale_price = listPrice.value
    form.settle_mode = 'now'
    form.capital_account_id = ''
    form.remark = ''
    searchCounterparty('')
    loadAccounts()
})

const searchCounterparty = (kw: string) => {
    cpLoading.value = true
    getErpCounterpartyOptions(kw || '').then((res: any) => {
        counterpartyOptions.value = res.data || []
        cpLoading.value = false
    }).catch(() => { cpLoading.value = false })
}

const loadAccounts = () => {
    getErpCapitalAccounts().then((res: any) => {
        accountOptions.value = res.data?.list || res.data || []
    })
}

const submit = () => {
    if (erpAssetId.value <= 0) return ElMessage.warning('该商品未关联 ERP 设备，无法开单')
    if (!form.counterparty_id) return ElMessage.warning('请选择客户')
    if (!form.sale_price || form.sale_price <= 0) return ElMessage.warning('请填写成交价')
    if (form.settle_mode === 'now' && !form.capital_account_id) return ElMessage.warning('现结请选择收款账户')

    submitting.value = true
    erpOutboundCreate({
        outbound_type: 'peer_sale',
        counterparty_id: form.counterparty_id,
        settle_mode: form.settle_mode,
        capital_account_id: form.settle_mode === 'now' ? form.capital_account_id : 0,
        remark: form.remark,
        items: [{ asset_id: erpAssetId.value, sale_price: form.sale_price }]
    }).then(() => {
        submitting.value = false
        ElMessage.success('已开单出库，商品将置为已售')
        visible.value = false
        emit('done')
    }).catch(() => { submitting.value = false })
}
</script>

<style lang="scss" scoped>
.goods-box { display: flex; gap: 12px; align-items: center; background: #f8fafc; border-radius: 8px; padding: 12px; }
.goods-cover { width: 56px; height: 56px; border-radius: 6px; flex-shrink: 0; }
.goods-name { font-weight: 600; font-size: 14px; }
.goods-meta { margin-top: 4px; }
.meta-tag { display: inline-block; font-size: 11px; color: #64748b; background: #fff; border: 1px solid #e2e8f0; border-radius: 4px; padding: 0 6px; margin-right: 6px; }
.goods-asset { font-size: 11px; color: #94a3b8; margin-top: 4px; }
.settle-cards { display: flex; gap: 12px; }
.settle-card { flex: 1; border: 1.5px solid #e5e7eb; border-radius: 8px; padding: 10px; text-align: center; cursor: pointer; transition: all .15s; }
.settle-card:hover { border-color: #93c5fd; }
.settle-card.active { border-color: var(--el-color-primary); background: var(--el-color-primary-light-9); }
.settle-icon { font-size: 22px; }
.settle-title { font-weight: 600; font-size: 13px; margin-top: 2px; }
.settle-desc { font-size: 11px; color: #94a3b8; }
</style>
