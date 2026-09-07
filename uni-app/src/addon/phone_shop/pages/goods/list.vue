<template>
    <view class="bg-gray-100 min-h-[100vh]" :style="themeColor()">
        <view class="fixed left-0 right-0 top-0 product-warp bg-[#fff]" :style="headerStyle">
            <view class="search-row" :style="searchRowStyle">
                <!-- #ifndef H5 -->
                <view v-if="showBack" class="header-back" @click="back">
                    <text class="nc-iconfont nc-icon-zuoV6xx"></text>
                </view>
                <!-- #endif -->
                <view class="flex-1 search-input bg-[#f5f7fa]">
                    <text @click.stop="submitSearch" class="nc-iconfont nc-icon-sousuo-duanV6xx1 btn"></text>
                    <input class="input" maxlength="50" type="text" v-model="goods_name"
                           placeholder="搜索型号、商品或关键词"
                           placeholderClass="text-[var(--text-color-light9)] text-[24rpx]" confirm-type="search"
                           @confirm="submitSearch">
                    <text v-if="goods_name" class="nc-iconfont nc-icon-cuohaoV6xx1 clear" @click="goods_name=''"></text>
                </view>
                <view class="list-mode-button" @click="listIconBtn">
                    <view :class="['iconfont text-[32rpx] text-[#475569]', listType ? 'icona-yingyongzhongxinV6xx-32' : 'icona-yingyongliebiaoV6xx-32']"></view>
                </view>
                <view v-if="goodsAction?.event === 'cart'" class="list-mode-button" @click="toCart">
                    <text class="nc-iconfont nc-icon-gouwucheV6xx6 text-[34rpx]"></text>
                </view>
            </view>
            <scroll-view scroll-x :enable-flex="true" :show-scrollbar="false" class="filter-toolbar">
                <view class="filter-toolbar__inner">
                    <view class="filter-entry" :class="{ 'filter-entry--active': filters.category_ids.length || categorySubscriptionCount }" @click="openCategory">
                        <u-icon
                            :name="categorySubscriptionCount ? 'bell-fill' : 'bell'"
                            :color="categorySubscriptionCount ? 'var(--primary-color)' : '#64748b'"
                            size="15"
                        />
                        <text class="ml-[5rpx]">分类</text>
                        <text v-if="categorySubscriptionCount" class="filter-entry__subscribed">已订阅{{ categorySubscriptionCount }}</text>
                        <text v-else-if="filters.category_ids.length" class="filter-entry__count">{{ filters.category_ids.length }}</text>
                        <u-icon name="arrow-down-fill" size="10" :color="filters.category_ids.length || categorySubscriptionCount ? 'var(--primary-color)' : '#94a3b8'" />
                    </view>
                    <view class="filter-entry" :class="{ 'filter-entry--active': filters.memory_group.length }" @click="popup.memory = true">
                        内存<text v-if="filters.memory_group.length" class="filter-entry__count">{{ filters.memory_group.length }}</text><u-icon name="arrow-down-fill" size="10" :color="filters.memory_group.length ? 'var(--primary-color)' : '#94a3b8'" />
                    </view>
                    <view class="filter-entry" :class="{ 'filter-entry--active': filters.condition_grade.length }" @click="popup.grade = true">
                        成色<text v-if="filters.condition_grade.length" class="filter-entry__count">{{ filters.condition_grade.length }}</text><u-icon name="arrow-down-fill" size="10" :color="filters.condition_grade.length ? 'var(--primary-color)' : '#94a3b8'" />
                    </view>
                    <view v-if="filterOptions.colors?.length" class="filter-entry" :class="{ 'filter-entry--active': filters.device_color.length }" @click="popup.color = true">
                        颜色<text v-if="filters.device_color.length" class="filter-entry__count">{{ filters.device_color.length }}</text><u-icon name="arrow-down-fill" size="10" :color="filters.device_color.length ? 'var(--primary-color)' : '#94a3b8'" />
                    </view>
                    <view class="filter-entry" :class="{ 'filter-entry--active': filters.label_ids.length }" @click="popup.label = true">
                        标签<text v-if="filters.label_ids.length" class="filter-entry__count">{{ filters.label_ids.length }}</text><u-icon name="arrow-down-fill" size="10" :color="filters.label_ids.length ? 'var(--primary-color)' : '#94a3b8'" />
                    </view>
                    <view class="filter-entry" :class="{ 'filter-entry--active': filters.service_ids.length }" @click="popup.service = true">
                        服务<text v-if="filters.service_ids.length" class="filter-entry__count">{{ filters.service_ids.length }}</text><u-icon name="arrow-down-fill" size="10" :color="filters.service_ids.length ? 'var(--primary-color)' : '#94a3b8'" />
                    </view>
                    <view class="filter-entry" :class="{ 'filter-entry--active': moreFilterCount }" @click="openMore">
                        更多<text v-if="moreFilterCount" class="filter-entry__count">{{ moreFilterCount }}</text><u-icon name="arrow-down-fill" size="10" :color="moreFilterCount ? 'var(--primary-color)' : '#94a3b8'" />
                    </view>
                </view>
            </scroll-view>
        </view>

        <GoodsCategoryFilterPopup
            v-model:show="popup.category"
            :categories="filterOptions.categories"
            :model-value="filters.category_ids"
            :subscription-map="categorySubscriptionMap"
            :subscription-loading-id="categorySubscriptionLoadingId"
            @confirm="applyFilter('category_ids', $event)"
            @subscribe-node="subscribeCategoryNode"
            @cancel-node="cancelCategoryNode"
        />
        <GoodsOptionFilterPopup v-model:show="popup.memory" title="选择内存" :tip="filterContextTip" :model-value="filters.memory_group" :groups="memoryGroups" @confirm="applyFilter('memory_group', $event)" />
        <GoodsOptionFilterPopup v-model:show="popup.grade" title="选择成色" :tip="filterContextTip" :model-value="filters.condition_grade" :groups="gradeGroups" @confirm="applyFilter('condition_grade', $event)" />
        <GoodsOptionFilterPopup v-model:show="popup.color" title="选择颜色" :tip="filterContextTip" :model-value="filters.device_color" :groups="colorGroups" @confirm="applyFilter('device_color', $event)" />
        <GoodsOptionFilterPopup v-model:show="popup.label" title="选择标签" :model-value="filters.label_ids" :groups="labelGroups" @confirm="applyFilter('label_ids', $event)" />
        <GoodsOptionFilterPopup v-model:show="popup.service" title="选择服务" :model-value="filters.service_ids" :groups="serviceGroups" @confirm="applyFilter('service_ids', $event)" />
        <GoodsMoreFilterPopup
            v-model:show="popup.more"
            :model-value="moreFilterValue"
            :options="filterOptions"
            :subscribed="subscription.subscribed"
            :can-subscribe="hasSubscriptionRule"
            :subscription-loading="subscription.loading"
            @confirm="applyMoreFilters"
            @subscribe="subscribeCurrentRule"
            @cancel-subscription="cancelCurrentSubscription"
        />
        <ShareDownload
            ref="shareDownloadRef"
            :goods-item="forwardItem"
            :user-id="memberStore.info?.member_id"
            :show-trigger="false"
            back-url="/addon/phone_shop/pages/goods/list"
        />

        <mescroll-body ref="mescrollRef" :top="mescrollTop" bottom="60px" @init="mescrollInit" :down="{ use: false }" @up="getAllAppListFn">
            <view v-if="categoryConfigFailed" class="config-retry" @click="loadCategoryConfig">商品操作配置加载失败，点击重试</view>
            <view v-if="goodsList.length" class="sidebar-margin">
                <template v-if="listType">
                    <view v-for="(item, index) in goodsList" :key="index"
                          class="goods-row-card bg-white flex p-[12rpx]  rounded-[var(--rounded-small)] overflow-hidden top-mar"
                          :class="{ 'mb-[20rpx]': (index+1) == goodsList.length}" @click="toDetail(item.goods_id)">
                        <PhoneGoodsCover :src="item.goods_cover_thumb_mid" :grade="item.condition_grade" />

                        <view class="goods-row-content flex-1 flex flex-col ml-[20rpx]">
                            <view class="goods-row-title text-[28rpx] text-[#333] leading-[40rpx] multi-hidden">
                                <view class="brand-tag" v-if="item.goods_brand" :style="diyGoods.baseTagStyle(item.goods_brand)">{{ item.goods_brand.brand_name }}</view>
                                {{ item.goods_name }}
                            </view>
                            <PhoneGoodsMeta :subtitle="item.sub_title" :imei="item.goodsSku?.sku_no" />
                            <PhoneGoodsSaleState :state="item.sale_state" />

                            <view class="goods-row-footer">
                                <view class="goods-row-price flex items-baseline">
                                  <view class="flex items-baseline">
                                    <view class="text-[var(--price-text-color)] price-font flex items-baseline">
                                        <text class="text-[24rpx] font-500 mr-[4rpx]">￥</text>
                                        <text class="text-[40rpx] font-500">{{ diyGoods.goodsPrice(item).toFixed(2).split('.')[0] }}</text>
                                        <text class="text-[24rpx] font-500">.{{ diyGoods.goodsPrice(item).toFixed(2).split('.')[1] }}</text>
                                    </view>
                                    <image v-if="diyGoods.priceType(item) == 'member_price'"
                                           class="max-w-[50rpx] h-[28rpx] ml-[6rpx]" :src="img('addon/phone_shop/VIP.png')"
                                           mode="heightFix" />
									<image v-else-if="diyGoods.priceType(item) == 'newcomer_price'"
									       class="max-w-[60rpx] h-[28rpx] ml-[6rpx]" :src="img('addon/phone_shop/newcomer.png')"
									       mode="heightFix" />
									<image v-else-if="diyGoods.priceType(item) == 'discount_price'"
									       class="max-w-[80rpx] h-[28rpx] ml-[6rpx]" :src="img('addon/phone_shop/discount.png')"
									       mode="heightFix" />
                                  </view>
                                </view>
                                <PhoneGoodsActionButton :action="goodsAction" @action="handleGoodsAction(item)" />
                            </view>
                        </view>
                    </view>
                </template>
                <template v-else>
                    <PhoneGoodsWaterfall :items="goodsList" :estimate-height="estimateGoodsCardHeight">
                        <template #default="{ item }">
                            <PhoneGoodsWaterfallCard :item="item" :action="goodsAction" @click="toDetail(item.goods_id)" @action="handleGoodsAction(item)" />
                        </template>
                    </PhoneGoodsWaterfall>
                </template>
            </view>
            <mescroll-empty v-if="!goodsList.length && loading" :option="{tip : '暂无商品', btnText:'去逛逛'}" @emptyclick="redirect({ url: '/addon/phone_shop/pages/index', mode: 'reLaunch' })"></mescroll-empty>
        </mescroll-body>

        <add-cart-popup ref="cartRef" />
        <tabbar />
    </view>
</template>

<script setup lang="ts">
import { computed, reactive, ref, onMounted, nextTick } from 'vue'
import { t } from '@/locale'
import { redirect, img, handleOnloadParams } from '@/utils/common';
import {
    addGoodsSubscription,
    cancelGoodsSubscription,
    getGoodsFilterOptions,
    getGoodsCategoryConfig,
    getGoodsPages,
    getGoodsSubscriptionList,
    getGoodsSubscriptionStatus
} from '@/addon/phone_shop/api/goods';
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js';
import { onLoad, onShow, onPageScroll, onReachBottom } from '@dcloudio/uni-app';
import { useGoods } from '@/addon/phone_shop/hooks/useGoods'
import GoodsCategoryFilterPopup from '@/addon/phone_shop/components/goods-filter/GoodsCategoryFilterPopup.vue'
import GoodsOptionFilterPopup from '@/addon/phone_shop/components/goods-filter/GoodsOptionFilterPopup.vue'
import GoodsMoreFilterPopup from '@/addon/phone_shop/components/goods-filter/GoodsMoreFilterPopup.vue'
import PhoneGoodsMeta from '@/addon/phone_shop/components/PhoneGoodsMeta.vue'
import PhoneGoodsSaleState from '@/addon/phone_shop/components/PhoneGoodsSaleState.vue'
import PhoneGoodsCover from '@/addon/phone_shop/components/PhoneGoodsCover.vue'
import PhoneGoodsWaterfall from '@/addon/phone_shop/components/PhoneGoodsWaterfall.vue'
import PhoneGoodsWaterfallCard from '@/addon/phone_shop/components/PhoneGoodsWaterfallCard.vue'
import PhoneGoodsActionButton from '@/addon/phone_shop/components/PhoneGoodsActionButton.vue'
import addCartPopup from './components/add-cart-popup.vue'
import { resolveGoodsCardAction } from '@/addon/phone_shop/utils/goods-card'
import { useGoodsDetailNavigation } from '@/addon/phone_shop/hooks/useGoodsDetailNavigation'
import ShareDownload from '@/addon/phone_shop/components/share-download/share-download.vue'
import useMemberStore from '@/stores/member'
import { useLogin } from '@/hooks/useLogin'
import { useGoodsSubscriptionNotice } from '@/addon/phone_shop/hooks/useGoodsSubscriptionNotice'

const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);
const diyGoods = useGoods();
const goodsList = ref<Array<any>>([]);
const coupon_id = ref<number | string>('');
const mescrollRef = ref(null);
const loading = ref<boolean>(false);
const goods_name = ref("");
const price = ref("");
const sale_num = ref("");
const searchType = ref('all');
const listType = ref(true)
const memberStore = useMemberStore()
const systemInfo = ref<any>(uni.getSystemInfoSync())
const menuButtonInfo = ref<any>({})
const showBack = ref(false)
const shareDownloadRef = ref<any>(null)
const forwardItem = ref<any>({})
const cartRef = ref<any>(null)
const categoryConfig = ref<any>(null)
const categoryConfigFailed = ref(false)
const goodsAction = computed(() => resolveGoodsCardAction(categoryConfig.value))
const { openGoodsDetail } = useGoodsDetailNavigation()
let configRequest: Promise<boolean> | null = null
const loadCategoryConfig = () => {
    if (configRequest) return configRequest
    configRequest = getGoodsCategoryConfig().then((res: any) => {
        if (!res.data?.cart) throw new Error('商品操作配置不完整')
        categoryConfig.value = res.data
        categoryConfigFailed.value = false
        return true
    }).catch(() => {
        categoryConfig.value = null
        categoryConfigFailed.value = true
        return false
    }).finally(() => { configRequest = null })
    return configRequest
}
onShow(() => { void loadCategoryConfig() })
const { requestAuthorization: requestSubscriptionAuthorization, explainAuthorization } = useGoodsSubscriptionNotice()

// #ifdef MP-WEIXIN || MP-BAIDU || MP-TOUTIAO || MP-QQ
try {
    menuButtonInfo.value = uni.getMenuButtonBoundingClientRect() || {}
} catch (e) {
    menuButtonInfo.value = {}
}
// #endif

const capsuleReady = computed(() => Number(menuButtonInfo.value?.height || 0) > 0)
const statusTopPx = computed(() => {
    if (capsuleReady.value) return Number(menuButtonInfo.value.top || 0)
    return Number(systemInfo.value?.statusBarHeight || 0)
})
const capsuleRightInsetPx = computed(() => {
    if (!capsuleReady.value) return 0
    const windowWidth = Number(systemInfo.value?.windowWidth || systemInfo.value?.screenWidth || 0)
    const capsuleLeft = Number(menuButtonInfo.value?.left || 0)
    return windowWidth > capsuleLeft ? windowWidth - capsuleLeft + 8 : 0
})
const headerStyle = computed(() => statusTopPx.value > 0
    ? `padding-top:${ statusTopPx.value }px;`
    : '')
const searchRowStyle = computed(() => {
    if (!capsuleReady.value) return ''
    return `height:calc(${ Number(menuButtonInfo.value.height) }px + 20rpx);padding-right:${ capsuleRightInsetPx.value }px;`
})
const mescrollTop = computed(() => {
    if (capsuleReady.value) {
        return `${ statusTopPx.value + Number(menuButtonInfo.value.height) + uni.upx2px(100) }px`
    }
    // App 自定义导航需要额外避让系统状态栏；H5 仍使用原生导航。
    return `${ statusTopPx.value + uni.upx2px(168) }px`
})

const filters = reactive({
    category_ids: [] as string[],
    memory_group: [] as string[],
    condition_grade: [] as string[],
    device_color: [] as string[],
    battery_range: [] as string[],
    warranty_range: [] as string[],
    label_ids: [] as string[],
    service_ids: [] as string[],
    brand_ids: [] as string[],
    start_price: '' as string | number,
    end_price: '' as string | number,
    warehouse: '',
    in_stock: false
})

const popup = reactive({
    category: false,
    memory: false,
    grade: false,
    color: false,
    label: false,
    service: false,
    more: false
})

const subscription = reactive({
    subscribed: false,
    subscription_id: 0,
    loading: false
})
const categorySubscriptionMap = reactive<Record<string, number>>({})
const categorySubscriptionLoadingId = ref('')
const categorySubscriptionCount = computed(() => Object.keys(categorySubscriptionMap).length)

const filterOptions = reactive<any>({
    categories: [],
    memories: [],
    grades: [],
    colors: [],
    battery_ranges: [],
    warranty_ranges: [],
    label_groups: [],
    services: [],
    brands: [],
    warehouses: {},
    price_ranges: []
})

const memoryGroups = computed(() => [{
    key: 'memory',
    title: '内存容量',
    items: (filterOptions.memories || []).map((item: any) => ({
        value: item.value,
        label: item.label
    }))
}])

const gradeGroups = computed(() => [{
    key: 'grade',
    title: '设备成色',
    items: (filterOptions.grades || []).map((item: any) => ({
        value: item.grade_name,
        label: item.grade_name
    }))
}])

const colorGroups = computed(() => [{
    key: 'color',
    title: '机身颜色',
    items: (filterOptions.colors || []).map((item: any) => ({
        value: item.value,
        label: item.label
    }))
}])

const filterContextTip = computed(() => filters.category_ids.length
    ? '已按当前分类收敛可选项'
    : '当前展示全部在售商品的可选项')

const labelGroups = computed(() => (filterOptions.label_groups || []).map((group: any) => ({
    key: group.group_id,
    title: group.group_name,
    items: (group.items || []).map((item: any) => ({
        value: item.label_id,
        label: item.label_name
    }))
})))

const serviceGroups = computed(() => [{
    key: 'service',
    title: '商品服务',
    items: (filterOptions.services || []).map((item: any) => ({
        value: item.service_id,
        label: item.service_name,
        desc: item.desc
    }))
}])

const moreFilterValue = computed(() => ({
    start_price: filters.start_price,
    end_price: filters.end_price,
    brand_ids: filters.brand_ids,
    battery_range: filters.battery_range,
    warranty_range: filters.warranty_range,
    warehouse: filters.warehouse,
    in_stock: filters.in_stock,
    order: searchType.value,
    sort: searchType.value === 'price' ? price.value : sale_num.value
}))

const moreFilterCount = computed(() => {
    let count = filters.brand_ids.length
    if (filters.battery_range.length) count++
    if (filters.warranty_range.length) count++
    if (filters.start_price !== '' || filters.end_price !== '') count++
    if (filters.warehouse) count++
    if (filters.in_stock) count++
    if (searchType.value !== 'all') count++
    return count
})

const currentSubscriptionRule = computed(() => {
    const rule: Record<string, any> = {}
    const keyword = goods_name.value.trim()
    if (keyword) rule.keyword = keyword
    if (filters.category_ids.length) rule.category_ids = [...filters.category_ids]
    if (filters.memory_group.length) rule.memory_group = [...filters.memory_group]
    if (filters.condition_grade.length) rule.condition_grade = [...filters.condition_grade]
    if (filters.device_color.length) rule.device_color = [...filters.device_color]
    if (filters.battery_range.length) rule.battery_range = [...filters.battery_range]
    if (filters.warranty_range.length) rule.warranty_range = [...filters.warranty_range]
    if (filters.label_ids.length) rule.label_ids = [...filters.label_ids]
    if (filters.service_ids.length) rule.service_ids = [...filters.service_ids]
    if (filters.brand_ids.length) rule.brand_ids = [...filters.brand_ids]
    if (filters.start_price !== '') rule.start_price = filters.start_price
    if (filters.end_price !== '') rule.end_price = filters.end_price
    if (filters.warehouse) rule.warehouse = filters.warehouse
    if (filters.in_stock) rule.in_stock = 1
    return rule
})

const hasSubscriptionRule = computed(() => Object.keys(currentSubscriptionRule.value).length > 0)

onLoad(async(option: any) => {
    showBack.value = getCurrentPages().length > 1
    // #ifdef MP-WEIXIN
    // 处理小程序场景值参数
    option = handleOnloadParams(option);
    // #endif
    if (option.curr_goods_category) filters.category_ids = [String(option.curr_goods_category)]
    goods_name.value = option.goods_name ? decodeURIComponent(option.goods_name) : ''
    coupon_id.value = option.coupon_id || ''
    await loadFilterOptions()
    if (memberStore.token) {
        await loadCategorySubscriptions().catch(() => {})
    }
})

interface mescrollStructure {
    num: number,
    size: number,
    endSuccess: Function,
    [propName: string]: any
}

const getAllAppListFn = (mescroll: mescrollStructure) => {
    loading.value = false;
    let data: object = {
        goods_category: filters.category_ids.join(','),
        page: mescroll.num,
        limit: mescroll.size,
        keyword: goods_name.value,
        coupon_id: coupon_id.value,
        order: searchType.value === 'all' ? '' : searchType.value,
        sort: searchType.value == 'price' ? price.value : (searchType.value === 'sale_num' ? sale_num.value : 'desc'),
        memory_group: filters.memory_group.join(','),
        condition_grade: filters.condition_grade.join(','),
        device_color: filters.device_color.join(','),
        battery_range: filters.battery_range.join(','),
        warranty_range: filters.warranty_range.join(','),
        label_ids: filters.label_ids.join(','),
        service_ids: filters.service_ids.join(','),
        brand_id: filters.brand_ids.join(','),
        start_price: filters.start_price,
        end_price: filters.end_price,
        warehouse: filters.warehouse,
        in_stock: filters.in_stock ? 1 : ''
    };
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

onPageScroll((e)=> {
    // uni.$emit('scroll')
})

const refreshList = () => {
    goodsList.value = [];
    const mescroll = getMescroll()
    if (mescroll) mescroll.resetUpScroll();
}

const submitSearch = () => refreshList()

const resetSubscriptionState = () => {
    subscription.subscribed = false
    subscription.subscription_id = 0
}

const ensureLogin = () => {
    if (memberStore.token) return true
    useLogin().setLoginBack({
        url: '/addon/phone_shop/pages/goods/list',
        param: goods_name.value ? { goods_name: goods_name.value } : {}
    })
    return false
}

const loadCategorySubscriptions = async() => {
    Object.keys(categorySubscriptionMap).forEach(key => delete categorySubscriptionMap[key])
    if (!memberStore.token) return
    const res: any = await getGoodsSubscriptionList({ page: 1, limit: 120 })
    const list = res.data?.data || res.data?.list || []
    list.forEach((item: any) => {
        if (Number(item.status) !== 1) return
        const rule = item.rule || {}
        const keys = Object.keys(rule)
        const categories = Array.isArray(rule.category_ids) ? rule.category_ids : []
        if (keys.length !== 1 || categories.length !== 1) return
        categorySubscriptionMap[String(categories[0])] = Number(item.subscription_id || 0)
    })
}

const openCategory = async() => {
    popup.category = true
    if (!memberStore.token) return
    try {
        await loadCategorySubscriptions()
    } catch (e) {}
}

const subscribeCategoryNode = async(node: any) => {
    if (!ensureLogin() || !node?.category_id || categorySubscriptionLoadingId.value) return
    const id = String(node.category_id)
    categorySubscriptionLoadingId.value = id
    try {
        const authorization = await requestSubscriptionAuthorization()
        const res: any = await addGoodsSubscription({
            name: `分类上新 · ${node.category_name || '商品'}`,
            rule: { category_ids: [id] }
        })
        categorySubscriptionMap[id] = Number(res.data?.subscription_id || res.data || 0)
        explainAuthorization(authorization)
    } finally {
        categorySubscriptionLoadingId.value = ''
    }
}

const cancelCategoryNode = async(node: any) => {
    if (!ensureLogin() || !node?.category_id || categorySubscriptionLoadingId.value) return
    const id = String(node.category_id)
    categorySubscriptionLoadingId.value = id
    try {
        const subscriptionId = Number(categorySubscriptionMap[id] || 0)
        await cancelGoodsSubscription(subscriptionId
            ? { subscription_id: subscriptionId }
            : { rule: { category_ids: [id] } })
        delete categorySubscriptionMap[id]
    } finally {
        categorySubscriptionLoadingId.value = ''
    }
}

const loadSubscriptionStatus = async() => {
    resetSubscriptionState()
    if (!memberStore.token || !hasSubscriptionRule.value) return
    subscription.loading = true
    try {
        const res: any = await getGoodsSubscriptionStatus(currentSubscriptionRule.value)
        subscription.subscribed = Boolean(res.data?.subscribed)
        subscription.subscription_id = Number(res.data?.subscription_id || 0)
    } finally {
        subscription.loading = false
    }
}

const openMore = async() => {
    popup.more = true
    await loadSubscriptionStatus()
}

const subscribeCurrentRule = async() => {
    if (!ensureLogin() || !hasSubscriptionRule.value || subscription.loading) return
    subscription.loading = true
    try {
        const authorization = await requestSubscriptionAuthorization()
        const res: any = await addGoodsSubscription({ rule: currentSubscriptionRule.value })
        subscription.subscribed = true
        subscription.subscription_id = Number(res.data?.subscription_id || res.data || 0)
        explainAuthorization(authorization)
    } finally {
        subscription.loading = false
    }
}

const cancelCurrentSubscription = async() => {
    if (!ensureLogin() || subscription.loading) return
    subscription.loading = true
    try {
        await cancelGoodsSubscription(subscription.subscription_id
            ? { subscription_id: subscription.subscription_id }
            : { rule: currentSubscriptionRule.value })
        resetSubscriptionState()
    } finally {
        subscription.loading = false
    }
}

let filterOptionRequestId = 0
const loadFilterOptions = async() => {
    const requestId = ++filterOptionRequestId
    try {
        const res: any = await getGoodsFilterOptions({
            goods_category: filters.category_ids.join(','),
            brand_id: filters.brand_ids.join(','),
            memory_group: filters.memory_group.join(','),
            condition_grade: filters.condition_grade.join(','),
            device_color: filters.device_color.join(',')
        })
        if (requestId !== filterOptionRequestId) return
        Object.assign(filterOptions, res.data || {})
    } catch (e) {}
}

const applyFilter = async(key: 'category_ids' | 'memory_group' | 'condition_grade' | 'device_color' | 'label_ids' | 'service_ids', value: string[]) => {
    filters[key] = value
    if (key === 'category_ids') {
        filters.memory_group = []
        filters.condition_grade = []
        filters.device_color = []
        filters.battery_range = []
        filters.warranty_range = []
    }
    if (key === 'category_ids' || key === 'memory_group' || key === 'condition_grade' || key === 'device_color') {
        await loadFilterOptions()
    }
    resetSubscriptionState()
    refreshList()
}

const applyMoreFilters = (value: any) => {
    const previousBrands = filters.brand_ids.join(',')
    filters.start_price = value.start_price
    filters.end_price = value.end_price
    filters.brand_ids = value.brand_ids || []
    filters.battery_range = value.battery_range || []
    filters.warranty_range = value.warranty_range || []
    filters.warehouse = value.warehouse || ''
    filters.in_stock = Boolean(value.in_stock)
    searchType.value = value.order || 'all'
    price.value = searchType.value === 'price' ? (value.sort || 'asc') : ''
    sale_num.value = searchType.value === 'sale_num' ? (value.sort || 'desc') : ''
    if (previousBrands !== filters.brand_ids.join(',')) {
        filters.memory_group = []
        filters.condition_grade = []
        filters.device_color = []
    }
    loadFilterOptions()
    resetSubscriptionState()
    refreshList()
}

//列表样式切换
const listIconBtn = () => {
    listType.value = !listType.value
}

const back = () => {
    if (getCurrentPages().length > 1) {
        uni.navigateBack()
        return
    }
    redirect({ url: '/addon/phone_shop/pages/index', mode: 'reLaunch' })
}

const visualTextLength = (value: unknown) => String(value || '').split('').reduce((total, char) => {
    return total + (/^[\u0000-\u00ff]$/.test(char) ? 0.55 : 1)
}, 0)

/**
 * 使用固定封面和已知信息区估算卡片高度，不依赖节点测量。
 * 这样小程序滚动时不会反复重排，追加分页数据时已有商品也不会跳列。
 */
const estimateGoodsCardHeight = (item: Record<string, any>) => {
    const titleLines = visualTextLength(item?.goods_name) > 12 ? 2 : 1
    let height = 344 + 16 + titleLines * 40 + 22

    if (String(item?.sub_title || '').trim()) height += 33
    if (String(item?.goodsSku?.sku_no || '').trim()) height += 37
    if (item?.sale_state?.code && item.sale_state.code !== 'sellable') height += 42

    return height + 72
}

const toDetail = async(id: string | number) => {
    // 配置未就绪时不绕过登录规则；失败后再次点击可以重试。
    if ((configRequest || !categoryConfig.value) && !await loadCategoryConfig()) {
        uni.showToast({ title: '商品配置加载失败，请重试', icon: 'none' })
        return
    }
    openGoodsDetail(id, categoryConfig.value)
}

const handleGoodsAction = async(item: any) => {
    if (configRequest && !await configRequest) return
    const action = goodsAction.value
    if (!action) return
    if (action.event === 'download') return forwardGoods(item)
    if (action.event === 'detail' || (item.goods_type === 'virtual' && item.virtual_receive_type === 'verify')) return toDetail(item.goods_id)
    if (!ensureLogin()) return
    if (!item.goodsSku?.sku_id || Number(item.goodsSku.stock) <= 0) {
        uni.showToast({ title: '商品库存不足，暂时无法加入购物车', icon: 'none' })
        return
    }
    cartRef.value?.open(item.goodsSku.sku_id)
}

const toCart = () => {
    if (ensureLogin()) redirect({ url: '/addon/phone_shop/pages/goods/cart' })
}

const forwardGoods = async(item: any) => {
    forwardItem.value = item || {}
    await nextTick()
    await shareDownloadRef.value?.handleDownload?.()
}
onMounted(() => {
    setTimeout(() => {
        getMescroll().optUp.textNoMore = t("end");
    }, 500)
});
</script>

<style lang="scss" scoped>
@import '@/addon/phone_shop/styles/common.scss';

.config-retry {
    padding: 20rpx;
    margin: 16rpx 24rpx;
    border-radius: 12rpx;
    background: #fff7ed;
    color: #9a3412;
    font-size: 24rpx;
    text-align: center;
}

.product-warp {
    z-index: 100;
    box-sizing: border-box;
    box-shadow: 0 8rpx 22rpx rgba(15, 23, 42, 0.04);
}

.search-row {
    height: 88rpx;
    padding: 12rpx 20rpx 8rpx;
    display: flex;
    align-items: center;
    box-sizing: border-box;
}

.header-back {
    width: 64rpx;
    height: 64rpx;
    margin-right: 10rpx;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    flex-shrink: 0;
    color: #1f2937;
    font-size: 42rpx;
}

.header-back .nc-iconfont {
    font-size: 42rpx;
    line-height: 1;
}

.search-input {
    margin-right: 14rpx;
    border: 1rpx solid #edf1f5;
}

.list-mode-button {
    width: 64rpx;
    height: 64rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border-radius: 50%;
    background: #f5f7fa;
}

.filter-toolbar {
    width: 100%;
    height: 80rpx;
    white-space: nowrap;
    border-top: 1rpx solid #f4f6f8;
    border-bottom: 1rpx solid #eef2f6;
    background: linear-gradient(180deg, #fff 0%, #fbfcfe 100%);
}

.filter-toolbar__inner {
    width: max-content;
    min-width: 100%;
    height: 80rpx;
    padding: 0 20rpx;
    display: inline-flex;
    align-items: center;
    box-sizing: border-box;
    gap: 10rpx;
}

.filter-entry {
    position: relative;
    height: 54rpx;
    padding: 0 18rpx;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
    border: 1rpx solid #eef2f6;
    border-radius: 28rpx;
    color: #475569;
    background: #f5f7fa;
    font-size: 24rpx;
    flex-shrink: 0;
}

.filter-entry--active {
    color: var(--primary-color);
    border-color: rgba(var(--primary-color-rgb, 18, 85, 231), 0.16);
    background: rgba(var(--primary-color-rgb, 18, 85, 231), 0.08);
    box-shadow: 0 5rpx 14rpx rgba(var(--primary-color-rgb, 18, 85, 231), 0.07);
    font-weight: 600;
}

.filter-entry__count {
    min-width: 28rpx;
    height: 28rpx;
    margin-left: 6rpx;
    padding: 0 6rpx;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
    border-radius: 14rpx;
    color: #fff;
    background: var(--primary-color);
    font-size: 18rpx;
}

.filter-entry__subscribed {
    height: 30rpx;
    margin-left: 6rpx;
    padding: 0 8rpx;
    display: inline-flex;
    align-items: center;
    border-radius: 15rpx;
    color: var(--primary-color);
    background: #fff;
    font-size: 18rpx;
    font-weight: 600;
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

.goods-row-card {
    // min-height: 270rpx;
    align-items: stretch;

}

.goods-row-content {
    min-width: 0;
    padding: 2rpx 0;
    overflow: hidden;
    box-sizing: border-box;
}

.goods-row-title {
    flex-shrink: 0;
    max-height: 80rpx;
    margin-bottom: 2rpx;
}

.goods-row-price {
    min-width: 0;
    overflow: hidden;
    box-sizing: border-box;
    flex: 1 0 auto;
}

.goods-row-footer {
    min-width: 0;
    margin-top: auto;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12rpx;
}


</style>
