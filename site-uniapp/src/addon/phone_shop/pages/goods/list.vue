<template>
    <view class="min-h-[100vh] bg-[var(--page-bg-color)]" :style="themeColor()">
        <!-- 顶部：小程序将搜索、扫码、筛选收进自定义导航区，给商品列表留出更多空间 -->
        <view class="top-bar" :class="{ 'top-bar--mp': isMp }">
            <!-- #ifdef MP -->
            <view class="custom-nav" :style="customNavStyle">
                <view class="custom-nav__back" @tap="handleNavAction">
                    <u-icon :name="canGoBack ? 'arrow-left' : 'home'" color="#172033" size="21"></u-icon>
                </view>
                <view class="search-input search-input--compact">
                    <u-icon name="search" color="#9098A3" size="17"></u-icon>
                    <input class="flex-1 text-[24rpx] ml-[10rpx]" v-model="keyword" placeholder="商品名称 / IMEI" placeholder-class="ph" confirm-type="search" @confirm="reload" />
                    <u-icon v-if="keyword" name="close-circle-fill" color="#c4c4c4" size="17" @click="keyword=''; reload()"></u-icon>
                    <view class="nav-icon-btn nav-icon-btn--scan" @click="scanKeyword">
                        <u-icon name="scan" color="var(--primary-color)" size="19"></u-icon>
                    </view>
                    <view class="nav-icon-btn" :class="{ 'nav-icon-btn--on': filterCount }" @click="openFilter">
                        <u-icon name="list" :color="filterCount ? 'var(--primary-color)' : '#64748b'" size="19"></u-icon>
                        <text v-if="filterCount" class="filter-badge">{{ filterCount > 9 ? '9+' : filterCount }}</text>
                    </view>
                </view>
            </view>
            <!-- #endif -->

            <!-- #ifndef MP -->
            <view class="search-input">
                <u-icon name="search" color="#9098A3" size="18"></u-icon>
                <input class="flex-1 text-[28rpx] ml-[12rpx]" v-model="keyword" placeholder="搜索商品名称 / IMEI" placeholder-class="ph" confirm-type="search" @confirm="reload" />
                <u-icon v-if="keyword" name="close-circle-fill" color="#c4c4c4" size="18" @click="keyword=''; reload()"></u-icon>
                <u-icon class="ml-[16rpx]" name="scan" color="var(--primary-color)" size="20" @click="scanKeyword"></u-icon>
                <view class="nav-icon-btn ml-[12rpx]" :class="{ 'nav-icon-btn--on': filterCount }" @click="openFilter">
                    <u-icon name="list" :color="filterCount ? 'var(--primary-color)' : '#64748b'" size="19"></u-icon>
                    <text v-if="filterCount" class="filter-badge">{{ filterCount > 9 ? '9+' : filterCount }}</text>
                </view>
            </view>
            <!-- #endif -->

            <view class="bar-row">
                <view class="tabs">
                    <view v-for="tab in statusTabs" :key="tab.value" class="tab" :class="{ 'tab--on': statusFilter === tab.value }" @click="changeStatus(tab.value)">{{ tab.label }}</view>
                </view>
                <view class="bar-ops">
                    <view class="op" @click="sortShow = true">
                        <text :class="{ 'op--on': sortIdx > 0 }">排序</text>
                        <u-icon name="arrow-down" :color="sortIdx > 0 ? 'var(--primary-color)' : '#9098A3'" size="13"></u-icon>
                    </view>
                </view>
            </view>
        </view>

        <mescroll-body ref="mescrollRef" @init="mescrollInit" :down="{ use: true }" @down="downCallback" @up="getListFn" :up="{ noMoreSize: 4, empty: { tip: '没有符合条件的商品' } }" :top="listTop">
            <view class="px-[24rpx] pt-[20rpx]">
                <view class="goods-card" v-for="item in list" :key="item.goods_id">
                    <view class="flex">
                        <up-image width="160rpx" height="160rpx" radius="16rpx" :src="img(item.goods_cover || (item.goods_image||'').split(',')[0])" model="aspectFill">
                            <template #error>
                                <view class="w-[160rpx] h-[160rpx] rounded-[16rpx] bg-[#F5F7FA] flex-center"><u-icon name="photo" color="#c4c8cf" size="36"></u-icon></view>
                            </template>
                        </up-image>
                        <view class="flex-1 ml-[20rpx] overflow-hidden flex flex-col justify-between">
                            <view>
                                <view class="flex items-center">
                                    <text v-if="!isMasterSite && item.is_proxy_goods == 1" class="tag tag--proxy">代理</text>
                                    <text v-else-if="!isMasterSite" class="tag tag--self">自营</text>
                                    <text class="text-[28rpx] font-600 text-[#333] truncate flex-1">{{ item.goods_name }}</text>
                                </view>
                                <text class="block text-[24rpx] text-[#9098A3] mt-[6rpx] truncate" v-if="item.sub_title">{{ item.sub_title }}</text>
                                <view class="meta" v-if="item.condition_grade || item.memory_group">
                                    <text class="meta-tag" v-if="item.condition_grade">{{ item.condition_grade }}</text>
                                    <text class="meta-tag" v-if="item.memory_group">{{ item.memory_group }}</text>
                                </view>
                                <text class="block text-[22rpx] text-[#b4b8bf] mt-[6rpx] truncate" v-if="imei(item)">IMEI：{{ imei(item) }}</text>
                            </view>
                            <view class="flex items-end justify-between">
                                <view class="flex items-center">
                                    <text class="text-[var(--primary-color)] font-600"><text class="text-[24rpx]">¥</text><text class="text-[36rpx]">{{ price(item) }}</text></text>
                                    <text class="sale-tag sale-tag--sold" v-if="item.sale_status === 'sold'">已售</text>
                                    <text class="sale-tag sale-tag--lock" v-else-if="item.sale_status === 'locked'">锁定</text>
                                    <text class="sale-tag sale-tag--off" v-else-if="item.status != 1">下架</text>
                                </view>
                                <text class="text-[22rpx] text-[#bbb]">库存 {{ item.stock ?? 0 }}</text>
                            </view>
                        </view>
                    </view>
                    <view class="btn-row">
                        <view class="op-btn" @click="toggleStatus(item)">{{ item.status == 1 ? '下架' : '上架' }}</view>
                        <view class="op-btn" @click="toEdit(item)">编辑</view>
                        <view class="op-btn op-btn--danger" @click="confirmDel(item)">删除</view>
                    </view>
                </view>
            </view>
        </mescroll-body>

        <view class="fab" @click="toAdd"><u-icon name="plus" color="#fff" size="26"></u-icon></view>

        <!-- 排序 -->
        <u-popup :show="sortShow" mode="bottom" round="20" @close="sortShow=false">
            <view class="sheet">
                <view class="sheet-title">排序方式</view>
                <view v-for="(s, i) in sortOptions" :key="i" class="sheet-item" :class="{ 'sheet-item--on': sortIdx === i }" @click="pickSort(i)">
                    <text>{{ s.label }}</text>
                    <u-icon v-if="sortIdx === i" name="checkmark" color="var(--primary-color)" size="18"></u-icon>
                </view>
            </view>
        </u-popup>

        <!-- 筛选 -->
        <u-popup :show="filterShow" mode="right" @close="filterShow=false">
            <view class="drawer">
                <view class="drawer-title">筛选</view>
                <scroll-view scroll-y class="drawer-body">
                    <view class="fblk">
                        <category-popup v-model="filter.goods_category" />
                    </view>
                    <view class="fblk">
                        <view class="fblk-label">价格区间</view>
                        <view class="price-range">
                            <input class="pr-input" type="digit" v-model="filter.start_price" placeholder="最低价" placeholder-class="ph" />
                            <text class="pr-sep">—</text>
                            <input class="pr-input" type="digit" v-model="filter.end_price" placeholder="最高价" placeholder-class="ph" />
                        </view>
                    </view>
                    <view class="fblk"><chip-select label="内存" :items="memOptions" v-model="filter.memory_group" emptyText="暂无内存" /></view>
                    <view class="fblk"><chip-select label="成色" :items="gradeOptions" v-model="filter.condition_grade" emptyText="暂无成色" /></view>
                    <view class="fblk"><chip-select label="售卖状态" :items="saleStatusOptions" v-model="filter.sale_status" /></view>
                    <view class="fblk"><chip-select label="库龄" :items="stockAgeOptions" v-model="filter.stock_age" /></view>
                    <view class="fblk" v-if="showSourceFilter"><chip-select label="归属" :items="sourceOptions" v-model="filter.proxy_type" /></view>
                </scroll-view>
                <view class="drawer-foot">
                    <view class="d-btn d-btn--reset" @click="resetFilter">重置</view>
                    <view class="d-btn d-btn--ok" @click="applyFilter">确定</view>
                </view>
            </view>
        </u-popup>

        <u-modal :show="delShow" title="提示" content="确认删除该商品吗？" :showCancelButton="true" @confirm="doDel" @cancel="delShow=false"></u-modal>
    </view>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue';
import { onLoad, onShow, onPageScroll, onReachBottom } from '@dcloudio/uni-app';
import { img, pxToRpx, redirect } from '@/utils/common';
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js';
import CategoryPopup from '@/addon/phone_shop/components/category-popup.vue';
import ChipSelect from '@/addon/phone_shop/components/chip-select.vue';
import { getGoodsList, changeGoodsStatus, deleteGoods, getSpecGroup, getGradeList } from '@/addon/phone_shop/api/goods';

const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);

const list = ref<any[]>([]);
const keyword = ref('');
const statusFilter = ref<any>('');
const isMasterSite = ref<boolean | null>(null);
const firstLoaded = ref(false);
const isMp = ref(false);

// #ifdef MP
isMp.value = true;
// #endif

const navbarMetrics = (() => {
    const systemInfo = uni.getSystemInfoSync();
    let menuButtonInfo: any = null;
    try {
        // #ifdef MP-WEIXIN || MP-BAIDU || MP-TOUTIAO || MP-QQ
        menuButtonInfo = uni.getMenuButtonBoundingClientRect();
        // #endif
    } catch (error) {}

    const statusTopPx = Number(menuButtonInfo?.top ?? systemInfo.statusBarHeight ?? 0);
    const contentHeightPx = Number(menuButtonInfo?.height || 32);
    const bottomGapPx = 8;
    const windowWidth = Number(systemInfo.windowWidth || 375);
    const rightInsetPx = menuButtonInfo?.left
        ? Math.max(12, windowWidth - Number(menuButtonInfo.left) + 8)
        : 12;

    return {
        statusTopPx,
        contentHeightPx,
        bottomGapPx,
        rightInsetPx,
        navbarHeightPx: statusTopPx + contentHeightPx + bottomGapPx
    };
})();
const customNavStyle = [
    `height:${ navbarMetrics.navbarHeightPx }px`,
    `padding-top:${ navbarMetrics.statusTopPx }px`,
    `padding-bottom:${ navbarMetrics.bottomGapPx }px`,
    `padding-right:${ navbarMetrics.rightInsetPx }px`
].join(';') + ';';
const listTop = computed(() => isMp.value
    ? `${ pxToRpx(navbarMetrics.navbarHeightPx) + 88 }rpx`
    : '220rpx'
);
const canGoBack = computed(() => getCurrentPages().length > 1);

const statusTabs = [{ label: '全部', value: '' }, { label: '在售', value: 1 }, { label: '已下架', value: 0 }];
const saleStatusOptions = [{ label: '在售', value: 'available' }, { label: '锁定', value: 'locked' }, { label: '已售', value: 'sold' }];
const stockAgeOptions = [
    { label: '7天内', value: '0-7' }, { label: '8-15天', value: '8-15' }, { label: '16-30天', value: '16-30' },
    { label: '31-60天', value: '31-60' }, { label: '60天以上', value: '61-' }
];
const sourceOptions = [{ label: '全部', value: '' }, { label: '自营', value: 'self' }, { label: '代理', value: 'proxy' }];
const sortOptions = [
    { label: '综合排序', order: '', sort: '' },
    { label: '价格从低到高', order: 'price', sort: 'asc' },
    { label: '价格从高到低', order: 'price', sort: 'desc' },
    { label: '库存从少到多', order: 'stock', sort: 'asc' },
    { label: '库存从多到少', order: 'stock', sort: 'desc' },
    { label: '最新发布', order: 'create_time', sort: 'desc' }
];

// 与 PC 管理端共用归属筛选：source 是原始来源值，不能用本站 ID 代替“自营”。
// 站点身份由接口返回；初次请求默认查自营，主站由后端忽略此条件。
const showSourceFilter = computed(() => isMasterSite.value === false);
const defaultProxyType = () => isMasterSite.value === true ? '' : 'self';

const filter = reactive<any>({ goods_category: '', start_price: '', end_price: '', memory_group: '', condition_grade: '', sale_status: '', stock_age: '', proxy_type: defaultProxyType() });
const filterShow = ref(false);
const sortShow = ref(false);
const sortIdx = ref(0);
const delShow = ref(false);
const delItem = ref<any>(null);

const memOptions = ref<string[]>([]);
const gradeOptions = ref<string[]>([]);

const filterCount = computed(() => {
    let n = 0;
    ['goods_category', 'memory_group', 'condition_grade', 'sale_status', 'stock_age'].forEach(k => { if (filter[k] !== '' && filter[k] != null) n++; });
    if (showSourceFilter.value && filter.proxy_type) n++;
    if (filter.start_price || filter.end_price) n++;
    return n;
});

const sku = (item: any) => Array.isArray(item.goodsSku) ? (item.goodsSku[0] || {}) : (item.goodsSku || item.goods_sku || {});
const price = (item: any) => { const p = sku(item).price; return p != null ? p : (item.price ?? '0.00'); };
const imei = (item: any) => sku(item).sku_no || '';

const buildQuery = (mescroll: any) => {
    const q: any = { page: mescroll.num, limit: mescroll.size };
    const k = keyword.value.trim();
    if (k) {
        if (/^[0-9]{5,}$/.test(k)) q.device_keywords = k; else q.goods_name = k;
    }
    if (statusFilter.value !== '') q.status = statusFilter.value;
    if (filter.goods_category) q.goods_category = filter.goods_category;
    if (filter.start_price) q.start_price = filter.start_price;
    if (filter.end_price) q.end_price = filter.end_price;
    if (filter.memory_group) q.memory_group = filter.memory_group;
    if (filter.condition_grade) q.condition_grade = filter.condition_grade;
    if (filter.sale_status) q.sale_status = filter.sale_status;
    if (isMasterSite.value !== true && filter.proxy_type) q.proxy_type = filter.proxy_type;
    if (filter.stock_age) {
        const [min, max] = String(filter.stock_age).split('-');
        if (min !== '') q.start_stock_age = min;
        if (max !== undefined && max !== '') q.end_stock_age = max;
    }
    const s = sortOptions[sortIdx.value];
    if (s.order) { q.order = s.order; q.sort = s.sort; }
    return q;
};

const getListFn = (mescroll: any) => {
    getGoodsList(buildQuery(mescroll)).then((res: any) => {
        const data = res.data?.data || res.data?.list || [];
        if (res.data?.is_master_site != null) {
            isMasterSite.value = Number(res.data.is_master_site) === 1;
            if (isMasterSite.value) filter.proxy_type = '';
        }
        if (mescroll.num == 1) list.value = [];
        list.value = list.value.concat(data);
        mescroll.endSuccess(data.length);
        firstLoaded.value = true;
    }).catch(() => mescroll.endErr());
};

const reload = () => getMescroll() && getMescroll().resetUpScroll();
const changeStatus = (v: any) => { if (statusFilter.value === v) return; statusFilter.value = v; reload(); };
// 扫码搜索 IMEI / SN（条形码/二维码）
const scanKeyword = () => {
    uni.scanCode({
        onlyFromCamera: false,
        scanType: ['barCode', 'qrCode'],
        success: (res: any) => {
            const v = String(res?.result || '').trim();
            if (v) { keyword.value = v; reload(); }
        },
        fail: () => {}
    });
};
const pickSort = (i: number) => { sortIdx.value = i; sortShow.value = false; reload(); };
const openFilter = () => { filterShow.value = true; };
const applyFilter = () => { filterShow.value = false; reload(); };
const resetFilter = () => {
    Object.keys(filter).forEach(k => filter[k] = '');
    filter.proxy_type = defaultProxyType();
    filterShow.value = false;
    reload();
};

const handleNavAction = () => {
    if (canGoBack.value) {
        uni.navigateBack({
            delta: 1,
            fail: () => uni.reLaunch({ url: '/app/pages/index/index' })
        });
        return;
    }
    uni.reLaunch({ url: '/app/pages/index/index' });
};

const loadOptions = () => {
    getSpecGroup({ category_id: 0 }).then((res: any) => {
        const groups = res.data || [];
        const mem = groups.filter((g: any) => String(g.label || '').includes('内存'));
        const use = mem.length ? mem : groups;
        const set = new Set<string>();
        use.forEach((g: any) => (g.items || []).forEach((it: any) => { const v = String(it.item_value ?? '').trim(); if (v) set.add(v); }));
        memOptions.value = Array.from(set);
    }).catch(() => {});
    getGradeList().then((res: any) => {
        gradeOptions.value = (res.data || []).map((x: any) => String(x.grade_name ?? '').trim()).filter(Boolean);
    }).catch(() => {});
};

const toggleStatus = (item: any) => {
    const status = item.status == 1 ? 0 : 1;
    changeGoodsStatus(String(item.goods_id), status).then(() => { item.status = status; });
};
const toEdit = (item: any) => redirect({ url: `/addon/phone_shop/pages/goods/add?goods_id=${ item.goods_id }` });
const toAdd = () => redirect({ url: '/addon/phone_shop/pages/goods/add' });
const confirmDel = (item: any) => { delItem.value = item; delShow.value = true; };
const doDel = () => { delShow.value = false; if (!delItem.value) return; deleteGoods(String(delItem.value.goods_id)).then(() => reload()); };

onLoad(() => {
    loadOptions();
});
onShow(() => { if (firstLoaded.value && getMescroll()) getMescroll().resetUpScroll(); });
</script>

<style lang="scss" scoped>
.top-bar { position: fixed; top: 0; left: 0; right: 0; z-index: 100; background: var(--page-bg-color); padding: 20rpx 24rpx 14rpx; box-sizing: border-box; }
.top-bar--mp { padding: 0 18rpx 12rpx; background: #fff; border-bottom: 1rpx solid #eef2f7; box-shadow: 0 4rpx 14rpx rgba(15, 23, 42, 0.04); }
.custom-nav { width: 100%; display: flex; align-items: center; gap: 8rpx; box-sizing: border-box; }
.custom-nav__back { width: 56rpx; height: 56rpx; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-radius: 50%; }
.custom-nav__back:active { background: #f2f4f7; }
.search-input { height: 72rpx; background: #fff; border-radius: 36rpx; display: flex; align-items: center; padding: 0 28rpx; }
.search-input--compact { height: 60rpx; min-width: 0; flex: 1; padding: 0 14rpx; background: #f5f7fa; border-radius: 30rpx; }
.nav-icon-btn { position: relative; width: 42rpx; height: 42rpx; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: #f8fafc; }
.nav-icon-btn--scan { margin-left: 6rpx; background: #eff3ff; }
.nav-icon-btn--on { background: var(--primary-color-light, #e6fff5); }
.filter-badge { position: absolute; top: -8rpx; right: -8rpx; min-width: 28rpx; height: 28rpx; padding: 0 6rpx; border-radius: 14rpx; background: #ef4444; color: #fff; font-size: 18rpx; line-height: 28rpx; text-align: center; box-sizing: border-box; }
.ph { color: #c4c8cf; }
.bar-row { display: flex; align-items: center; justify-content: space-between; margin-top: 16rpx; }
.top-bar--mp .bar-row { margin-top: 8rpx; padding: 0 6rpx; }
.tabs { display: flex; gap: 14rpx; }
.tab { padding: 8rpx 26rpx; font-size: 26rpx; color: #666; background: #fff; border-radius: 28rpx; border: 2rpx solid transparent; }
.tab--on { color: var(--primary-color); background: var(--primary-color-light, #E6FFF5); border-color: var(--primary-color); font-weight: 600; }
.bar-ops { display: flex; align-items: center; gap: 24rpx; }
.op { display: flex; align-items: center; gap: 4rpx; font-size: 26rpx; color: #666; }
.op--on { color: var(--primary-color); }

.goods-card { background: #fff; border-radius: 24rpx; padding: 24rpx; margin-bottom: 20rpx; }
.tag { font-size: 20rpx; padding: 2rpx 10rpx; border-radius: 8rpx; margin-right: 10rpx; flex-shrink: 0; }
.tag--proxy { background: #FFF3E0; color: #E8954A; }
.tag--self { background: var(--primary-color-light, #E6FFF5); color: var(--primary-color); }
.meta { display: flex; flex-wrap: wrap; gap: 10rpx; margin-top: 10rpx; }
.meta-tag { font-size: 20rpx; color: #64748b; background: #f1f5f9; border-radius: 6rpx; padding: 2rpx 12rpx; }
.sale-tag { font-size: 20rpx; padding: 2rpx 10rpx; border-radius: 6rpx; margin-left: 12rpx; }
.sale-tag--sold { background: #f0f1f3; color: #9098A3; }
.sale-tag--lock { background: #FFF3E0; color: #E8954A; }
.sale-tag--off { background: #f0f1f3; color: #9098A3; }
.btn-row { display: flex; justify-content: flex-end; gap: 18rpx; margin-top: 20rpx; padding-top: 20rpx; border-top: 2rpx solid #f3f4f6; }
.op-btn { height: 56rpx; line-height: 56rpx; text-align: center; border: 2rpx solid #ddd; border-radius: 28rpx; font-size: 26rpx; color: #555; padding: 0 28rpx; }
.op-btn--danger { color: #FF4D4F; border-color: #FFD4D4; }
.fab { position: fixed; right: 36rpx; bottom: calc(60rpx + env(safe-area-inset-bottom)); width: 96rpx; height: 96rpx; border-radius: 50%; background: var(--primary-color); display: flex; align-items: center; justify-content: center; box-shadow: 0 8rpx 24rpx rgba(0,0,0,0.16); z-index: 20; }

.sheet { padding: 12rpx 0 calc(20rpx + env(safe-area-inset-bottom)); }
.sheet-title { text-align: center; font-size: 30rpx; font-weight: 600; color: #333; padding: 20rpx 0; }
.sheet-item { display: flex; align-items: center; justify-content: space-between; padding: 26rpx 40rpx; font-size: 28rpx; color: #333; }
.sheet-item--on { color: var(--primary-color); font-weight: 600; }

.drawer { width: 600rpx; height: 100vh; display: flex; flex-direction: column; }
.drawer-title { font-size: 30rpx; font-weight: 600; color: #333; padding: 40rpx 32rpx 16rpx; }
.drawer-body { flex: 1; padding: 0 32rpx; box-sizing: border-box; }
.fblk { padding: 20rpx 0; border-bottom: 2rpx solid #f6f7f9; }
.fblk-label { font-size: 28rpx; color: #333; margin-bottom: 20rpx; }
.price-range { display: flex; align-items: center; }
.pr-input { flex: 1; height: 72rpx; background: #F5F7FA; border-radius: 12rpx; text-align: center; font-size: 26rpx; }
.pr-sep { margin: 0 18rpx; color: #c4c8cf; }
.drawer-foot { display: flex; gap: 20rpx; padding: 20rpx 32rpx calc(20rpx + env(safe-area-inset-bottom)); }
.d-btn { flex: 1; height: 84rpx; line-height: 84rpx; text-align: center; border-radius: 42rpx; font-size: 28rpx; font-weight: 500; }
.d-btn--reset { background: #F5F7FA; color: #555; }
.d-btn--ok { background: var(--primary-color); color: #fff; }
</style>
