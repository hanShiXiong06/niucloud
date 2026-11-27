<template>
    <view>
        <!-- 触发字段 -->
        <view class="field" :class="[`field--${layout}`, { 'field--embedded': embedded, 'field--on': selectedName }]" @click="open">
            <text class="label">
                <text v-if="required" class="req">*</text>{{ label }}
            </text>
            <view class="value" :class="{ 'value--ph': !selectedName }">
                <text class="truncate "  v-if="selectedName" >{{ selectedName || placeholder }}</text>
                <text v-else class="text-[#c0c4cc]">{{ placeholder || placeholder }}</text>
                <text
                    v-if="clearable && selectedName"
                    class="nc-iconfont nc-icon-guanbiV6xx clear-icon"
                    @click.stop="clear"
                ></text>
                <text class="nc-iconfont nc-icon-youV6xx text-[24rpx] text-[#c4c8cf] ml-[8rpx]"></text>
            </view>
        </view>

        <u-popup :show="show" mode="bottom" round="20" :closeOnClickOverlay="true" @close="show = false">
            <view class="pop">
                <view class="head">
                    <text class="h-btn" @click="show = false">取消</text>
                    <text class="h-title">选择分类</text>
                    <view v-if="createable" class="h-add" @click.stop="openCreate">
                        <u-icon name="plus" color="var(--primary-color)" size="16" />
                        <text>新增</text>
                    </view>
                    <text v-else class="h-btn h-btn--hide">取消</text>
                </view>

                <view class="search">
                    <text class="nc-iconfont nc-icon-sousuo text-[30rpx] text-[#9098A3] mr-[10rpx]"></text>
                    <input class="flex-1 text-[26rpx]" v-model="keyword" placeholder="搜索分类名称" placeholder-class="ph" />
                    <text v-if="keyword" class="nc-iconfont nc-icon-guanbiV6xx text-[26rpx] text-[#c4c4c4]" @click="keyword = ''"></text>
                </view>

                <!-- 搜索结果 -->
                <scroll-view v-if="keyword.trim()" scroll-y class="search-list">
                    <view v-for="n in searchResult" :key="n.category_id" class="sr-item" :class="{ 'sr-item--on': n.category_id == modelValue }" @click="selectNode(n)">
                        <view class="sr-main">
                            <text class="sr-name" :class="{ 'sr-name--on': n.category_id == modelValue }">{{ n.category_name }}</text>
                            <text class="sr-full">{{ n.category_full_name }}</text>
                        </view>
                        <text v-if="n.category_id == modelValue" class="nc-iconfont nc-icon-duihaoV6xx text-primary"></text>
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
                                        @click="selectNode(l3)">
                                        <text>{{ l3.category_name }}</text>
                                        <text v-if="l3.category_id == modelValue" class="nc-iconfont nc-icon-duihaoV6xx"></text>
                                    </view>
                                </view>
                            </view>
                        </template>
                        <view v-else class="tip tip--center">请选择左侧分类</view>
                    </scroll-view>
                </view>
            </view>
        </u-popup>

        <u-popup :show="createShow" mode="bottom" round="20" :closeOnClickOverlay="true" @close="createShow = false">
            <view class="create-pop">
                <view class="create-head">
                    <text class="h-btn" @click="createShow = false">取消</text>
                    <text class="h-title">新增分类</text>
                    <text class="h-btn h-btn--ok" @click="saveCreate">保存</text>
                </view>

                <view class="create-field create-field--block">
                    <text class="create-label">上级分类</text>
                    <scroll-view scroll-y class="parent-list">
                        <view
                            v-for="item in parentOptions"
                            :key="item.id"
                            class="parent-item"
                            :class="{ 'parent-item--on': createParentId == item.id }"
                            @click="createParentId = item.id"
                        >
                            <view class="parent-main">
                                <text class="parent-name">{{ item.name }}</text>
                                <text class="parent-level">{{ item.levelText }}</text>
                            </view>
                            <u-icon v-if="createParentId == item.id" name="checkbox-mark" color="var(--primary-color)" size="17" />
                        </view>
                    </scroll-view>
                </view>

                <view class="create-field">
                    <text class="create-label"><text class="req">*</text>分类名称</text>
                    <u-input v-model="createName" placeholder="请输入分类名称" border="none" inputAlign="right" maxlength="30" />
                </view>
                <view class="create-field">
                    <text class="create-label">排序</text>
                    <u-input v-model="createSort" type="number" placeholder="数字越大越靠前" border="none" inputAlign="right" />
                </view>
                <view class="create-actions">
                    <view class="create-button">
                        <u-button :loading="createSaving" type="primary" shape="circle" @click="saveCreate">保存并选择</u-button>
                    </view>
                </view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import request from '@/utils/request';

const props = defineProps({
    modelValue: { type: [Number, String], default: '' },
    label: { type: String, default: '分类' },
    placeholder: { type: String, default: '请选择分类' },
    required: { type: Boolean, default: true },
    layout: { type: String, default: 'horizontal' },
    embedded: { type: Boolean, default: false },
    clearable: { type: Boolean, default: false },
    apiPath: { type: String, default: 'phone_shop/goods/category/tree' },
    createable: { type: Boolean, default: true },
    createApiPath: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue', 'change', 'clear']);

const CHILD = 'child_list';
const show = ref(false);
const loading = ref(false);
const loaded = ref(false);
const tree = ref<any[]>([]);
const activeL1 = ref<any>(null);
const keyword = ref('');
const selectedName = ref('');
const createShow = ref(false);
const createSaving = ref(false);
const createName = ref('');
const createSort = ref('');
const createParentId = ref<any>(0);

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
const parentOptions = computed(() => {
    const options: any[] = [{ id: 0, name: '一级分类（顶级）', level: 0, levelText: '顶级' }];
    const walk = (list: any[], level = 1) => {
        list.forEach((n: any) => {
            const nodeLevel = Number(n.level || level);
            if (nodeLevel < 3) {
                options.push({
                    id: n.category_id,
                    name: n.category_full_name || n.category_name,
                    level: nodeLevel,
                    levelText: nodeLevel === 1 ? '一级下新增' : '二级下新增'
                });
            }
            if (hasChild(n)) walk(n[CHILD], nodeLevel + 1);
        });
    };
    walk(tree.value);
    return options;
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
watch(() => props.apiPath, () => {
    loaded.value = false;
    tree.value = [];
    activeL1.value = null;
    selectedName.value = '';
    load();
});

const selectNode = (node: any) => {
    const p = findPath(tree.value, node.category_id) || [node];
    selectedName.value = node.category_full_name || node.category_name;
    emit('update:modelValue', node.category_id);
    emit('change', { category_id: node.category_id, category_path: p.map((n: any) => n.category_id), category_nodes: p, node });
    show.value = false;
};

const clear = () => {
    selectedName.value = '';
    emit('update:modelValue', '');
    emit('change', { category_id: '', category_path: [], node: null });
    emit('clear');
};

const load = (force = false) => {
    if (force) loaded.value = false;
    if (loaded.value || loading.value) return Promise.resolve();
    loading.value = true;
    return request.get(props.apiPath).then((res: any) => {
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

const inferCreateApiPath = () => {
    if (props.createApiPath) return props.createApiPath;
    if (props.apiPath.includes('phone_shop/')) return 'phone_shop/goods/category';
    return 'erp/goods/category/save/0';
};
const openCreate = () => {
    if (!loaded.value) load();
    createName.value = '';
    createSort.value = '';
    const currentPath = props.modelValue ? findPath(tree.value, props.modelValue) : null;
    const current = currentPath?.[currentPath.length - 1];
    if (current && Number(current.level || currentPath.length) < 3) {
        createParentId.value = current.category_id;
    } else if (currentPath && currentPath.length > 1) {
        createParentId.value = currentPath[currentPath.length - 2].category_id;
    } else if (activeL1.value && Number(activeL1.value.level || 1) < 3) {
        createParentId.value = activeL1.value.category_id;
    } else {
        createParentId.value = 0;
    }
    createShow.value = true;
};
const saveCreate = () => {
    const name = createName.value.trim();
    if (!name) return uni.showToast({ title: '请输入分类名称', icon: 'none' });
    const payload = {
        category_name: name,
        pid: Number(createParentId.value) || 0,
        sort: Number(createSort.value) || 0,
        is_show: 1,
        image: ''
    };
    createSaving.value = true;
    request.post(inferCreateApiPath(), payload, { showSuccessMessage: true }).then((res: any) => {
        const newId = Number(res?.data?.category_id || res?.data || 0);
        createShow.value = false;
        return load(true).then(() => {
            if (!newId) return;
            const path = findPath(tree.value, newId);
            if (path && path.length) {
                activeL1.value = path[0];
                selectNode(path[path.length - 1]);
            }
        });
    }).catch(() => {}).finally(() => {
        createSaving.value = false;
    });
};

load();
</script>

<style lang="scss" scoped>
.field { width: 100%; display: flex; align-items: center; min-height: 88rpx; box-sizing: border-box; }
.field--vertical { align-items: stretch; flex-direction: column; gap: 12rpx; }
.field--embedded { padding-top: 0; }
.field--embedded.field--vertical { min-height: auto; }
.field--embedded.field--horizontal { min-height: 92rpx; gap: 16rpx; border-bottom: 2rpx solid #f3f4f6; }
.label { width: 170rpx; font-size: 28rpx; color: #333; flex-shrink: 0; }
.field--vertical .label { width: auto; font-size: 26rpx; color: #334155; font-weight: 600; }
.field--embedded .label { color: #334155; font-weight: 600; }
.field--embedded.field--horizontal .label { width: 150rpx; font-size: 26rpx; }
.req { color: #FF4D4F; margin-right: 6rpx; }
.value { flex: 1; display: flex; align-items: center; justify-content: flex-end; font-size: 28rpx; color: #333; overflow: hidden; }
.field--vertical .value { width: 100%; min-height: 72rpx; justify-content: space-between; box-sizing: border-box; }
.field--embedded .value { min-height: 72rpx; background: #f8fafc; border: 2rpx solid transparent; border-radius: 12rpx; padding: 0 18rpx; color: #0f172a; font-size: 26rpx; box-sizing: border-box; justify-content: space-between; }
.field--embedded.field--on .value { background: #f8fbff; border-color: #3b6ef5; }
.value--ph { color: #c4c8cf; }
.truncate { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.clear-icon { width: 40rpx; height: 40rpx; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 26rpx; flex-shrink: 0; }
.ph { color: #c4c8cf; }

.pop { height: 76vh; display: flex; flex-direction: column; }
.head { display: flex; align-items: center; justify-content: space-between; padding: 28rpx 30rpx 14rpx; }
.h-title { font-size: 30rpx; font-weight: 600; color: #333; }
.h-btn { font-size: 28rpx; color: #9098A3; }
.h-btn--hide { opacity: 0; }
.h-btn--ok { color: var(--primary-color); font-weight: 600; }
.h-add { min-width: 92rpx; display: flex; align-items: center; justify-content: flex-end; gap: 6rpx; font-size: 26rpx; color: var(--primary-color); font-weight: 600; }
.search { display: flex; align-items: center; margin: 8rpx 30rpx 14rpx; height: 64rpx; background: #F5F7FA; border-radius: 32rpx; padding: 0 24rpx; }

.search-list { flex: 1; padding: 0 30rpx; }
.sr-item { padding: 22rpx 0; border-bottom: 2rpx solid #f3f4f6; display: flex; align-items: center; justify-content: space-between; gap: 16rpx; }
.sr-item--on { background: #f8fbff; }
.sr-main { flex: 1; min-width: 0; display: flex; flex-direction: column; }
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
.chip { padding: 10rpx 26rpx; background: #F5F7FA; color: #555; font-size: 25rpx; border-radius: 28rpx; border: 2rpx solid transparent; display: flex; align-items: center; gap: 8rpx; }
.chip--on { background: var(--primary-color-light, #E6FFF5); color: var(--primary-color); border-color: var(--primary-color); }
.tip { font-size: 24rpx; color: #9098A3; padding: 30rpx 0; text-align: center; }
.tip--center { padding-top: 120rpx; }
.create-pop { padding-bottom: calc(24rpx + env(safe-area-inset-bottom)); background: #fff; }
.create-head { display: flex; align-items: center; justify-content: space-between; padding: 28rpx 30rpx 18rpx; border-bottom: 2rpx solid #f3f4f6; }
.create-field { display: flex; align-items: center; min-height: 92rpx; padding: 0 30rpx; border-bottom: 2rpx solid #f6f7f9; box-sizing: border-box; }
.create-field--block { display: block; padding: 24rpx 30rpx; }
.create-label { width: 170rpx; flex-shrink: 0; font-size: 28rpx; color: #334155; font-weight: 600; }
.parent-list { height: 360rpx; margin-top: 18rpx; }
.parent-item { min-height: 78rpx; display: flex; align-items: center; justify-content: space-between; gap: 16rpx; padding: 0 20rpx; margin-bottom: 14rpx; border-radius: 14rpx; background: #f8fafc; border: 2rpx solid transparent; box-sizing: border-box; }
.parent-item--on { background: #f8fbff; border-color: var(--primary-color); }
.parent-main { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.parent-name { font-size: 27rpx; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.parent-level { font-size: 22rpx; color: #94a3b8; margin-top: 4rpx; }
.create-actions { padding: 28rpx 30rpx 0; }
.create-button { width: 100%; }
</style>
