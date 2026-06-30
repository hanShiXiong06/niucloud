<template>
    <view>
        <!-- 触发字段 -->
        <view class="picker-field" @click="open">
            <text class="label"><text class="req">*</text>分类</text>
            <view class="value" :class="{ 'value--ph': !selectedName }">
                <text class="truncate">{{ selectedName || '请选择分类' }}</text>
                <text class="nc-iconfont nc-icon-youV6xx text-[24rpx] text-[#c4c8cf] ml-[8rpx]"></text>
            </view>
        </view>

        <u-popup :show="show" mode="bottom" round="20" :closeOnClickOverlay="true" @close="show = false">
            <view class="pk-wrap">
                <view class="pk-head">
                    <text class="pk-btn" @click="show = false">取消</text>
                    <text class="pk-title">选择分类</text>
                    <text class="pk-btn pk-btn--hide">取消</text>
                </view>

                <!-- 面包屑 -->
                <scroll-view scroll-x class="pk-crumb" v-if="path.length && !keyword">
                    <text class="crumb-item" @click="goRoot">全部</text>
                    <text v-for="(p, i) in path" :key="p.category_id">
                        <text class="crumb-sep">/</text>
                        <text class="crumb-item" :class="{ 'crumb-cur': i === path.length - 1 }" @click="goLevel(i)">{{ p.category_name }}</text>
                    </text>
                </scroll-view>

                <!-- 搜索 -->
                <view class="pk-search">
                    <text class="nc-iconfont nc-icon-sousuo text-[30rpx] text-[#9098A3] mr-[10rpx]"></text>
                    <input class="flex-1 text-[26rpx]" v-model="keyword" placeholder="搜索分类名称" placeholder-class="ph" />
                    <text v-if="keyword" class="nc-iconfont nc-icon-guanbiV6xx text-[26rpx] text-[#c4c4c4]" @click="keyword = ''"></text>
                </view>

                <scroll-view scroll-y class="pk-list">
                    <view v-for="node in showList" :key="node.category_id" class="pk-item">
                        <view class="pk-item-main" @click="select(node)">
                            <text class="pk-name" :class="{ 'pk-name--on': node.category_id == modelValue }">{{ node.category_name }}</text>
                            <text v-if="keyword && node.category_full_name" class="pk-full">{{ node.category_full_name }}</text>
                        </view>
                        <text v-if="node.category_id == modelValue" class="nc-iconfont nc-icon-duihaoV6xx text-[30rpx] text-primary mr-[16rpx]"></text>
                        <view v-if="!keyword && hasChild(node)" class="pk-drill" @click.stop="drill(node)">
                            <text class="text-[24rpx] text-[#9098A3] mr-[4rpx]">下级</text>
                            <text class="nc-iconfont nc-icon-youV6xx text-[26rpx] text-[#c4c8cf]"></text>
                        </view>
                    </view>

                    <view v-if="loading" class="pk-tip">加载中…</view>
                    <view v-else-if="!showList.length" class="pk-tip">{{ keyword ? '没有匹配的分类' : '暂无分类' }}</view>
                </scroll-view>
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
const path = ref<any[]>([]);
const selectedName = ref('');
const keyword = ref('');

const hasChild = (n: any) => Array.isArray(n[CHILD]) && n[CHILD].length > 0;

// 当前层级列表
const currentList = computed(() => {
    if (!path.value.length) return tree.value;
    return path.value[path.value.length - 1][CHILD] || [];
});

// 拍平用于搜索
const flatList = computed(() => {
    const out: any[] = [];
    const walk = (list: any[]) => {
        list.forEach((n: any) => {
            out.push(n);
            if (hasChild(n)) walk(n[CHILD]);
        });
    };
    walk(tree.value);
    return out;
});

const showList = computed(() => {
    const k = keyword.value.trim();
    if (k) {
        return flatList.value.filter((n: any) =>
            (n.category_name || '').includes(k) || (n.category_full_name || '').includes(k)
        );
    }
    return currentList.value;
});

const findNode = (list: any[], id: any): any => {
    for (const n of list) {
        if (n.category_id == id) return n;
        if (hasChild(n)) { const f = findNode(n[CHILD], id); if (f) return f; }
    }
    return null;
};
const syncName = () => {
    if (props.modelValue === '' || props.modelValue == null) { selectedName.value = ''; return; }
    const n = findNode(tree.value, props.modelValue);
    if (n) selectedName.value = n.category_full_name || n.category_name;
};
watch(() => props.modelValue, syncName);

const load = () => {
    if (loading.value) return;
    loading.value = true;
    getCategoryTree().then((res: any) => {
        tree.value = Array.isArray(res.data) ? res.data : (res.data?.data || res.data?.list || []);
        loaded.value = true;
        syncName();
    }).catch(() => {}).finally(() => {
        loading.value = false;
    });
};

const open = () => {
    keyword.value = '';
    path.value = [];
    show.value = true;
    if (!loaded.value) load();
};
const goRoot = () => { path.value = []; };
const goLevel = (i: number) => { path.value = path.value.slice(0, i + 1); };
const drill = (node: any) => { path.value.push(node); };

const select = (node: any) => {
    emit('update:modelValue', node.category_id);
    emit('change', node);
    selectedName.value = node.category_full_name || node.category_name;
    show.value = false;
};

// 首次预加载名称（编辑回填时）
load();
</script>

<style lang="scss" scoped>
.picker-field {
    display: flex;
    align-items: center;
    min-height: 88rpx;
    border-bottom: 2rpx solid #f3f4f6;
}
.label { width: 180rpx; font-size: 28rpx; color: #333; flex-shrink: 0; }
.req { color: #FF4D4F; margin-right: 6rpx; }
.value {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    font-size: 28rpx;
    color: #333;
    overflow: hidden;
}
.value--ph { color: #c4c8cf; }
.ph { color: #c4c8cf; }
.pk-wrap { height: 72vh; display: flex; flex-direction: column; }
.pk-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 28rpx 30rpx 12rpx;
}
.pk-title { font-size: 30rpx; font-weight: 600; color: #333; }
.pk-btn { font-size: 28rpx; color: #9098A3; }
.pk-btn--hide { opacity: 0; }
.pk-crumb {
    white-space: nowrap;
    padding: 6rpx 30rpx;
}
.crumb-item { font-size: 24rpx; color: var(--primary-color); }
.crumb-cur { color: #9098A3; }
.crumb-sep { font-size: 24rpx; color: #c4c8cf; margin: 0 8rpx; }
.pk-search {
    display: flex;
    align-items: center;
    margin: 12rpx 30rpx 8rpx;
    height: 64rpx;
    background: #F5F7FA;
    border-radius: 32rpx;
    padding: 0 24rpx;
}
.pk-list { flex: 1; padding: 0 30rpx; }
.pk-item {
    display: flex;
    align-items: center;
    min-height: 92rpx;
    border-bottom: 2rpx solid #f3f4f6;
}
.pk-item-main { flex: 1; display: flex; flex-direction: column; justify-content: center; min-height: 92rpx; }
.pk-name { font-size: 28rpx; color: #333; }
.pk-name--on { color: var(--primary-color); font-weight: 600; }
.pk-full { font-size: 22rpx; color: #b4b8bf; margin-top: 4rpx; }
.pk-drill {
    display: flex;
    align-items: center;
    padding-left: 24rpx;
    height: 92rpx;
    border-left: 2rpx solid #f3f4f6;
    margin-left: 8rpx;
}
.pk-tip { text-align: center; color: #9098A3; font-size: 26rpx; padding: 60rpx 0; }
</style>
