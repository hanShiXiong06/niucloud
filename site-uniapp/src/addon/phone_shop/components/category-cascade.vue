<template>
    <view class="cascade">
        <view class="row-label">
            <text><text class="req">*</text>分类</text>
            <text class="picked" v-if="pickedName">{{ pickedName }}</text>
        </view>

        <view v-for="(items, lv) in levelItems" :key="lv" class="level">
            <view class="chips">
                <view v-for="node in displayItems(items, lv)" :key="node.category_id"
                    class="chip" :class="{ 'chip--on': selected[lv] && selected[lv].category_id === node.category_id }"
                    @click="pick(lv, node)">{{ node.category_name }}</view>

                <view v-if="items.length > collapseN" class="chip chip--more" @click="toggle(lv)">
                    <text>{{ expanded[lv] ? '收起' : '更多' }}</text>
                    <text class="nc-iconfont text-[20rpx] ml-[4rpx]" :class="expanded[lv] ? 'nc-icon-shangV6xx' : 'nc-icon-xiaV6xx'"></text>
                </view>
            </view>
        </view>

        <view v-if="loading" class="tip">加载中…</view>
        <view v-else-if="!levelItems.length || !levelItems[0] || !levelItems[0].length" class="tip">暂无分类</view>
    </view>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue';
import { getCategoryTree } from '@/addon/phone_shop/api/goods';

const props = defineProps({
    modelValue: { type: [Number, String], default: '' }
});
const emit = defineEmits(['update:modelValue', 'change']);

const CHILD = 'child_list';
const collapseN = 9;

const tree = ref<any[]>([]);
const loading = ref(false);
const loaded = ref(false);
const selected = ref<any[]>([]);          // 每级选中的节点
const expanded = reactive<Record<number, boolean>>({});

const hasChild = (n: any) => Array.isArray(n[CHILD]) && n[CHILD].length > 0;

// 各层级的可选项
const levelItems = computed(() => {
    const arr: any[][] = [tree.value];
    for (let i = 0; i < selected.value.length; i++) {
        const node = selected.value[i];
        if (node && hasChild(node)) arr.push(node[CHILD]);
        else break;
    }
    return arr;
});

const pickedName = computed(() => {
    if (!selected.value.length) return '';
    const last = selected.value[selected.value.length - 1];
    return last ? (last.category_full_name || last.category_name) : '';
});

const displayItems = (items: any[], lv: number) => {
    if (expanded[lv] || items.length <= collapseN) return items;
    return items.slice(0, collapseN);
};
const toggle = (lv: number) => { expanded[lv] = !expanded[lv]; };

const emitChange = () => {
    const last = selected.value[selected.value.length - 1];
    const path = selected.value.map((n: any) => n.category_id);
    emit('update:modelValue', last ? last.category_id : '');
    emit('change', { category_id: last ? last.category_id : '', category_path: path, node: last });
};

const pick = (lv: number, node: any) => {
    // 同级点击已选项：取消到上一级
    if (selected.value[lv] && selected.value[lv].category_id === node.category_id) {
        selected.value = selected.value.slice(0, lv);
    } else {
        selected.value = selected.value.slice(0, lv);
        selected.value.push(node);
    }
    // 清掉更深层的展开态
    Object.keys(expanded).forEach(k => { if (Number(k) > lv) expanded[Number(k)] = false; });
    emitChange();
};

// 根据 id 反查路径（编辑回填）
const findPath = (list: any[], id: any, trail: any[] = []): any[] | null => {
    for (const n of list) {
        const t = [...trail, n];
        if (n.category_id == id) return t;
        if (hasChild(n)) { const f = findPath(n[CHILD], id, t); if (f) return f; }
    }
    return null;
};
const syncFromValue = () => {
    if (props.modelValue === '' || props.modelValue == null) return;
    if (selected.value.length && selected.value[selected.value.length - 1]?.category_id == props.modelValue) return;
    const p = findPath(tree.value, props.modelValue);
    if (p) selected.value = p;
};
watch(() => props.modelValue, syncFromValue);

const load = () => {
    if (loaded.value || loading.value) return;
    loading.value = true;
    getCategoryTree().then((res: any) => {
        tree.value = Array.isArray(res.data) ? res.data : (res.data?.data || res.data?.list || []);
        loaded.value = true;
        syncFromValue();
    }).catch(() => {}).finally(() => { loading.value = false; });
};
load();
</script>

<style lang="scss" scoped>
.cascade { padding: 8rpx 0 4rpx; }
.row-label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 28rpx;
    color: #333;
    margin-bottom: 20rpx;
}
.req { color: #FF4D4F; margin-right: 6rpx; }
.picked { font-size: 24rpx; color: var(--primary-color); max-width: 380rpx; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.level { margin-bottom: 8rpx; }
.chips { display: flex; flex-wrap: wrap; gap: 18rpx; margin-bottom: 14rpx; }
.chip {
    padding: 12rpx 28rpx;
    background: #F5F7FA;
    color: #555;
    font-size: 26rpx;
    border-radius: 32rpx;
    border: 2rpx solid transparent;
    line-height: 1.3;
}
.chip--on {
    background: var(--primary-color-light, #E6FFF5);
    color: var(--primary-color);
    border-color: var(--primary-color);
}
.chip--more {
    background: transparent;
    color: #9098A3;
    display: flex;
    align-items: center;
}
.tip { font-size: 24rpx; color: #9098A3; padding: 16rpx 0; }
</style>
