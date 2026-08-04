<template>
    <view class="bg-gray-100 min-h-[100vh]" :style="themeColor()">
        <view class="fixed left-0 right-0 top-0 product-warp bg-[#fff]">
            <view class="search-row">
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
        <GoodsOptionFilterPopup v-model:show="popup.memory" title="选择内存" :model-value="filters.memory_group" :groups="memoryGroups" @confirm="applyFilter('memory_group', $event)" />
        <GoodsOptionFilterPopup v-model:show="popup.grade" title="选择成色" :model-value="filters.condition_grade" :groups="gradeGroups" @confirm="applyFilter('condition_grade', $event)" />
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

        <mescroll-body ref="mescrollRef" top="168rpx" bottom="60px" @init="mescrollInit" :down="{ use: false }" @up="getAllAppListFn">
            <view v-if="goodsList.length" :class="['sidebar-margin', !listType ? 'biserial-goods-list' : '']">
                <template v-if="listType">
                    <view v-for="(item, index) in goodsList" :key="index"
                          class="goods-row-card bg-white flex px-[20rpx] py-[20rpx] rounded-[var(--rounded-small)] overflow-hidden top-mar"
                          :class="{ 'mb-[20rpx]': (index+1) == goodsList.length}" @click="toDetail(item.goods_id)">
                        <PhoneGoodsCover :src="item.goods_cover_thumb_mid" :grade="item.condition_grade" />

                        <view class="goods-row-content flex-1 flex flex-col ml-[20rpx]">
                            <view class="goods-row-title text-[28rpx] text-[#333] leading-[40rpx] multi-hidden">
                                <view class="brand-tag" v-if="item.goods_brand" :style="diyGoods.baseTagStyle(item.goods_brand)">{{ item.goods_brand.brand_name }}</view>
                                {{ item.goods_name }}
                            </view>
                            <PhoneGoodsMeta :subtitle="item.sub_title" :imei="item.goodsSku?.sku_no" />
                            <PhoneGoodsSaleState :state="item.sale_state" />

                            <view class="goods-row-price mt-auto flex items-baseline">
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
                        </view>
                    </view>
                </template>
                <template v-else>
                    <view>
                        <template v-for="(item, index) in goodsList">
                            <view v-if="(index%2) == 0" class="flex flex-col bg-[#fff] box-border rounded-[var(--rounded-mid)] overflow-hidden mt-[var(--top-m)]"
                                  @click="toDetail(item.goods_id)">

<!--                                <easy-image class="w-[100%] h-[344rpx]" image-class="rounded-tl-[var(&#45;&#45;rounded-mid)] rounded-tr-[var(&#45;&#45;rounded-mid)]"-->
<!--                                                :image-src="item.goods_cover_thumb_small" />-->
                                <PhoneGoodsCover :src="item.goods_cover_thumb_mid" :grade="item.condition_grade" variant="grid" />

                                <view class="px-[20rpx] flex-1 pt-[16rpx] pb-[24rpx] flex flex-col justify-between">
                                    <view class="text-[#303133] leading-[40rpx] text-[28rpx] multi-hidden">
                                        <view class="brand-tag" v-if="item.goods_brand" :style="diyGoods.baseTagStyle(item.goods_brand)">{{ item.goods_brand.brand_name }}</view>
                                        {{ item.goods_name }}
                                    </view>
                                    <PhoneGoodsMeta :subtitle="item.sub_title" :imei="item.goodsSku?.sku_no" compact />
                                    <PhoneGoodsSaleState :state="item.sale_state" />
                                    <view v-if="item.goods_label_name && item.goods_label_name.length" class="flex flex-wrap">
                                        <template v-for="(tagItem, tagIndex) in item.goods_label_name">
                                            <image class="img-tag" v-if="tagItem.style_type == 'icon' && tagItem.icon" :src="img(tagItem.icon)" mode="heightFix" @error="diyGoods.error(tagItem,'icon')"/>
                                            <view class="base-tag" v-else-if="tagItem.style_type == 'diy' || !tagItem.icon" :style="diyGoods.baseTagStyle(tagItem)">{{ tagItem.label_name }}</view>
                                        </template>
                                    </view>
                                    <view class="flex flex-wrap items-end">
                                        <view class="flex items-baseline mt-[20rpx]">
                                            <view class="text-[var(--price-text-color)] price-font flex items-baseline">
                                                <text class="text-[24rpx] font-500">￥</text>
                                                <text class="text-[40rpx] font-500">{{ diyGoods.goodsPrice(item).toFixed(2).split('.')[0] }}</text>
                                                <text class="text-[24rpx] font-500">.{{ diyGoods.goodsPrice(item).toFixed(2).split('.')[1] }}</text>
                                            </view>
                                            <image v-if="diyGoods.priceType(item) == 'member_price'" class="max-w-[50rpx] h-[28rpx] ml-[6rpx]" :src="img('addon/phone_shop/VIP.png')" mode="heightFix" />
											<image v-else-if="diyGoods.priceType(item) == 'newcomer_price'" class="max-w-[60rpx] h-[28rpx] ml-[6rpx]" :src="img('addon/phone_shop/newcomer.png')" mode="heightFix" />
											<image v-else-if="diyGoods.priceType(item) == 'discount_price'" class="max-w-[80rpx] h-[28rpx] ml-[6rpx]" :src="img('addon/phone_shop/discount.png')" mode="heightFix" />
                                        </view>
                                    </view>
                                </view>
                            </view>
                        </template>
                    </view>
                    <view>
                        <template v-for="(item, index) in goodsList">
                            <view v-if="(index%2) == 1" class="flex flex-col bg-[#fff] box-border rounded-[var(--rounded-mid)] overflow-hidden mt-[var(--top-m)]" @click="toDetail(item.goods_id)">
<!--                                <easy-image class="w-[100%] h-[344rpx]" image-class="rounded-tl-[var(&#45;&#45;rounded-mid)] rounded-tr-[var(&#45;&#45;rounded-mid)]"-->
<!--                                                :image-src="item.goods_cover_thumb_small" />-->
                                <PhoneGoodsCover :src="item.goods_cover_thumb_mid" :grade="item.condition_grade" variant="grid" />
                                <view class="px-[20rpx] flex-1 pt-[16rpx] pb-[24rpx] flex flex-col justify-between">
                                    <view class="text-[#303133] leading-[40rpx] text-[28rpx] multi-hidden">
                                        <view class="brand-tag" v-if="item.goods_brand" :style="diyGoods.baseTagStyle(item.goods_brand)">
                                            {{ item.goods_brand.brand_name }}
                                        </view>
                                        {{ item.goods_name }}
                                    </view>
                                    <PhoneGoodsMeta :subtitle="item.sub_title" :imei="item.goodsSku?.sku_no" compact />
                                    <PhoneGoodsSaleState :state="item.sale_state" />
                                    <view v-if="item.goods_label_name && item.goods_label_name.length" class="flex flex-wrap">
                                        <template v-for="(tagItem, tagIndex) in item.goods_label_name">
                                            <image class="img-tag" v-if="tagItem.style_type == 'icon' && tagItem.icon" :src="img(tagItem.icon)" mode="heightFix" @error="diyGoods.error(tagItem,'icon')" />
                                            <view class="base-tag" v-else-if="tagItem.style_type == 'diy' || !tagItem.icon" :style="diyGoods.baseTagStyle(tagItem)">
                                                {{ tagItem.label_name }}
                                            </view>
                                        </template>
                                    </view>
                                    <view class="flex flex-wrap items-baseline">
                                        <view class="flex items-baseline mt-[20rpx]">
                                            <view class="text-[var(--price-text-color)] price-font flex items-baseline">
                                                <text class="text-[24rpx] font-500">￥</text>
                                                <text class="text-[40rpx] font-500">{{ diyGoods.goodsPrice(item).toFixed(2).split('.')[0] }}</text>
                                                <text class="text-[24rpx] font-500">.{{ diyGoods.goodsPrice(item).toFixed(2).split('.')[1] }}</text>
                                            </view>
                                            <image v-if="diyGoods.priceType(item) == 'member_price'" class="max-w-[50rpx] h-[28rpx] ml-[6rpx]" :src="img('addon/phone_shop/VIP.png')" mode="heightFix" />
											<image v-else-if="diyGoods.priceType(item) == 'newcomer_price'"  class="max-w-[60rpx] h-[28rpx] ml-[6rpx]" :src="img('addon/phone_shop/newcomer.png')" mode="heightFix" />
											<image v-else-if="diyGoods.priceType(item) == 'discount_price'" class="max-w-[80rpx] h-[28rpx] ml-[6rpx]" :src="img('addon/phone_shop/discount.png')" mode="heightFix" />
                                        </view>
                                    </view>
                                </view>
                            </view>
                        </template>
                    </view>
                </template>
            </view>
            <mescroll-empty v-if="!goodsList.length && loading" :option="{tip : '暂无商品', btnText:'去逛逛'}" @emptyclick="redirect({ url: '/addon/phone_shop/pages/index', mode: 'reLaunch' })"></mescroll-empty>
        </mescroll-body>

        <tabbar />
    </view>
</template>

<script setup lang="ts">
import { computed, reactive, ref, onMounted } from 'vue'
import { t } from '@/locale'
import { redirect, img, handleOnloadParams } from '@/utils/common';
import {
    addGoodsSubscription,
    cancelGoodsSubscription,
    getGoodsFilterOptions,
    getGoodsPages,
    getGoodsSubscriptionList,
    getGoodsSubscriptionStatus
} from '@/addon/phone_shop/api/goods';
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js';
import { onLoad, onPageScroll, onReachBottom } from '@dcloudio/uni-app';
import { useGoods } from '@/addon/phone_shop/hooks/useGoods'
import GoodsCategoryFilterPopup from '@/addon/phone_shop/components/goods-filter/GoodsCategoryFilterPopup.vue'
import GoodsOptionFilterPopup from '@/addon/phone_shop/components/goods-filter/GoodsOptionFilterPopup.vue'
import GoodsMoreFilterPopup from '@/addon/phone_shop/components/goods-filter/GoodsMoreFilterPopup.vue'
import PhoneGoodsMeta from '@/addon/phone_shop/components/PhoneGoodsMeta.vue'
import PhoneGoodsSaleState from '@/addon/phone_shop/components/PhoneGoodsSaleState.vue'
import PhoneGoodsCover from '@/addon/phone_shop/components/PhoneGoodsCover.vue'
import useMemberStore from '@/stores/member'
import { useLogin } from '@/hooks/useLogin'

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

const filters = reactive({
    category_ids: [] as string[],
    memory_group: [] as string[],
    condition_grade: [] as string[],
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
    warehouse: filters.warehouse,
    in_stock: filters.in_stock,
    order: searchType.value,
    sort: searchType.value === 'price' ? price.value : sale_num.value
}))

const moreFilterCount = computed(() => {
    let count = filters.brand_ids.length
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
    // #ifdef MP-WEIXIN
    // 处理小程序场景值参数
    option = handleOnloadParams(option);
    // #endif
    if (option.curr_goods_category) filters.category_ids = [String(option.curr_goods_category)]
    goods_name.value = option.goods_name ? decodeURIComponent(option.goods_name) : ''
    coupon_id.value = option.coupon_id || ''
    await getGoodsFilterOptions().then((res: any) => {
        Object.assign(filterOptions, res.data || {})
    }).catch(() => {})
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
        const res: any = await addGoodsSubscription({
            name: `分类上新 · ${node.category_name || '商品'}`,
            rule: { category_ids: [id] }
        })
        categorySubscriptionMap[id] = Number(res.data?.subscription_id || res.data || 0)
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
        const res: any = await addGoodsSubscription({ rule: currentSubscriptionRule.value })
        subscription.subscribed = true
        subscription.subscription_id = Number(res.data || 0)
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

const applyFilter = (key: 'category_ids' | 'memory_group' | 'condition_grade' | 'label_ids' | 'service_ids', value: string[]) => {
    filters[key] = value
    resetSubscriptionState()
    refreshList()
}

const applyMoreFilters = (value: any) => {
    filters.start_price = value.start_price
    filters.end_price = value.end_price
    filters.brand_ids = value.brand_ids || []
    filters.warehouse = value.warehouse || ''
    filters.in_stock = Boolean(value.in_stock)
    searchType.value = value.order || 'all'
    price.value = searchType.value === 'price' ? (value.sort || 'asc') : ''
    sale_num.value = searchType.value === 'sale_num' ? (value.sort || 'desc') : ''
    resetSubscriptionState()
    refreshList()
}

//列表样式切换
const listIconBtn = () => {
    listType.value = !listType.value
}
const toDetail = (id: string | number) => {
    redirect({ url: '/addon/phone_shop/pages/goods/detail', param: { goods_id: id }, mode: 'navigateTo' })
}
onMounted(() => {
    setTimeout(() => {
        getMescroll().optUp.textNoMore = t("end");
    }, 500)
});
</script>

<style lang="scss" scoped>
@import '@/addon/phone_shop/styles/common.scss';

.product-warp {
    z-index: 100;
    box-shadow: 0 8rpx 22rpx rgba(15, 23, 42, 0.04);
}

.search-row {
    height: 88rpx;
    padding: 12rpx 20rpx 8rpx;
    display: flex;
    align-items: center;
    box-sizing: border-box;
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
}

.filter-toolbar__inner {
    width: max-content;
    min-width: 100%;
    height: 80rpx;
    padding: 0 20rpx;
    display: inline-flex;
    align-items: center;
    box-sizing: border-box;
}

.filter-entry {
    position: relative;
    height: 54rpx;
    margin-right: 8rpx;
    padding: 0 18rpx;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
    border-radius: 28rpx;
    color: #475569;
    background: #f5f7fa;
    font-size: 24rpx;
    flex-shrink: 0;
}

.filter-entry--active {
    color: var(--primary-color);
    background: var(--primary-color-light);
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

.biserial-goods-list {
    display: grid;
    grid-template-columns: 1fr 1fr;
    grid-gap: 10px;
}

.goods-row-card {
    min-height: 270rpx;
    align-items: stretch;
}

.goods-row-content {
    min-width: 0;
    height: 230rpx;
    padding: 2rpx 0;
    overflow: hidden;
    box-sizing: border-box;
}

.goods-row-title {
    flex-shrink: 0;
    max-height: 80rpx;
    margin-bottom: 2rpx;
}

.goods-row-labels {
    flex-shrink: 0;
    max-height: 34rpx;
    overflow: hidden;
}

.goods-row-price {
    min-height: 46rpx;
    padding-top: 6rpx;
    overflow: hidden;
    box-sizing: border-box;
    flex-shrink: 0;
}
</style>
