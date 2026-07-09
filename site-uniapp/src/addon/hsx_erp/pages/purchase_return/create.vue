<template>
    <view class="erp-page">
        <RecyclePageHeader title="采购退货" />
        <scroll-view scroll-y style="height:calc(100vh - 180rpx)">
            <view style="padding-bottom:120rpx">

                <!-- 原采购单信息 -->
                <view class="form-card" style="margin-top:24rpx">
                    <view class="form-card-title">采购单信息</view>
                    <view class="field">
                        <text class="label"><text class="req">*</text>原采购单</text>
                        <text class="value" :class="purchaseNo ? '' : 'placeholder'">
                            {{ purchaseNo || '未选择' }}
                        </text>
                    </view>
                    <view class="field field--last">
                        <text class="label">供应商</text>
                        <text class="value">{{ partyName || '-' }}</text>
                    </view>
                </view>

                <!-- 退货设备选择 -->
                <view class="section-head">
                    <text class="section-title">选择退货设备</text>
                    <view>
                      <u-button size="mini" plain type="primary" @click="scanSelectAsset">扫码定位</u-button>
                    </view>
                </view>
                <view v-if="loadingAssets" class="loading-center">
                    <u-loading-icon size="28" />
                </view>
                <template v-else>
                    <view
                        v-for="item in availableAssets"
                        :key="item.id"
                        class="erp-card"
                        :class="{ 'device-selected': isSelected(item.id) }"
                        @click="toggleSelect(item)"
                    >
                        <view class="erp-card__head">
                            <view class="device-check-row">
                                <u-checkbox
                                    :modelValue="isSelected(item.id)"
                                    @click.stop
                                    @change="() => toggleSelect(item)"
                                />
                                <text class="card-title">{{ item.model }}</text>
                            </view>
                            <u-tag text="在库" type="success" plain plainFill size="mini" />
                        </view>
                        <text class="card-meta">{{ item.spec || '-' }} · IMEI {{ item.imei || '-' }}</text>
                        <text class="card-meta">成本 ¥{{ money(item.total_cost) }} · {{ item.warehouse_name }}</text>
                        <!-- 已选时展示退货价和原因输入 -->
                        <template v-if="isSelected(item.id)">
                            <view class="return-inputs">
                                <view class="field" style="border-top:1rpx solid #f3f4f6;margin-top:12rpx">
                                    <text class="label">退货价</text>
                                    <u-input
                                        v-model="getSelectedItem(item.id).return_cost"
                                        type="number"
                                        :placeholder="money(item.total_cost)"
                                        :customStyle="{ textAlign: 'right', flex: '1' }"
                                        @click.stop
                                    />
                                </view>
                                <view class="field field--last">
                                    <text class="label">退货原因</text>
                                    <u-input
                                        v-model="getSelectedItem(item.id).reason"
                                        placeholder="可选"
                                        :customStyle="{ textAlign: 'right', flex: '1' }"
                                        @click.stop
                                    />
                                </view>
                            </view>
                        </template>
                    </view>
                    <view v-if="!availableAssets.length && !loadingAssets" class="empty-tip">
                        该采购单暂无可退货的在库设备
                    </view>
                </template>

                <!-- 退款方式 -->
                <view class="form-card">
                    <view class="form-card-title">退款设置</view>
                    <view class="field">
                        <text class="label">退款方式</text>
                        <view style="display:flex;gap:16rpx">
                            <u-button
                                v-for="m in refundModes" :key="m.value"
                                :type="form.refund_mode === m.value ? 'primary' : 'default'"
                                size="mini"
                                @click="form.refund_mode = m.value"
                            >{{ m.label }}</u-button>
                        </view>
                    </view>
                    <view class="field field--last">
                        <text class="label">备注</text>
                        <u-input
                            v-model="form.remark"
                            placeholder="可选备注"
                            :customStyle="{ textAlign: 'right', flex: '1' }"
                        />
                    </view>
                </view>

                <!-- 汇总 -->
                <view class="form-card" v-if="selectedItems.length">
                    <view class="field">
                        <text class="label">已选设备</text>
                        <text class="value" style="color:#3b6ef5">{{ selectedItems.length }} 台</text>
                    </view>
                    <view class="field field--last">
                        <text class="label">退货金额合计</text>
                        <text class="value" style="color:#ea580c;font-weight:600">¥{{ money(totalReturn) }}</text>
                    </view>
                </view>

            </view>
        </scroll-view>

        <!-- 底部提交 -->
        <view class="float-bar">
            <u-button @click="uni.navigateBack()" :customStyle="{flex:'1'}">取消</u-button>
            <u-button
                type="primary"
                :loading="submitting"
                :disabled="!canSubmit"
                @click="submit"
                :customStyle="{flex:'2'}"
            >提交退货申请</u-button>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { createErpPurchaseReturn } from '@/addon/hsx_erp/api/erp'
import { scanErpCode } from '@/addon/hsx_erp/hooks/useErpScan'
import request from '@/utils/request'

const purchaseOrderId = ref(0)
const purchaseNo = ref('')
const partyName = ref('')
const availableAssets = ref<any[]>([])
const loadingAssets = ref(false)
const submitting = ref(false)
const selectedItems = ref<any[]>([])

const form = ref({ refund_mode: 'cash', remark: '' })
const refundModes = [
    { label: '现金退回', value: 'cash' },
    { label: '应收冲减', value: 'offset' },
]

onLoad(async (query: any) => {
    purchaseOrderId.value = Number(query?.purchase_order_id || 0)
    purchaseNo.value = decodeURIComponent(query?.purchase_no || '')
    partyName.value = decodeURIComponent(query?.party_name || '')
    if (purchaseOrderId.value) await loadAssets()
})

async function loadAssets() {
    loadingAssets.value = true
    try {
        const res: any = await request.get(`erp/purchase/${purchaseOrderId.value}`)
        const data = res?.data || {}
        // 只取 in_stock 的设备（从 items 里找 asset_id，再过滤 status）
        availableAssets.value = (data.items || []).filter((i: any) =>
            i.status === 'in_stock' || i.asset_status === 'in_stock'
        ).map((i: any) => ({
            id: i.asset_id || i.id,
            asset_id: i.asset_id || i.id,
            model: i.model,
            imei: i.imei,
            spec: i.spec,
            total_cost: i.total_cost || i.purchase_cost,
            warehouse_name: i.warehouse_name || data.warehouse_name || '',
            purchase_item_id: i.id,
        }))
    } finally { loadingAssets.value = false }
}

const isSelected = (id: number) => selectedItems.value.some(s => s.asset_id === id)
const getSelectedItem = (id: number) => selectedItems.value.find(s => s.asset_id === id)!

function toggleSelect(item: any) {
    const idx = selectedItems.value.findIndex(s => s.asset_id === item.id)
    if (idx >= 0) {
        selectedItems.value.splice(idx, 1)
    } else {
        selectedItems.value.push({
            asset_id: item.id,
            return_cost: Number(item.total_cost || 0),
            reason: '',
        })
    }
}

async function scanSelectAsset() {
    try {
        const code = await scanErpCode()
        const item = availableAssets.value.find(i => scanMatch(i, code))
        if (!item) {
            uni.showToast({ title: '当前采购单未找到该设备', icon: 'none' })
            return
        }
        if (!isSelected(item.id)) toggleSelect(item)
        uni.showToast({ title: '已选中设备', icon: 'success' })
    } catch (e: any) {
        if (e?.errMsg?.includes('cancel')) return
        uni.showToast({ title: e?.message || '扫码失败', icon: 'none' })
    }
}

const scanMatch = (item: any, code: string) => ['imei', 'sn', 'asset_no'].some(key => String(item?.[key] || '').trim() === code)

const totalReturn = computed(() => selectedItems.value.reduce((s, i) => s + Number(i.return_cost || 0), 0))
const canSubmit = computed(() => selectedItems.value.length > 0 && purchaseOrderId.value > 0)

async function submit() {
    submitting.value = true
    try {
        await createErpPurchaseReturn({
            purchase_order_id: purchaseOrderId.value,
            refund_mode: form.value.refund_mode,
            remark: form.value.remark,
            items: selectedItems.value.map(i => ({
                asset_id: i.asset_id,
                return_cost: Number(i.return_cost),
                reason: i.reason || '',
            }))
        })
        uni.showToast({ title: '采购退货已完成', icon: 'success', duration: 800 })
        setTimeout(() => {
            uni.hideToast()
            uni.navigateBack()
        }, 850)
    } catch (e: any) {
        uni.showToast({ title: e?.message || '提交失败', icon: 'none' })
    } finally { submitting.value = false }
}

const money = (v: any) => Number(v || 0).toFixed(2)
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
.device-selected { border: 2rpx solid var(--primary-color, #3b6ef5); background: #f0f5ff; }
.section-head { display:flex; align-items:center; justify-content:space-between; padding:16rpx 28rpx 8rpx; }
.section-head .section-title { padding:0; }
.device-check-row { display: flex; align-items: center; gap: 12rpx; }
.return-inputs { padding: 0; }
.loading-center { display: flex; justify-content: center; padding: 48rpx; }
.empty-tip { text-align: center; color: #94a3b8; font-size: 26rpx; padding: 48rpx 0; }
</style>
