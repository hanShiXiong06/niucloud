<template>
    <view  @touchmove.prevent.stop>
        <u-popup :show="show" @close="show = false" mode="bottom" :round="10">
            <view @touchmove.prevent.stop class="popup-common flex flex-col">
                <view class="title">卖家备注</view>
                <view class="px-[var(--popup-sidebar-m)] py-[var(--pad-top-m)]" style="height: 180px;">
                    <up-textarea class="message-input" v-model.trim="formData.shop_remark" placeholder="请输入备注信息..."
                        :maxlength="200" :height="180"></up-textarea>
                </view>

                <view class="btn-wrap mt-[40rpx]">
                    <button class="primary-btn-bg btn" @click="submit">提交</button>
                </view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { setShopRemark } from '@/addon/mall/api/order'

const emits = defineEmits(['confirm'])

const show = ref(false)
const loading = ref(true)
/**
 * 表单数据
 */
const formData = reactive<any>({
    order_id: 0,
    shop_remark: ''
})


// 监听消息长度变化
watch(formData.shop_remark, (newVal) => {
	if (newVal && newVal.length > 200) {
		formData.shop_remark = newVal.substring(0, 200)
	}
})

const open = (data: any) => {
    if (data) {
        formData.order_id = data.order_id
        formData.shop_remark = data.shop_remark
    }
	show.value = true
    loading.value = false
}

const submit = () => {
    if(!formData.shop_remark) {
        uni.showToast({
            title: '请输入卖家备注',
            icon: 'none'
        })
        return false
    }

    if(loading.value) return
    loading.value = true

    setShopRemark(formData).then(() => {
        loading.value = false
        emits('confirm')
	    show.value = false
    }).catch(() => {
        loading.value = false
    })
	
}

defineExpose({
	open
})
</script>

<style scoped>

</style>