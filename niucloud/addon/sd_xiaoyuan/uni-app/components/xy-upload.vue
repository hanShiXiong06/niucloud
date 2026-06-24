<template>
    <view class="xy-upload">
        <view class="xy-upload-list">
            <view class="xy-upload-item" v-for="(url, index) in fileList" :key="index">
                <image :src="img(url)" mode="aspectFill" @click="preview(index)"></image>
                <view class="xy-upload-del" @click.stop="remove(index)">
                    <u-icon name="close" size="12" color="#fff"></u-icon>
                </view>
            </view>
            <up-upload
                v-if="fileList.length < maxCount"
                :maxCount="maxCount - fileList.length"
                @afterRead="afterRead"
                :previewImage="false"
                :multiple="multiple"
            >
                <view class="xy-upload-add">
                    <u-icon name="camera" size="40" color="#ccc"></u-icon>
                    <text class="xy-upload-count">{{ fileList.length }}/{{ maxCount }}</text>
                </view>
            </up-upload>
        </view>
    </view>
    <!-- #ifdef MP-WEIXIN -->
    <wx-privacy-popup ref="wxPrivacyPopupRef"></wx-privacy-popup>
    <!-- #endif -->
</template>

<script setup lang="ts">
import { computed, ref, onMounted, nextTick } from 'vue'
import { img } from '@/utils/common'
import { uploadImage } from '@/app/api/system'
import useMemberStore from '@/stores/member'

const props = defineProps({
    modelValue: {
        type: [String, Array],
        default: ''
    },
    maxCount: {
        type: Number,
        default: 9
    },
    multiple: {
        type: Boolean,
        default: true
    },
    separator: {
        type: String,
        default: ','
    }
})

const emit = defineEmits(['update:modelValue'])

const wxPrivacyPopupRef: any = ref(null)

onMounted(() => {
    nextTick(() => {
        if (wxPrivacyPopupRef.value) {
            wxPrivacyPopupRef.value.proactive()
        }
    })
})

const getList = (): string[] => {
    if (Array.isArray(props.modelValue)) return props.modelValue as string[]
    if (!props.modelValue) return []
    return String(props.modelValue).split(props.separator).filter(Boolean)
}

const setList = (val: string[]) => {
    if (Array.isArray(props.modelValue)) {
        emit('update:modelValue', val)
    } else {
        emit('update:modelValue', val.join(props.separator))
    }
}

const fileList = computed(() => getList())

const afterRead = (event: any) => {
    const files = event.file ? (Array.isArray(event.file) ? event.file : [event.file]) : []
    files.forEach((file: any) => upload(file))
}

const upload = async (file: any) => {
    const list = getList()
    if (list.length >= props.maxCount) return
    const memberStore = useMemberStore()
    const tk = import.meta.env.VITE_REQUEST_STORAGE_TOKEN_KEY
    const stored = uni.getStorageSync(tk)
    if (stored && !memberStore.token) {
        memberStore.token = stored as string
    }
    try {
        const res: any = await uploadImage({ filePath: file.url, name: 'file' })
        if (res.data?.url) {
            setList([...list, res.data.url])
        } else {
            uni.showToast({ title: '图片上传失败', icon: 'none' })
        }
    } catch (e) {
        uni.showToast({ title: '图片上传失败', icon: 'none' })
    }
}

const remove = (index: number) => {
    const list = getList()
    list.splice(index, 1)
    setList(list)
}

const preview = (index: number) => {
    uni.previewImage({
        urls: getList().map(u => img(u)),
        current: index
    })
}
</script>

<style lang="scss" scoped>
.xy-upload-list {
    display: flex;
    flex-wrap: wrap;
    gap: 20rpx;
}

.xy-upload-item {
    width: 160rpx;
    height: 160rpx;
    border-radius: 12rpx;
    overflow: hidden;
    position: relative;

    image {
        width: 100%;
        height: 100%;
    }

    .xy-upload-del {
        position: absolute;
        top: 0;
        right: 0;
        width: 36rpx;
        height: 36rpx;
        background: rgba(0, 0, 0, 0.5);
        border-radius: 0 0 0 12rpx;
        display: flex;
        align-items: center;
        justify-content: center;
    }
}

.xy-upload-add {
    width: 160rpx;
    height: 160rpx;
    background: #f8f8f8;
    border-radius: 12rpx;
    border: 2rpx dashed #ddd;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8rpx;

    .xy-upload-count {
        font-size: 22rpx;
        color: #999;
    }
}
</style>
