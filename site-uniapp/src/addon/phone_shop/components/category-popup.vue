<template>
    <view>
        <!-- 触发字段 -->
        <view class="field" @click="open">
            <text class="label"><text class="req">*</text>分类</text>
            <view class="value" :class="{ 'value--ph': !selectedName }">
                <text class="truncate">{{ selectedName || '请选择分类' }}</text>
                <text class="nc-iconfont nc-icon-youV6xx text-[24rpx] text-[#c4c8cf] ml-[8rpx]"></text>
            </view>
        </view>

        <u-popup :show="show" mode="bottom" round="20" :closeOnClickOverlay="true" @close="show = false">
            <view class="pop">
                <view class="head">
                    <text class="h-btn" @click="show = false">取消</text>
                    <text class="h-title">选择分类</text>
                    <text class="h-btn h-btn--hide">取消</text>
                </view>

                <view class="search">
                    <text class="nc-iconfont nc-icon-sousuo text-[30rpx] text-[#9098A3] mr-[10rpx]"></text>
                    <input class="flex-1 text-[26rpx]" v-model="keyword" placeholder="搜索分类名称" placeholder-class="ph" />
                    <text v-if="keyword" class="nc-iconfont nc-icon-guanbiV6xx text-[26rpx] text-[#c4c4c4]" @click="keyword = ''"></text>
                </view>

                <!-- 搜索结果 -->
                <scroll-view v-if="keyword.trim()" scroll-y class="search-list">
                    <view v-for="n in searchResult" :key="n.category_id" class="sr-item" @click="selectNode(n)">
                        <text class="sr-name" :class="{ 'sr-name--on': n.category_id == modelValue }">{{ n.category_name }}</text>
                        <text class="sr-full">{{ n.category_full_name }}</text>
                    </view>
                    <view v-if="!searchResult.length" class="tip">没有匹配的分类</view>
                </scroll-view>

                <!-- 左右分栏 -->
                <view v-else class="body">
                    <scroll-view scroll-y class="left">
                        <view v-for="l1 in tree" :key="l1.category_id"
                            class="l1" :class="{ 'l1--on': activeL1 && activeL1.category_id === l1.category_id }"
                            @click="activeL1 = l1">
                            {{ l1.category_name }}
                        </view>
                        <view v-if="loading" class="tip">加载中…</view>
                        <view v-else-if="!tree.length" class="tip">暂无分类</view>
                    </scroll-view>

                    <scroll-view scroll-y class="right">
                        <template v-if="activeL1">
                            <!-- 一级无子级：可直接选该一级 -->
                            <view v-if="!hasChild(activeL1)" class="leaf-row" :class="{ 'leaf--on': activeL1.category_id == modelValue }" @click="selectNode(activeL1)">
                                选择「{{ activeL1.category_name }}」
                                <text v-if="activeL1.category_id == modelValue" class="nc-iconfont nc-icon-duihaoV6xx text-primary"></text>
                            </view>

                            <view v-for="l2 in activeL1[CHILD]" :key="l2.category_id" class="grp">
                                <view class="grp-title" :class="{ 'leaf--on': l2.category_id == modelValue }" @click="hasChild(l2) ? null : selectNode(l2)">
                                    <text>{{ l2.category_name }}</text>
                                    <text v-if="!hasChild(l2) && l2.category_id == modelValue" class="nc-iconfont nc-icon-duihaoV6xx text-primary"></text>
                                </view>
                                <view v-if="hasChild(l2)" class="chips">
                                    <view v-for="l3 in l2[CHILD]" :key="l3.category_id"
                                        class="chip" :class="{ 'chip--on': l3.category_id == modelValue }"
                                        @click="selectNode(l3)">{{ l3.category_name }}</view>
                                </view>
                            </view>
                        </template>
                        <view v-else class="tip tip--center">请选择左侧分类</view>
                    </scroll-view>
                </view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { getCategoryTree } from '@/addon/phone_shop/api/goods';

const props = defineProps({
    modelValue: { type: [Number, String], default: '' }
});
const emit = defineEmits(['update:modelValue', 'change']);

const CHILD = 'child_list';
const show = ref(false);
const loading = ref(false);
const loaded = ref(false);
const tree = ref<any[]>([]);
const activeL1 = ref<any>(null);
const keyword = ref('');
const selectedName = ref('');

const hasChild = (n: any) => Array.isArray(n[CHILD]) && n[CHILD].length > 0;

// 拍平用于搜索
const flat = computed(() => {
    const out: any[] = [];
    const walk = (list: any[]) => list.forEach((n: any) => { out.push(n); if (hasChild(n)) walk(n[CHILD]); });
    walk(tree.value);
    return out;
});
const searchResult = computed(() => {
    const k = keyword.value.trim();
    if (!k) return [];
    return flat.value.filter((n: any) => (n.category_name || '').includes(k) || (n.category_full_name || '').includes(k));
});

// id -> 祖先路径
const findPath = (list: any[], id: any, trail: any[] = []): any[] | null => {
    for (const n of list) {
        const t = [...trail, n];
        if (n.category_id == id) return t;
        if (hasChild(n)) { const f = findPath(n[CHILD], id, t); if (f) return f; }
    }
    return null;
};
const syncName = () => {
    if (props.modelValue === '' || props.modelValue == null) { selectedName.value = ''; return; }
    const p = findPath(tree.value, props.modelValue);
    if (p && p.length) selectedName.value = p[p.length - 1].category_full_name || p[p.length - 1].category_name;
};
watch(() => props.modelValue, syncName);

const selectNode = (node: any) => {
    const p = findPath(tree.value, node.category_id) || [node];
    selectedName.value = node.category_full_name || node.category_name;
    emit('update:modelValue', node.category_id);
    emit('change', { category_id: node.category_id, category_path: p.map((n: any) => n.category_id), node });
    show.value = false;
};

const load = () => {
    if (loaded.value || loading.value) return;
    loading.value = true;
    getCategoryTree().then((res: any) => {
        tree.value = Array.isArray(res.data) ? res.data : (res.data?.data || res.data?.list || []);
        loaded.value = true;
        syncName();
    }).catch(() => {}).finally(() => { loading.value = false; });
};

const open = () => {
    keyword.value = '';
    show.value = true;
    if (!loaded.value) load();
    // 定位到当前已选的一级
    if (props.modelValue) {
        const p = findPath(tree.value, props.modelValue);
        if (p && p.length) activeL1.value = p[0];
    }
    if (!activeL1.value && tree.value.length) activeL1.value = tree.value[0];
};

load();
</script>

<style lang="scss" scoped>
.field { display: flex; align-items: center; min-height: 88rpx; }
.label { width: 170rpx; font-size: 28rpx; color: #333; flex-shrink: 0; }
.req { color: #FF4D4F; margin-right: 6rpx; }
.value { flex: 1; display: flex; align-items: center; justify-content: flex-end; font-size: 28rpx; color: #333; overflow: hidden; }
.value--ph { color: #c4c8cf; }
.ph { color: #c4c8cf; }

.pop { height: 76vh; display: flex; flex-direction: column; }
.head { display: flex; align-items: center; justify-content: space-between; padding: 28rpx 30rpx 14rpx; }
.h-title { font-size: 30rpx; font-weight: 600; color: #333; }
.h-btn { font-size: 28rpx; color: #9098A3; }
.h-btn--hide { opacity: 0; }
.search { display: flex; align-items: center; margin: 8rpx 30rpx 14rpx; height: 64rpx; background: #F5F7FA; border-radius: 32rpx; padding: 0 24rpx; }

.search-list { flex: 1; padding: 0 30rpx; }
.sr-item { padding: 22rpx 0; border-bottom: 2rpx solid #f3f4f6; display: flex; flex-direction: column; }
.sr-name { font-size: 28rpx; color: #333; }
.sr-name--on { color: var(--primary-color); font-weight: 600; }
.sr-full { font-size: 22rpx; color: #b4b8bf; margin-top: 4rpx; }

.body { flex: 1; display: flex; overflow: hidden; }
.left { width: 220rpx; background: #F7F8FA; height: 100%; }
.l1 { padding: 28rpx 24rpx; font-size: 26rpx; color: #555; position: relative; }
.l1--on { background: #fff; color: var(--primary-color); font-weight: 600; }
.l1--on::before { content: ''; position: absolute; left: 0; top: 28rpx; bottom: 28rpx; width: 6rpx; background: var(--primary-color); border-radius: 4rpx; }
.right { flex: 1; height: 100%; padding: 8rpx 28rpx 40rpx; }
.leaf-row { padding: 24rpx 0; font-size: 28rpx; color: #333; display: flex; align-items: center; justify-content: space-between; border-bottom: 2rpx solid #f3f4f6; }
.leaf--on { color: var(--primary-color); font-weight: 600; }
.grp { padding: 20rpx 0 8rpx; }
.grp-title { font-size: 27rpx; color: #333; font-weight: 600; margin-bottom: 18rpx; display: flex; align-items: center; justify-content: space-between; }
.chips { display: flex; flex-wrap: wrap; gap: 16rpx; }
.chip { padding: 10rpx 26rpx; background: #F5F7FA; color: #555; font-size: 25rpx; border-radius: 28rpx; border: 2rpx solid transparent; }
.chip--on { background: var(--primary-color-light, #E6FFF5); color: var(--primary-color); border-color: var(--primary-color); }
.tip { font-size: 24rpx; color: #9098A3; padding: 30rpx 0; text-align: center; }
.tip--center { padding-top: 120rpx; }
</style>
