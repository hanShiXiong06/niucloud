<template>
    <view class="page" :style="themeColor()">

        <!-- 商品图片（支持拖拽排序，第一张为封面） -->
        <view class="card">
            <view class="card-title">商品图片</view>
            <shmily-drag-image
                ref="shmilyRef"
                v-model:list="goodsImageList"
                :number="9"
                :cols="4"
                custom
                @addImage="onAddImage"
            />
            <view class="tip">长按拖动可排序，第一张将作为封面图</view>
        </view>

        <!-- 基本信息 -->
        <view class="card">
            <view class="card-title">基本信息</view>
            <view class="field">
                <text class="label"><text class="req">*</text>名称</text>
                <input class="input" v-model="form.goods_name" placeholder="请输入商品名称" placeholder-class="ph" maxlength="60" />
            </view>
            <view class="field">
                <text class="label">副标题</text>
                <input class="input" v-model="form.sub_title" placeholder="选填，一句话卖点" placeholder-class="ph" maxlength="100" />
            </view>
            <view class="field field--last">
                <text class="label">IMEI / SN</text>
                <input class="input" v-model="form.sku_no" placeholder="请输入 IMEI / SN码" placeholder-class="ph" maxlength="50" />
                <u-icon class="scan-icon" name="scan" color="var(--primary-color)" size="22" @click="scanSn"></u-icon>
            </view>
        </view>

        <!-- 分类（弹窗选择） -->
        <view class="card">
            <category-popup v-model="form.category_id" @change="onCategoryChange" />
        </view>

        <!-- 成色 / 内存 -->
        <view class="card">
            <chip-select label="成色" :items="gradeList" v-model="form.condition_grade" emptyText="暂无成色选项" />
            <view class="hr"></view>
            <view class="mem">
                <view class="mem-label">内存</view>
                <view v-if="!form.category_id" class="tip2">请先选择分类</view>
                <view v-else-if="memoryLoading" class="tip2">加载中…</view>
                <chip-select v-else :items="memoryList" v-model="form.memory_group" emptyText="该分类暂无内存选项" />
            </view>
        </view>

        <!-- 配送 -->
        <view class="card">
            <chip-select label="配送方式" :items="deliveryItems" v-model="form.delivery_type" :multiple="true" emptyText="暂无配送方式" />
        </view>

        <!-- 商品详情 -->
        <view class="card">
            <view class="card-title">商品详情</view>
            <textarea class="textarea" v-model="form.goods_desc" placeholder="可自由编辑商品介绍，选填" placeholder-class="ph" />
        </view>

        <!-- 上架状态 -->
        <view class="card">
            <view class="field field--last field--switch">
                <text class="label">立即上架</text>
                <u-switch v-model="statusOn" activeColor="var(--primary-color)" size="22"></u-switch>
            </view>
        </view>

        <!-- 底部常驻：价格 + 发布 -->
        <view class="float-bar">
            <view class="more-panel" v-if="showMore">
                <view class="mp-field">
                    <text class="mp-name">同行价</text>
                    <view class="mp-money"><text class="rmb">¥</text><input class="mp-input" type="digit" v-model="form.market_price" placeholder="0.00" placeholder-class="ph" /></view>
                </view>
                <view class="mp-field">
                    <text class="mp-name">成本价</text>
                    <view class="mp-money"><text class="rmb">¥</text><input class="mp-input" type="digit" v-model="form.cost_price" placeholder="0.00" placeholder-class="ph" /></view>
                </view>
                <view class="mp-field mp-field--last">
                    <text class="mp-name">库存</text>
                    <input class="mp-input mp-input--stock" type="number" v-model="form.stock" placeholder="默认 1" placeholder-class="ph" />
                </view>
            </view>
            <view class="bar-main">
                <view class="more-toggle" @click="showMore = !showMore">
                    <text class="text-[24rpx] text-[#9098A3]">更多</text>
                    <text class="nc-iconfont text-[22rpx] text-[#9098A3]" :class="showMore ? 'nc-icon-xiaV6xx' : 'nc-icon-shangV6xx'"></text>
                </view>
                <view class="price-box">
                    <text class="rmb">¥</text>
                    <input class="price-input" type="digit" v-model="form.price" placeholder="零售价" placeholder-class="ph" />
                </view>
                <view class="pub-btn" :class="{ 'pub-btn--ing': submitting }" @click="submit">{{ goodsId ? '保存' : '发布' }}</view>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, reactive, watch, nextTick } from 'vue';
import { onLoad } from '@dcloudio/uni-app';
import { redirect } from '@/utils/common';
import { uploadImage } from '@/app/api/system';
import { addGoods, editGoods, getGoodsInit, getGradeList, getSpecOptions, getDeliveryList } from '@/addon/phone_shop/api/goods';
import CategoryPopup from '@/addon/phone_shop/components/category-popup.vue';
import ChipSelect from '@/addon/phone_shop/components/chip-select.vue';
import ShmilyDragImage from '@/components/shmily-drag-image/shmily-drag-image.vue';

// 商品图片：shmily 用数组(:list)，表单提交用逗号串(form.goods_image)，双向同步
const shmilyRef = ref<any>();
const goodsImageList = ref<string[]>([]);
watch(goodsImageList, (v) => { form.goods_image = (v || []).join(','); }, { deep: true });

// 自定义添加：选图 -> 上传 -> 追加到列表 -> 刷新组件
const onAddImage = () => {
    const remain = 9 - goodsImageList.value.length;
    if (remain <= 0) { uni.showToast({ title: '最多上传 9 张', icon: 'none' }); return; }
    uni.chooseImage({
        count: remain,
        success: (res: any) => {
            const paths: string[] = res.tempFilePaths || [];
            if (!paths.length) return;
            uni.showLoading({ title: '上传中...' });
            let done = 0;
            paths.forEach((p: string) => {
                uploadImage({ filePath: p, name: 'file' }).then((r: any) => {
                    const url = r?.data?.url;
                    if (url && goodsImageList.value.length < 9) goodsImageList.value.push(url);
                }).catch(() => {}).finally(() => {
                    done++;
                    if (done === paths.length) {
                        uni.hideLoading();
                        nextTick(() => shmilyRef.value?.refresh());
                    }
                });
            });
        }
    });
};

const goodsId = ref<any>('');
const statusOn = ref(true);
const submitting = ref(false);
const showMore = ref(false);
const memoryLoading = ref(false);

const form = reactive<any>({
    goods_image: '',
    goods_name: '',
    sub_title: '',
    sku_no: '',
    category_id: '',
    category_path: [] as any[],
    condition_grade: '',
    memory_group: '',
    price: '',
    market_price: '',
    cost_price: '',
    stock: '',
    delivery_type: [] as string[],
    goods_desc: ''
});

const gradeList = ref<string[]>([]);
const memoryList = ref<string[]>([]);
const deliveryItems = ref<any[]>([]);

const toArr = (data: any): any[] => {
    if (Array.isArray(data)) return data;
    if (data && Array.isArray(data.data)) return data.data;
    if (data && Array.isArray(data.list)) return data.list;
    return [];
};

// 成色（全局）
const loadGrade = () => {
    getGradeList().then((res: any) => {
        gradeList.value = toArr(res.data).map((it: any) => it.grade_name ?? it.name ?? it.value).filter(Boolean);
    }).catch(() => {});
};

// 配送（默认全选）
const loadDelivery = () => {
    getDeliveryList().then((res: any) => {
        const list = toArr(res.data).filter((it: any) => it.status == undefined || it.status == 1 || it.status == 2);
        deliveryItems.value = list.map((it: any) => ({ label: it.name, value: it.key }));
        form.delivery_type = deliveryItems.value.map((it: any) => it.value);
    }).catch(() => {});
};

// 内存（随分类变）
const loadMemory = (cid: any, path: any[] = []) => {
    if (!cid) { memoryList.value = []; return; }
    memoryLoading.value = true;
    getSpecOptions({ category_id: cid, category_path: path }).then((res: any) => {
        const groups = toArr(res.data?.spec_groups);
        const mem: string[] = [];
        groups.forEach((g: any) => toArr(g.items).forEach((it: any) => {
            const v = it.item_value ?? it.item_name ?? it.value ?? it.name;
            if (v) mem.push(v);
        }));
        memoryList.value = Array.from(new Set(mem));
    }).catch(() => { memoryList.value = []; }).finally(() => { memoryLoading.value = false; });
};

const onCategoryChange = (payload: any) => {
    form.category_path = payload.category_path || [];
    form.memory_group = '';
    loadMemory(payload.category_id, form.category_path);
};

// 扫码录入 IMEI / SN（条形码/二维码），比手输快
const scanSn = () => {
    uni.scanCode({
        onlyFromCamera: false,
        scanType: ['barCode', 'qrCode'],
        success: (res: any) => {
            const v = String(res?.result || '').trim();
            if (v) form.sku_no = v;
        },
        fail: () => {}
    });
};

// 编辑回填
const loadInfo = () => {
    getGoodsInit({ goods_id: goodsId.value }).then((res: any) => {
        const d = res.data?.goods_info || res.data || {};
        form.goods_name = d.goods_name || '';
        form.sub_title = d.sub_title || '';
        form.goods_image = Array.isArray(d.goods_image) ? d.goods_image.join(',') : (d.goods_image || '');
        // 回填拖拽组件的图片列表(去空格、过滤空值)，并刷新渲染
        goodsImageList.value = String(form.goods_image || '').split(',').map((s: string) => s.trim()).filter(Boolean);
        nextTick(() => shmilyRef.value?.refresh());
        form.memory_group = d.memory_group || '';
        form.condition_grade = d.condition_grade || '';
        form.goods_desc = d.goods_desc || '';
        form.sku_no = d.sku_no || '';
        let cat = d.goods_category;
        if (Array.isArray(cat)) { form.category_path = cat; cat = cat[cat.length - 1]; }
        else if (typeof cat === 'string' && cat) { form.category_path = cat.split(','); cat = form.category_path[form.category_path.length - 1]; }
        if (cat !== undefined && cat !== '') form.category_id = isNaN(Number(cat)) ? cat : Number(cat);
        if (form.category_id) loadMemory(form.category_id, form.category_path);
        if (Array.isArray(d.delivery_type)) form.delivery_type = d.delivery_type;
        else if (typeof d.delivery_type === 'string' && d.delivery_type) form.delivery_type = d.delivery_type.split(',');
        // 单规格价格在 sku_list[0] 里
        const sku = (Array.isArray(d.sku_list) ? d.sku_list[0] : null)
            || d.sku_data
            || (Array.isArray(d.goods_sku_data) ? d.goods_sku_data[0] : null)
            || d.goods_sku || {};
        form.price = sku.price ?? d.price ?? '';
        form.market_price = sku.market_price ?? d.market_price ?? '';
        form.cost_price = sku.cost_price ?? d.cost_price ?? '';
        form.stock = sku.stock ?? d.stock ?? '';
        if (!form.sku_no) form.sku_no = sku.sku_no ?? '';
        statusOn.value = d.status == 1;
    }).catch(() => {});
};

onLoad((option: any) => {
    loadGrade();
    loadDelivery();
    if (option && option.goods_id) {
        goodsId.value = option.goods_id;
        uni.setNavigationBarTitle({ title: '编辑商品' });
        loadInfo();
    } else {
        uni.setNavigationBarTitle({ title: '发布商品' });
    }
});

const submit = () => {
    if (submitting.value) return;
    if (!form.goods_image) return uni.showToast({ title: '请上传商品图片', icon: 'none' });
    if (!form.goods_name) return uni.showToast({ title: '请输入商品名称', icon: 'none' });
    if (!form.category_id) return uni.showToast({ title: '请选择分类', icon: 'none' });
    if (!form.price || Number(form.price) <= 0) return uni.showToast({ title: '请输入零售价', icon: 'none' });
    if (!form.delivery_type.length) return uni.showToast({ title: '请至少选择一种配送方式', icon: 'none' });

    const data: Record<string, any> = {
        goods_name: form.goods_name,
        sub_title: form.sub_title,
        goods_type: 'real',
        goods_image: form.goods_image,
        goods_category: form.category_path.length ? form.category_path : [form.category_id],
        memory_group: form.memory_group,
        condition_grade: form.condition_grade,
        // 后端会对这些字段 array_map，必须传数组（即使为空）
        label_ids: [],
        service_ids: [],
        attr_ids: [],
        status: statusOn.value ? 1 : 0,
        spec_type: 'single',
        price: form.price,
        market_price: form.market_price || 0,
        cost_price: form.cost_price || 0,
        stock: form.stock || 1,
        sku_no: form.sku_no,
        unit: '件',
        delivery_type: form.delivery_type,
        is_free_shipping: 1,
        goods_desc: form.goods_desc || form.goods_name
    };

    submitting.value = true;
    const req = goodsId.value ? editGoods(goodsId.value, data) : addGoods(data);
    req.then(() => {
        submitting.value = false;
        setTimeout(() => {
            redirect({ url: '/addon/phone_shop/pages/goods/list', mode: 'redirectTo' });
        }, 800);
    }).catch(() => { submitting.value = false; });
};
</script>

<style lang="scss" scoped>
.page {
    min-height: 100vh;
    background: var(--page-bg-color);
    padding: 24rpx 0 calc(180rpx + env(safe-area-inset-bottom));
}
.card {
    background: #fff;
    margin: 0 24rpx 24rpx;
    padding: 30rpx 28rpx;
    border-radius: 28rpx;
}
.card-title { font-size: 30rpx; font-weight: 600; color: #333; margin-bottom: 24rpx; }
.tip { font-size: 24rpx; color: #9098A3; margin-top: 16rpx; }
.tip2 { font-size: 24rpx; color: #9098A3; padding: 4rpx 0; }
.field {
    display: flex;
    align-items: center;
    min-height: 92rpx;
    border-bottom: 2rpx solid #f3f4f6;
}
.field--last { border-bottom: 0; }
.field--switch { justify-content: space-between; }
.label { width: 170rpx; font-size: 28rpx; color: #333; flex-shrink: 0; }
.req { color: #FF4D4F; margin-right: 6rpx; }
.input { flex: 1; font-size: 28rpx; color: #333; text-align: right; }
.scan-icon { margin-left: 16rpx; padding: 6rpx; flex-shrink: 0; }
.ph { color: #c4c8cf; }
.hr { height: 2rpx; background: #f3f4f6; margin: 24rpx 0; }
.mem-label { font-size: 28rpx; color: #333; margin-bottom: 20rpx; }
.textarea { width: 100%; min-height: 220rpx; font-size: 28rpx; color: #333; box-sizing: border-box; }

/* 底部常驻浮条 */
.float-bar {
    position: fixed;
    left: 0; right: 0; bottom: 0;
    background: #fff;
    box-shadow: 0 -6rpx 24rpx rgba(0,0,0,0.06);
    padding-bottom: env(safe-area-inset-bottom);
    z-index: 50;
}
.more-panel { padding: 8rpx 32rpx 0; border-bottom: 2rpx solid #f3f4f6; }
.mp-field {
    display: flex; align-items: center; justify-content: space-between;
    height: 84rpx; border-bottom: 2rpx solid #f6f7f9;
}
.mp-field--last { border-bottom: 0; }
.mp-name { font-size: 26rpx; color: #555; }
.mp-money { display: flex; align-items: center; }
.mp-input { text-align: right; font-size: 28rpx; color: #333; width: 220rpx; }
.mp-input--stock { width: 220rpx; }
.bar-main {
    display: flex;
    align-items: center;
    padding: 16rpx 24rpx;
    gap: 20rpx;
}
.more-toggle { display: flex; flex-direction: column; align-items: center; line-height: 1.1; flex-shrink: 0; }
.price-box {
    flex: 1;
    display: flex;
    align-items: baseline;
    background: #F5F7FA;
    border-radius: 40rpx;
    height: 80rpx;
    padding: 0 28rpx;
}
.rmb { color: var(--primary-color); font-size: 28rpx; margin-right: 6rpx; }
.price-input { flex: 1; font-size: 34rpx; font-weight: 600; color: var(--primary-color); height: 80rpx; }
.pub-btn {
    flex-shrink: 0;
    width: 200rpx;
    height: 80rpx;
    line-height: 80rpx;
    text-align: center;
    background: var(--primary-color);
    color: #fff;
    font-size: 30rpx;
    font-weight: 500;
    border-radius: 40rpx;
}
.pub-btn--ing { opacity: 0.6; }
</style>
