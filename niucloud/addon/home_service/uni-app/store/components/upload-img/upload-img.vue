<template>
    <view class="flex flex-wrap">
        <view v-for="(item, index) in valueList" class=" relative"  :class="{'mr-[18rpx]': (index + 1) % 4 != 0 }">
            <up-image class="rounded-[10rpx] overflow-hidden" width="300rpx" height="220rpx" :src="img(item || '')" model="aspectFill" @click="imgListPreview(item)" v-if="prop.bgUrl">
                <template #error>
                    <u-icon name="photo" color="#999" size="50"></u-icon>
                </template>
            </up-image>
			<up-image class="rounded-[10rpx] overflow-hidden mb-[18rpx]" width="140rpx" height="140rpx" :src="img(item || '')" model="aspectFill" @click="imgListPreview(item)" v-else>
			    <template #error>
			        <u-icon name="photo" color="#999" size="50"></u-icon>
			    </template>
			</up-image>
            <view class="absolute top-0 right-[0] bg-[#373737] flex justify-end h-[28rpx] w-[28rpx] rounded-bl-[40rpx]" @click.stop="deleteImg(index)">
                <text class="nc-iconfont nc-icon-guanbiV6xx !text-[20rpx] mt-[2rpx] mr-[2rpx] text-[#fff]"></text>
            </view>
        </view>
        <view class="" v-show="valueList.length < maxCount">
            <u-upload @afterRead="afterRead" :maxCount="maxCount" :multiple="prop.multiple"  :maxSize="10485760" @oversize="oversizeTit">
                <view v-if="prop.bgUrl">
                	<view  class="w-[320rpx] h-[220rpx] flex items-center justify-center flex-col">
                		<image :src="img(prop.bgUrl)" mode="aspectFit"
                			class="w-[320rpx] h-[220rpx] rounded" alt="身份证正面示例" />
                	</view>
                </view>
				<view v-else class="flex items-center justify-center w-[140rpx] h-[140rpx] border-[2rpx] border-dashed border-[#ddd] text-center text-[var(--text-color-light9)] rounded-[var(--goods-rounded-big)]">
				    <view>
				        <view class="iconfont iconzhaoxiangji text-[50rpx]"></view>
				        <view class="text-[24rpx] mt-[12rpx]">{{ valueList.length }}/{{ maxCount }}</view>
				    </view>
				</view>
            </u-upload>
        </view>
    </view>
</template>
<script lang="ts" setup>
import { computed, ref } from 'vue';
import { img } from '@/utils/common';
import { uploadImage } from '@/app/api/system'

const prop = defineProps({
    modelValue: {
        type: [String, Array],
        default: () => []
    },
    maxCount: {
        type: Number,
        default: 9
    },
    multiple: {
        type: Boolean,
        default: false
    },
	bgUrl:{
		type: String,
		default: ''
	}
})

const emit = defineEmits(['update:modelValue'])

// 处理modelValue，统一转换为数组便于处理
const valueList = computed({
    get() {
        if (!prop.modelValue) {
            return [];
        } else if (typeof prop.modelValue === 'string') {
            // 单图上传时，将字符串转换为单元素数组
            return prop.modelValue ? [prop.modelValue] : [];
        } else {
            // 多图上传时，直接返回数组
            return prop.modelValue;
        }
    },
    set(newValue) {
        // 根据multiple属性决定返回字符串还是数组
        if (prop.multiple) {
            emit('update:modelValue', newValue);
        } else {
            emit('update:modelValue', newValue.length > 0 ? newValue[0] : '');
        }
    }
})

const maxCount = computed(() => {
    return prop.maxCount
})

const afterRead = (event: any) => {
    if (prop.multiple) {
        event.file.forEach((file: any) => {
            upload({ file })
        })
    } else {
        upload(event)
    }
}
const oversizeTit = () =>{
		uni.showToast({ title: '图片体积过大，请压缩后上传', icon: 'none' })
	}
const upload = (event: any) => {
    if (valueList.value.length >= maxCount.value) {
        uni.showToast({ title: `最多允许上传${ maxCount.value }张图片`, icon: 'none' })
        return false
    }

    uploadImage({
        filePath: event.file.url,
        name: 'file'
    }).then(res => {
        if (valueList.value.length < maxCount.value) {
            // 创建新数组以触发响应式更新
            const newList = [...valueList.value, res.data.url];
            valueList.value = newList;
        }
    }).catch(() => {
        uni.showToast({ title: '上传失败', icon: 'none' })
    })
}

const deleteImg = (index: number) => {
    // 创建新数组以触发响应式更新
    const newList = [...valueList.value];
    newList.splice(index, 1);
    valueList.value = newList;
}

//预览图片
const imgListPreview = (item: any) => {
    if (item === '') return false
    const urlList = [];
    urlList.push(img(item))
    uni.previewImage({
        indicator: "number",
        loop: true,
        urls: urlList
    })
}
</script>
