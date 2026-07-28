<template>
    <view class="min-h-screen box-border bg-[#f5f6f8] px-[24rpx] pb-[190rpx] pt-[20rpx] text-[#1f2937]">
        <view class="mb-[18rpx] flex items-center rounded-[20rpx] bg-[#edf4ff] px-[20rpx] py-[16rpx]">
            <view class="flex flex-1 items-center gap-[8rpx] text-[22rpx] font-medium">
                <view class="flex h-[34rpx] w-[34rpx] items-center justify-center rounded-full bg-[#2468f2] text-white">1</view>
                <text :class="member.member_id ? 'text-[#2468f2]' : 'text-[#344054]'">客户</text>
                <view class="mx-[6rpx] h-[2rpx] flex-1 bg-[#d6e4ff]"></view>
                <view class="flex h-[34rpx] w-[34rpx] items-center justify-center rounded-full" :class="form.product_id ? 'bg-[#2468f2] text-white' : 'bg-white text-[#7b8798]'">2</view>
                <text :class="form.product_id ? 'text-[#2468f2]' : 'text-[#7b8798]'">卡种</text>
                <view class="mx-[6rpx] h-[2rpx] flex-1 bg-[#d6e4ff]"></view>
                <view class="flex h-[34rpx] w-[34rpx] items-center justify-center rounded-full bg-white text-[#7b8798]">3</view>
                <text class="text-[#7b8798]">收款</text>
            </view>
        </view>

        <view
            class="mb-[16rpx] flex min-h-[108rpx] box-border items-center gap-[16rpx] rounded-[22rpx] bg-white px-[22rpx] py-[20rpx]"
            hover-class="bg-[#f8faff]"
            @click="openMemberPicker"
        >
            <view
                class="flex h-[60rpx] w-[60rpx] flex-none items-center justify-center rounded-[17rpx]"
                :class="member.member_id ? 'bg-[#2468f2]' : 'bg-[#edf4ff]'"
            >
                <u-icon
                    :name="member.member_id ? 'account-fill' : 'account'"
                    :color="member.member_id ? '#ffffff' : '#2468f2'"
                    size="21"
                />
            </view>
            <view class="flex min-w-0 flex-1 flex-col gap-[4rpx]">
                <text class="truncate text-[28rpx] font-semibold text-[#27364b]">
                    {{ member.member_id ? member.display_name : '选择购卡客户' }}
                </text>
                <text class="mt-[4rpx] truncate text-[22rpx] text-[#8a96a8]">
                    {{ member.member_id ? member.mobile_masked : '姓名 / 手机号 / 会员号' }}
                </text>
            </view>
            <text v-if="member.member_id" class="flex-none text-[22rpx] font-medium text-[#2468f2]">更换</text>
            <u-icon name="arrow-right" color="#a3acba" size="16" />
        </view>

        <view class="mb-[16rpx] overflow-hidden rounded-[22rpx] bg-white">
            <view class="px-[20rpx] pb-[20rpx] pt-[22rpx]">
                <view class="mb-[16rpx] flex items-center justify-between">
                    <text class="text-[28rpx] font-semibold text-[#27364b]">服务卡种</text>
                    <text class="text-[21rpx] text-[#98a2b3]">{{ products.length }} 个可选</text>
                </view>

                <!-- 搜索框 -->
                <view v-if="products.length > 6" class="mb-[12rpx] flex h-[68rpx] box-border items-center gap-[10rpx] rounded-[14rpx] bg-[#f5f7fa] px-[16rpx]">
                    <u-icon name="search" color="#94a3b8" size="17" />
                    <input
                        v-model="productKeyword"
                        class="h-[70rpx] min-w-0 flex-1 text-[25rpx] text-[#475569]"
                        type="text"
                        placeholder="搜索卡种名称"
                        placeholder-class="text-[#94a3b8]"
                    />
                    <u-icon v-if="productKeyword" name="close-circle-fill" color="#cbd5e1" size="17" @click="productKeyword = ''" />
                </view>

                <!-- 产品列表 -->
                <view class="overflow-hidden rounded-[16rpx] border border-[#e9edf3] bg-white">
                    <view
                        v-for="product in displayProducts"
                        :key="product.id"
                        class="product relative flex min-h-[88rpx] box-border items-center gap-[13rpx] border-b border-[#eef1f5] px-[16rpx] py-[15rpx]"
                        :class="{ 'bg-[#f3f7ff]': form.product_id === product.id }"
                        hover-class="bg-[#f7f9fc]"
                        @click="selectProduct(product)"
                    >
                        <view class="flex flex-none">
                            <u-icon
                                :name="form.product_id === product.id ? 'checkmark-circle-fill' : 'checkmark-circle'"
                                :color="form.product_id === product.id ? '#2468f2' : '#c7d0dc'"
                                size="21"
                            />
                        </view>
                        <view class="flex min-w-0 flex-1 flex-col gap-[4rpx]">
                            <text class="truncate text-[26rpx] font-medium leading-[1.35] text-[#344054]">
                                {{ product.product_name }}
                            </text>
                            <text class="text-[21rpx] text-[#8a96a8]">
                                {{ product.item?.usage_mode === 'unlimited' ? '不限次' : `${product.item?.total_times || 0} 次` }} · {{ product.validity_text }}
                            </text>
                        </view>
                        <text class="w-[138rpx] flex-none text-right text-[27rpx] font-semibold text-[#e65f18]">
                            ¥{{ money(product.sale_price) }}
                        </text>
                    </view>

                    <view v-if="products.length && !visibleProducts.length" class="flex min-h-[120rpx] flex-col items-center justify-center gap-[12rpx] text-[23rpx] text-[#94a3b8]">
                        <text>没有找到“{{ productKeyword }}”</text>
                        <view class="text-[#2563eb] font-medium" @click="productKeyword = ''">清除搜索</view>
                    </view>

                    <view v-if="!products.length" class="flex min-h-[100rpx] box-border items-center justify-between gap-[14rpx] rounded-[14rpx] border border-dashed border-[#cbd5e1] bg-[#fafcff] px-[18rpx] py-[18rpx]">
                        <view class="flex min-w-0 items-center gap-[12rpx]">
                            <u-icon name="order" color="#94a3b8" size="22" />
                            <view class="flex min-w-0 flex-col gap-[4rpx]">
                                <text class="text-[26rpx] text-[#475569]">暂无已启用卡种</text>
                                <text class="text-[22rpx] text-[#94a3b8]">创建并启用卡种后即可开卡</text>
                            </view>
                        </view>
                        <view class="flex-none text-[24rpx] font-medium text-[#2563eb]" @click="goProductSettings">去设置</view>
                    </view>
                </view>

                <!-- 展开/收起 -->
                <view
                    v-if="!productKeyword && visibleProducts.length > productPreviewCount"
                    class="mt-[12rpx] flex min-h-[62rpx] items-center justify-center gap-[8rpx] rounded-[14rpx] bg-[#f5f7fa] text-[23rpx] font-medium text-[#2468f2]"
                    @click="productExpanded = !productExpanded"
                >
                    <text>{{ productExpanded ? '收起卡种' : `查看其余 ${visibleProducts.length - productPreviewCount} 个卡种` }}</text>
                    <u-icon :name="productExpanded ? 'arrow-up' : 'arrow-down'" color="#2563eb" size="15" />
                </view>
            </view>
        </view>

        <view class="mb-[16rpx] overflow-hidden rounded-[22rpx] bg-white">
            <view class="px-[20rpx] pb-[20rpx] pt-[22rpx]">
                <view class="mb-[16rpx] flex items-center justify-between">
                    <text class="text-[28rpx] font-semibold text-[#27364b]">收款方式</text>
                    <text class="text-[21rpx] text-[#98a2b3]">选择本次结算方式</text>
                </view>
                <view class="grid grid-cols-2 gap-[12rpx] rounded-[16rpx] bg-[#f4f6f8] p-[6rpx]">
                    <view
                        class="flex min-h-[72rpx] box-border items-center justify-center gap-[8rpx] rounded-[12rpx] px-[12rpx]"
                        :class="form.settlement_mode === 'immediate' ? 'bg-white font-semibold text-[#2468f2]' : 'text-[#667085]'"
                        @click="form.settlement_mode = 'immediate'"
                    >
                        <u-icon name="rmb-circle" :color="form.settlement_mode === 'immediate' ? '#2468f2' : '#98a2b3'" size="18" />
                        <text class="text-[25rpx]">现场收款</text>
                    </view>
                    <view
                        v-if="config.allow_receivable === 1"
                        class="flex min-h-[72rpx] box-border items-center justify-center gap-[8rpx] rounded-[12rpx] px-[12rpx]"
                        :class="form.settlement_mode === 'receivable' ? 'bg-white font-semibold text-[#2468f2]' : 'text-[#667085]'"
                        @click="form.settlement_mode = 'receivable'"
                    >
                        <u-icon name="clock" :color="form.settlement_mode === 'receivable' ? '#2468f2' : '#98a2b3'" size="18" />
                        <text class="text-[25rpx]">记入应收</text>
                    </view>
                </view>

                <view
                    v-if="form.settlement_mode === 'immediate'"
                    class="mt-[14rpx] flex min-h-[78rpx] box-border items-center justify-between gap-[14rpx] border-b border-[#eef1f5] px-[4rpx] py-[14rpx]"
                    @click="accountVisible = true"
                >
                    <view class="flex flex-col gap-[4rpx]">
                        <text class="text-[25rpx] font-medium text-[#344054]">到账账户</text>
                        <text class="mt-[3rpx] text-[22rpx] text-[#8a96a8]">{{ accountName || '请选择实际到账账户' }}</text>
                    </view>
                    <u-icon name="arrow-right" color="#94a3b8" size="17" />
                </view>

                <view v-if="form.settlement_mode === 'immediate'" class="mt-[4rpx] flex min-h-[68rpx] items-center justify-between px-[4rpx]" @click="voucherExpanded = !voucherExpanded">
                    <view class="flex items-center gap-[10rpx]"><u-icon name="camera" color="#7b8798" size="17" /><text class="text-[24rpx] text-[#4b586c]">收款凭证</text><text class="text-[21rpx] text-[#a3acba]">选填</text></view>
                    <u-icon :name="voucherExpanded ? 'arrow-up' : 'arrow-down'" color="#a3acba" size="14" />
                </view>
                <MemberCardVoucherUploader v-if="form.settlement_mode === 'immediate' && voucherExpanded" v-model="voucherUrls" title="上传收款凭证" hint="最多上传 3 张" class="mt-[6rpx]" />
            </view>
        </view>

        <view class="mb-[16rpx] overflow-hidden rounded-[22rpx] bg-white px-[20rpx]">
            <view class="flex min-h-[78rpx] items-center justify-between" @click="remarkExpanded = !remarkExpanded">
                <view class="flex items-center gap-[10rpx]"><u-icon name="edit-pen" color="#7b8798" size="17" /><text class="text-[24rpx] text-[#4b586c]">业务备注</text><text class="text-[21rpx] text-[#a3acba]">选填</text></view>
                <u-icon :name="remarkExpanded ? 'arrow-up' : 'arrow-down'" color="#a3acba" size="14" />
            </view>
            <view v-if="remarkExpanded" class="pb-[20rpx]"><u-textarea v-model="form.remark" height="100rpx" maxlength="255" placeholder="活动来源或特殊约定" class="!rounded-[14rpx] !bg-[#f5f7fa]" /></view>
        </view>

        <view
            class="fixed bottom-0 left-0 right-0 z-20 flex items-center gap-[18rpx] border-t border-[#e8ecf1] bg-white px-[24rpx] pt-[14rpx]"
            :style="{ paddingBottom: 'calc(16rpx + env(safe-area-inset-bottom))' }"
        >
            <view class="min-w-0 flex-1"><text class="block text-[20rpx] text-[#98a2b3]">本次应收</text><text class="mt-[2rpx] block text-[32rpx] font-bold text-[#e65f18]">¥{{ money(selectedProduct?.sale_price) }}</text></view>
            <view class="w-[390rpx] flex-none"><MemberCardButton type="primary" icon="checkmark" text="确认开卡" :loading="submitting" @click="submit" class="!h-[84rpx] !rounded-[18rpx] !text-[28rpx] !font-semibold" /></view>
        </view>

        <!-- 选择会员弹窗（逻辑保持原样） -->
        <MemberCardMemberPopup v-if="memberVisible" :show="memberVisible" @update:show="onMemberPopupVisible" @select="onMemberSelected" />

        <!-- 选择账户弹窗（样式优化） -->
        <u-popup :show="accountVisible" mode="bottom" :safe-area-inset-bottom="true" round="24" @close="accountVisible = false">
            <view class="bg-white px-[28rpx] pt-[32rpx]" :style="{ paddingBottom: 'calc(32rpx + env(safe-area-inset-bottom))' }">
                <view class="flex items-start justify-between gap-[20rpx] pb-[24rpx]">
                    <view class="flex flex-col">
                        <text class="text-[34rpx] font-bold text-[#1e293b]">选择到账账户</text>
                        <text class="mt-[6rpx] text-[23rpx] text-[#64748b]">本次收款将记录到所选资金账户</text>
                    </view>
                    <view class="h-[56rpx] w-[56rpx] flex items-center justify-center rounded-full bg-[#f1f5f9]" @click="accountVisible = false">
                        <u-icon name="close" color="#64748b" size="20" />
                    </view>
                </view>
                <view
                    v-for="account in config.capital_account_options || []"
                    :key="account.id"
                    class="flex min-h-[96rpx] items-center gap-[16rpx] rounded-[16rpx] border-t border-[#f1f5f9] px-[18rpx]"
                    :class="{ 'bg-[#f0f7ff]': form.capital_account_id === account.id }"
                    @click="selectAccount(account)"
                >
                    <view class="flex h-[56rpx] w-[56rpx] items-center justify-center rounded-[16rpx] bg-[#eff6ff]">
                        <u-icon name="account" color="#2563eb" size="19" />
                    </view>
                    <text class="min-w-0 flex-1 text-[27rpx] font-medium text-[#334155]">{{ account.name }}</text>
                    <u-icon v-if="form.capital_account_id === account.id" name="checkmark-circle-fill" color="#2563eb" size="21" />
                </view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { createCardOrder, getCardMember, getCardProductOptions, getMemberCardConfig, memberCardRequestId } from '../../api'
import MemberCardButton from '../../components/MemberCardButton.vue'
import MemberCardMemberPopup from '../../components/MemberCardMemberPopup.vue'
import MemberCardVoucherUploader from '../../components/MemberCardVoucherUploader.vue'

const memberVisible = ref(false)
const accountVisible = ref(false)
const submitting = ref(false)
const products = ref<any[]>([])
const productKeyword = ref('')
const productExpanded = ref(false)
const voucherExpanded = ref(false)
const remarkExpanded = ref(false)
const productPreviewCount = 5
const config = ref<any>({ allow_receivable: 1, capital_account_options: [] })
const member = ref<any>({})
const voucherUrls = ref('')
const form = reactive<any>({ product_id: 0, settlement_mode: 'immediate', capital_account_id: 0, remark: '' })
const money = (value: any) => Number(value || 0).toFixed(2)
const accountName = computed(() => config.value.capital_account_options?.find((item: any) => Number(item.id) === Number(form.capital_account_id))?.name || '')
const selectedProduct = computed(() => products.value.find((item: any) => Number(item.id) === Number(form.product_id)))
const visibleProducts = computed(() => {
    const keyword = productKeyword.value.trim().toLowerCase()
    if (!keyword) return products.value
    return products.value.filter((item: any) => String(item.product_name || '').toLowerCase().includes(keyword))
})
const displayProducts = computed(() => {
    if (productKeyword.value.trim() || productExpanded.value) return visibleProducts.value
    return visibleProducts.value.slice(0, productPreviewCount)
})
const openMemberPicker = () => { memberVisible.value = true }
const onMemberPopupVisible = (visible: boolean) => { memberVisible.value = Boolean(visible) }
const onMemberSelected = (value: any) => {
    member.value = value || {}
    memberVisible.value = false
}
const selectProduct = (product: any) => {
    form.product_id = Number(product.id)
}
const selectAccount = (account: any) => {
    form.capital_account_id = Number(account.id)
    accountVisible.value = false
}
const goProductSettings = () => uni.navigateTo({ url: '/addon/hsx_member_card/pages/product/list' })
const submit = async () => {
    if (!member.value.member_id) return uni.showToast({ title: '请选择购卡客户', icon: 'none' })
    if (!form.product_id) return uni.showToast({ title: '请选择卡种', icon: 'none' })
    if (form.settlement_mode === 'immediate' && !form.capital_account_id) return uni.showToast({ title: '请选择到账账户', icon: 'none' })
    submitting.value = true
    try {
        const result: any = (await createCardOrder({
            ...form,
            member_id: member.value.member_id,
            request_id: memberCardRequestId('issue'),
            voucher_urls: voucherUrls.value ? voucherUrls.value.split(',').filter(Boolean) : [],
        }))?.data
        uni.showToast({ title: result.success ? '开卡成功' : '开卡单已保存', icon: result.success ? 'success' : 'none' })
        setTimeout(() => uni.redirectTo({ url: '/addon/hsx_member_card/pages/order/list' }), 700)
    } finally {
        submitting.value = false
    }
}

onLoad(async (options: any) => {
    const [productResult, configResult]: any = await Promise.all([getCardProductOptions(), getMemberCardConfig()])
    products.value = productResult?.data || []
    config.value = configResult?.data || config.value
    form.capital_account_id = Number(config.value.default_capital_account_id || 0)
    const memberId = Number(options?.member_id || 0)
    if (memberId > 0) {
        const memberResult: any = await getCardMember(memberId)
        member.value = memberResult?.data?.member || {}
    }
})
</script>

<style scoped lang="scss">
.product:last-child { border-bottom: 0; }
.product.active { background: #f2f7ff; }
.product.active::before { position: absolute; top: 15rpx; bottom: 15rpx; left: 0; width: 5rpx; border-radius: 0 5rpx 5rpx 0; background: #2563eb; content: ''; }
.customer-icon--selected { background: linear-gradient(145deg, #2563eb, #4f46e5); }
.remark-section :deep(.u-textarea) { background: #f8fafc !important; }
</style>
