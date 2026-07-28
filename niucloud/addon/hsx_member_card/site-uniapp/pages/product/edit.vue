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

        <view class="mb-[18rpx] rounded-[24rpx] border border-[#dbeafe] bg-[#f8fbff] p-[20rpx]">
            <view class="flex items-start gap-[12rpx]">
                <u-icon name="gift" color="#2563eb" size="21" />
                <view class="min-w-0 flex-1">
                    <text class="block text-[27rpx] font-semibold text-[#334155]">终身贴膜快捷方案</text>
                    <text class="mt-[4rpx] block text-[21rpx] leading-[1.5] text-[#718096]">永久有效、不限总次数、每日限 1 次；应用后仍可继续调整。</text>
                </view>
            </view>
            <view class="mt-[16rpx] grid grid-cols-3 gap-[10rpx]">
                <view v-for="option in bindingOptions" :key="option.value" class="rounded-[13rpx] bg-white px-[8rpx] py-[13rpx] text-center text-[22rpx] font-medium text-[#2563eb]" @click="applyLifetimePreset(option.value)">
                    {{ option.value === 'member' ? '按会员' : option.value === 'imei' ? '一机一卡' : '限定型号' }}
                </view>
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
            <view class="border-t border-[#f1f5f9] px-[24rpx] py-[22rpx]">
                <text class="mb-[8rpx] block text-[26rpx] font-medium text-[#475569]">适用对象</text>
                <text class="mb-[16rpx] block text-[21rpx] leading-[1.5] text-[#94a3b8]">{{ bindingHelp }}</text>
                <view class="grid grid-cols-3 gap-[10rpx]">
                    <view
                        v-for="option in bindingOptions"
                        :key="option.value"
                        class="flex min-h-[82rpx] items-center justify-center rounded-[14rpx] border px-[8rpx] text-center text-[23rpx] font-medium"
                        :class="form.item.binding_mode === option.value ? 'border-[#93c5fd] bg-[#eff6ff] text-[#2563eb]' : 'border-[#e8ecf1] bg-[#f8fafc] text-[#64748b]'"
                        @click="form.item.binding_mode = option.value"
                    >
                        {{ option.label }}
                    </view>
                </view>
            </view>
        </view>

        <view class="mb-[18rpx] overflow-hidden rounded-[28rpx] bg-white shadow-[0_8rpx_30rpx_rgba(15,23,42,0.04)]">
            <view class="px-[24rpx] pb-[4rpx] pt-[24rpx]">
                <text class="text-[29rpx] font-bold text-[#1e293b]">核销耗材</text>
                <text class="mt-[4rpx] block text-[21rpx] text-[#94a3b8]">次数和实际用料分开，贴坏重贴按实际数量扣库存</text>
            </view>
            <view class="min-h-[100rpx] flex items-center justify-between gap-[24rpx] border-t border-[#f1f5f9] px-[24rpx]">
                <text class="flex-none text-[26rpx] font-medium text-[#475569]">默认耗材</text>
                <u-input v-model="form.item.consumable_name" border="none" inputAlign="right" placeholder="留空则不关联库存" class="flex-1 text-[26rpx]" />
            </view>
            <view class="min-h-[100rpx] flex items-center justify-between gap-[24rpx] border-t border-[#f1f5f9] px-[24rpx]">
                <text class="text-[26rpx] font-medium text-[#475569]">标准用量</text>
                <view class="flex items-center gap-[12rpx]">
                    <u-number-box v-model="form.item.standard_consumable_qty" :min="0" :max="9999" />
                    <u-input v-model="form.item.consumable_unit" border="none" inputAlign="center" placeholder="张" class="w-[90rpx] text-[25rpx]" />
                </view>
            </view>
            <view v-if="inventoryAvailable && form.item.consumable_name" class="border-t border-[#f1f5f9] px-[24rpx] py-[20rpx]">
                <view class="mb-[14rpx] flex items-center justify-between">
                    <view>
                        <text class="block text-[26rpx] font-medium text-[#475569]">{{ id ? '期初 / 盘点库存' : '期初库存（选填）' }}</text>
                        <text class="mt-[3rpx] block text-[20rpx] text-[#94a3b8]">{{ id ? '写入设置中选择的默认耗材库位' : '保存时同步建立耗材档案与库存' }}</text>
                    </view>
                    <u-number-box v-model="stockTarget" :min="0" :max="999999" />
                </view>
                <MemberCardButton v-if="id" text="更新耗材库存" :loading="stockSaving" @click="adjustStock" class="!h-[72rpx] !rounded-[15rpx] !bg-[#f1f5f9] !text-[24rpx] !font-semibold !text-[#475569]" />
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
import { computed, reactive, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { adjustCardProductStock, enableCardProduct, getCardProduct, getMemberCardConfig, saveCardProduct } from '../../api'
import MemberCardButton from '../../components/MemberCardButton.vue'

const id = ref(0)
const submitting = ref(false)
const stockSaving = ref(false)
const stockTarget = ref(0)
const inventoryConfig = ref<any>({})
const inventoryAvailable = computed(() => Number(inventoryConfig.value.inventory_available || 0) === 1)
const usageOptions = [{ label: '有限次数', value: 'limited' }, { label: '不限次数', value: 'unlimited' }]
const bindingOptions = [{ label: '按会员本人', value: 'member' }, { label: '绑定 IMEI', value: 'imei' }, { label: '限定型号', value: 'model' }]
const effectiveOptions = [{ label: '开卡即生效', value: 'immediate' }, { label: '首次核销生效', value: 'first_use' }]
const validityOptions = [{ label: '永久有效', value: 'permanent' }, { label: '固定时长', value: 'duration' }]
const form = reactive<any>({
    product_name: '', sale_price: '', market_price: '', effective_mode: 'immediate', validity_mode: 'permanent',
    duration_value: 30, duration_unit: 'day', usage_notice: '', sort: 0,
    item: {
        item_code: 'film_service', item_name: '贴膜服务', binding_mode: 'member', usage_mode: 'limited',
        total_times: 10, daily_limit: 0, recognition_mode: 'average', recognition_amount: 0,
        consumable_code: '', consumable_name: '钢化膜', consumable_unit: '张', standard_consumable_qty: 1,
    },
})
const bindingHelp = computed(() => ({
    member: '仅核对购卡人姓名和手机号，可为本人任意设备服务。',
    imei: '开卡时绑定唯一设备，核销必须扫描或输入相同 IMEI。',
    model: '开卡时指定产品型号，相同型号设备均可使用。'
}[form.item.binding_mode] || ''))
const applyLifetimePreset = (mode: string) => {
    form.product_name = form.product_name || '终身贴膜服务卡'
    form.item.item_name = '终身贴膜服务'
    form.item.binding_mode = mode
    form.item.usage_mode = 'unlimited'
    form.item.daily_limit = 1
    form.effective_mode = 'immediate'
    form.validity_mode = 'permanent'
}
const submit = async () => {
    if (!form.product_name.trim()) return uni.showToast({ title: '请填写卡种名称', icon: 'none' })
    if (form.sale_price === '' || Number(form.sale_price) < 0) return uni.showToast({ title: '请填写正确销售价', icon: 'none' })
    if (!form.item.item_name.trim()) return uni.showToast({ title: '请填写权益名称', icon: 'none' })
    if (form.item.usage_mode === 'limited' && Number(form.item.total_times) <= 0) return uni.showToast({ title: '可用次数必须大于 0', icon: 'none' })
    submitting.value = true
    try {
        const wasNew = !id.value
        const result: any = await saveCardProduct(form, id.value)
        const savedId = Number(result?.data?.id || id.value)
        if (wasNew && savedId) {
            id.value = savedId
            if (inventoryAvailable.value && form.item.consumable_name) {
                await adjustCardProductStock(savedId, {
                    target_quantity: Number(stockTarget.value),
                    remark: '移动端新建卡种时录入期初库存'
                })
            }
            await enableCardProduct(savedId)
        }
        uni.showToast({ title: wasNew ? '卡种已创建并启用' : '保存成功', icon: 'success' })
        setTimeout(() => uni.navigateBack(), 650)
    } finally { submitting.value = false }
}
const adjustStock = async () => {
    stockSaving.value = true
    try {
        const result: any = await adjustCardProductStock(id.value, { target_quantity: Number(stockTarget.value), remark: '移动端卡种设置中初始化/盘点库存' })
        const data = result?.data || {}
        uni.showToast({ title: `库存已更新为 ${data.stock_after ?? stockTarget.value}`, icon: 'success' })
    } finally { stockSaving.value = false }
}
onLoad(async (options: any) => {
    id.value = Number(options?.id || 0)
    inventoryConfig.value = ((await getMemberCardConfig()) as any)?.data || {}
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
