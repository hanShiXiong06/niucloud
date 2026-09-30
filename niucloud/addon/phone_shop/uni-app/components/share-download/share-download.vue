<template>
    <view
        v-if="showTrigger"
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
import { getGoodsDetail } from '@/addon/phone_shop/api/goods'
import { useGoodsDownload } from '@/addon/phone_shop/hooks/useGoodsDownload'
import { useGoodsForwardAccess } from '@/addon/phone_shop/hooks/useGoodsForwardAccess'
import { type DownloadConfig } from '@/addon/phone_shop/hooks/useDownloadConfig'
import DownloadConfigDialog from '@/addon/phone_shop/components/download-config-dialog/download-config-dialog.vue'

interface Props {
    // 商品数据
    goodsItem: any
    // 按钮类型: 'circle' | 'grid' | 'pill'
    type?: 'circle' | 'grid' | 'pill'
    // 自定义样式
    customStyle?: string
    // 是否显示提示
    showToast?: boolean
    // 用户ID（用于生成分享链接）
    userId?: string | number
    // 未获得同行权限时，申请完成后返回的页面
    backUrl?: string
    // 允许页面复用一个弹窗实例，由外部按钮主动触发，避免长列表重复创建弹窗。
    showTrigger?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    type: 'circle',
    showToast: true,
    backUrl: '/addon/phone_shop/pages/goods/list',
    showTrigger: true
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
    if (props.type === 'grid') return 'grid-style'
    if (props.type === 'pill') return 'pill-style'
    return 'circle-style'
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
        const allowed = await useGoodsForwardAccess().ensureGoodsForwardAccess(props.backUrl)
        if (!allowed) return

        const source = props.goodsItem?.goods || props.goodsItem || {}
        const goodsId = source.goods_id || props.goodsItem?.goods_id
        if (!goodsId) {
            uni.showToast({ title: '商品信息获取失败', icon: 'none' })
            return
        }

        // 列表数据只保留缩略信息。统一重新读取详情，保证图片、SKU 和当前会员
        // 可见价格与分类页、详情页完全一致。
        const res: any = await getGoodsDetail({ goods_id: goodsId })
        if (!res.data?.goods) {
            uni.showToast({ title: '商品信息获取失败', icon: 'none' })
            return
        }
        const goods = res.data.goods
        const rawImages = Array.isArray(goods.goods_image)
            ? goods.goods_image
            : String(goods.goods_image || '').split(',')
        const images = rawImages.map((url: string) => String(url || '').trim()).filter(Boolean)
        const cover = source.goods_cover_thumb_mid || goods.goods_cover_thumb_mid || goods.goods_cover
        if (cover) images.push(String(cover))
        const uniqImages = [...new Set(images)]
        const detailItem = res.data

        // 检查是否需要显示配置弹窗
        if (needShowConfigDialog()) {
            // 保存待下载数据
            pendingDownload.value = { images: uniqImages, item: detailItem }
            // 显示配置弹窗
            showConfigDialog.value = true
        } else {
            // 直接下载
            await performDownload(uniqImages, detailItem)
        }
    } catch (error) {
        console.error('下载失败:', error)
        if (props.showToast) {
            uni.showToast({ title: '下载失败', icon: 'none' })
        }
    }
}

defineExpose({ handleDownload })
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

.pill-style {
    min-width: 88rpx;
    height: 44rpx;
    padding: 0 18rpx;
    box-sizing: border-box;
    border-radius: 24rpx;
    font-size: 23rpx;
    font-weight: 600;
    line-height: 44rpx;
    white-space: nowrap;
    flex-shrink: 0;
}
</style>
