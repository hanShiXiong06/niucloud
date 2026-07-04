<template>
    <view class="erp-page">
        <RecyclePageHeader title="采购开单" />
        <scroll-view scroll-y style="height:calc(100vh - 100rpx)">
            <view class="form-wrap">

                <!-- 供应商 -->
                <view class="form-section">
                    <view class="form-row" @click="showPartyPicker = true">
                        <text class="form-label required">供应商</text>
                        <view class="form-input">
                            <text :class="form.party_name ? 'input-text' : 'input-placeholder'">
                                {{ form.party_name || '点击选择供应商' }}
                            </text>
                            <text class="input-arrow">›</text>
                        </view>
                    </view>
                    <view class="form-row">
                        <text class="form-label">M号</text>
                        <u-input v-model="form.m_no" placeholder="选填：业务编号/M号" :customStyle="inputStyle" />
                    </view>
                </view>

                <!-- 仓库库位 -->
                <view class="form-section">
                    <view class="form-row" @click="showWhPicker = true">
                        <text class="form-label required">入库仓库</text>
                        <view class="form-input">
                            <text :class="form.warehouse_name ? 'input-text' : 'input-placeholder'">
                                {{ whDisplayText || '点击选择仓库' }}
                            </text>
                            <text class="input-arrow">›</text>
                        </view>
                    </view>
                </view>

                <!-- 设备明细 -->
                <view class="form-section">
                    <view class="form-section__head">
                        <text class="form-section__title">设备明细（{{ form.items.length }} 台）</text>
                        <view>
                         <u-button size="mini" type="primary" @click="addDevice">+ 添加设备</u-button>
                        </view>
                    </view>
                    <view v-for="(item, idx) in form.items" :key="idx" class="device-form-card">
                        <view class="device-form__head">
                            <text class="device-form__index">设备 {{ idx + 1 }}</text>
                            <u-icon name="close-circle" color="#94a3b8" size="20" @click="removeDevice(idx)" />
                        </view>
                        <view class="form-row">
                            <text class="form-label required">IMEI</text>
                            <u-input v-model="item.imei" placeholder="扫描或手输 IMEI" :customStyle="inputStyle" />
                        </view>
                        <view class="form-row">
                            <text class="form-label required">型号</text>
                            <u-input v-model="item.model" placeholder="如：iPhone 15 128G 黑色" :customStyle="inputStyle" />
                        </view>
                        <view class="form-row">
                            <text class="form-label">规格/成色</text>
                            <u-input v-model="item.spec" placeholder="如：9成新 黑色 128G" :customStyle="inputStyle" />
                        </view>
                        <view class="form-row">
                            <text class="form-label required">采购成本</text>
                            <u-input v-model="item.purchase_cost" type="number" placeholder="0.00" :customStyle="inputStyle" />
                        </view>
                        <view class="form-row">
                            <text class="form-label">预估售价</text>
                            <u-input v-model="item.estimate_sale_price" type="number" placeholder="0.00（选填）" :customStyle="inputStyle" />
                        </view>
                    </view>
                    <view class="add-hint" v-if="!form.items.length">点击「添加设备」开始录入</view>
                </view>

                <!-- 备注 -->
                <view class="form-section">
                    <view class="form-row">
                        <text class="form-label">备注</text>
                        <u-input v-model="form.remark" placeholder="可选备注" :customStyle="inputStyle" />
                    </view>
                </view>

                <!-- 结算区域（ErpSettleBar 组件） -->
                <ErpSettleBar
                    v-model:settle-mode="form.settle_mode"
                    v-model:amount="form.paid_amount"
                    v-model:account-id="form.capital_account_id"
                    :total="totalCost"
                    :accounts="accounts"
                    label-cash="本次付款"
                />

            </view>
        </scroll-view>

        <!-- 底部提交 -->
        <view class="bottom-bar">
            <u-button @click="uni.navigateBack()" :customStyle="{flex:'1'}">取消</u-button>
            <u-button type="primary" :loading="submitting" :disabled="!canSubmit" @click="submit" :customStyle="{flex:'2'}">
                确认开单 ¥{{ money(totalCost) }}
            </u-button>
        </view>

        <!-- 组件弹窗 -->
        <ErpPartyPopup
            v-model:show="showPartyPicker"
            role-type="supplier"
            v-model:party-id="form.party_id"
            v-model:party-name="form.party_name"
        />

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
import { ref, computed, onMounted } from 'vue'
import { getMobileCapitalAccounts } from '@/addon/hsx_erp/api/erp'
import ErpPartyPopup from '@/addon/hsx_erp/components/ErpPartyPopup.vue'
import ErpWarehousePopup from '@/addon/hsx_erp/components/ErpWarehousePopup.vue'
import ErpSettleBar from '@/addon/hsx_erp/components/ErpSettleBar.vue'
import request from '@/utils/request'

const submitting = ref(false)
const accounts = ref<any[]>([])
const showPartyPicker = ref(false)
const showWhPicker = ref(false)

const form = ref({
    party_id: 0, party_name: '', m_no: '',
    warehouse_id: 0, warehouse_name: '',
    location_id: 0, location_name: '',
    settle_mode: 'credit' as 'credit' | 'cash',
    paid_amount: 0, capital_account_id: 0,
    remark: '',
    items: [] as any[],
})

const inputStyle = { background: '#f8fafc', borderRadius: '8rpx', padding: '8rpx 16rpx' }
const whDisplayText = computed(() => {
    if (!form.value.warehouse_name) return ''
    return form.value.location_name
        ? `${form.value.warehouse_name} / ${form.value.location_name}`
        : form.value.warehouse_name
})
const totalCost = computed(() => form.value.items.reduce((s, i) => s + Number(i.purchase_cost || 0), 0))
const canSubmit = computed(() =>
    form.value.party_id > 0 &&
    form.value.warehouse_id > 0 &&
    form.value.items.length > 0 &&
    form.value.items.every(i => i.imei && i.model && Number(i.purchase_cost) > 0)
)

onMounted(async () => {
    try {
        const res: any = await getMobileCapitalAccounts()
        accounts.value = res?.data?.list || []
    } catch {}
})

function addDevice() {
    form.value.items.push({ imei: '', model: '', spec: '', purchase_cost: 0, estimate_sale_price: 0 })
}
function removeDevice(idx: number) { form.value.items.splice(idx, 1) }

async function submit() {
    if (!canSubmit.value) return
    submitting.value = true
    try {
        await request.post('erp/purchase/create', {
            party_id: form.value.party_id,
            party_name: form.value.party_name,
            m_no: form.value.m_no,
            warehouse_id: form.value.warehouse_id,
            warehouse_name: form.value.warehouse_name,
            location_id: form.value.location_id || 0,
            location_name: form.value.location_name,
            settle_mode: form.value.settle_mode,
            paid_amount: form.value.settle_mode === 'cash' ? Number(form.value.paid_amount) : 0,
            capital_account_id: form.value.settle_mode === 'cash' ? form.value.capital_account_id : 0,
            remark: form.value.remark,
            items: form.value.items.map(i => ({
                imei: i.imei, model: i.model, spec: i.spec,
                purchase_cost: Number(i.purchase_cost),
                estimate_sale_price: Number(i.estimate_sale_price || 0),
            }))
        })
        uni.showToast({ title: '采购开单成功', icon: 'success' })
        setTimeout(() => uni.navigateBack(), 1200)
    } catch (e: any) {
        uni.showToast({ title: e?.message || '开单失败', icon: 'none' })
    } finally { submitting.value = false }
}

const money = (v: any) => Number(v || 0).toFixed(2)
</script>

<style scoped lang="scss">
@import '@/addon/hsx_erp/styles/erp-mobile.scss';
.form-wrap { padding: 24rpx 24rpx 120rpx; display: flex; flex-direction: column; gap: 20rpx; }
.form-section { background:#fff; border-radius:16rpx; padding:24rpx; }
.form-section__head { display:flex; align-items:center; justify-content:space-between; margin-bottom:16rpx; }
.form-section__title { font-size:28rpx; font-weight:600; color:#374151; }
.form-row { display:flex; align-items:center; gap:16rpx; margin-bottom:16rpx; &:last-child { margin-bottom:0; } }
.form-label { font-size:26rpx; color:#374151; width:150rpx; flex-shrink:0; }
.form-label.required::before { content:'*'; color:#dc2626; margin-right:4rpx; }
.form-input { flex:1; display:flex; align-items:center; justify-content:space-between; background:#f8fafc; border-radius:8rpx; padding:12rpx 16rpx; }
.input-text { font-size:26rpx; color:#0f172a; }
.input-placeholder { font-size:26rpx; color:#94a3b8; }
.input-arrow { font-size:32rpx; color:#94a3b8; }
.device-form-card { border:1rpx solid #e2e8f0; border-radius:12rpx; padding:16rpx; margin-bottom:16rpx; }
.device-form__head { display:flex; align-items:center; justify-content:space-between; margin-bottom:12rpx; }
.device-form__index { font-size:26rpx; font-weight:600; color:#374151; }
.add-hint { text-align:center; color:#94a3b8; font-size:26rpx; padding:32rpx 0; }
.bottom-bar { position:fixed; bottom:0; left:0; right:0; padding:20rpx 32rpx; padding-bottom:calc(20rpx + env(safe-area-inset-bottom)); background:#fff; box-shadow:0 -2rpx 16rpx rgba(0,0,0,.08); display:flex; gap:16rpx; }
</style>
