<template>
    <view class="erp-page">
        <RecyclePageHeader title="采购开单" />
        <scroll-view scroll-y style="height:calc(100vh - 200rpx)">
            <view class="form-wrap">
                <!-- 基本信息 -->
                <view class="form-section">
                    <view class="form-section__title">基本信息</view>
                    <view class="form-row">
                        <text class="form-label required">供应商</text>
                        <view class="form-input" @click="openPartyPicker">
                            <text :class="form.party_name ? 'input-text' : 'input-placeholder'">
                                {{ form.party_name || '点击选择供应商' }}
                            </text>
                            <text class="input-arrow">›</text>
                        </view>
                    </view>
                    <view class="form-row">
                        <text class="form-label required">入库仓库</text>
                        <view class="form-input" @click="openWarehousePicker">
                            <text :class="form.warehouse_name ? 'input-text' : 'input-placeholder'">
                                {{ form.warehouse_name || '点击选择仓库' }}
                            </text>
                            <text class="input-arrow">›</text>
                        </view>
                    </view>
                    <view class="form-row" v-if="locations.length">
                        <text class="form-label">库位</text>
                        <view class="form-input" @click="openLocationPicker">
                            <text :class="form.location_name ? 'input-text' : 'input-placeholder'">
                                {{ form.location_name || '可选' }}
                            </text>
                            <text class="input-arrow">›</text>
                        </view>
                    </view>
                    <view class="form-row">
                        <text class="form-label">备注</text>
                        <u-input v-model="form.remark" placeholder="可选备注" :customStyle="inputStyle" />
                    </view>
                </view>

                <!-- 设备明细 -->
                <view class="form-section">
                    <view class="form-section__head">
                        <text class="form-section__title">设备明细（{{ form.items.length }} 台）</text>
                        <u-button size="mini" type="primary" @click="addDevice">+ 添加设备</u-button>
                    </view>
                    <view v-for="(item, idx) in form.items" :key="idx" class="device-form-card">
                        <view class="device-form__head">
                            <text class="device-form__index">设备 {{ idx + 1 }}</text>
                            <u-icon name="close-circle" color="#94a3b8" size="20" @click="removeDevice(idx)" />
                        </view>
                        <view class="form-row">
                            <text class="form-label required">IMEI/序列号</text>
                            <u-input v-model="item.imei" placeholder="扫描或手输 IMEI" :customStyle="inputStyle" />
                        </view>
                        <view class="form-row">
                            <text class="form-label required">型号</text>
                            <u-input v-model="item.model" placeholder="如：iPhone 15 128G 黑色" :customStyle="inputStyle" />
                        </view>
                        <view class="form-row">
                            <text class="form-label">规格</text>
                            <u-input v-model="item.spec" placeholder="成色/内存/颜色" :customStyle="inputStyle" />
                        </view>
                        <view class="form-row">
                            <text class="form-label required">采购成本</text>
                            <u-input v-model="item.purchase_cost" type="number" placeholder="0.00" :customStyle="inputStyle" />
                        </view>
                    </view>
                    <view class="add-hint" v-if="!form.items.length">点击「添加设备」开始录入</view>
                </view>

                <!-- 付款设置 -->
                <view class="form-section">
                    <view class="form-section__title">付款设置（可选）</view>
                    <view class="form-row">
                        <text class="form-label">本次付款</text>
                        <u-input v-model="form.paid_amount" type="number" placeholder="0 = 全部挂账" :customStyle="inputStyle" />
                    </view>
                    <view class="form-row" v-if="Number(form.paid_amount) > 0">
                        <text class="form-label required">付款账户</text>
                        <u-select v-model="form.capital_account_id" :list="accountOptions" />
                    </view>
                    <view class="summary-row">
                        <text class="summary-label">合计成本：</text>
                        <text class="summary-value">¥{{ money(totalCost) }}</text>
                    </view>
                </view>
            </view>
        </scroll-view>

        <!-- 底部提交 -->
        <view class="bottom-bar">
            <u-button @click="uni.navigateBack()" :customStyle="{flex:'1'}">取消</u-button>
            <u-button type="primary" :loading="submitting" :disabled="!canSubmit" @click="submit" :customStyle="{flex:'2'}">确认开单</u-button>
        </view>

        <!-- 供应商选择弹窗 -->
        <u-popup v-model="partyPickerVisible" mode="bottom" :safe-area-inset-bottom="true" border-radius="24">
            <view class="picker-wrap">
                <view class="picker-title">选择供应商</view>
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

        <!-- 仓库选择 -->
        <u-popup v-model="warehousePickerVisible" mode="bottom" :safe-area-inset-bottom="true" border-radius="24">
            <view class="picker-wrap">
                <view class="picker-title">选择仓库</view>
                <view v-for="w in warehouses" :key="w.id" class="picker-item" @click="selectWarehouse(w)">
                    <text class="picker-item__name">{{ w.warehouse_name }}</text>
                    <text class="picker-item__sub">{{ warehouseTypeLabel(w.warehouse_type) }}</text>
                </view>
            </view>
        </u-popup>

        <!-- 库位选择 -->
        <u-popup v-model="locationPickerVisible" mode="bottom" :safe-area-inset-bottom="true" border-radius="24">
            <view class="picker-wrap">
                <view class="picker-title">选择库位</view>
                <view v-for="l in locations" :key="l.id" class="picker-item" @click="selectLocation(l)">
                    <text class="picker-item__name">{{ l.location_name }}</text>
                </view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { getMobileCapitalAccounts } from '@/addon/hsx_erp/api/erp'
import request from '@/utils/request'

const submitting = ref(false)
const accounts = ref<any[]>([])
const warehouses = ref<any[]>([])
const locations = ref<any[]>([])
const parties = ref<any[]>([])
const partyKeyword = ref('')
const partyPickerVisible = ref(false)
const warehousePickerVisible = ref(false)
const locationPickerVisible = ref(false)

const form = ref({
    party_id: 0, party_name: '',
    warehouse_id: 0, warehouse_name: '',
    location_id: 0, location_name: '',
    paid_amount: 0, capital_account_id: 0,
    remark: '',
    items: [] as any[],
})

const accountOptions = computed(() => accounts.value.map(a => ({ value: a.id, label: `${a.account_name}（¥${money(a.balance)}）` })))
const totalCost = computed(() => form.value.items.reduce((s, i) => s + Number(i.purchase_cost || 0), 0))
const canSubmit = computed(() => form.value.party_id > 0 && form.value.warehouse_id > 0 && form.value.items.length > 0 && form.value.items.every(i => i.imei && i.model && Number(i.purchase_cost) > 0))

const inputStyle = { background: '#f8fafc', borderRadius: '8rpx', padding: '8rpx 16rpx' }

onMounted(async () => {
    try {
        const [acRes, whRes]: any[] = await Promise.all([
            getMobileCapitalAccounts(),
            request.get('erp/warehouse/options')
        ])
        accounts.value = acRes?.data?.data || []
        // warehouse/options 直接返回数组
        warehouses.value = Array.isArray(whRes?.data) ? whRes.data : (whRes?.data?.data || [])
    } catch (e) {
        uni.showToast({ title: '数据加载失败，请返回重试', icon: 'none' })
    }
})

function addDevice() {
    form.value.items.push({ imei: '', model: '', spec: '', purchase_cost: 0 })
}
function removeDevice(idx: number) {
    form.value.items.splice(idx, 1)
}

function openPartyPicker() { partyPickerVisible.value = true; searchParties() }
function openWarehousePicker() { warehousePickerVisible.value = true }
function openLocationPicker() { if (locations.value.length) locationPickerVisible.value = true }

async function searchParties() {
    try {
        const res: any = await request.get('erp/counterparty/options', { keyword: partyKeyword.value, role_type: 'supplier', limit: 30 })
        // 接口直接返回数组，不是分页结构
        parties.value = Array.isArray(res?.data) ? res.data : (res?.data?.data || [])
    } catch (e) {
        uni.showToast({ title: '加载供应商失败', icon: 'none' })
    }
}
function selectParty(p: any) { form.value.party_id = p.id; form.value.party_name = p.party_name; partyPickerVisible.value = false }
function selectWarehouse(w: any) {
    form.value.warehouse_id = w.id; form.value.warehouse_name = w.warehouse_name
    form.value.location_id = 0; form.value.location_name = ''
    locations.value = w.locations?.filter((l: any) => l.status === 1) || []
    warehousePickerVisible.value = false
}
function selectLocation(l: any) { form.value.location_id = l.id; form.value.location_name = l.location_name; locationPickerVisible.value = false }

async function submit() {
    submitting.value = true
    try {
        await request.post('erp/purchase/create', {
            party_id: form.value.party_id,
            party_name: form.value.party_name,
            warehouse_id: form.value.warehouse_id,
            location_id: form.value.location_id || 0,
            paid_amount: Number(form.value.paid_amount) || 0,
            capital_account_id: form.value.capital_account_id || 0,
            remark: form.value.remark,
            items: form.value.items.map(i => ({
                imei: i.imei, model: i.model, spec: i.spec,
                purchase_cost: Number(i.purchase_cost),
            }))
        })
        uni.showToast({ title: '采购开单成功', icon: 'success' })
        setTimeout(() => uni.navigateBack(), 1200)
    } catch (e: any) {
        uni.showToast({ title: e?.message || '开单失败', icon: 'none' })
    } finally { submitting.value = false }
}

const money = (v: any) => Number(v || 0).toFixed(2)
const warehouseTypeLabel = (t: string) => ({ second_hand: '二手仓', peer: '同行仓', consignment: '代卖仓', abnormal: '异常仓' }[t] || t || '')
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
.form-wrap { padding: 24rpx 24rpx 32rpx; }
.form-section { background:#fff; border-radius:16rpx; padding:24rpx; margin-bottom:20rpx; }
.form-section__head { display:flex; align-items:center; justify-content:space-between; margin-bottom:16rpx; }
.form-section__title { font-size:28rpx; font-weight:600; color:#374151; margin-bottom:16rpx; }
.form-row { display:flex; align-items:center; gap:16rpx; margin-bottom:16rpx; }
.form-label { font-size:26rpx; color:#374151; width:140rpx; flex-shrink:0; }
.form-label.required::before { content:'*'; color:#dc2626; margin-right:4rpx; }
.form-input { flex:1; display:flex; align-items:center; justify-content:space-between; background:#f8fafc; border-radius:8rpx; padding:12rpx 16rpx; }
.input-text { font-size:26rpx; color:#0f172a; }
.input-placeholder { font-size:26rpx; color:#94a3b8; }
.input-arrow { font-size:32rpx; color:#94a3b8; }
.device-form-card { border:1rpx solid #e2e8f0; border-radius:12rpx; padding:16rpx; margin-bottom:16rpx; }
.device-form__head { display:flex; align-items:center; justify-content:space-between; margin-bottom:12rpx; }
.device-form__index { font-size:26rpx; font-weight:600; color:#374151; }
.add-hint { text-align:center; color:#94a3b8; font-size:26rpx; padding:32rpx 0; }
.summary-row { display:flex; justify-content:space-between; padding-top:12rpx; border-top:1rpx solid #f1f5f9; margin-top:8rpx; }
.summary-label { font-size:26rpx; color:#374151; }
.summary-value { font-size:28rpx; font-weight:700; color:#0f172a; }
.bottom-bar { position:fixed; bottom:0; left:0; right:0; padding:20rpx 32rpx; padding-bottom:calc(20rpx + env(safe-area-inset-bottom)); background:#fff; box-shadow:0 -2rpx 16rpx rgba(0,0,0,.08); display:flex; gap:16rpx; }
.picker-wrap { padding:32rpx; }
.picker-title { font-size:30rpx; font-weight:600; color:#0f172a; margin-bottom:20rpx; }
.picker-item { padding:20rpx 0; border-bottom:1rpx solid #f1f5f9; }
.picker-item__name { font-size:28rpx; color:#0f172a; display:block; }
.picker-item__sub { font-size:24rpx; color:#64748b; margin-top:4rpx; display:block; }
.picker-empty { text-align:center; color:#94a3b8; padding:32rpx; }
</style>
