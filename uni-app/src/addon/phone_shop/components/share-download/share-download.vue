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
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { img } from '@/utils/common'
import { getGoodsDetail } from '@/addon/phone_shop/api/goods'

interface Props {
    // 商品数据
    goodsItem: any
    // 按钮类型: 'circle' | 'grid'
    type?: 'circle' | 'grid'
    // 自定义样式
    customStyle?: string
    // 是否显示提示
    showToast?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    type: 'circle',
    showToast: true
})

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

// 计算商品价格
const getGoodsPrice = (item: any) => {
    console.log(item);
    
    if (item.goodsSku) {
        // 折扣价
        if (item.is_discount && item.goodsSku.sale_price != item.goodsSku.price) {
            return item.goodsSku.sale_price || item.goodsSku.price
        }
        // 会员价
        if (item.member_discount && item.goodsSku.member_price != item.goodsSku.price) {
            return item.goodsSku.member_price || item.goodsSku.price
        }
        return item.goodsSku.price
    }
    return item.price || '0.00'
}

// 下载图片并复制文案
const downloadImages = (images: string[], item: any) => {
    return new Promise((resolve) => {
        const tasks = images.map((url: string) => {
            return new Promise((resolve, reject) => {
                uni.downloadFile({
                    url,
                    success: (res) => {
                        if (res.statusCode === 200) {
                            uni.saveImageToPhotosAlbum({
                                filePath: res.tempFilePath,
                                success: resolve,
                                fail: reject
                            })
                        } else {
                            reject()
                        }
                    },
                    fail: reject
                })
            })
        })

        Promise.all(tasks).then(() => {
            // 构建文案
            const brandName = item.goods.goods_brand ? item.goods.goods_brand.brand_name + ' ' : ''
            const subtitle = item.goods.sub_title ? item.goods.sub_title + ' ' : ''
            const sku_no = item.goods.goodsSku?.sku_no ? '#' + item.goods.goodsSku.sku_no + ' ' : ''
            const price = getGoodsPrice(item)
            const text = `${brandName} ${item.goods.goods_name} ${subtitle}仅售💰${price} ${sku_no}`

            uni.setClipboardData({
                data: text,
                success: () => {
                    if (props.showToast) {
                        uni.showToast({ title: '图片下载及文案复制成功', icon: 'none' })
                    }
                    resolve(true)
                }
            })
        }).catch(() => {
            if (props.showToast) {
                uni.showToast({ title: '下载失败', icon: 'none' })
            }
            resolve(false)
        })
    })
}

// 处理下载
const handleDownload = async () => {
    try {
        // 如果商品数据中已有图片数组
        if (props.goodsItem.goods.goods_image) {
            const images = Array.isArray(props.goodsItem.goods.goods_image)
                ? props.goodsItem.goods.goods_image
                : props.goodsItem.goods.goods_image.split(',')
            await downloadImages(images, props.goodsItem)
        } else {
            // 需要获取详情
            const res = await getGoodsDetail({ goods_id: props.goodsItem.goods.goods_id })
            if (!res.data.goods) {
                uni.showToast({ title: '商品信息获取失败', icon: 'none' })
                return
            }
            const images = res.data.goods.goods_image.split(',')
            await downloadImages(images, props.goodsItem)
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
    box-shadow: 0 4rpx 12rpx rgba(7, 193, 96, 0.3);
    z-index: 10;

    .default-icon {
        font-size: 28rpx;
    }
}
</style>
