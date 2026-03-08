import { ref } from 'vue'
import { getToken } from '@/utils/common'
import { generateShareLink } from '@/addon/phone_shop/api/share'

/**
 * 下载配置类型
 */
export interface DownloadConfig {
    // 价格类型：retail-零售价, wholesale-批发价
    priceType: 'retail' | 'wholesale'
    // 是否携带小程序链接
    includeLink: boolean
    // 是否已配置过（首次下载需要弹窗）
    configured: boolean
}

/**
 * 下载配置管理 Hook
 * 仅限 phone_shop 插件使用
 */
export function useDownloadConfig() {
    const STORAGE_KEY = 'phone_shop_download_config'

    /**
     * 获取存储的配置
     */
    const getStoredConfig = (): DownloadConfig => {
        try {
            const stored = uni.getStorageSync(STORAGE_KEY)
            if (stored) {
                return JSON.parse(stored)
            }
        } catch (error) {
            console.error('读取下载配置失败:', error)
        }

        // 默认配置
        return {
            priceType: 'retail',
            includeLink: false,
            configured: false
        }
    }

    /**
     * 保存配置到本地
     */
    const saveConfig = (config: DownloadConfig): boolean => {
        try {
            uni.setStorageSync(STORAGE_KEY, JSON.stringify(config))
            return true
        } catch (error) {
            console.error('保存下载配置失败:', error)
            return false
        }
    }

    /**
     * 检查用户是否是会员
     * 在当前系统中，只要用户登录就可以享受会员价
     * 通过 token 判断是否登录
     */
    const isMember = (): boolean => {
        return !!getToken()
    }

    /**
     * 获取商品价格（根据配置）
     * @param item 商品数据
     * @param config 下载配置
     */
    const getGoodsPrice = (item: any, config: DownloadConfig): string => {
        // 兼容两种数据结构：
        // 1. list.vue: item.goodsSku 包含价格信息
        // 2. detail.vue: item 直接包含 price, member_price 等字段
        const sku = item.goodsSku || item

        if (!sku) {
            return item.price || '0.00'
        }

        // 如果是会员且选择批发价
        if (isMember() && config.priceType === 'wholesale') {
            // 检查商品是否支持会员折扣
            const memberDiscount = item.member_discount || (item.goods && item.goods.member_discount)

            // 优先使用会员价作为批发价（需要商品支持会员折扣且会员价与原价不同）
            if (memberDiscount && sku.member_price && sku.member_price !== sku.price) {
                return sku.member_price
            }
        }

        // 零售价（默认）
        return sku.price || '0.00'
    }

    /**
     * 生成小程序分享短链接（从后端获取微信 Short Link）
     * @param goodsId 商品ID
     * @param userId 用户ID（从 token 或用户信息获取）
     */
    const getShareLink = async (
        goodsId: string | number,
        userId?: string | number
    ): Promise<string> => {
        try {
            const params: any = { goods_id: goodsId }
            if (userId) {
                params.share_user_id = userId
            }

            const res = await generateShareLink(params)
            if (res.code === 1 && res.data.scheme) {
                // 返回后端生成的 Short Link，格式如：#小程序://xxxxx
                return res.data.scheme
            }

            // 如果后端返回失败，返回小程序路径作为备用
            const path = `/addon/phone_shop/pages/goods/detail?goods_id=${goodsId}`
            return userId ? `${path}&share_user_id=${userId}` : path
        } catch (error) {
            console.error('获取分享链接失败:', error)
            // 出错时返回小程序路径
            const path = `/addon/phone_shop/pages/goods/detail?goods_id=${goodsId}`
            return userId ? `${path}&share_user_id=${userId}` : path
        }
    }

    /**
     * 构建分享文案
     * @param item 商品数据（兼容 list 和 detail 两种数据结构）
     * @param config 下载配置
     * @param userId 用户ID（可选）
     */
    const buildShareText = async (
        item: any,
        config: DownloadConfig,
        userId?: string | number
    ): Promise<string> => {
        try {
            // 兼容两种数据结构：
            // 1. list.vue: item 直接包含 goods_brand, goods_name 等
            // 2. detail.vue: item.goods 包含这些字段
            const goods = item.goods || item

            // 品牌名称：兼容 brand_info 和 goods_brand 两种结构
            let brandName = ''
            if (item.brand_info) {
                brandName = item.brand_info.brand_name + ' '
            } else if (goods.goods_brand) {
                brandName = goods.goods_brand.brand_name + ' '
            }

            const subtitle = goods.sub_title ? goods.sub_title + ' ' : ''

            // 兼容多种 SKU 数据结构：
            // detail.vue: item.sku_no (直接在 item 上)
            // list.vue: goods.goodsSku.sku_no
            let sku_no = ''
            if (item.sku_no) {
                sku_no = '#' + item.sku_no + ' '
            } else if (goods.goodsSku?.sku_no) {
                sku_no = '#' + goods.goodsSku.sku_no + ' '
            }

            const price = getGoodsPrice(item, config)

            let text = `${brandName}${goods.goods_name} ${subtitle}${sku_no}💰￥${price}`

            // 如果配置了携带小程序链接
            if (config.includeLink) {
                const goodsId = goods.goods_id || goods.id
                const link = await getShareLink(goodsId, userId)
                text += `\n\n📱 ${link}`
            }

            console.log('生成的文案:', text)
            return text
        } catch (error) {
            console.error('构建分享文案失败:', error)
            // 返回一个基本的文案，避免复制失败
            const goods = item.goods || item
            return `${goods.goods_name || '商品'}`
        }
    }

    /**
     * 重置配置（清除本地存储）
     */
    const resetConfig = (): boolean => {
        try {
            uni.removeStorageSync(STORAGE_KEY)
            return true
        } catch (error) {
            console.error('重置下载配置失败:', error)
            return false
        }
    }

    return {
        getStoredConfig,
        saveConfig,
        isMember,
        getGoodsPrice,
        getShareLink,
        buildShareText,
        resetConfig
    }
}
