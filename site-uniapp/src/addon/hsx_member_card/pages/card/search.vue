<template>
    <view class="min-h-screen bg-[#f5f6f8] px-[24rpx] pb-[48rpx] pt-[20rpx] text-[#1f2937]">
        <view class="rounded-[24rpx] bg-white px-[22rpx] pb-[22rpx] pt-[24rpx]">
            <view class="mb-[18rpx] flex items-center justify-between">
                <view>
                    <text class="block text-[30rpx] font-semibold text-[#27364b]">查找会员卡</text>
                    <text class="mt-[4rpx] block text-[22rpx] text-[#8a96a8]">输入手机号后四位即可查询</text>
                </view>
                <view class="flex h-[58rpx] w-[58rpx] items-center justify-center rounded-[17rpx] bg-[#edf4ff]">
                    <u-icon name="phone" color="#2468f2" size="20" />
                </view>
            </view>

            <view class="flex h-[82rpx] items-center rounded-[16rpx] bg-[#f5f7fa] px-[18rpx]">
                    <u-icon name="phone" color="#7b8798" size="18" class="mr-[10rpx]" />
                    <u-input
                        v-model="mobile"
                        type="number"
                        maxlength="11"
                        border="none"
                        placeholder="完整手机号或后四位"
                        class="flex-1 text-[28rpx] font-medium"
                        confirm-type="search"
                        @confirm="search"
                    />
                    <u-icon v-if="mobile" name="close-circle-fill" color="#c7d0dc" size="17" @click="mobile = ''" />
            </view>

            <view class="mt-[12rpx] flex min-h-[56rpx] items-center justify-between px-[4rpx]" @click="advancedVisible = !advancedVisible">
                <view class="flex items-center gap-[8rpx]"><u-icon name="account" color="#7b8798" size="16" /><text class="text-[23rpx] text-[#667085]">精确筛选姓名</text><text class="text-[20rpx] text-[#a3acba]">选填</text></view>
                <u-icon :name="advancedVisible ? 'arrow-up' : 'arrow-down'" color="#a3acba" size="14" />
            </view>
            <view v-if="advancedVisible" class="mb-[12rpx] flex h-[72rpx] items-center rounded-[14rpx] bg-[#f5f7fa] px-[16rpx]">
                    <u-icon name="account" color="#7b8798" size="17" class="mr-[10rpx]" />
                    <u-input
                        v-model="name"
                        border="none"
                        placeholder="多人命中时输入购卡姓名"
                        class="flex-1 text-[26rpx]"
                    />
            </view>

            <MemberCardButton
                type="primary"
                icon="search"
                text="查询会员卡"
                :loading="loading"
                @click="search"
                class="!h-[82rpx] !rounded-[16rpx] !text-[28rpx] !font-semibold"
            />
        </view>

        <view class="mt-[14rpx] flex items-center gap-[7rpx] px-[6rpx] text-[21rpx] text-[#8a96a8]">
            <u-icon name="info-circle" color="#98a2b3" size="14" />
            <text>核销前请当面核对购卡姓名</text>
        </view>

        <view v-if="searched && !candidates.length" class="py-[110rpx]">
            <u-empty text="没有找到可核销会员卡" mode="search" />
        </view>

        <view v-if="candidates.length" class="flex items-center justify-between px-[4rpx] pb-[14rpx] pt-[28rpx]">
            <text class="text-[28rpx] font-semibold text-[#27364b]">可用会员卡</text>
            <text class="text-[21rpx] text-[#98a2b3]">{{ candidates.length }} 位客户</text>
        </view>

        <view
            v-for="candidate in candidates"
            :key="candidate.member_id"
            class="mb-[16rpx] overflow-hidden rounded-[22rpx] bg-white"
        >
            <view class="flex items-center gap-[14rpx] border-b border-[#eef1f5] px-[20rpx] py-[18rpx]">
                <view class="flex h-[58rpx] w-[58rpx] items-center justify-center rounded-full bg-[#edf4ff] text-[26rpx] font-semibold text-[#2468f2]">
                    {{ String(candidate.holder_name || '客').slice(0, 1) }}
                </view>
                <view class="min-w-0 flex-1">
                    <text class="block truncate text-[27rpx] font-semibold text-[#344054]">{{ candidate.holder_name }}</text>
                    <text class="mt-[3rpx] block text-[21rpx] text-[#8a96a8]">{{ candidate.mobile_masked }}</text>
                </view>
                <text class="rounded-full bg-[#eaf9f0] px-[12rpx] py-[5rpx] text-[20rpx] font-medium text-[#16a34a]">{{ candidate.available_card_count }} 张可用</text>
            </view>

            <view
                v-for="card in candidate.cards"
                :key="card.card_id"
                class="flex items-center gap-[14rpx] border-b border-[#f0f2f5] px-[20rpx] py-[18rpx]"
                :class="card.available ? 'bg-white' : 'bg-[#f7f8fa] opacity-60'"
            >
                <view
                    class="flex h-[54rpx] w-[54rpx] flex-none items-center justify-center rounded-[15rpx]"
                    :class="card.available ? 'bg-[#f1efff]' : 'bg-[#eef1f5]'"
                >
                    <u-icon name="order" :color="card.available ? '#6d4aff' : '#98a2b3'" size="19" />
                </view>
                <view class="min-w-0 flex-1">
                    <text class="block truncate text-[25rpx] font-medium text-[#344054]">{{ card.product_name }}</text>
                    <text class="mt-[5rpx] block truncate text-[21rpx] text-[#8a96a8]">{{ card.item_name }} · {{ card.validity_text }}</text>
                    <text v-if="card.binding_mode !== 'member'" class="mt-[5rpx] block truncate text-[20rpx] text-[#2468f2]">
                        {{ card.binding_mode === 'imei' ? `绑定 IMEI：${card.bound_imei}` : `适用型号：${card.bound_model}` }}
                    </text>
                    <text class="mt-[5rpx] block text-[21rpx] font-medium text-[#16a34a]">{{ card.usage_mode === 'unlimited' ? '不限次' : `剩余 ${card.remaining_times} 次` }}</text>
                    <text v-if="card.consumable_name && inventoryMode !== 'none'" class="mt-[4rpx] block text-[20rpx] text-[#8a96a8]">
                        核销耗材：{{ card.consumable_name }} {{ formatQty(card.standard_consumable_qty) }}{{ card.consumable_unit }}
                    </text>
                </view>
                <view class="w-[138rpx] flex-none">
                    <MemberCardButton
                        type="primary"
                        compact
                        :disabled="!card.available"
                        text="核销"
                        @click="openConfirm(candidate, card)"
                        class="!rounded-[14rpx] !text-[24rpx] !font-medium"
                    />
                </view>
            </view>
        </view>

        <u-popup
            :show="confirmVisible"
            mode="bottom"
            :safe-area-inset-bottom="true"
            round="24"
            @close="confirmVisible = false"
        >
            <view v-if="selected" class="px-[28rpx] pb-[40rpx] pt-[30rpx]">
                <view class="mb-[24rpx] flex items-start justify-between">
                    <view>
                        <text class="block text-[32rpx] font-semibold text-[#27364b]">确认核销</text>
                        <text class="mt-[4rpx] block text-[22rpx] text-[#8a96a8]">本次将扣减 1 次服务</text>
                    </view>
                    <view class="h-[56rpx] w-[56rpx] flex items-center justify-center rounded-full bg-[#f1f5f9]" @click="confirmVisible = false">
                        <u-icon name="close" color="#64748b" size="20" />
                    </view>
                </view>

                <view class="mb-[22rpx] rounded-[18rpx] bg-[#f5f8ff] p-[20rpx]">
                    <view class="flex items-center gap-[16rpx]">
                        <view class="h-[68rpx] w-[68rpx] flex items-center justify-center rounded-full bg-[#dbeafe]">
                            <u-icon name="account" color="#2563eb" size="24" />
                        </view>
                        <view>
                            <text class="text-[21rpx] text-[#8a96a8]">购卡人</text>
                            <text class="block text-[32rpx] font-semibold text-[#27364b]">{{ selected.candidate.holder_name }}</text>
                            <text class="text-[21rpx] text-[#667085]">
                                {{ selected.candidate.mobile_masked }} · {{ selected.card.product_name }}
                            </text>
                        </view>
                    </view>
                </view>

                <view v-if="selected.card.binding_mode !== 'member'" class="mb-[20rpx] rounded-[18rpx] border border-[#dbeafe] bg-[#f8fbff] p-[18rpx]">
                    <text class="mb-[12rpx] block text-[24rpx] font-semibold text-[#344054]">{{ selected.card.binding_mode === 'imei' ? '核验服务设备' : '核验产品型号' }}</text>
                    <view v-if="selected.card.binding_mode === 'imei'" class="flex min-h-[76rpx] items-center gap-[10rpx] rounded-[14rpx] bg-white px-[14rpx]">
                        <u-icon name="scan" color="#2468f2" size="18" @click="scanServiceImei" />
                        <u-input v-model="serviceImei" border="none" placeholder="扫描或输入本次设备 IMEI" class="min-w-0 flex-1 text-[25rpx]" />
                        <text class="text-[22rpx] font-medium text-[#2468f2]" @click="scanServiceImei">扫码</text>
                    </view>
                    <view v-else class="flex min-h-[76rpx] items-center gap-[10rpx] rounded-[14rpx] bg-white px-[14rpx]">
                        <u-icon name="tags" color="#2468f2" size="18" />
                        <u-input v-model="serviceModel" border="none" :placeholder="`请输入：${selected.card.bound_model}`" class="min-w-0 flex-1 text-[25rpx]" />
                    </view>
                    <text class="mt-[10rpx] block text-[20rpx] text-[#8a96a8]">系统将在提交时校验，不一致则不会扣减次数。</text>
                </view>

                <view v-if="inventoryMode !== 'none' && selected.card.consumable_name" class="mb-[20rpx] rounded-[18rpx] border border-[#e8ecf1] bg-[#fafbfc] p-[18rpx]">
                    <view class="flex items-center justify-between gap-[18rpx]">
                        <view class="min-w-0 flex-1">
                            <view class="flex items-center gap-[8rpx]">
                                <text class="text-[25rpx] font-semibold text-[#344054]">实际耗材</text>
                                <text class="rounded-full px-[9rpx] py-[3rpx] text-[18rpx]" :class="inventoryMode === 'strict' ? 'bg-[#fff4e5] text-[#d97706]' : 'bg-[#edf4ff] text-[#2468f2]'">
                                    {{ inventoryMode === 'strict' ? '严格库存' : '自动扣减' }}
                                </text>
                            </view>
                            <text class="mt-[5rpx] block text-[21rpx] text-[#8a96a8]">
                                {{ selected.card.consumable_name }} · 标准 {{ formatQty(selected.card.standard_consumable_qty) }}{{ selected.card.consumable_unit }}
                            </text>
                        </view>
                        <view class="flex flex-none items-center gap-[8rpx]">
                            <u-number-box v-model="actualConsumableQty" :min="1" :max="99" />
                            <text class="text-[23rpx] text-[#667085]">{{ selected.card.consumable_unit }}</text>
                        </view>
                    </view>
                    <text v-if="lossQuantity > 0" class="mt-[12rpx] block rounded-[12rpx] bg-[#fff7ed] px-[13rpx] py-[10rpx] text-[20rpx] text-[#c26b16]">
                        多耗 {{ formatQty(lossQuantity) }}{{ selected.card.consumable_unit }}，将记为贴坏 / 返工损耗
                    </text>
                    <text v-else class="mt-[10rpx] block text-[20rpx] text-[#94a3b8]">
                        {{ inventoryMode === 'strict' ? '库存不足将阻止核销，不会扣减会员卡次数。' : '库存不足仍可核销，并形成缺货提醒。' }}
                    </text>
                </view>

                <view
                    class="mb-[20rpx] flex items-center gap-[10rpx] rounded-[15rpx] bg-[#f5f7fa] p-[17rpx]"
                    @click="confirmed = !confirmed"
                >
                    <u-checkbox :checked="confirmed" @change="confirmed = $event" />
                    <text class="text-[23rpx] font-medium text-[#475467]">已当面核对手机号与姓名</text>
                </view>

                <!-- 备注 -->
                <u-textarea
                    v-model="remark"
                    height="100rpx"
                    placeholder="本次服务说明（选填）"
                    class="!rounded-[16rpx] !bg-[#f8fafc]"
                />

                <!-- 底部按钮 -->
                <view class="mt-[28rpx] flex gap-[16rpx]">
                    <view class="flex-1">
                        <MemberCardButton
                            text="取消"
                            @click="confirmVisible = false"
                            class="!h-[88rpx] !rounded-[18rpx] !bg-[#f1f5f9] !text-[#475569]"
                        />
                    </view>
                    <view class="flex-1">
                        <MemberCardButton
                            type="primary"
                            icon="checkmark"
                            text="确认核销"
                            :disabled="!confirmed"
                            :loading="redeeming"
                            @click="redeem"
                            class="!h-[88rpx] !rounded-[18rpx] !font-semibold"
                        />
                    </view>
                </view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { memberCardRequestId, redeemMemberCard, searchMemberCards } from '../../api'
import MemberCardButton from '../../components/MemberCardButton.vue'

const mobile = ref('')
const name = ref('')
const advancedVisible = ref(false)
const loading = ref(false)
const searched = ref(false)
const candidates = ref<any[]>([])
const inventoryConfig = ref<any>({ mode: 'none' })
const confirmVisible = ref(false)
const selected = ref<any>(null)
const confirmed = ref(false)
const remark = ref('')
const serviceImei = ref('')
const serviceModel = ref('')
const redeeming = ref(false)
const actualConsumableQty = ref(1)
const inventoryMode = computed(() => ['auto', 'strict'].includes(inventoryConfig.value?.mode) ? inventoryConfig.value.mode : 'none')
const lossQuantity = computed(() => Math.max(0, Number(actualConsumableQty.value || 0) - Number(selected.value?.card?.standard_consumable_qty || 0)))
const formatQty = (value: any) => Number(value || 0).toFixed(3).replace(/\.?0+$/, '')

const search = async () => {
    if (!/^\d{4}$|^1\d{10}$/.test(mobile.value)) return uni.showToast({ title: '请输入完整手机号或后四位', icon: 'none' })
    loading.value = true
    try {
        const data = ((await searchMemberCards({ mobile_keyword: mobile.value, name: name.value })) as any)?.data || {}
        candidates.value = data.candidates || []
        inventoryConfig.value = data.inventory_config || { mode: 'none' }
        searched.value = true
    } finally {
        loading.value = false
    }
}
const openConfirm = (candidate: any, card: any) => {
    selected.value = { candidate, card }
    confirmed.value = false
    remark.value = ''
    serviceImei.value = ''
    serviceModel.value = ''
    actualConsumableQty.value = Math.max(1, Number(card.standard_consumable_qty || 1))
    confirmVisible.value = true
}
const scanServiceImei = () => uni.scanCode({ success: result => { serviceImei.value = String(result.result || '').trim() } })
const redeem = async () => {
    if (!confirmed.value) return
    if (selected.value?.card?.binding_mode === 'imei' && !serviceImei.value.trim()) return uni.showToast({ title: '请扫描或输入设备 IMEI', icon: 'none' })
    if (selected.value?.card?.binding_mode === 'model' && !serviceModel.value.trim()) return uni.showToast({ title: '请输入本次设备型号', icon: 'none' })
    const modal = await uni.showModal({ title: '核销确认', content: '确认贴膜服务已经完成？核销后将扣减 1 次并记录当前操作人。', confirmText: '确认核销' })
    if (!modal.confirm) return
    redeeming.value = true
    try {
        const result: any = (await redeemMemberCard(selected.value.card.card_id, {
            request_id: memberCardRequestId('redeem'),
            card_item_id: selected.value.card.item_id,
            service_imei: serviceImei.value,
            service_model: serviceModel.value,
            verification_confirmed: 1,
            actual_consumable_qty: inventoryMode.value === 'none' ? 0 : Number(actualConsumableQty.value),
            remark: remark.value,
        }))?.data
        confirmVisible.value = false
        if (['negative', 'failed'].includes(result?.inventory_status)) {
            await uni.showModal({
                title: result.inventory_status === 'negative' ? '核销成功，库存不足' : '核销成功，库存待补记',
                content: result?.inventory_message || '会员卡次数已扣减，请及时处理耗材库存。',
                showCancel: false,
            })
        } else {
            uni.showToast({ title: result?.message || '核销成功', icon: 'success' })
        }
        await search()
    } finally {
        redeeming.value = false
    }
}
onLoad(async (options: any) => {
    const value = String(options?.mobile || '').trim()
    if (/^1\d{10}$/.test(value)) {
        mobile.value = value
        await search()
    }
})
</script>
