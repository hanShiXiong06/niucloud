<template>
    <view class="flex flex-wrap">
        <view
            v-for="(item, index) in images.data"
            :key="index"
            class="image-item relative"
            :class="{
                'mr-[20rpx]': (index + 1) % 3 != 0,
                'mb-[20rpx]': maxCount > 1,
                'z-50': dragState.isDragging && dragState.dragIndex === index,
                'opacity-50': dragState.isDragging && dragState.dragIndex !== index
            }"
            :style="{
                transform: dragState.isDragging && dragState.dragIndex === index ?
                    `translate(${dragState.offsetX}px, ${dragState.offsetY}px)` : 'none'
            }"
        >
            <up-image
                class="rounded-[10rpx] overflow-hidden"
                width="200rpx"
                height="200rpx"
                :src="img(item || '')"
                model="aspectFill"
                @click="!dragState.isDragging && imgListPreview(item)"
                @touchstart="onTouchStart($event, index)"
                @touchmove="onTouchMove($event)"
                @touchend="onTouchEnd($event)"
            >
                <template #error>
                    <u-icon name="photo" color="#999" size="50"></u-icon>
                </template>
            </up-image>
            <view class="absolute top-0 right-0 bg-[#373737] flex justify-end h-[28rpx] w-[28rpx] rounded-bl-[40rpx]" @click.stop="deleteImg(index)">
                <text class="nc-iconfont nc-icon-guanbiV6xx !text-[20rpx] mt-[2rpx] mr-[2rpx] text-[#fff]"></text>
            </view>
            <view class="absolute top-0 left-0 bg-primary flex-center h-[28rpx] w-[54rpx] rounded-tl-[16rpx] rounded-br-[16rpx]  text-[18rpx] text-[#fff]" v-if="index == 0">封面</view>
            <!-- 拖拽指示器 -->
            <!-- <view
                class="absolute bottom-[5rpx] right-[5rpx] bg-black bg-opacity-50 rounded-[8rpx] p-[4rpx]"
                v-if="images.data.length > 1"
            >
                <text class="text-[16rpx] text-white">拖拽</text>
            </view> -->

        </view>
        <view class="w-[200rpx] h-[200rpx]" v-show="images.data.length < maxCount">
            <u-upload @afterRead="afterRead" :maxCount="maxCount" :multiple="prop.multiple">
                <view class="flex items-center justify-center w-[200rpx] h-[200rpx] border-[2rpx] border-dashed border-[#ddd] text-center text-[var(--text-color-light9)] rounded-[var(--goods-rounded-big)]">
                    <view>
                        <view class="nc-iconfont nc-icon-xiangjiV6xx text-[50rpx]"></view>
                        <view class="text-[24rpx] mt-[12rpx]">{{ title }}</view>
                    </view>
                </view>
            </u-upload>
        </view>
    </view>
</template>
<script lang="ts" setup>
import { reactive, toRaw, computed, watch, ref } from 'vue';
import { img } from '@/utils/common';
import { uploadImage } from '@/app/api/system'

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
    },
    title: {
        type: String,
        default: '点击上传'
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

// 拖拽状态管理
const dragState = reactive({
    isDragging: false,
    dragIndex: -1,
    startX: 0,
    startY: 0,
    offsetX: 0,
    offsetY: 0,
    startTime: 0
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
    if (prop.multiple) {
        event.file.forEach((file: any) => {
            upload({ file })
        })
    } else {
        upload(event)
    }
}

const upload = (event:any) => {
    if (images.data?.length >= maxCount.value) {
        uni.showToast({ title: `最多允许上传${maxCount.value}张图片`, icon: 'none' })
        return false
    }

    uploadImage({
        filePath: event.file.url,
        name: 'file'
    }).then((res: any) => {
        if (images.data?.length < maxCount.value){
            images.data.push(res.data.url)
        }
        setValue()
    }).catch(() => {
    })
}

const deleteImg = (index:number)=>{
    images.data.splice(index, 1)
    setValue()
}

//预览图片
const imgListPreview = (item:any) => {
    if (item === '') return false
    const urlList = [];
    urlList.push(img(item))  //push中的参数为 :src="item.img_url" 中的图片地址
    uni.previewImage({
        indicator: "number",
        loop: true,
        urls: urlList
    })
}

// 拖拽事件处理
const onTouchStart = (event: any, index: number) => {
    if (images.data.length <= 1) return

    // 阻止默认行为和事件冒泡
    event.preventDefault()
    event.stopPropagation()

    const touch = event.touches[0]
    dragState.isDragging = false
    dragState.dragIndex = index
    dragState.startX = touch.pageX || touch.clientX || 0
    dragState.startY = touch.pageY || touch.clientY || 0
    dragState.offsetX = 0
    dragState.offsetY = 0
    dragState.startTime = Date.now()

    // 触觉反馈
    try {
        uni.vibrateShort({ type: 'light' })
    } catch (e) {}
}

const onTouchMove = (event: any) => {
    if (dragState.dragIndex === -1) return

    // 阻止默认行为和事件冒泡
    event.preventDefault()
    event.stopPropagation()

    const touch = event.touches[0]
    const currentX = touch.pageX || touch.clientX || 0
    const currentY = touch.pageY || touch.clientY || 0
    const deltaX = currentX - dragState.startX
    const deltaY = currentY - dragState.startY
    const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY)

    // 移动距离超过阈值才开始拖拽
    if (distance > 8 && !dragState.isDragging) {
        dragState.isDragging = true
        try {
            uni.vibrateShort({ type: 'medium' })
        } catch (e) {}
    }

    if (dragState.isDragging) {
        dragState.offsetX = deltaX
        dragState.offsetY = deltaY
        // 移除实时位置交换，只在松开手指时交换
    }
}

const onTouchEnd = (event: any) => {
    if (dragState.dragIndex === -1) return

    event.preventDefault()
    event.stopPropagation()

    const duration = Date.now() - dragState.startTime

    // 如果是短时间点击且没有拖拽，则不处理
    if (duration < 200 && !dragState.isDragging) {
        resetDragState()
        return
    }

    // 松开手指时检查是否需要交换位置
    if (dragState.isDragging) {
        checkSwapPosition()
        try {
            uni.vibrateShort({ type: 'light' })
        } catch (e) {}
    }

    resetDragState()
}

// 检查是否需要交换位置（松开手指时调用）
const checkSwapPosition = () => {
    const dragIndex = dragState.dragIndex
    if (dragIndex === -1) return

    const moveThreshold = 30 // 降低阈值，更容易触发
    const absOffsetX = Math.abs(dragState.offsetX)
    const absOffsetY = Math.abs(dragState.offsetY)

    // 只要有移动就可以交换
    if (absOffsetX > moveThreshold || absOffsetY > moveThreshold) {
        const columnsPerRow = 3 // 每行3个图片
        const itemWidth = 100 // 假设每个图片项的宽度
        const itemHeight = 100 // 假设每个图片项的高度

        // 计算当前位置的行列
        const currentRow = Math.floor(dragIndex / columnsPerRow)
        const currentCol = dragIndex % columnsPerRow

        // 根据偏移量计算移动了多少个位置
        const moveStepsX = Math.round(dragState.offsetX / itemWidth)
        const moveStepsY = Math.round(dragState.offsetY / itemHeight)

        // 计算目标位置
        let targetRow = currentRow + moveStepsY
        let targetCol = currentCol + moveStepsX

        // 边界检查和修正
        targetRow = Math.max(0, Math.min(targetRow, Math.floor((images.data.length - 1) / columnsPerRow)))
        targetCol = Math.max(0, Math.min(targetCol, columnsPerRow - 1))

        // 计算目标索引
        const targetIndex = targetRow * columnsPerRow + targetCol

        // 检查目标位置是否有效且不同于当前位置
        const isValidTarget = targetIndex >= 0 &&
                             targetIndex < images.data.length &&
                             targetIndex !== dragIndex

        if (isValidTarget) {
            // 交换位置
            const dragItem = images.data[dragIndex]
            const targetItem = images.data[targetIndex]
            images.data[dragIndex] = targetItem
            images.data[targetIndex] = dragItem

            setValue()
            try {
                uni.vibrateShort({ type: 'medium' })
            } catch (e) {}
        }
    }
}

// 重置拖拽状态
const resetDragState = () => {
    dragState.isDragging = false
    dragState.dragIndex = -1
    dragState.startX = 0
    dragState.startY = 0
    dragState.offsetX = 0
    dragState.offsetY = 0
    dragState.startTime = 0
}

// 添加测试图片
const addTestImages = () => {
    const testImages = [
        'https://via.placeholder.com/200x200/FF6B6B/FFFFFF?text=1',
        'https://via.placeholder.com/200x200/4ECDC4/FFFFFF?text=2',
        'https://via.placeholder.com/200x200/45B7D1/FFFFFF?text=3',
        'https://via.placeholder.com/200x200/96CEB4/FFFFFF?text=4',
        'https://via.placeholder.com/200x200/FFEAA7/000000?text=5'
    ]

    images.data = [...testImages]
    setValue()

    uni.showToast({
        title: '已添加测试图片，现在可以测试拖拽功能了！',
        icon: 'none',
        duration: 2000
    })
}
</script>

<style scoped>
/* 拖拽时的阴影效果 */
.shadow-lg {
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

/* 拖拽时的缩放效果 */
.scale-110 {
    transform: scale(1.1);
}

/* 高层级显示 */
.z-50 {
    z-index: 50;
}

/* 半透明效果 */
.opacity-50 {
    opacity: 0.5;
}

/* 背景透明度 */
.bg-opacity-50 {
    background-color: rgba(0, 0, 0, 0.5);
}
</style>
