<template>
    <view class="erp-page">
        <RecyclePageHeader title="销售出库" />
        <scroll-view scroll-y style="height:calc(100vh - 200rpx)">
            <view class="form-wrap">
                <!-- 基本信息 -->
                <view class="form-section">
                    <view class="form-section__title">基本信息</view>
                    <view class="form-row">
                        <text class="form-label required">客户</text>
                        <view class="form-input" @click="openPartyPicker">
                            <text :class="form.party_name ? 'input-text' : 'input-placeholder'">
                                {{ form.party_name || '点击选择客户' }}
                            </text>
                            <text class="input-arrow">›</text>
                        </view>
                    </view>
                    <view class="form-row">
                        <text class="form-label">销售渠道</text>
                        <u-input v-model="form.sale_channel" placeholder="可选，如：同行/商城/门店" :customStyle="inputStyle" />
                    </view>
                    <view class="form-row">
                        <text class="form-label">备注</text>
                        <u-input v-model="form.remark" placeholder="可选备注" :customStyle="inputStyle" />
                    </view>
                </view>

                <!-- 选择库存设备 -->
                <view class="form-section">
                    <view class="form-section__head">
                        <text class="form-section__title">选择设备（{{ selectedAssets.length }} 台）</text>
                        <u-button size="mini" type="primary" @click="openStockPicker">+ 选设备</u-button>
                    </view>
                    <view v-for="(asset, idx) in selectedAssets" :key="asset.id" class="device-form-card">
                        <view class="device-form__head">
                            <text class="device-name">{{ asset.model }}</text>
                            <u-icon name="close-circle" color="#94a3b8" size="20" @click="removeAsset(idx)" />
                        </view>
                        <view class="card-meta">{{ asset.spec || '-' }} · IMEI {{ asset.imei || '-' }}</view>
                        <view class="card-meta">成本 ¥{{ money(asset.total_cost) }}</view>
                        <view class="form-row" style="margin-top:12rpx">
                            <text class="form-label required">销售价</text>
                            <u-input v-model="asset._sale_price" type="number" :placeholder="'建议 ¥'+money(asset.estimate_sale_price || asset.total_cost)" :customStyle="inputStyle" />
                        </view>
                    </view>
                    <view class="add-hint" v-if="!selectedAssets.length">点击「选设备」从库存中选择</view>
                </view>

                <!-- 收款设置 -->
                <view class="form-section" v-if="selectedAssets.length">
                    <view class="form-section__title">收款设置（可选）</view>
                    <view class="form-row">
                        <text class="form-label">本次收款</text>
                        <u-input v-model="form.received_amount" type="number" placeholder="0 = 全部挂账" :customStyle="inputStyle" />
                    </view>
                    <view class="form-row" v-if="Number(form.received_amount) > 0">
                        <text class="form-label required">收款账户</text>
                        <u-select v-model="form.capital_account_id" :list="accountOptions" />
                    </view>
                    <view class="summary-row">
                        <text class="summary-label">销售合计：</text>
                        <text class="summary-value blue">¥{{ money(totalSale) }}</text>
                    </view>
                    <view class="summary-row">
                        <text class="summary-label">预估毛利：</text>
                        <text class="summary-value green">¥{{ money(totalProfit) }}</text>
                    </view>
                </view>
            </view>
        </scroll-view>

        <!-- 底部提交 -->
        <view class="bottom-bar">
            <u-button @click="uni.navigateBack()" :customStyle="{flex:'1'}">取消</u-button>
            <u-button type="primary" :loading="submitting" :disabled="!canSubmit" @click="submit" :customStyle="{flex:'2'}">确认出库</u-button>
        </view>

        <!-- 客户选择弹窗 -->
        <u-popup v-model="partyPickerVisible" mode="bottom" :safe-area-inset-bottom="true" border-radius="24">
            <view class="picker-wrap">
                <view class="picker-title">选择客户</view>
                <u-search v-model="partyKeyword" placeholder="搜索名称/手机" :showAction="false" @search="searchParties" @clear="searchParties" />
                <scroll-view scroll-y style="max-height:600rpx;margin-top:16rpx">
                    <view v-for="p in parties" :key="p.id" class="picker-item" @click="selectParty(p)">
                        <text class="picker-item__name">{{ p.party_name }}</text>
                        <text class="picker-item__sub" v-if="p.contact_mobile">{{ p.contact_mobile }}</text>
                    </view>
                    <view class="picker-empty" v-if="!parties.length">暂无结果</view>
                </scroll-view>
            </view>
        </u-popup>

        <!-- 库存设备选择 -->
        <u-popup v-model="stockPickerVisible" mode="bottom" :safe-area-inset-bottom="true" border-radius="24">
            <view class="picker-wrap">
                <view class="picker-title">选择库存设备</view>
                <u-search v-model="stockKeyword" placeholder="型号/IMEI/资产号" :showAction="false" @search="searchStock" @clear="searchStock" />
                <scroll-view scroll-y style="max-height:700rpx;margin-top:16rpx">
                    <view v-for="a in stockList" :key="a.id" class="picker-item" @click="selectAsset(a)">
                        <view class="picker-item__row">
                            <text class="picker-item__name">{{ a.model }}</text>
                            <text class="picker-item__price">¥{{ money(a.total_cost) }}</text>
                        </view>
                        <text class="picker-item__sub">{{ a.spec || '-' }} · {{ a.imei }}</text>
                        <text class="picker-item__sub">{{ a.warehouse_name }}</text>
                    </view>
                    <view class="picker-empty" v-if="!stockList.length">暂无在库设备</view>
                </scroll-view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { getMobileCapitalAccounts, getMobileStockList } from '@/addon/hsx_erp/api/erp'
import request from '@/utils/request'

const submitting = ref(false)
const accounts = ref<any[]>([])
const parties = ref<any[]>([])
const stockList = ref<any[]>([])
const selectedAssets = ref<any[]>([])
const partyKeyword = ref('')
const stockKeyword = ref('')
const partyPickerVisible = ref(false)
const stockPickerVisible = ref(false)

const form = ref({
    party_id: 0, party_name: '',
    sale_channel: '', remark: '',
    received_amount: 0, capital_account_id: 0,
})

const accountOptions = computed(() => accounts.value.map(a => ({ value: a.id, label: `${a.account_name}（¥${money(a.balance)}）` })))
const totalSale = computed(() => selectedAssets.value.reduce((s, a) => s + Number(a._sale_price || 0), 0))
const totalCost = computed(() => selectedAssets.value.reduce((s, a) => s + Number(a.total_cost || 0), 0))
const totalProfit = computed(() => totalSale.value - totalCost.value)
const canSubmit = computed(() =>
    form.value.party_id > 0 &&
    selectedAssets.value.length > 0 &&
    selectedAssets.value.every(a => Number(a._sale_price) > 0)
)
const inputStyle = { background: '#f8fafc', borderRadius: '8rpx', padding: '8rpx 16rpx' }

onMounted(async () => {
    const res: any = await getMobileCapitalAccounts()
    accounts.value = res?.data?.data || []
})

function openPartyPicker() { partyPickerVisible.value = true; searchParties() }
function openStockPicker() { stockPickerVisible.value = true; searchStock() }

async function searchParties() {
    try {
        const res: any = await request.get('erp/counterparty/options', { keyword: partyKeyword.value, role_type: 'customer', limit: 30 })
        parties.value = Array.isArray(res?.data) ? res.data : (res?.data?.data || [])
    } catch { uni.showToast({ title: '加载客户失败', icon: 'none' }) }
}
async function searchStock() {
    const res: any = await getMobileStockList({ keyword: stockKeyword.value, status: 'in_stock', page: 1, limit: 30 })
    const selectedIds = new Set(selectedAssets.value.map(a => a.id))
    stockList.value = (res?.data?.data || []).filter((a: any) => !selectedIds.has(a.id))
}

function selectParty(p: any) { form.value.party_id = p.id; form.value.party_name = p.party_name; partyPickerVisible.value = false }
function selectAsset(a: any) {
    selectedAssets.value.push({ ...a, _sale_price: Number(a.retail_price || a.estimate_sale_price || 0) })
    stockPickerVisible.value = false
}
function removeAsset(idx: number) { selectedAssets.value.splice(idx, 1) }

async function submit() {
    submitting.value = true
    try {
        await request.post('erp/sale/create', {
            party_id: form.value.party_id,
            party_name: form.value.party_name,
            sale_channel: form.value.sale_channel,
            remark: form.value.remark,
            received_amount: Number(form.value.received_amount) || 0,
            capital_account_id: form.value.capital_account_id || 0,
            items: selectedAssets.value.map(a => ({
                asset_id: a.id,
                sale_price: Number(a._sale_price),
            }))
        })
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
.form-wrap { padding: 24rpx 24rpx 32rpx; }
.form-section { background:#fff; border-radius:16rpx; padding:24rpx; margin-bottom:20rpx; }
.form-section__head { display:flex; align-items:center; justify-content:space-between; margin-bottom:16rpx; }
.form-section__title { font-size:28rpx; font-weight:600; color:#374151; margin-bottom:16rpx; }
.form-row { display:flex; align-items:center; gap:16rpx; margin-bottom:16rpx; }
.form-label { font-size:26rpx; color:#374151; width:120rpx; flex-shrink:0; }
.form-label.required::before { content:'*'; color:#dc2626; margin-right:4rpx; }
.form-input { flex:1; display:flex; align-items:center; justify-content:space-between; background:#f8fafc; border-radius:8rpx; padding:12rpx 16rpx; }
.input-text { font-size:26rpx; color:#0f172a; }
.input-placeholder { font-size:26rpx; color:#94a3b8; }
.input-arrow { font-size:32rpx; color:#94a3b8; }
.device-form-card { border:1rpx solid #e2e8f0; border-radius:12rpx; padding:16rpx; margin-bottom:16rpx; }
.device-form__head { display:flex; align-items:center; justify-content:space-between; margin-bottom:8rpx; }
.device-name { font-size:28rpx; font-weight:600; color:#0f172a; }
.add-hint { text-align:center; color:#94a3b8; font-size:26rpx; padding:32rpx 0; }
.summary-row { display:flex; justify-content:space-between; padding:8rpx 0; }
.summary-label { font-size:26rpx; color:#374151; }
.summary-value { font-size:28rpx; font-weight:700; color:#0f172a; }
.summary-value.blue { color:#2563eb; }
.summary-value.green { color:#16a34a; }
.bottom-bar { position:fixed; bottom:0; left:0; right:0; padding:20rpx 32rpx; padding-bottom:calc(20rpx + env(safe-area-inset-bottom)); background:#fff; box-shadow:0 -2rpx 16rpx rgba(0,0,0,.08); display:flex; gap:16rpx; }
.picker-wrap { padding:32rpx; }
.picker-title { font-size:30rpx; font-weight:600; color:#0f172a; margin-bottom:20rpx; }
.picker-item { padding:20rpx 0; border-bottom:1rpx solid #f1f5f9; }
.picker-item__row { display:flex; justify-content:space-between; }
.picker-item__name { font-size:28rpx; color:#0f172a; }
.picker-item__price { font-size:26rpx; color:#2563eb; }
.picker-item__sub { font-size:24rpx; color:#64748b; margin-top:4rpx; display:block; }
.picker-empty { text-align:center; color:#94a3b8; padding:32rpx; }
</style>
