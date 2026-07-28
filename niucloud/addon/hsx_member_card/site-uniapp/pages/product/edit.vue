<template>
    <view class="min-h-screen bg-[#f0f3f9] px-[24rpx] pt-[24rpx] pb-[180rpx] text-[#334155]">
        <!-- 页面标题 -->
        <view class="mb-[20rpx] flex items-center gap-[16rpx] rounded-[28rpx] bg-white p-[24rpx] shadow-[0_8rpx_30rpx_rgba(15,23,42,0.04)]">
            <view class="flex h-[64rpx] w-[64rpx] items-center justify-center rounded-[18rpx] bg-[#eff6ff]">
                <u-icon name="order" color="#2563eb" size="24" />
            </view>
            <view class="flex min-w-0 flex-1 flex-col gap-[4rpx]">
                <text class="text-[31rpx] font-bold text-[#1e293b]">{{ id ? '编辑服务卡' : '新增服务卡' }}</text>
                <text class="text-[23rpx] text-[#64748b]">设置售价、权益次数和有效期</text>
            </view>
        </view>

        <!-- 基础信息卡片 -->
        <view class="mb-[18rpx] overflow-hidden rounded-[28rpx] bg-white shadow-[0_8rpx_30rpx_rgba(15,23,42,0.04)]">
            <view class="px-[24rpx] pb-[4rpx] pt-[24rpx]">
                <text class="text-[29rpx] font-bold text-[#1e293b]">基础信息</text>
            </view>
            <view class="min-h-[100rpx] flex items-center justify-between gap-[24rpx] border-t border-[#f1f5f9] px-[24rpx]">
                <text class="required flex-none text-[26rpx] font-medium text-[#475569]">卡种名称</text>
                <u-input v-model="form.product_name" border="none" inputAlign="right" placeholder="例如：9.9 元贴膜 10 次卡" class="flex-1 text-[26rpx]" />
            </view>
            <view class="min-h-[100rpx] flex items-center justify-between gap-[24rpx] border-t border-[#f1f5f9] px-[24rpx]">
                <text class="required flex-none text-[26rpx] font-medium text-[#475569]">销售价</text>
                <u-input v-model="form.sale_price" type="digit" border="none" inputAlign="right" placeholder="0.00" class="flex-1 text-[26rpx]">
                    <template #suffix><text class="text-[25rpx] text-[#475569]">元</text></template>
                </u-input>
            </view>
            <view class="min-h-[100rpx] flex items-center justify-between gap-[24rpx] border-t border-[#f1f5f9] px-[24rpx]">
                <text class="flex-none text-[26rpx] font-medium text-[#475569]">划线价</text>
                <u-input v-model="form.market_price" type="digit" border="none" inputAlign="right" placeholder="选填" class="flex-1 text-[26rpx]">
                    <template #suffix><text class="text-[25rpx] text-[#475569]">元</text></template>
                </u-input>
            </view>
        </view>

        <!-- 服务权益卡片 -->
        <view class="mb-[18rpx] overflow-hidden rounded-[28rpx] bg-white shadow-[0_8rpx_30rpx_rgba(15,23,42,0.04)]">
            <view class="px-[24rpx] pb-[4rpx] pt-[24rpx]">
                <text class="text-[29rpx] font-bold text-[#1e293b]">服务权益</text>
            </view>
            <view class="min-h-[100rpx] flex items-center justify-between gap-[24rpx] border-t border-[#f1f5f9] px-[24rpx]">
                <text class="required flex-none text-[26rpx] font-medium text-[#475569]">权益名称</text>
                <u-input v-model="form.item.item_name" border="none" inputAlign="right" placeholder="例如：贴膜服务" class="flex-1 text-[26rpx]" />
            </view>
            <view class="border-t border-[#f1f5f9] px-[24rpx] py-[22rpx]">
                <text class="mb-[16rpx] block text-[26rpx] font-medium text-[#475569]">次数模式</text>
                <view class="flex flex-wrap gap-[12rpx]">
                    <view
                        v-for="option in usageOptions"
                        :key="option.value"
                        class="rounded-[14rpx] border px-[22rpx] py-[14rpx] text-[24rpx] font-medium transition-all"
                        :class="form.item.usage_mode === option.value ? 'border-[#93c5fd] bg-[#eff6ff] text-[#2563eb]' : 'border-[#e8ecf1] bg-[#f8fafc] text-[#475569]'"
                        @click="form.item.usage_mode = option.value"
                    >
                        {{ option.label }}
                    </view>
                </view>
            </view>
            <view v-if="form.item.usage_mode === 'limited'" class="min-h-[100rpx] flex items-center justify-between gap-[24rpx] border-t border-[#f1f5f9] px-[24rpx]">
                <text class="required text-[26rpx] font-medium text-[#475569]">可用次数</text>
                <u-number-box v-model="form.item.total_times" :min="1" :max="9999" />
            </view>
            <view class="min-h-[100rpx] flex items-center justify-between gap-[24rpx] border-t border-[#f1f5f9] px-[24rpx]">
                <text class="text-[26rpx] font-medium text-[#475569]">每日上限</text>
                <view class="flex items-center gap-[12rpx]">
                    <u-number-box v-model="form.item.daily_limit" :min="0" :max="99" />
                    <text class="text-[21rpx] text-[#94a3b8]">0 表示不限</text>
                </view>
            </view>
        </view>

        <!-- 生效与有效期卡片 -->
        <view class="mb-[18rpx] overflow-hidden rounded-[28rpx] bg-white shadow-[0_8rpx_30rpx_rgba(15,23,42,0.04)]">
            <view class="px-[24rpx] pb-[4rpx] pt-[24rpx]">
                <text class="text-[29rpx] font-bold text-[#1e293b]">生效与有效期</text>
            </view>
            <view class="border-t border-[#f1f5f9] px-[24rpx] py-[22rpx]">
                <text class="mb-[16rpx] block text-[26rpx] font-medium text-[#475569]">生效方式</text>
                <view class="flex flex-wrap gap-[12rpx]">
                    <view
                        v-for="option in effectiveOptions"
                        :key="option.value"
                        class="rounded-[14rpx] border px-[22rpx] py-[14rpx] text-[24rpx] font-medium transition-all"
                        :class="form.effective_mode === option.value ? 'border-[#93c5fd] bg-[#eff6ff] text-[#2563eb]' : 'border-[#e8ecf1] bg-[#f8fafc] text-[#475569]'"
                        @click="form.effective_mode = option.value"
                    >
                        {{ option.label }}
                    </view>
                </view>
            </view>
            <view class="border-t border-[#f1f5f9] px-[24rpx] py-[22rpx]">
                <text class="mb-[16rpx] block text-[26rpx] font-medium text-[#475569]">有效期</text>
                <view class="flex flex-wrap gap-[12rpx]">
                    <view
                        v-for="option in validityOptions"
                        :key="option.value"
                        class="rounded-[14rpx] border px-[22rpx] py-[14rpx] text-[24rpx] font-medium transition-all"
                        :class="form.validity_mode === option.value ? 'border-[#93c5fd] bg-[#eff6ff] text-[#2563eb]' : 'border-[#e8ecf1] bg-[#f8fafc] text-[#475569]'"
                        @click="form.validity_mode = option.value"
                    >
                        {{ option.label }}
                    </view>
                </view>
            </view>
            <view v-if="form.validity_mode === 'duration'" class="min-h-[100rpx] flex items-center justify-between gap-[24rpx] border-t border-[#f1f5f9] px-[24rpx]">
                <text class="required text-[26rpx] font-medium text-[#475569]">固定时长</text>
                <view class="flex items-center gap-[12rpx]">
                    <u-number-box v-model="form.duration_value" :min="1" :max="3650" />
                    <view
                        class="min-w-[80rpx] rounded-[12rpx] bg-[#eff6ff] px-[16rpx] py-[12rpx] text-center text-[24rpx] font-medium text-[#2563eb]"
                        @click="form.duration_unit = form.duration_unit === 'day' ? 'month' : 'day'"
                    >
                        {{ form.duration_unit === 'day' ? '天' : '个月' }}
                    </view>
                </view>
            </view>
        </view>

        <!-- 使用说明卡片 -->
        <view class="mb-[18rpx] overflow-hidden rounded-[28rpx] bg-white shadow-[0_8rpx_30rpx_rgba(15,23,42,0.04)]">
            <view class="px-[24rpx] pb-[16rpx] pt-[24rpx]">
                <text class="text-[29rpx] font-bold text-[#1e293b]">使用说明</text>
            </view>
            <view class="px-[24rpx] pb-[24rpx]">
                <u-textarea
                    v-model="form.usage_notice"
                    height="130rpx"
                    maxlength="1000"
                    placeholder="填写适用范围、注意事项等（选填）"
                    class="!rounded-[16rpx] !bg-[#f8fafc]"
                />
            </view>
        </view>

        <!-- 底部保存按钮 毛玻璃统一风格 -->
        <view
            class="fixed bottom-0 left-0 right-0 z-20 border-t border-[#e8ecf1] bg-white/80 px-[24rpx] pt-[16rpx] backdrop-blur-[20rpx]"
            :style="{ paddingBottom: 'calc(16rpx + env(safe-area-inset-bottom))' }"
        >
            <MemberCardButton
                type="primary"
                icon="checkmark"
                :text="id ? '保存修改' : '保存并启用'"
                :loading="submitting"
                @click="submit"
                class="!h-[88rpx] !rounded-[20rpx] !text-[29rpx] !font-bold shadow-[0_8rpx_20rpx_rgba(37,99,235,0.25)]"
            />
        </view>
    </view>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { enableCardProduct, getCardProduct, saveCardProduct } from '../../api'
import MemberCardButton from '../../components/MemberCardButton.vue'

const id = ref(0)
const submitting = ref(false)
const usageOptions = [{ label: '有限次数', value: 'limited' }, { label: '不限次数', value: 'unlimited' }]
const effectiveOptions = [{ label: '开卡即生效', value: 'immediate' }, { label: '首次核销生效', value: 'first_use' }]
const validityOptions = [{ label: '永久有效', value: 'permanent' }, { label: '固定时长', value: 'duration' }]
const form = reactive<any>({
    product_name: '', sale_price: '', market_price: '', effective_mode: 'immediate', validity_mode: 'permanent',
    duration_value: 30, duration_unit: 'day', usage_notice: '', sort: 0,
    item: { item_code: 'film_service', item_name: '贴膜服务', usage_mode: 'limited', total_times: 10, daily_limit: 0, recognition_mode: 'average', recognition_amount: 0 },
})
const submit = async () => {
    if (!form.product_name.trim()) return uni.showToast({ title: '请填写卡种名称', icon: 'none' })
    if (form.sale_price === '' || Number(form.sale_price) < 0) return uni.showToast({ title: '请填写正确销售价', icon: 'none' })
    if (!form.item.item_name.trim()) return uni.showToast({ title: '请填写权益名称', icon: 'none' })
    if (form.item.usage_mode === 'limited' && Number(form.item.total_times) <= 0) return uni.showToast({ title: '可用次数必须大于 0', icon: 'none' })
    submitting.value = true
    try {
        const result: any = await saveCardProduct(form, id.value)
        const savedId = Number(result?.data?.id || id.value)
        if (!id.value && savedId) await enableCardProduct(savedId)
        uni.showToast({ title: id.value ? '保存成功' : '卡种已创建并启用', icon: 'success' })
        setTimeout(() => uni.navigateBack(), 650)
    } finally { submitting.value = false }
}
onLoad(async (options: any) => {
    id.value = Number(options?.id || 0)
    if (!id.value) return
    const result: any = await getCardProduct(id.value)
    const data = result?.data || {}
    Object.assign(form, data, { item: { ...form.item, ...(data.item || {}) } })
})
</script>

<style scoped lang="scss">
.required::after { margin-left: 5rpx; color: #dc2626; content: '*'; }.suffix { color: #475569; font-size: 24rpx; }
:deep(.u-textarea) { margin: 8rpx 0 24rpx; background: #f8fafc; }
</style>
