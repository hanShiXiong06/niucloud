<template>
    <view class="w-full min-h-screen bg-page account-wrap !pb-[20rpx] overflow-hidden" v-if="Object.keys(saveInfo).length" :style="themeColor()">
        <view class="overflow-hidden px-[24rpx] py-[20rpx] bg-[#fff]">
            <u-cell-group :border="false" class="cell-group">
                <u-cell title="头像" :titleStyle="{'font-size': '28rpx'}" :is-link="true">
                    <template #value>
                        <!-- #ifndef MP-WEIXIN -->
                        <u-upload @afterRead="afterRead" :maxCount="1">
                            <u-avatar :src="img(saveInfo.headimg)" :default-url="img('static/resource/images/default_headimg.png')" size="40" leftIcon="none" />
                        </u-upload>
                        <!-- #endif -->
                    </template>
                </u-cell>
                <u-cell title="名称" :titleStyle="{'font-size': '28rpx'}" :is-link="true" :value="saveInfo.real_name" @click="updateRealname.modal = true"></u-cell>
                <u-cell title="修改密码" :titleStyle="{'font-size': '28rpx'}" :is-link="true"  @click="redirect({url: '/app/pages/auth/updatepwd'})"></u-cell>
                <u-cell title="用户名" :titleStyle="{'font-size': '28rpx'}">
                    <template #value>
                        <view v-if="saveInfo.username" class="mr-[10rpx]">{{ saveInfo.username }}</view>
                    </template>
                </u-cell>
                <u-cell title="手机号" :titleStyle="{'font-size': '28rpx'}">
                    <template #value>
                        <view v-if="saveInfo.mobile" class="mr-[10rpx]">{{ saveInfo.mobile }}</view>
                    </template>
                </u-cell>
            </u-cell-group>
        </view>
        <!-- 修改昵称 -->
        <u-popup class="popup-type" :safeAreaInsetBottom="false" round="var(--rounded-big)" :show="updateRealname.modal" mode="center" @close="updateRealname.modal = false">
            <view class="w-[620rpx] popup-common pb-[40rpx]" @touchmove.prevent.stop>
                <view class="title !pt-[50rpx] !pb-[60rpx]">修改名称</view>
                <view class="mx-[50rpx] border-0 border-b border-[#eee] border-solid">
                    <input  class="h-[88rpx] text-[26rpx]" v-model="updateRealname.value" placeholder="请输入名称" placeholderClass="text-[26rpx] h-[88rpx] flex items-center" @blur="bindRealname" />
                </view>
                <view class="px-[60rpx] pt-[70rpx]">
                    <button hover-class="none" class="primary-btn-bg text-[#fff] h-[80rpx] font-500 leading-[80rpx] rounded-[16rpx] text-[26rpx]" @click="updateRealnameConfirm">确认</button>
                </view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { ref, computed, reactive, nextTick } from 'vue'
import { redirect, img } from '@/utils/common';
import { getUserInfo, setUserInfo } from '@/app/api/site'
import { uploadImage } from '@/app/api/system'
import useUserStore from '@/stores/user'

const userStore = useUserStore()


// 提交信息
const saveInfo = reactive<any>({})
const loading = ref(true)
/**
 * 获取用户信息
 */
const getUserInfoFn = () => {
    getUserInfo().then((res: any) => {
        loading.value = false
        const data = res.data
        userStore.setUserInfo(data)
        saveInfo.head_img = data.head_img
        saveInfo.real_name = data.real_name
        saveInfo.username = data.username
    })
}
getUserInfoFn()

const afterRead = (event: any) => {
    uploadImage({
        filePath: event.file.url,
        name: 'file'
    }).then((res: any) => {
        
    }).catch(() => {
    })
    uploadImage({
        filePath: event.file.url,
        name: 'file'
    }).then((res: any) => {
        
        setUserInfo({
            head_img: res.data.url,
            real_name: saveInfo.real_name
        }).then(() => {
            getUserInfoFn()
        })
    }).catch(() => {
    })
}

/**
 * 修改昵称
 */
const updateRealname = reactive({
    modal: false,
    value: saveInfo.real_name || ''
})
const bindRealname = (e: any) => {
    updateRealname.value = e.detail.value
}

const updateRealnameConfirm = () => {
    if (uni.$u.test.isEmpty(updateRealname.value)) {
        uni.showToast({ title: '请输入名称', icon: 'none' });
        return
    }

    setUserInfo({
        head_img: saveInfo.head_img,
        real_name: updateRealname.value
    }).then(res => {
        getUserInfoFn()
        updateRealname.modal = false
    })
}
</script>

<style lang="scss" scoped>
:deep(.u-upload ) {
    flex: none;
}
:deep(button, button:after) {
    border: none;
}
:deep(.cell-group), :deep(.u-cell-group) {
    .u-cell {
        .u-cell__body {
            padding: 0;
            height: 80rpx;
            margin-top: 10rpx;
            margin-bottom: 10rpx;
            box-sizing: border-box;
        }

        &:first-of-type .u-cell__body {
            margin-top: 0;
        }

        .u-cell__title-text {
            font-size: 26rpx;
            line-height: 40rpx;
        }

        .u-icon__icon {
            font-size: 24rpx !important;
            margin-top: 4rpx !important;
            font-weight: 500 !important;
        }

        .u-cell__value {
            line-height: 1;
           font-size: 26rpx;
            color: #333 !important;
        }

        
    }
}
:deep(.u-line){
    border-color: #ebebeb !important;
}
</style>

<style lang="scss">
.account-wrap .u-cell--clickable {
    background-color: transparent !important;
}
</style>