<template>
    <view class="erp-page">

        <scroll-view scroll-y style="height:calc(100vh - 200rpx)">
            <view class="form-wrap">

                <!-- 客户 -->
                <view class="form-section">
                    <view class="form-row" @click="showPartyPicker = true">
                        <text class="form-label required">客户</text>
                        <view class="form-input">
                            <text :class="form.party_name ? 'input-text' : 'input-placeholder'">
                                {{ form.party_name || '点击选择客户' }}
                            </text>
                            <text class="input-arrow">›</text>
                        </view>
                    </view>
                    <view class="form-row" @click="showChannelPicker = true">
                        <text class="form-label required">销售渠道</text>
                        <view class="form-input">
                            <text :class="form.sale_channel ? 'input-text' : 'input-placeholder'">
                                {{ form.sale_channel || '点击选择销售渠道' }}
                            </text>
                            <text class="input-arrow">›</text>
                        </view>
                    </view>
                    <view v-if="form.channel_source_plugin" class="channel-source-tip">
                        渠道来源：{{ form.channel_source_plugin === 'hsx_erp' ? 'ERP' : '已安装插件' }}
                    </view>
                    <view class="form-row">
                        <text class="form-label">备注</text>
                        <u-input v-model="form.remark" placeholder="可选" :customStyle="inputStyle" />
                    </view>
                </view>

                <!-- 选择库存设备 -->
                <view class="form-section">
                    <view class="form-section__head">
                        <text class="form-section__title">已选设备（{{ selectedAssets.length }} 台）</text>
                        <view class="form-section__actions">
                            <u-button size="small" plain type="primary" @click="openScanStockPicker">扫码选设备</u-button>
                            <u-button size="small" type="primary" @click="showStockPicker = true">+ 选设备</u-button>
                        </view>
                    </view>

                    <view v-for="(asset, idx) in selectedAssets" :key="asset.id" class="device-form-card">
                        <view class="device-form__head">
                            <text class="device-name">{{ asset.model }}</text>
                            <u-icon name="close-circle" color="#94a3b8" size="20" @click="removeAsset(idx)" />
                        </view>
                        <text class="card-meta">{{ asset.spec || '-' }} · IMEI {{ asset.imei }}</text>
                        <text class="card-meta">成本 ¥{{ money(asset.total_cost) }}</text>
                        <view class="form-row" style="margin-top:12rpx">
                            <text class="form-label required">销售价</text>
                            <u-input
                                v-model="asset._sale_price"
                                type="number"
                                :placeholder="'建议 ¥' + money(suggestedSalePrice(asset))"
                                :customStyle="inputStyle"
                            />
                        </view>
                        <view class="profit-hint" v-if="Number(asset._sale_price) > 0">
                            预估毛利：<text :class="profitClass(asset)">¥{{ money(Number(asset._sale_price) - Number(asset.total_cost)) }}</text>
                        </view>
                    </view>

                    <view class="add-hint" v-if="!selectedAssets.length">点击「选设备」从待售库存中选择</view>

                    <!-- 合计 -->
                    <view v-if="selectedAssets.length" class="total-row">
                        <text class="total-label">合计：¥{{ money(totalSale) }}</text>
                        <text class="total-profit">毛利：<text :class="totalProfit >= 0 ? 'green' : 'red'">¥{{ money(totalProfit) }}</text></text>
                    </view>
                </view>

                <!-- 结算区域 -->
                <ErpSettleBar
                    v-model:settle-mode="form.settle_mode"
                    v-model:amount="form.received_amount"
                    v-model:account-id="form.capital_account_id"
                    :total="totalSale"
                    :accounts="accounts"
                    label-cash="本次收款"
                    label-credit="全部挂账"
                />
                <ErpVoucherUploader v-if="form.settle_mode === 'cash'" v-model="form.voucher_urls" title="收款凭证" @uploading="voucherUploading = $event" />

            </view>
        </scroll-view>

        <!-- 底部提交 -->
        <view class="bottom-bar">
            <u-button @click="goBack" :customStyle="{flex:'1'}">取消</u-button>
            <u-button type="primary" :loading="submitting" :disabled="!canSubmit" @click="submit" :customStyle="{flex:'2'}">
                确认出库 ¥{{ money(totalSale) }}
            </u-button>
        </view>

        <!-- 组件弹窗 -->
        <ErpPartyPopup
            v-model:show="showPartyPicker"
            role-type="customer"
            v-model:party-id="form.party_id"
            v-model:party-name="form.party_name"
        />

        <ErpStockPickerPopup
            v-model:show="showStockPicker"
            :excludeIds="selectedAssets.map(a => a.id)"
            :scanTrigger="stockPickerScanTrigger"
            @select="onAssetSelected"
        />
        <ErpSaleChannelPopup
            v-model:show="showChannelPicker"
            v-model="form.sale_channel_key"
            @change="onSaleChannelChange"
        />
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { confirmMobileSaleReceipt, createMobileSale, getMobileCapitalAccounts, getMobileSaleInfo, getMobileSaleStock } from '@/addon/hsx_erp/api/erp'
import ErpPartyPopup from '@/addon/hsx_erp/components/ErpPartyPopup.vue'
import ErpStockPickerPopup from '@/addon/hsx_erp/components/ErpStockPickerPopup.vue'
import ErpSaleChannelPopup from '@/addon/hsx_erp/components/ErpSaleChannelPopup.vue'
import ErpSettleBar from '@/addon/hsx_erp/components/ErpSettleBar.vue'
import ErpVoucherUploader from '@/addon/hsx_erp/components/ErpVoucherUploader.vue'
import { confirmErpSensitiveAction } from '@/addon/hsx_erp/hooks/useErpSensitiveConfirm'
import { useErpSaleChannels } from '@/addon/hsx_erp/hooks/useErpSaleChannels'
import { firstPositiveErpAmount } from '@/addon/hsx_erp/hooks/useErpAmounts'
import ErpPageHeader from '@/addon/hsx_erp/components/ErpPageHeader.vue'

const submitting = ref(false)
const voucherUploading = ref(false)
const goBack = () => uni.navigateBack()
const accounts = ref<any[]>([])
const selectedAssets = ref<any[]>([])
const showPartyPicker = ref(false)
const showStockPicker = ref(false)
const stockPickerScanTrigger = ref(0)
const showChannelPicker = ref(false)
const { load: loadSaleChannels, preferred: preferredSaleChannel } = useErpSaleChannels()

const form = ref({
    party_id: 0, party_name: '',
    sale_channel: '', sale_channel_key: '', channel_source_plugin: '', channel_source_key: '', remark: '',
    settle_mode: 'credit' as 'credit' | 'cash',
    received_amount: 0, capital_account_id: 0, voucher_urls: '',
})

const inputStyle = { background: '#f8fafc', borderRadius: '8rpx', padding: '8rpx 16rpx' }
const totalSale = computed(() => selectedAssets.value.reduce((s, a) => s + Number(a._sale_price || 0), 0))
const totalCost = computed(() => selectedAssets.value.reduce((s, a) => s + Number(a.total_cost || 0), 0))
const totalProfit = computed(() => totalSale.value - totalCost.value)

const canSubmit = computed(() =>
    form.value.party_id > 0 &&
    Boolean(form.value.sale_channel_key) &&
    selectedAssets.value.length > 0 &&
    selectedAssets.value.every(a => Number(a._sale_price) > 0) &&
    (
        form.value.settle_mode !== 'cash' ||
        (
            Number(form.value.received_amount || 0) > 0 &&
            Number(form.value.received_amount || 0) <= totalSale.value &&
            Number(form.value.capital_account_id || 0) > 0 && !voucherUploading.value
        )
    )
)

onMounted(() => {
    loadAccounts()
    loadDefaultSaleChannel()
})

onLoad((query: any) => {
    const assetIds = String(query?.asset_ids || '').split(',').map(Number).filter(id => id > 0)
    if (assetIds.length) preloadAssets(assetIds)
})

async function preloadAssets(assetIds: number[]) {
    try {
        const res: any = await getMobileSaleStock({ asset_ids: assetIds, page: 1, limit: Math.min(200, Math.max(15, assetIds.length)) })
        const rows = Array.isArray(res?.data?.data) ? res.data.data : []
        selectedAssets.value = rows.map((asset: any) => ({ ...asset, _sale_price: suggestedSalePrice(asset) }))
        if (!rows.length) uni.showToast({ title: '所选设备当前不可直接销售', icon: 'none' })
    } catch (e: any) { uni.showToast({ title: e?.message || '待售设备加载失败', icon: 'none' }) }
}

async function loadAccounts() {
    try {
        const res: any = await getMobileCapitalAccounts()
        accounts.value = res?.data?.list || []
    } catch {}
}

async function loadDefaultSaleChannel() {
    try {
        await loadSaleChannels()
        if (!form.value.sale_channel_key) {
            const channel = preferredSaleChannel()
            if (channel) onSaleChannelChange(channel)
        }
    } catch (error: any) {
        uni.showToast({ title: error?.message || '销售渠道加载失败', icon: 'none' })
    }
}

function onAssetSelected(asset: any) {
    selectedAssets.value.push({
        ...asset,
        _sale_price: suggestedSalePrice(asset),
    })
}

function onSaleChannelChange(channel: any) {
    form.value.sale_channel_key = String(channel?.key || '')
    form.value.sale_channel = String(channel?.name || '')
    form.value.channel_source_plugin = String(channel?.source_plugin || '')
    form.value.channel_source_key = String(channel?.source_key || '')
}

function removeAsset(idx: number) { selectedAssets.value.splice(idx, 1) }

function openScanStockPicker() {
    showStockPicker.value = true
    setTimeout(() => { stockPickerScanTrigger.value++ }, 80)
}

const profitClass = (a: any) => Number(a._sale_price) - Number(a.total_cost) >= 0 ? 'green' : 'red'
const suggestedSalePrice = (asset: any) => firstPositiveErpAmount(asset?.retail_price, asset?.estimate_sale_price, asset?.total_cost)

async function submit() {
    if (submitting.value) return
    if (!canSubmit.value) {
        uni.showToast({ title: '请完善客户、销售渠道、设备和收款信息', icon: 'none' })
        return
    }
    submitting.value = true
    const saleTotal = selectedAssets.value.reduce((sum, item) => sum + Number(item._sale_price || 0), 0)
    const confirmed = await confirmErpSensitiveAction({
        title: '确认销售出库',
        content: `客户：${form.value.party_name || '-'}\n渠道：${form.value.sale_channel || '-'}\n设备：${selectedAssets.value.length} 台\n销售总额：¥${money(saleTotal)}\n提交后设备立即退出库存并生成应收，不能普通撤销。`,
        confirmText: '确认出库',
    })
    if (!confirmed) {
        submitting.value = false
        return
    }
    try {
        const res: any = await createMobileSale({
            party_id: form.value.party_id,
            party_name: form.value.party_name,
            sale_channel: form.value.sale_channel,
            sale_channel_key: form.value.sale_channel_key,
            channel_source_plugin: form.value.channel_source_plugin,
            channel_source_key: form.value.channel_source_key,
            remark: form.value.remark,
            items: selectedAssets.value.map(a => ({
                asset_id: a.id,
                sale_price: Number(a._sale_price),
            }))
        })
        // 现结：自动调用收款接口（对齐PC端逻辑）
        if (form.value.settle_mode === 'cash' && form.value.received_amount > 0) {
            const saleId = res?.data?.id || res?.data
            if (saleId) {
                const detail: any = await getMobileSaleInfo(Number(saleId))
                const receivableId = detail?.data?.receivables?.[0]?.id
                if (receivableId) {
                    await confirmMobileSaleReceipt(Number(receivableId), {
                        amount: form.value.received_amount,
                        capital_account_id: form.value.capital_account_id,
                        remark: '销售现结收款',
                        voucher_urls: form.value.voucher_urls,
                    })
                }
            }
        }
        uni.showToast({ title: '销售出库成功', icon: 'success' })
        setTimeout(() => uni.navigateBack(), 1200)
    } catch (e: any) {
        uni.showToast({ title: e?.message || '出库失败', icon: 'none' })
    } finally { submitting.value = false }
}

const money = (v: any) => Number(v || 0).toFixed(2)
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
.form-wrap { padding: 24rpx 24rpx 120rpx; display: flex; flex-direction: column; gap: 20rpx; }
.form-section { background:#fff; border-radius:16rpx; padding:24rpx; }
.form-section__head { display:flex; align-items:center; justify-content:space-between; margin-bottom:16rpx; }
.form-section__actions { display:flex; align-items:center; gap:12rpx; }
.form-section__title { font-size:28rpx; font-weight:600; color:#374151; }
.form-row { display:flex; align-items:center; gap:16rpx; margin-bottom:16rpx; &:last-child { margin-bottom:0; } }
.channel-source-tip { margin:-6rpx 0 16rpx 176rpx; color:#94a3b8; font-size:20rpx; }
.form-label { font-size:26rpx; color:#374151; width:150rpx; flex-shrink:0; }
.form-label.required::before { content:'*'; color:#dc2626; margin-right:4rpx; }
.form-input { flex:1; display:flex; align-items:center; justify-content:space-between; background:#f8fafc; border-radius:8rpx; padding:12rpx 16rpx; }
.input-text { font-size:26rpx; color:#0f172a; }
.input-placeholder { font-size:26rpx; color:#94a3b8; }
.input-arrow { font-size:32rpx; color:#94a3b8; }
.device-form-card { border:1rpx solid #e2e8f0; border-radius:12rpx; padding:16rpx; margin-bottom:16rpx; }
.device-form__head { display:flex; align-items:center; justify-content:space-between; margin-bottom:8rpx; }
.device-name { font-size:28rpx; font-weight:600; color:#0f172a; }
.profit-hint { font-size:24rpx; color:#64748b; margin-top:6rpx; }
.profit-hint .green { color: #16a34a; }
.profit-hint .red { color: #dc2626; }
.total-row { display:flex; justify-content:space-between; padding-top:16rpx; border-top:1rpx solid #f1f5f9; margin-top:8rpx; }
.total-label { font-size:26rpx; font-weight:600; color:#0f172a; }
.total-profit { font-size:26rpx; color:#374151; }
.total-profit .green { color: #16a34a; }
.total-profit .red { color: #dc2626; }
.add-hint { text-align:center; color:#94a3b8; font-size:26rpx; padding:32rpx 0; }
.bottom-bar { position:fixed; bottom:0; left:0; right:0; padding:20rpx 32rpx; padding-bottom:calc(20rpx + env(safe-area-inset-bottom)); background:#fff; box-shadow:0 -2rpx 16rpx rgba(0,0,0,.08); display:flex; gap:16rpx; }
</style>
