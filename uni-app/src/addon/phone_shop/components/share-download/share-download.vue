<template>
    <view
        class="share-download-btn"
        :class="btnClass"
        :style="buttonStyle"
        @click.stop="handleDownload"
    >
        <slot>
            <text class="nc-iconfont nc-icon-fenxiangV6xx default-icon"></text>
        </slot>
    </view>

    <!-- 下载配置弹窗 -->
    <download-config-dialog
        :show="showConfigDialog"
        @close="showConfigDialog = false"
        @confirm="handleConfigConfirm"
    />
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { img } from '@/utils/common'
import { getGoodsDetail } from '@/addon/phone_shop/api/goods'
import { useGoodsDownload } from '@/addon/phone_shop/hooks/useGoodsDownload'
import { type DownloadConfig } from '@/addon/phone_shop/hooks/useDownloadConfig'
import DownloadConfigDialog from '@/addon/phone_shop/components/download-config-dialog/download-config-dialog.vue'

interface Props {
    // 商品数据
    goodsItem: any
    // 按钮类型: 'circle' | 'grid'
    type?: 'circle' | 'grid'
    // 自定义样式
    customStyle?: string
    // 是否显示提示
    showToast?: boolean
    // 用户ID（用于生成分享链接）
    userId?: string | number
}

const props = withDefaults(defineProps<Props>(), {
    type: 'circle',
    showToast: true
})

const {
    downloadGoodsImagesWithConfig,
    needShowConfigDialog,
    saveConfig
} = useGoodsDownload()

// 配置弹窗显示状态
const showConfigDialog = ref(false)
// 待下载的图片数据（配置确认后使用）
const pendingDownload = ref<{ images: string[], item: any } | null>(null)

// 按钮样式
const buttonStyle = computed(() => {
    let style = `background: var(--primary-color);`
    if (props.customStyle) {
        style += props.customStyle
    }
    return style
})

// 按钮类名
const btnClass = computed(() => {
    return props.type === 'grid' ? 'grid-style' : 'circle-style'
})

// 执行下载
const performDownload = async (images: string[], item: any, config?: DownloadConfig) => {
    await downloadGoodsImagesWithConfig(
        images,
        item,
        config,
        props.userId,
        props.showToast
    )
}

// 处理配置确认
const handleConfigConfirm = async (config: DownloadConfig) => {
    // 保存配置
    saveConfig(config)

    // 关闭弹窗
    showConfigDialog.value = false

    // 如果有待下载的数据，执行下载
    if (pendingDownload.value) {
        await performDownload(
            pendingDownload.value.images,
            pendingDownload.value.item,
            config
        )
        pendingDownload.value = null
    }
}

// 处理下载
const handleDownload = async () => {
    try {
        let images: string[] = []

        // 如果商品数据中已有图片数组
        if (props.goodsItem.goods.goods_image) {
            images = Array.isArray(props.goodsItem.goods.goods_image)
                ? props.goodsItem.goods.goods_image
                : props.goodsItem.goods.goods_image.split(',').map((url: string) => img(url.trim()))
        } else {
            // 需要获取详情
            const res = await getGoodsDetail({ goods_id: props.goodsItem.goods.goods_id })
            if (!res.data.goods) {
                uni.showToast({ title: '商品信息获取失败', icon: 'none' })
                return
            }
            images = res.data.goods.goods_image.split(',').map((url: string) => img(url.trim()))
        }

        // 检查是否需要显示配置弹窗
        if (needShowConfigDialog()) {
            // 保存待下载数据
            pendingDownload.value = { images, item: props.goodsItem }
            // 显示配置弹窗
            showConfigDialog.value = true
        } else {
            // 直接下载
            await performDownload(images, props.goodsItem)
        }
    } catch (error) {
        console.error('下载失败:', error)
        if (props.showToast) {
            uni.showToast({ title: '下载失败', icon: 'none' })
        }
    }
}
</script>

<style lang="scss" scoped>
.share-download-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    transition: all 0.3s;

    &:active {
        opacity: 0.8;
        transform: scale(0.95);
    }
}

// 圆形按钮样式 - 用于列表视图
.circle-style {
    width: 48rpx;
    height: 48rpx;
    border-radius: 24rpx;
    flex-shrink: 0;

    .default-icon {
        font-size: 24rpx;
    }
}

// 网格按钮样式 - 用于瀑布流视图
.grid-style {

    right: 16rpx;
    bottom: 16rpx;
    padding: 10rpx 20rpx;

    border-radius: 28rpx;
    box-shadow: 0 4rpx 12rpx var(--primary-color-light);
    z-index: 10;

    .default-icon {
        font-size: 28rpx;
    }
}
</style>
