<template>
    <view class="erp-page">
        <RecyclePageHeader title="销售退货" />
        <scroll-view scroll-y style="height:calc(100vh - 180rpx)">
            <view style="padding-bottom:120rpx">

                <!-- 原销售单信息 -->
                <view class="form-card" style="margin-top:24rpx">
                    <view class="form-card-title">销售单信息</view>
                    <view class="field">
                        <text class="label">原销售单</text>
                        <text class="value" :class="saleNo ? '' : 'placeholder'">{{ saleNo || '未选择' }}</text>
                    </view>
                    <view class="field field--last">
                        <text class="label">客户</text>
                        <text class="value">{{ partyName || '-' }}</text>
                    </view>
                </view>

                <!-- 退货设备 -->
                <view class="section-title">选择退货设备</view>
                <view v-if="loadingAssets" class="loading-center"><u-loading-icon size="28" /></view>
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
                                <u-checkbox :modelValue="isSelected(item.id)" @click.stop @change="() => toggleSelect(item)" />
                                <text class="card-title">{{ item.model }}</text>
                            </view>
                            <u-tag text="已售" type="primary" plain plainFill size="mini" />
                        </view>
                        <text class="card-meta">{{ item.spec || '-' }} · IMEI {{ item.imei || '-' }}</text>
                        <text class="card-meta">售价 ¥{{ money(item.sale_price) }} · 成本 ¥{{ money(item.cost) }}</text>
                        <template v-if="isSelected(item.id)">
                            <view class="return-inputs">
                                <view class="field" style="border-top:1rpx solid #f3f4f6;margin-top:12rpx">
                                    <text class="label">退货价</text>
                                    <u-input v-model="getSelected(item.id).return_price" type="number" :placeholder="money(item.sale_price)" :customStyle="{ textAlign:'right', flex:'1' }" @click.stop />
                                </view>
                                <view class="field field--last">
                                    <text class="label">退货原因</text>
                                    <u-input v-model="getSelected(item.id).reason" placeholder="可选" :customStyle="{ textAlign:'right', flex:'1' }" @click.stop />
                                </view>
                            </view>
                        </template>
                    </view>
                    <view v-if="!availableAssets.length && !loadingAssets" class="empty-tip">该销售单暂无可退货的设备</view>
                </template>

                <!-- 退回仓库 -->
                <view class="form-card">
                    <view class="form-card-title">退款 & 退回设置</view>
                    <view class="field">
                        <text class="label">退款方式</text>
                        <view style="display:flex;gap:16rpx">
                            <u-button v-for="m in refundModes" :key="m.value"
                                :type="form.refund_mode === m.value ? 'primary' : 'default'"
                                size="mini" @click="form.refund_mode = m.value">{{ m.label }}</u-button>
                        </view>
                    </view>
                    <view class="field" @click="showWhPicker = true">
                        <text class="label">退回仓库</text>
                        <text class="value" :class="form.warehouse_name ? '' : 'placeholder'">
                            {{ form.warehouse_name ? (form.location_name ? form.warehouse_name+' / '+form.location_name : form.warehouse_name) : '点击选择' }}
                        </text>
                        <text class="arrow">›</text>
                    </view>
                    <view class="field field--last">
                        <text class="label">备注</text>
                        <u-input v-model="form.remark" placeholder="可选" :customStyle="{ textAlign:'right', flex:'1' }" />
                    </view>
                </view>

                <view class="form-card" v-if="selectedItems.length">
                    <view class="field"><text class="label">已选</text><text class="value" style="color:#3b6ef5">{{ selectedItems.length }} 台</text></view>
                    <view class="field field--last"><text class="label">退款合计</text><text class="value" style="color:#ea580c;font-weight:600">¥{{ money(totalReturn) }}</text></view>
                </view>
            </view>
        </scroll-view>

        <view class="float-bar">
            <u-button @click="uni.navigateBack()" :customStyle="{flex:'1'}">取消</u-button>
            <u-button type="primary" :loading="submitting" :disabled="!canSubmit" @click="submit" :customStyle="{flex:'2'}">提交退货申请</u-button>
        </view>

        <ErpWarehousePopup
            v-model:show="showWhPicker"
            v-model:warehouse-id="form.warehouse_id"
            v-model:warehouse-name="form.warehouse_name"
            v-model:location-id="form.location_id"
            v-model:location-name="form.location_name"
        />
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { createErpSaleReturn } from '@/addon/hsx_erp/api/erp'
import ErpWarehousePopup from '@/addon/hsx_erp/components/ErpWarehousePopup.vue'
import request from '@/utils/request'

const saleOrderId = ref(0)
const saleNo = ref('')
const partyName = ref('')
const availableAssets = ref<any[]>([])
const loadingAssets = ref(false)
const submitting = ref(false)
const selectedItems = ref<any[]>([])
const showWhPicker = ref(false)

const form = ref({ refund_mode: 'cash', warehouse_id: 0, warehouse_name: '', location_id: 0, location_name: '', remark: '' })
const refundModes = [{ label: '现金退回', value: 'cash' }, { label: '应付冲减', value: 'offset' }]

onLoad(async (query: any) => {
    saleOrderId.value = Number(query?.sale_order_id || 0)
    saleNo.value = decodeURIComponent(query?.sale_no || '')
    partyName.value = decodeURIComponent(query?.party_name || '')
    if (saleOrderId.value) await loadAssets()
})

async function loadAssets() {
    loadingAssets.value = true
    try {
        const res: any = await request.get(`erp/sale/${saleOrderId.value}`)
        const data = res?.data || {}
        availableAssets.value = (data.items || [])
            .filter((i: any) => i.status === 'sold')
            .map((i: any) => ({ id: i.asset_id || i.id, asset_id: i.asset_id || i.id, sale_item_id: i.id, model: i.model, imei: i.imei, spec: i.spec, sale_price: i.sale_price, cost: i.cost }))
    } finally { loadingAssets.value = false }
}

const isSelected = (id: number) => selectedItems.value.some(s => s.asset_id === id)
const getSelected = (id: number) => selectedItems.value.find(s => s.asset_id === id)!

function toggleSelect(item: any) {
    const idx = selectedItems.value.findIndex(s => s.asset_id === item.id)
    if (idx >= 0) { selectedItems.value.splice(idx, 1) }
    else { selectedItems.value.push({ asset_id: item.id, sale_item_id: item.sale_item_id, return_price: Number(item.sale_price || 0), reason: '' }) }
}

const totalReturn = computed(() => selectedItems.value.reduce((s, i) => s + Number(i.return_price || 0), 0))
const canSubmit = computed(() => selectedItems.value.length > 0 && saleOrderId.value > 0)

async function submit() {
    submitting.value = true
    try {
        await createErpSaleReturn({
            sale_order_id: saleOrderId.value,
            refund_mode: form.value.refund_mode,
            return_to_warehouse_id: form.value.warehouse_id || 0,
            return_to_location_id: form.value.location_id || 0,
            remark: form.value.remark,
            items: selectedItems.value.map(i => ({ asset_id: i.asset_id, return_price: Number(i.return_price), reason: i.reason || '' }))
        })
        uni.showToast({ title: '退货申请已提交，等待财务确认', icon: 'success' })
        setTimeout(() => uni.navigateBack(), 1500)
    } catch (e: any) {
        uni.showToast({ title: e?.message || '提交失败', icon: 'none' })
    } finally { submitting.value = false }
}

const money = (v: any) => Number(v || 0).toFixed(2)
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
.device-selected { border: 2rpx solid var(--primary-color, #3b6ef5); background: #f0f5ff; }
.device-check-row { display: flex; align-items: center; gap: 12rpx; }
.loading-center { display: flex; justify-content: center; padding: 48rpx; }
.empty-tip { text-align: center; color: #94a3b8; font-size: 26rpx; padding: 48rpx 0; }
</style>
