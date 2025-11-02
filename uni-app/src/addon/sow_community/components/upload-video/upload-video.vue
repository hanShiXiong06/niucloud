<template>
    <view class="flex flex-wrap">
        <view v-for="(item, index) in images.data" class="relative rounded-[10rpx] ovflow-hidden" :class="{'mr-[20rpx]': (index + 1) % 3 != 0,'mb-[20rpx]': maxCount > 1 }">
            <up-image class="rounded-[10rpx] overflow-hidden" width="200rpx" height="200rpx" :src="img('')" model="aspectFill" @click="videoListPreview(item)">
                <template #error>
                    <view class="w-[200rpx] h-[200rpx] rounded-[10rpx] flex flex-col justify-center items-center" :style="{background: 'url(' + img('/addon/sow_community/detail/video.jpg') + ') left top /cover no-repeat'}">
                        <image class="w-[50rpx] h-[50rpx]" :src="img('/addon/sow_community/index/play.png')" :mode="'aspectFill'"></image>
                        <text class="mt-[22rpx] text-[18rpx] text-[#fff]">点击可预览视频</text>
                    </view>
                </template>
            </up-image>
            <view class="absolute top-0 right-0 bg-[#373737] z-100 flex justify-end h-[28rpx] w-[28rpx] rounded-bl-[40rpx]" @click.stop="deleteVideo(index)">
                <text class="nc-iconfont nc-icon-guanbiV6xx !text-[20rpx] mt-[2rpx] mr-[2rpx] text-[#fff]"></text>
            </view>
        </view>
        <view class="w-[200rpx] h-[200rpx]" v-show="images.data.length < maxCount">
            <up-upload @afterRead="afterRead" :multiple="prop.multiple" :maxCount="maxCount"  accept="video">
                <view class="flex items-center justify-center w-[200rpx] h-[200rpx] border-[2rpx] border-dashed border-[#ddd] text-center text-[var(--text-color-light9)] rounded-[var(--goods-rounded-big)]">
                    <view>
                        <view class="nc-iconfont nc-icon-a-shipinV6xx-28-1 text-[50rpx]"></view>
                        <view class="text-[24rpx] mt-[12rpx]">添加视频</view>
                    </view>
                </view>
            </up-upload>
        </view>
    </view>
    <u-popup :show="videoShow" @close="videoShow = false" zIndex="999999">
        <view class="h-[100vh] w-[100vw] relative" @touchmove.prevent.stop>
            <text class="fixed top-[20rpx] left-[20rpx]  nc-iconfont nc-icon-guanbiV6xx z-1000 text-[#fff] text-[40rpx]" @click="handleVideo"></text>
            <video class="w-full h-full" ref="videoRef" :src="img(curvideo)" controls autoplay></video>
        </view>
    </u-popup>
</template>
<script lang="ts" setup>
import { ref, reactive, computed, watch, toRaw} from 'vue';
import { img } from '@/utils/common';
import { uploadVideo } from '@/app/api/system'
import { onUnload} from '@dcloudio/uni-app';

const prop = defineProps({
    modelValue: {
        type: String || Array,
    },
    maxCount: {
        type: Number,
        default: 9
    },
    multiple: {
        type: Boolean,
        default: false
    }
})
const emit = defineEmits(['update:modelValue'])
const value: any = computed({
    get() {
        return prop.modelValue
    },
    set(value) {
        emit('update:modelValue', value)
    }
})

const images: Record<string, any> = reactive({
    data: []
})

const setValue = () => {
    value.value = toRaw(images.data).toString()
}

watch(() => value.value, () => {
    images.data = [
        ...value.value.split(',').filter((item: string) => { return item })
    ]
    setValue()
}, { immediate: true })


const maxCount = computed(() => {
    return prop.maxCount
})
const afterRead = (event:any) => {
    if (event.file.size > 100 * 1024 * 1024) {
        uni.showToast({ title: '视频大小不能超过100MB', icon: 'none' })
        return false
    }
    if (prop.multiple) {
        event.file.forEach((file: any) => {
            upload({ file })
        })
    } else {
        upload(event)
    }
}

const upload = (event:any) => {
    if (value.value?.length >= maxCount.value) {
        uni.showToast({ title: `最多允许上传${maxCount.value}个视频`, icon: 'none' })
        return false
    }
    uni.showLoading({
        title: '上传中...',
        mask: true
    });
    uploadVideo({
        filePath: event.file.url,
        name: 'file'
    }).then((res: any)=> {
        if (value.value?.length < maxCount.value){
            images.data.push(res.data.url)
        }
        uni.hideLoading();
        setValue()
    }).catch(() => {
    })
}

const deleteVideo = (index:number)=>{
    images.data.splice(index, 1)
    setValue()
}

//预览视频
const videoShow = ref(false)
const curvideo = ref('')
const videoListPreview = (item:any) => {
    if (item === '') return false
    curvideo.value = item
    videoShow.value = true
}

const videoRef = ref()
const handleVideo = () => {
    videoShow.value = false
    videoRef.value.pause()
}

onUnload(() => {
	uni.hideLoading();
})

</script>
<style lang="scss" scoped>
.u-popup{
    flex: 0 !important;
}
</style>
