<template>
    <view class="sidebar-margin mt-[var(--top-m)] overflow-hidden rounded-[24rpx] bg-white">
        <view class="px-[28rpx] py-[26rpx]">
            <view class="flex items-start">
                <view class="mr-[18rpx] flex h-[68rpx] w-[68rpx] shrink-0 items-center justify-center rounded-[20rpx] bg-[var(--primary-color-light)]">
                    <u-icon name="lock-fill" color="var(--primary-color)" size="24" />
                </view>
                <view class="min-w-0 flex-1">
                    <view class="flex items-center justify-between gap-[16rpx]">
                        <text class="text-[30rpx] font-600 text-[#172033]">{{ statusTitle }}</text>
                        <text class="shrink-0 rounded-full bg-[#fff4e5] px-[14rpx] py-[6rpx] text-[22rpx] text-[#d97706]">设备已锁定</text>
                    </view>
                    <view class="mt-[8rpx] text-[24rpx] leading-[36rpx] text-[#718096]">
                        {{ contact?.contact_tip || '请在锁单有效期内联系门店完成付款。' }}
                    </view>
                </view>
            </view>

            <view v-if="contact?.payment_qrcode" class="mt-[24rpx] rounded-[20rpx] bg-[#f7f9fc] p-[22rpx]">
                <view class="flex items-center justify-between">
                    <view>
                        <view class="text-[27rpx] font-600 text-[#26344d]">门店收款码</view>
                        <view class="mt-[6rpx] text-[22rpx] text-[#8b98ad]">长按识别或保存后转账</view>
                    </view>
                    <image
                        :src="img(contact.payment_qrcode)"
                        mode="aspectFill"
                        class="h-[160rpx] w-[160rpx] rounded-[16rpx] bg-white"
                        @click="previewQrcode"
                    />
                </view>
                <view class="mt-[16rpx] text-[23rpx] leading-[34rpx] text-[#718096]">
                    {{ contact?.payment_tip || '转账后请上传付款凭证，门店确认后进入交付流程。' }}
                </view>
            </view>

            <view class="mt-[22rpx] rounded-[20rpx] bg-[#f7f9fc] px-[22rpx] py-[18rpx]">
                <view class="flex items-center justify-between text-[25rpx]">
                    <text class="text-[#718096]">订单负责人</text>
                    <text class="font-600 text-[#172033]">{{ contact?.handler_name || '门店业务员' }}</text>
                </view>
                <view
                    v-if="contact?.handler_mobile"
                    class="mt-[16rpx] flex h-[60rpx] items-center justify-center rounded-full bg-white text-[25rpx] font-600 text-[var(--primary-color)]"
                    @click="callHandler"
                >
                    <u-icon name="phone-fill" color="var(--primary-color)" size="17" />
                    <text class="ml-[8rpx]">{{ contact.handler_mobile }}</text>
                </view>
            </view>

            <view v-if="isSubmitted" class="mt-[22rpx] rounded-[18rpx] border border-solid border-[#dbeafe] bg-[#eff6ff] px-[22rpx] py-[18rpx]">
                <view class="flex items-center text-[26rpx] font-600 text-[#2563eb]">
                    <u-icon name="checkmark-circle-fill" color="#2563eb" size="19" />
                    <text class="ml-[8rpx]">付款凭证已提交，等待门店审核</text>
                </view>
                <view v-if="voucherPreviews.length" class="mt-[16rpx] flex flex-wrap gap-[12rpx]">
                    <image
                        v-for="(item, index) in voucherPreviews"
                        :key="item"
                        :src="item"
                        mode="aspectFill"
                        class="h-[112rpx] w-[112rpx] rounded-[12rpx]"
                        @click="previewVoucher(index)"
                    />
                </view>
            </view>

            <template v-else-if="Number(contact?.can_submit_voucher) === 1">
                <view v-if="contact?.status === 'voucher_rejected'" class="mt-[20rpx] rounded-[16rpx] bg-[#fff1f2] px-[20rpx] py-[16rpx] text-[24rpx] leading-[34rpx] text-[#be123c]">
                    上次凭证未通过：{{ contact?.remark || '请核对付款信息后重新上传' }}
                </view>
                <view class="mt-[22rpx] flex items-center justify-between">
                    <view>
                        <view class="text-[27rpx] font-600 text-[#26344d]">上传付款凭证</view>
                        <view class="mt-[5rpx] text-[22rpx] text-[#8b98ad]">最多 6 张，提交后由门店负责人审核</view>
                    </view>
                    <text class="text-[22rpx] text-[#8b98ad]">{{ vouchers.length }}/6</text>
                </view>
                <view class="mt-[16rpx]">
                    <u-upload
                        :file-list="uploadPreview"
                        multiple
                        :max-count="6"
                        @afterRead="afterRead"
                        @delete="deleteVoucher"
                    />
                </view>
                <view class="mt-[18rpx]">
                    <u-button type="primary" shape="circle" :loading="submitting" text="提交付款凭证" @click="submit" />
                </view>
            </template>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { img } from '@/utils/common'
import { uploadImage } from '@/app/api/system'
import { submitOfflinePaymentVoucher } from '@/addon/phone_shop/api/order'

const props = defineProps<{ orderId: number | string; contact: Record<string, any> }>()
const emit = defineEmits<{ (event: 'submitted'): void }>()
const vouchers = ref<string[]>([])
const submitting = ref(false)

watch(() => props.contact?.voucher_urls, value => {
    vouchers.value = Array.isArray(value) ? [...value] : []
}, { immediate: true })

const isSubmitted = computed(() => props.contact?.status === 'voucher_submitted')
const statusTitle = computed(() => props.contact?.status === 'voucher_rejected' ? '请重新提交付款凭证' : '订单已提交，设备已为您锁定')
const uploadPreview = computed(() => vouchers.value.map(url => ({ url: img(url) })))
const voucherPreviews = computed(() => (props.contact?.voucher_urls || []).map((url: string) => img(url)))

const afterRead = async(event: any) => {
    const files = Array.isArray(event.file) ? event.file : [event.file]
    for (const file of files) {
        if (!file?.url || vouchers.value.length >= 6) continue
        const result: any = await uploadImage({ filePath: file.url, name: 'file' })
        if (result?.data?.url) vouchers.value.push(result.data.url)
    }
}

const deleteVoucher = (event: any) => vouchers.value.splice(Number(event.index), 1)
const previewQrcode = () => uni.previewImage({ current: img(props.contact.payment_qrcode), urls: [img(props.contact.payment_qrcode)] })
const previewVoucher = (index: number) => uni.previewImage({ current: voucherPreviews.value[index], urls: voucherPreviews.value })
const callHandler = () => {
    const phoneNumber = String(props.contact?.handler_mobile || '').trim()
    if (phoneNumber) uni.makePhoneCall({ phoneNumber })
}

const submit = async() => {
    if (!vouchers.value.length) return uni.showToast({ title: '请先上传付款凭证', icon: 'none' })
    submitting.value = true
    try {
        await submitOfflinePaymentVoucher(props.orderId, { voucher_urls: vouchers.value })
        uni.showToast({ title: '凭证已提交', icon: 'success' })
        emit('submitted')
    } finally {
        submitting.value = false
    }
}
</script>
