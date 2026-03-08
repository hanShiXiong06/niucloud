<template>
    <view class="bg-gray-100 min-h-[100vh]" :style="themeColor()">
        <view class="fixed left-0 right-0 top-0 product-warp bg-[#fff] z-[99999]" :style="headerStyle">
            <view class="py-[14rpx] flex items-center justify-between px-[20rpx]" :style="headerSearchStyle">
                <view class="flex-1 search-input mr-[20rpx]" >
                    <text @click.stop="handleSearch" class="nc-iconfont nc-icon-sousuo-duanV6xx1 btn"></text>
                    <input class="input" maxlength="50" type="text" v-model="goods_name"
                           placeholder="请搜索您想要的商品"
                           placeholderClass="text-[var(--text-color-light9)] text-[24rpx]" confirm-type="search"
                           @confirm="handleSearch">
                    <text v-if="goods_name" class="nc-iconfont nc-icon-cuohaoV6xx1 clear" @click="goods_name='';handleSearch()"></text>
                </view>
                <view :class="['iconfont text-[32rpx] text-[#333] -mb-[2rpx]', listType ? 'icona-yingyongzhongxinV6xx-32' : 'icona-yingyongliebiaoV6xx-32']"
                    @click="listIconBtn"></view>
            </view>

            <!-- 筛选组件 -->
            <goods-filter
                ref="goodsFilterRef"
                :initialFilters="filterData"
                :showCategory="true"
                :showBrand="true"
                :showPrice="true"
                :showMemory="true"
                :showSort="true"
                @search="handleFilterSearch"
            />
        </view>

        <mescroll-body ref="mescrollRef" :top="scrollTopOffset" bottom="60px" @init="mescrollInit" :down="{ use: false }" @up="getAllAppListFn">
            <view v-if="goodsList.length" :class="['sidebar-margin', !listType ? 'biserial-goods-list' : '']">
                <template v-if="listType">
                    <view v-for="(item, index) in goodsList" :key="index"
                          class="bg-white flex px-[20rpx] py-[24rpx] rounded-[var(--rounded-small)] overflow-hidden top-mar relative"
                          :class="{ 'mb-[20rpx]': (index+1) == goodsList.length}">
                        <!-- 左上角标签 -->
                        <view v-if="item.brand_id" class="absolute left-0 top-0 z-10">
                                <view class="corner-tag bg-[var(--primary-color)]">
                                    {{ getBrandLabel(item.brand_id) }}
                                </view>
                        </view>

                        <view @click="toDetail(item.goods_id)" class="flex flex-1">
                            <image v-if="item.goods_cover_thumb_mid" class="w-[190rpx] h-[190rpx] rounded-[var(--rounded-mid)]"
                                   :src="img(item.goods_cover_thumb_mid)" :mode="'aspectFill'"
                                   @error="item.goods_cover_thumb_mid='static/resource/images/diy/shop_default.jpg'"/>
                            <image v-else class="w-[190rpx] h-[190rpx] rounded-[var(--rounded-mid)]" :src="img('static/resource/images/diy/shop_default.jpg')" :mode="'aspectFill'"/>
                            <view class="flex-1 flex flex-col ml-[20rpx] py-[6rpx]">
                                <view class="text-[25rpx] text-[#333] leading-[40rpx] multi-hidden mb-[4rpx]">
                                    <view class="brand-tag" v-if="item.goods_brand" :style="diyGoods.baseTagStyle(item.goods_brand)">{{ item.goods_brand.brand_name }}</view>
                                    {{ item.goods_name }}
                                </view>
                                <!-- 副标题 -->
                                <view v-if="item.sub_title" class="text-[24rpx] w-[450rpx] text-[#999] leading-[34rpx] truncate mb-[6rpx]">
                                    {{ item.sub_title }}
                                </view>
                                <!-- 多个标签展示 -->
                                <view v-if="item.goods_label_name && item.goods_label_name.length > 1" class="flex flex-wrap mb-[6rpx]">
                                    <template v-for="(tagItem, tagIndex) in item.goods_label_name.slice(1)">
                                        <image class="inline-tag-img" v-if="tagItem.style_type == 'icon' && tagItem.icon" :src="img(tagItem.icon)" mode="heightFix" @error="diyGoods.error(tagItem,'icon')"/>
                                        <view class="inline-tag" v-else-if="tagItem.style_type == 'diy' || !tagItem.icon" :style="diyGoods.baseTagStyle(tagItem)">
                                            {{ tagItem.label_name }}
                                        </view>
                                    </template>
                                </view>
                                <!-- 串号 -->
                                <view v-if="item.goodsSku && item.goodsSku.sku_no" class="text-[22rpx] text-[#666] mb-[4rpx]">
                                    <text class="text-[#999]">串号:</text>
                                    <text class="ml-[6rpx]">{{ item.goodsSku.sku_no }}</text>
                                </view>
                                
                                <view class="mt-auto flex justify-between items-end">
                                    <view class="flex items-baseline mt-[8rpx]">
                                        <view class="text-[var(--price-text-color)] price-font flex items-baseline">
                                            <text class="text-[24rpx] font-500 mr-[4rpx]">￥</text>
                                            <text class="text-[40rpx] font-500">{{ diyGoods.goodsPrice(item).toFixed(2).split('.')[0] }}</text>
                                            <text class="text-[24rpx] font-500">.{{ diyGoods.goodsPrice(item).toFixed(2).split('.')[1] }}</text>
                                        </view>
                                        <image v-if="diyGoods.priceType(item) == 'member_price'"
                                               class="max-w-[50rpx] h-[28rpx] ml-[6rpx]" :src="img('addon/phone_shop/VIP.png')"
                                               mode="heightFix" />
                                        <image v-if="diyGoods.priceType(item) == 'discount_price'"
                                               class="max-w-[50rpx] h-[28rpx] ml-[6rpx]" :src="img('addon/phone_shop/discount.png')"
                                               mode="heightFix" />
                                    </view>
                                    <view class="flex items-center gap-[12rpx]">
                                        <text class="text-[22rpx] text-[var(--text-color-light9)]">
                                            库存{{ item.goodsSku?.stock || 0 }}{{ item.unit }}
                                        </text>
                                        <view class="download-btn" @click.stop="downloadGoods(item)">
                                            <text class="nc-iconfont nc-icon-fenxiangV6xx"></text>
                                        </view>
                                    </view>
                                </view>
                            </view>
                        </view>
                    </view>
                </template>
                <template v-else>
                    <view>
                        <template v-for="(item, index) in goodsList">
                            <view v-if="(index%2) == 0" class="flex flex-col bg-[#fff] box-border rounded-[var(--rounded-mid)] overflow-hidden mt-[var(--top-m)] relative">
                                <!-- 左上角标签 -->
                                <view v-if="item.goods_label_name && item.goods_label_name.length" class="absolute left-0 top-0 z-10">
                                    <template v-for="(tagItem, tagIndex) in item.goods_label_name.slice(0, 1)">
                                        <image class="corner-tag-img-grid" v-if="tagItem.style_type == 'icon' && tagItem.icon" :src="img(tagItem.icon)" mode="heightFix" @error="diyGoods.error(tagItem,'icon')"/>
                                        <view class="corner-tag-grid" v-else-if="tagItem.style_type == 'diy' || !tagItem.icon" :style="diyGoods.baseTagStyle(tagItem)">
                                            {{ tagItem.label_name }}
                                        </view>
                                    </template>
                                </view>

                                <view @click="toDetail(item.goods_id)">
                                    <image v-if="item.goods_cover_thumb_mid"
                                           class="w-[100%] h-[344rpx] rounded-tl-[var(--rounded-mid)] rounded-tr-[var(--rounded-mid)]"
                                           :src="img(item.goods_cover_thumb_mid)" :mode="'aspectFill'"
                                           @error="item.goods_cover_thumb_mid='static/resource/images/diy/shop_default.jpg'"/>
                                    <image v-else
                                           class="w-[100%] h-[344rpx] rounded-tl-[var(--rounded-mid)] rounded-tr-[var(--rounded-mid)]"
                                           :src="img('static/resource/images/diy/shop_default.jpg')"
                                           :mode="'aspectFill'"/>
                                    <view class="px-[20rpx] flex-1 pt-[16rpx] pb-[20rpx] flex flex-col">
                                        <view class="text-[#303133] leading-[40rpx] text-[28rpx] multi-hidden mb-[4rpx]">
                                            <view class="brand-tag" v-if="item.goods_brand" :style="diyGoods.baseTagStyle(item.goods_brand)">{{ item.goods_brand.brand_name }}</view>
                                            {{ item.goods_name }}
                                        </view>
                                        <!-- 副标题 -->
                                        <view v-if="item.sub_title" class="text-[24rpx] w-[250rpx] text-[#999] leading-[34rpx] truncate mb-[6rpx]">
                                            {{ item.sub_title }}
                                        </view>
                                        <!-- 多个标签展示 -->
                                        <view v-if="item.goods_label_name && item.goods_label_name.length > 1" class="flex flex-wrap mb-[6rpx]">
                                            
                                            <template v-for="(tagItem, tagIndex) in item.goods_label_name.slice(1, 3)">
                                                <image class="inline-tag-img" v-if="tagItem.style_type == 'icon' && tagItem.icon" :src="img(tagItem.icon)" mode="heightFix" @error="diyGoods.error(tagItem,'icon')"/>
                                                <view class="inline-tag" v-else-if="tagItem.style_type == 'diy' || !tagItem.icon" :style="diyGoods.baseTagStyle(tagItem)">{{ tagItem.label_name }}</view>
                                            </template>
                                        </view>
                                        <!-- 串号 -->
                                        <view v-if="item.goodsSku && item.goodsSku.sku_no" class="text-[22rpx] text-[#666] mb-[4rpx] truncate w-[250rpx]">
                                            <text class="text-[#999]">串号:</text>
                                            <text class="ml-[6rpx]">{{ item.goodsSku.sku_no }}</text>
                                        </view>
                                        
                                        <view class="flex justify-between items-end mt-[8rpx]">
                                            <view class="flex items-baseline flex-wrap">
                                                <view class="text-[var(--price-text-color)] price-font flex items-baseline">
                                                    <text class="text-[24rpx] font-500">￥</text>
                                                    <text class="text-[40rpx] font-500">{{ diyGoods.goodsPrice(item).toFixed(2).split('.')[0] }}</text>
                                                    <text class="text-[24rpx] font-500">.{{ diyGoods.goodsPrice(item).toFixed(2).split('.')[1] }}</text>
                                                </view>
                                                <image v-if="diyGoods.priceType(item) == 'member_price'" class="max-w-[50rpx] h-[28rpx] ml-[6rpx]" :src="img('addon/phone_shop/VIP.png')" mode="heightFix" />
                                                <image v-if="diyGoods.priceType(item) == 'discount_price'" class="max-w-[50rpx] h-[28rpx] ml-[6rpx]" :src="img('addon/phone_shop/discount.png')" mode="heightFix" />
                                            </view>
                                            <!-- <text class="text-[22rpx] text-[var(--text-color-light9)]">库存{{ item.goodsSku?.stock || 0 }}</text> -->
                                        </view>
                                    </view>
                                </view>
                                <!-- 下载按钮 -->
                                <view class="download-btn-grid" @click.stop="downloadGoods(item)">
                                    <text class="nc-iconfont nc-icon-fenxiangV6xx"></text>
                                </view>
                            </view>
                        </template>
                    </view>
                    <view>
                        <template v-for="(item, index) in goodsList">
                            <view v-if="(index%2) == 1" class="flex flex-col bg-[#fff] box-border rounded-[var(--rounded-mid)] overflow-hidden mt-[var(--top-m)] relative">
                                <!-- 左上角标签 -->
                                <view v-if="item.goods_label_name && item.goods_label_name.length" class="absolute left-0 top-0 z-10">
                                    <template v-for="(tagItem, tagIndex) in item.goods_label_name.slice(0, 1)">
                                        <image class="corner-tag-img-grid" v-if="tagItem.style_type == 'icon' && tagItem.icon" :src="img(tagItem.icon)" mode="heightFix" @error="diyGoods.error(tagItem,'icon')" />
                                        <view class="corner-tag-grid" v-else-if="tagItem.style_type == 'diy' || !tagItem.icon" :style="diyGoods.baseTagStyle(tagItem)">
                                            {{ tagItem.label_name }}
                                        </view>
                                    </template>
                                </view>

                                <view @click="toDetail(item.goods_id)">
                                    <image v-if="item.goods_cover_thumb_mid"
                                           class="w-[100%] h-[344rpx] rounded-tl-[var(--rounded-mid)] rounded-tr-[var(--rounded-mid)]"
                                           :src="img(item.goods_cover_thumb_mid)" :mode="'aspectFill'"
                                           @error="item.goods_cover_thumb_mid='static/resource/images/diy/shop_default.jpg'" />
                                    <image v-else class="w-[100%] h-[344rpx] rounded-tl-[var(--rounded-mid)] rounded-tr-[var(--rounded-mid)]"
                                           :src="img('static/resource/images/diy/shop_default.jpg')"
                                           :mode="'aspectFill'" />
                                    <view class="px-[20rpx] flex-1 pt-[16rpx] pb-[20rpx] flex flex-col">
                                        <view class="text-[#303133] leading-[40rpx] text-[28rpx] multi-hidden mb-[4rpx]">
                                            <view class="brand-tag" v-if="item.goods_brand" :style="diyGoods.baseTagStyle(item.goods_brand)">
                                                {{ item.goods_brand.brand_name }}
                                            </view>
                                            {{ item.goods_name }}
                                        </view>
                                        <!-- 副标题 -->
                                        <view v-if="item.sub_title" class="text-[24rpx]  w-[250rpx] text-[#999] leading-[34rpx] truncate mb-[6rpx]">
                                            {{ item.sub_title }}
                                        </view>
                                        <!-- 多个标签展示 -->
                                        <view v-if="item.goods_label_name && item.goods_label_name.length > 1" class="flex flex-wrap mb-[6rpx]">
                                            <template v-for="(tagItem, tagIndex) in item.goods_label_name.slice(1, 3)">
                                                <image class="inline-tag-img" v-if="tagItem.style_type == 'icon' && tagItem.icon" :src="img(tagItem.icon)" mode="heightFix" @error="diyGoods.error(tagItem,'icon')" />
                                                <view class="inline-tag" v-else-if="tagItem.style_type == 'diy' || !tagItem.icon" :style="diyGoods.baseTagStyle(tagItem)">
                                                    {{ tagItem.label_name }}
                                                </view>
                                            </template>
                                        </view>
                                        <!-- 串号 -->
                                        <view v-if="item.goodsSku && item.goodsSku.sku_no" class="text-[22rpx] text-[#666] mb-[4rpx] truncate w-[250rpx]">
                                            <text class="text-[#999]">串号:</text>
                                            <text class="ml-[6rpx]">{{ item.goodsSku.sku_no }}</text>
                                        </view>
                                       
                                        <view class="flex justify-between items-end mt-[8rpx]">
                                            <view class="flex items-baseline flex-wrap">
                                                <view class="text-[var(--price-text-color)] price-font flex items-baseline">
                                                    <text class="text-[24rpx] font-500">￥</text>
                                                    <text class="text-[40rpx] font-500">{{ diyGoods.goodsPrice(item).toFixed(2).split('.')[0] }}</text>
                                                    <text class="text-[24rpx] font-500">.{{ diyGoods.goodsPrice(item).toFixed(2).split('.')[1] }}</text>
                                                </view>
                                                <image v-if="diyGoods.priceType(item) == 'member_price'" class="max-w-[50rpx] h-[28rpx] ml-[6rpx]" :src="img('addon/phone_shop/VIP.png')" mode="heightFix" />
                                                <image v-if="diyGoods.priceType(item) == 'discount_price'" class="max-w-[50rpx] h-[28rpx] ml-[6rpx]" :src="img('addon/phone_shop/discount.png')" mode="heightFix" />
                                            </view>
                                            <!-- <text class="text-[22rpx] text-[var(--text-color-light9)]">库存{{ item.goodsSku?.stock || 0 }}</text> -->
                                        </view>
                                    </view>
                                </view>
                                <!-- 下载按钮 -->
                                <view class="download-btn-grid" @click.stop="downloadGoods(item)">
                                    <text class="nc-iconfont nc-icon-fenxiangV6xx"></text>
                                </view>
                            </view>
                        </template>
                    </view>
                </template>
            </view>
            <mescroll-empty v-if="!goodsList.length && loading" :option="{tip : '暂无商品', btnText:'去逛逛'}" @emptyclick="redirect({ url: '/addon/phone_shop/pages/index', mode: 'reLaunch' })"></mescroll-empty>
        </mescroll-body>

        <tabbar />

        <!-- 下载配置弹窗 -->
        <download-config-dialog
            :show="showConfigDialog"
            @close="showConfigDialog = false"
            @confirm="handleConfigConfirm"
        />
    </view>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue'
import { t } from '@/locale'
import { redirect, img, handleOnloadParams, getToken } from '@/utils/common';
import { getGoodsPages, getGoodsDetail, getBrandList } from '@/addon/phone_shop/api/goods';
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import GoodsFilter from '@/addon/phone_shop/components/goods-filter/goods-filter.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js';
import { onLoad, onPageScroll, onReachBottom } from '@dcloudio/uni-app';
import { useGoods } from '@/addon/phone_shop/hooks/useGoods'
import { useGoodsDownload } from '@/addon/phone_shop/hooks/useGoodsDownload'
import { type DownloadConfig } from '@/addon/phone_shop/hooks/useDownloadConfig'
import DownloadConfigDialog from '@/addon/phone_shop/components/download-config-dialog/download-config-dialog.vue'
import { applyThemeColor } from '@/addon/phone_shop/utils/theme'

const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);
const diyGoods = useGoods();
const {
    downloadGoodsImagesWithConfig,
    needShowConfigDialog,
    saveConfig
} = useGoodsDownload();
const goodsList = ref<Array<any>>([]);
const coupon_id = ref<number | string>('');
const mescrollRef = ref(null);
const goodsFilterRef = ref(null);
const loading = ref<boolean>(false);
const goods_name = ref("");
const listType = ref(true)
const filterData = ref<any>({})
const brandList = ref<Array<any>>([])

// 下载配置相关
const showConfigDialog = ref(false)
const pendingDownload = ref<{ images: string[], item: any } | null>(null)

// 获取状态栏高度（用于小程序安全区域）
const statusBarHeight = ref(0)
const headerStyle = ref('')
const headerSearchStyle = ref('')
const scrollTopOffset = ref('250rpx')

onLoad(async(option: any) => {
    // 获取系统信息，计算状态栏高度
    // #ifdef MP-WEIXIN
    const systemInfo = uni.getSystemInfoSync()
    statusBarHeight.value = systemInfo.statusBarHeight || 0
    // 计算顶部安全距离：状态栏高度 + 导航栏高度(44px) + 额外间距
    const topSafeHeight = statusBarHeight.value  
    headerStyle.value = `padding-top: ${topSafeHeight}px;`
    headerSearchStyle.value = `padding-right:100px`;
    // 更新滚动区域的top偏移：基础220rpx + 状态栏高度
    // scrollTopOffset.value = `${220 + statusBarHeight.value * 2}rpx`
    // #endif
     // #ifdef MP-WEIXIN
     option = handleOnloadParams(option);
    // #endif
    if (option && option.category_id) {
        // 设置初始筛选数据
        filterData.value = {
            category_id: option.category_id
        };
        // 等待筛选组件加载完成后,直接设置筛选状态
        setTimeout(() => {
            try {
                if (goodsFilterRef.value && goodsFilterRef.value.setFilters) {
                    goodsFilterRef.value.setFilters({ category_id: option.category_id })
                }
                // 触发列表刷新
                getMescroll().resetUpScroll();
            } catch (e) {
                console.error('初始化筛选失败:', e)
            }
        }, 600);
    }

    // #ifdef MP-WEIXIN
    // 处理小程序场景值参数
    option = handleOnloadParams(option);
    // #endif
    goods_name.value = option.goods_name ? decodeURIComponent(option.goods_name) : ''
    coupon_id.value = option.coupon_id || ''

    // 加载成色列表
    await loadBrandList()

   
})

interface mescrollStructure {
    num: number,
    size: number,
    endSuccess: Function,

    [propName: string]: any
}

const getAllAppListFn = (mescroll: mescrollStructure) => {
    loading.value = false;

    // 获取筛选数据
    const filtersFromWidget = goodsFilterRef.value?.getFilters() || {}
    // 合并外部传入的 filterData 与 widget filters：
    // 以 filterData 为默认值，只有当 widget 提供非空值时才覆盖
    const filters = Object.assign({}, filterData.value || {})
    Object.keys(filtersFromWidget).forEach(key => {
        const val = (filtersFromWidget as any)[key]
        if (val !== '' && val !== undefined && val !== null) {
            ;(filters as any)[key] = val
        }
    })

    let data: any = {
        page: mescroll.num,
        limit: mescroll.size,
        keyword: goods_name.value,
        coupon_id: coupon_id.value,
        goods_category: filters.category_id || '',
        brand_id: filters.brand_id || '',
        start_price: filters.price_min || undefined,
        end_price: filters.price_max || undefined,
        memory_id: filters.memory_id || undefined
    };

    // 添加排序参数
    if (filters.sort && filters.sort !== 'default') {
        // 处理排序：找到最后一个下划线，分割字段名和排序方向
        const lastUnderscoreIndex = filters.sort.lastIndexOf('_')
        const field = filters.sort.substring(0, lastUnderscoreIndex)
        const order = filters.sort.substring(lastUnderscoreIndex + 1)

        data.order = field
        data.sort = order
    }

    getGoodsPages(data).then((res: any) => {
        let newArr = (res.data.data as Array<Object>);
        //设置列表数据
        if (Number(mescroll.num) === 1) {
            goodsList.value = []; //如果是第一页需手动制空列表
        }
        goodsList.value = goodsList.value.concat(newArr);
        mescroll.endSuccess(newArr.length);
        loading.value = true;
    }).catch(() => {
        loading.value = true;
        mescroll.endErr(); // 请求失败, 结束加载
    })
}

// 搜索
const handleSearch = () => {
    goodsList.value = [];
    getMescroll().resetUpScroll();
}

// 筛选搜索
const handleFilterSearch = () => {
    goodsList.value = [];
    getMescroll().resetUpScroll();
}

//列表样式切换
const listIconBtn = () => {
    listType.value = !listType.value
}

const toDetail = (id: string | number) => {
    redirect({ url: '/addon/phone_shop/pages/goods/detail', param: { goods_id: id }, mode: 'navigateTo' })
}

// 主题颜色
const themeColor = () => {
    return applyThemeColor()
}

// 加载成色列表
const loadBrandList = async () => {
    try {
        const res = await getBrandList()
        if (res.code === 1) {
            brandList.value = res.data.map((item: any) => ({
                label: item.brand_name,
                value: item.brand_id
            }))
        }
    } catch (error) {
        console.error('加载成色列表失败:', error)
    }
}

// 获取成色标签
const getBrandLabel = (brandId: string | number) => {
    const brand = brandList.value.find(item => item.value == brandId)
    return brand ? brand.label : ''
}

// 执行下载
const performDownload = async (images: string[], item: any, config?: DownloadConfig) => {
    await downloadGoodsImagesWithConfig(images, item, config, undefined, true)
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

// 下载单个商品图片
const downloadGoods = async (item: any) => {
    try {
        const res = await getGoodsDetail({ goods_id: item.goods_id })
        if (!res.data.goods) {
            uni.showToast({ title: '商品信息获取失败', icon: 'none' })
            return
        }

        // 处理图片URL：分割并转换为完整URL
        const images = res.data.goods.goods_image.split(',').map((url: string) => img(url.trim()))

        // 检查是否需要显示配置弹窗
        if (needShowConfigDialog()) {
            // 保存待下载数据 - 使用完整的商品详情数据
            pendingDownload.value = { images, item: res.data }
            // 显示配置弹窗
            showConfigDialog.value = true
        } else {
            // 直接下载 - 使用完整的商品详情数据
            await performDownload(images, res.data)
        }
    } catch (error) {
        console.error('下载失败:', error)
        uni.showToast({ title: '下载失败', icon: 'none' })
    }
}

onMounted(() => {
    setTimeout(() => {
        getMescroll().optUp.textNoMore = t("end");
    }, 500)
});
</script>

<style lang="scss" scoped>
@import '@/addon/phone_shop/styles/common.scss';

.scroll-view-wrap {
    word-break: keep-all;
}

.text-color {
    color: var(--primary-color);
}

.product-warp {
    z-index: 99999;
}

:deep(.tab-bar-placeholder) {
    display: none !important;
}

:deep(.u-tabbar__placeholder) {
    display: none !important;
}

:deep(.u-input__content__clear) {
    width: 28rpx;
    height: 28rpx;
    font-size: 28rpx;
    background-color: var(--text-color-light9);
}

.biserial-goods-list {
    display: grid;
    grid-template-columns: 1fr 1fr;
    grid-gap: 10px;
}

// 下载按钮样式
.download-btn {
    width: 48rpx;
    height: 48rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--primary-color);
    border-radius: 24rpx;
    color: #fff;
    flex-shrink: 0;

    .nc-iconfont {
        font-size: 24rpx;
    }
}

// 瀑布流下载按钮
.download-btn-grid {
    position: absolute;
    right: 16rpx;
    bottom: 16rpx;
    width: 56rpx;
    height: 56rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--primary-color);
    border-radius: 28rpx;
    color: #fff;
    box-shadow: 0 4rpx 12rpx var(--primary-color-light);
    z-index: 10;

    .nc-iconfont {
        font-size: 28rpx;
    }
}

// 品牌标签
.brand-tag {
    display: inline-block;
    padding: 2rpx 8rpx;
    border-radius: 4rpx;
    font-size: 20rpx;
    margin-right: 6rpx;
    vertical-align: middle;
}

// 商品标签样式
.img-tag {
    height: 32rpx;
    margin-right: 8rpx;
    margin-bottom: 6rpx;
}

.base-tag {
    display: inline-block;
    padding: 4rpx 12rpx;
    border-radius: 4rpx;
    font-size: 20rpx;
    margin-right: 8rpx;
    margin-bottom: 6rpx;
}

// 搜索框样式
.search-input {
    display: flex;
    align-items: center;
    height: 60rpx;
    background-color: #f6f6f6;
    padding: 0 30rpx;
    border-radius: 100rpx;

    .input {
        flex: 1;
        font-size: 24rpx;
        padding-right: 32rpx;
        height: 100%;
    }

    .clear {
        font-size: 24rpx;
        color: #999;
        line-height: 1;
    }

    .btn {
        margin-bottom: -2rpx;
        margin-right: 18rpx;
        font-size: 24rpx;
        color: #333;
    }
}

// 价格字体
.price-font {
    font-family: 'DIN', 'PingFang SC', sans-serif;
}

// 左上角标签样式 - 列表视图
.corner-tag {
    display: inline-block;
    padding: 6rpx 16rpx;
    border-radius: 0 0 12rpx 0;
    font-size: 20rpx;
    font-weight: 500;
    color: #fff;
    box-shadow: 2rpx 2rpx 8rpx rgba(0, 0, 0, 0.1);
}

.corner-tag-img {
    height: 48rpx;
    display: block;
    border-radius: 0 0 12rpx 0;
    box-shadow: 2rpx 2rpx 8rpx rgba(0, 0, 0, 0.1);
}

// 左上角标签样式 - 瀑布流视图
.corner-tag-grid {
    display: inline-block;
    padding: 8rpx 20rpx;
    border-radius: 0 0 16rpx 0;
    font-size: 22rpx;
    font-weight: 500;
    color: #fff;
    box-shadow: 2rpx 2rpx 10rpx rgba(0, 0, 0, 0.15);
}

.corner-tag-img-grid {
    height: 56rpx;
    display: block;
    border-radius: 0 0 16rpx 0;
    box-shadow: 2rpx 2rpx 10rpx rgba(0, 0, 0, 0.15);
}

// 内联标签样式
.inline-tag {
    display: inline-block;
    padding: 4rpx 12rpx;
    border-radius: 6rpx;
    font-size: 20rpx;
    margin-right: 8rpx;
    margin-bottom: 6rpx;
    font-weight: 400;
}

.inline-tag-img {
    height: 32rpx;
    margin-right: 8rpx;
    margin-bottom: 6rpx;
}
</style>
