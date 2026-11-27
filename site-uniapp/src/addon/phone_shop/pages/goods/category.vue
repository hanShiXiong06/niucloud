<template>
    <view class="min-h-[100vh] bg-[var(--page-bg-color)] pb-[40rpx]" :style="themeColor()">
        <view class="page-head">
            <text class="head-tip">支持最多三级分类</text>
            <view class="head-add" @click="openAdd(null)">
                <u-icon name="plus" color="var(--primary-color)" size="15"></u-icon>
                <text class="ml-[6rpx]">新增一级</text>
            </view>
        </view>

        <view class="px-[24rpx]">
            <view class="tree-card" v-if="tree.length">
                <!-- 一级 -->
                <template v-for="l1 in tree" :key="l1.category_id">
                    <view class="row row--l1">
                        <view class="row-main" @click="hasChild(l1) ? toggle(l1) : null">
                            <u-icon v-if="hasChild(l1)" :name="expanded[l1.category_id] ? 'arrow-down' : 'arrow-right'" color="#9098A3" size="14"></u-icon>
                            <text v-else class="dot"></text>
                            <text class="name">{{ l1.category_name }}</text>
                            <text class="count" v-if="hasChild(l1)">{{ l1.child_list.length }}</text>
                            <text class="hide-flag" v-if="!l1.is_show">隐藏</text>
                        </view>
                        <view class="acts">
                            <text class="act" @click="openAdd(l1)">+子级</text>
                            <text class="act" @click="openEdit(l1)">编辑</text>
                            <text class="act act--del" @click="confirmDel(l1)">删除</text>
                        </view>
                    </view>

                    <!-- 二级 -->
                    <template v-if="expanded[l1.category_id]">
                        <template v-for="l2 in l1.child_list" :key="l2.category_id">
                            <view class="row row--l2">
                                <view class="row-main" @click="hasChild(l2) ? toggle(l2) : null">
                                    <u-icon v-if="hasChild(l2)" :name="expanded[l2.category_id] ? 'arrow-down' : 'arrow-right'" color="#9098A3" size="13"></u-icon>
                                    <text v-else class="dot"></text>
                                    <text class="name">{{ l2.category_name }}</text>
                                    <text class="count" v-if="hasChild(l2)">{{ l2.child_list.length }}</text>
                                    <text class="hide-flag" v-if="!l2.is_show">隐藏</text>
                                </view>
                                <view class="acts">
                                    <text class="act" @click="openAdd(l2)">+子级</text>
                                    <text class="act" @click="openEdit(l2)">编辑</text>
                                    <text class="act act--del" @click="confirmDel(l2)">删除</text>
                                </view>
                            </view>

                            <!-- 三级 -->
                            <template v-if="expanded[l2.category_id]">
                                <view class="row row--l3" v-for="l3 in l2.child_list" :key="l3.category_id">
                                    <view class="row-main">
                                        <text class="dot"></text>
                                        <text class="name">{{ l3.category_name }}</text>
                                        <text class="hide-flag" v-if="!l3.is_show">隐藏</text>
                                    </view>
                                    <view class="acts">
                                        <text class="act" @click="openEdit(l3)">编辑</text>
                                        <text class="act act--del" @click="confirmDel(l3)">删除</text>
                                    </view>
                                </view>
                            </template>
                        </template>
                    </template>
                </template>
            </view>

            <view v-else-if="!loading" class="empty">还没有分类，点下方新增</view>
        </view>

        <!-- 增/改表单 -->
        <u-popup :show="formShow" mode="bottom" round="20" @close="formShow=false">
            <view class="form">
                <view class="form-head">
                    <text class="f-btn" @click="formShow=false">取消</text>
                    <text class="f-title">{{ form.category_id ? '编辑分类' : '新增分类' }}</text>
                    <text class="f-btn f-btn--ok" @click="save">保存</text>
                </view>
                <view class="f-field">
                    <text class="f-label">上级分类</text>
                    <text class="f-static">{{ parentName }}</text>
                </view>
                <view class="f-field">
                    <text class="f-label"><text class="req">*</text>名称</text>
                    <input class="f-input" v-model="form.category_name" placeholder="请输入分类名称" placeholder-class="ph" maxlength="30" />
                </view>
                <view class="f-field">
                    <text class="f-label">排序</text>
                    <input class="f-input" type="number" v-model="form.sort" placeholder="数字越大越靠前" placeholder-class="ph" />
                </view>
                <view class="f-field f-field--last">
                    <text class="f-label">前台显示</text>
                    <u-switch v-model="showOn" activeColor="var(--primary-color)" size="22"></u-switch>
                </view>
            </view>
        </u-popup>

        <u-modal :show="delShow" title="提示" :content="delTip" :showCancelButton="true" @confirm="doDel" @cancel="delShow=false"></u-modal>
    </view>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue';
import { onShow } from '@dcloudio/uni-app';
import { getCategoryTree, addCategory, editCategory, delCategory } from '@/addon/phone_shop/api/goods';

const CHILD = 'child_list';
const tree = ref<any[]>([]);
const loading = ref(false);
const expanded = reactive<Record<number, boolean>>({});

const formShow = ref(false);
const parentName = ref('');
const showOn = ref(true);
const form = reactive<any>({ category_id: 0, category_name: '', pid: 0, sort: 0, is_show: 1 });

const delShow = ref(false);
const delItem = ref<any>(null);

const hasChild = (n: any) => Array.isArray(n[CHILD]) && n[CHILD].length > 0;
const toggle = (n: any) => { expanded[n.category_id] = !expanded[n.category_id]; };

const load = () => {
    loading.value = true;
    getCategoryTree().then((res: any) => {
        tree.value = Array.isArray(res.data) ? res.data : (res.data?.data || []);
    }).catch(() => {}).finally(() => { loading.value = false; });
};

// 找父级名称
const findName = (list: any[], id: any): string => {
    for (const n of list) {
        if (n.category_id == id) return n.category_full_name || n.category_name;
        if (hasChild(n)) { const f = findName(n[CHILD], id); if (f) return f; }
    }
    return '';
};

const openAdd = (parent: any) => {
    form.category_id = 0;
    form.category_name = '';
    form.pid = parent ? parent.category_id : 0;
    form.sort = 0;
    showOn.value = true;
    parentName.value = parent ? (parent.category_full_name || parent.category_name) : '一级分类（顶级）';
    formShow.value = true;
};
const openEdit = (node: any) => {
    form.category_id = node.category_id;
    form.category_name = node.category_name;
    form.pid = node.pid || 0;
    form.sort = node.sort || 0;
    showOn.value = !!node.is_show;
    parentName.value = node.pid ? findName(tree.value, node.pid) : '一级分类（顶级）';
    formShow.value = true;
};

const save = () => {
    if (!form.category_name.trim()) return uni.showToast({ title: '请输入分类名称', icon: 'none' });
    const payload = {
        category_name: form.category_name.trim(),
        pid: form.pid,
        sort: Number(form.sort) || 0,
        is_show: showOn.value ? 1 : 0,
        image: ''
    };
    const req = form.category_id ? editCategory(form.category_id, payload) : addCategory(payload);
    req.then(() => { formShow.value = false; load(); }).catch(() => {});
};

const delTip = computed(() => {
    const n = delItem.value;
    if (n && n.level == 1 && hasChild(n)) return '该一级分类下的子分类会一并删除，确认删除吗？';
    return '确认删除该分类吗？';
});
const confirmDel = (node: any) => { delItem.value = node; delShow.value = true; };
const doDel = () => {
    delShow.value = false;
    if (!delItem.value) return;
    delCategory(delItem.value.category_id).then(() => load()).catch(() => {});
};

onShow(() => load());
</script>

<style lang="scss" scoped>
.page-head { display: flex; align-items: center; justify-content: space-between; padding: 24rpx 28rpx 8rpx; }
.head-tip { font-size: 24rpx; color: #9098A3; }
.head-add { display: flex; align-items: center; font-size: 26rpx; color: var(--primary-color); font-weight: 500; padding: 8rpx 24rpx; background: var(--primary-color-light, #E6FFF5); border-radius: 28rpx; }
.tree-card { background: #fff; border-radius: 24rpx; overflow: hidden; }
.row { display: flex; align-items: center; justify-content: space-between; min-height: 96rpx; padding: 0 28rpx; border-bottom: 2rpx solid #f3f4f6; }
.row--l2 { background: #fbfbfc; padding-left: 52rpx; }
.row--l3 { background: #f7f8fa; padding-left: 80rpx; }
.row-main { flex: 1; display: flex; align-items: center; overflow: hidden; }
.dot { width: 10rpx; height: 10rpx; border-radius: 50%; background: #d4d8df; margin-right: 6rpx; }
.name { font-size: 28rpx; color: #333; margin-left: 12rpx; max-width: 280rpx; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.count { font-size: 22rpx; color: #9098A3; background: #f0f1f3; border-radius: 20rpx; padding: 0 12rpx; margin-left: 12rpx; }
.hide-flag { font-size: 20rpx; color: #E8954A; background: #FFF3E0; border-radius: 6rpx; padding: 2rpx 10rpx; margin-left: 12rpx; }
.acts { display: flex; align-items: center; gap: 20rpx; flex-shrink: 0; }
.act { font-size: 25rpx; color: #555; }
.act--del { color: #FF4D4F; }
.empty { text-align: center; color: #9098A3; font-size: 26rpx; padding: 120rpx 0; }

.form { padding-bottom: calc(20rpx + env(safe-area-inset-bottom)); }
.form-head { display: flex; align-items: center; justify-content: space-between; padding: 28rpx 32rpx; border-bottom: 2rpx solid #f3f4f6; }
.f-title { font-size: 30rpx; font-weight: 600; color: #333; }
.f-btn { font-size: 28rpx; color: #9098A3; }
.f-btn--ok { color: var(--primary-color); font-weight: 600; }
.f-field { display: flex; align-items: center; min-height: 92rpx; padding: 0 32rpx; border-bottom: 2rpx solid #f6f7f9; }
.f-field--last { border-bottom: 0; }
.f-label { width: 160rpx; font-size: 28rpx; color: #333; flex-shrink: 0; }
.req { color: #FF4D4F; margin-right: 6rpx; }
.f-input { flex: 1; text-align: right; font-size: 28rpx; color: #333; }
.f-static { flex: 1; text-align: right; font-size: 28rpx; color: #9098A3; }
.ph { color: #c4c8cf; }
</style>
