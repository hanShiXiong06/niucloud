<template>
    <view :class="getSmsCodeClass()" @click="handleSend">{{ sendSms.tips.value }}</view>
    <u-code :seconds="sendSms.seconds" :change-text="sendSms.changeText" ref="smsRef" @change="sendSms.codeChange"></u-code>
</template>

<script lang="ts" setup>
import { ref, reactive, computed } from 'vue'
import { useSendSms } from '@/hooks/useSendSms'
import { t } from '@/locale'

const prop = defineProps({
    mobile: String,
    type: String,
    isAgree: {
        type: Boolean,
        default: true
    },
    modelValue: {
        type: String,
        default: ''
    }
})

const emit = defineEmits(['update:modelValue','codeSend'])
const value = computed({
    get() {
        return prop.modelValue
    },
    set(value) {
        emit('update:modelValue', value)
    }
})

const smsRef: any = ref(null)
const sendSms = useSendSms(smsRef)

const formData: any = reactive({
    mobile: '',
    captcha_code: '',
    captcha_type: '',
    type: prop.type
})

const getSmsCodeClass = () => {
    return sendSms.canGetCode.value ? 'text-[26rpx] text-primary' : 'text-[26rpx] text-gray-300'
}

const handleSend = async() => {
    if (smsRef.value.canGetCode) {
        formData.mobile = prop.mobile
        if (!prop.isAgree) {
            uni.showToast({ title: t('isAgreeTips'), icon: 'none' });
            return
        }
        if (uni.$u.test.isEmpty(formData.mobile)) {
            uni.showToast({ title: t('mobilePlaceholder'), icon: 'none' });
            return
        }
        if (!uni.$u.test.mobile(formData.mobile)) {
            uni.showToast({ title: t('mobileError'), icon: 'none' });
            return
        }
        emit('codeSend')
    }
}
const handleConfirm = async(data: any, callback: any = null ) => {
    formData.captcha_code = data.captcha_code
    formData.captcha_type = data.captcha_type
    const sendRes = await sendSms.send(formData)

    if (sendRes) {
        value.value = sendRes
    } 
    if (callback) callback();
}

defineExpose({
    handleConfirm
})
</script>

<style>
</style>
