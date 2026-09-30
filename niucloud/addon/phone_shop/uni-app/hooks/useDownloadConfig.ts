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
    // 加价转发规则:在"分享者看到的价"(会员=同行/批发价,非会员=零售价)基础上统一加价。
    // 开启后转发文案用"基准价+加价",且强制不带链接(避免客户进店看到真实价不一致)。规则存本地,后续自动套用。
    markupEnabled?: boolean
    markupAmount?: number | string
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
            configured: false,
            markupEnabled: false,
            markupAmount: ''
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

        // 详情接口把当前会员真正看到的价格放在顶层 show_price，列表接口则放在
        // goodsSku.show_price。历史数据里 show_price 可能为 0，不能让这个占位值
        // 截断后续回退，否则“不加价转发”会生成 ￥0.00。
        const visiblePrice = [
            item?.show_price,
            item?.goodsSku?.show_price,
            sku?.show_price,
            sku?.price,
            item?.price,
            item?.goodsSku?.price,
            item?.goods?.price
        ].map(value => parseFloat(String(value ?? '')))
            .find(value => Number.isFinite(value) && value > 0) || 0

        // 批发价:直接转发同行价(会员价),不加价
        if (config.priceType === 'wholesale') {
            // member_price 在新版中是 JSON，不能再直接 parseFloat；同行身份访问时，
            // 后端已经把对应等级价解析到 show_price，直接消费当前可见价最准确。
            return visiblePrice.toFixed(2)
        }

        // 零售价:基准 = 分享者"实际看到的价"(随会员等级而定,优先 show_price),再 + 加价
        let base = visiblePrice
        const markup = parseFloat(String(config.markupAmount || 0)) || 0
        if (config.markupEnabled && markup > 0) {
            base = base + markup
        }
        return base.toFixed(2)
    }

    /** 是否启用加价(仅零售价生效;用于启用时强制不带链接) */
    const isMarkupOn = (config: DownloadConfig): boolean => {
        return config.priceType === 'retail' && !!config.markupEnabled && (parseFloat(String(config.markupAmount || 0)) || 0) > 0
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

            // 携带小程序链接;但启用加价规则时强制不带链接(避免客户进店看到真实价与文案不一致)
            if (config.includeLink && !isMarkupOn(config)) {
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
        isMarkupOn,
        getShareLink,
        buildShareText,
        resetConfig
    }
}
