<template>
    <view class="min-h-[100vh] bg-[var(--page-bg-color)] overflow-hidden" :style="themeColor()">
        <view class="sidebar-margin  card-template my-[20rpx] !py-[20rpx]">
            <u-form labelPosition="left" :model="formData" :label-style="{'font-size':'28rpx'}"  class="pl-[15rpx]" labelWidth="140rpx" errorType='toast' :rules="rules" ref="formRef">
                <view>
                    <u-form-item label="原密码" prop="original_password">
                        <input v-model.trim="formData.original_password" type="password" maxlength="25" placeholderStyle="color: #888"  placeholder="请输入原密码" class="h-full text-[28rpx]" />
                    </u-form-item>
                </view>
                <view>
                    <u-form-item label="新密码" prop="password">
                        <input v-model.trim="formData.password" type="password"  maxlength="25" placeholderStyle="color: #888" placeholder="请输入新密码"   class="h-full text-[28rpx]"/>
                    </u-form-item>
                </view>
                <view>
                    <u-form-item label="确认密码" prop="password_copy">
                        <input  v-model.trim="formData.password_copy" type="password" maxlength="25" placeholderStyle="color: #888" placeholder="请输入新密码" class="h-full text-[28rpx]" />
                    </u-form-item>
                </view>
            </u-form>
        </view>
        <view class="py-[var(--top-m)] px-[var(--sidebar-m)]  box-border mt-[40rpx]">
            <button hover-class="none" class="primary-btn-bg !text-[#fff] h-[80rpx] leading-[80rpx] rounded-[16rpx] text-[26rpx] font-500"  @click="save" >确定</button>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, reactive, computed, nextTick } from 'vue'
import { setUserInfo } from '@/app/api/site'
import { redirect } from '@/utils/common'
const formRef = ref<any>()
const formData = reactive<any>({
    original_password: '',
    password: '',
    password_copy: ''
})

const rules = computed(() => {
    return {
        original_password: [
            { required: true, message: '请输入原密码', trigger: 'blur' }
        ],
        password: [
            { required: true, message: '请输入新密码', trigger: 'blur' },
        ],
        password_copy: [
            { required: true, message: '请输入确认密码', trigger: 'blur' },
            { validator: (rule: any, value: any, callback: any) => {
                if (value !== formData.password) {
                    return callback(new Error('输入的两次密码不一致'))  
                } else {
                    return callback()
                }
            }}
        ]
    }
})


const  operateLoading = ref(false)
const save = () => {
    formRef.value.validate().then(() => {
        
        if (operateLoading.value) return
        operateLoading.value = true

        setUserInfo(formData).then((res: any) => {
            operateLoading.value = false
            redirect({url: '/app/pages/site/index'})
        }).catch((err: any) => {
            operateLoading.value = false
        })
    })
    
}
</script>

<style lang="scss" scoped>
:deep(.u-form-item__body__right__content){
    height: 100% !important;
}
</style>