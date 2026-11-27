<template>
    <view class="min-h-[100vh] bg-[var(--page-bg-color)] overflow-hidden" :style="themeColor()">
        <!-- 固定头：搜索 + 筛选 -->
        <view class="fixed left-0 right-0 top-0 z-10 bg-[#fff] px-[24rpx] py-[12rpx] flex items-center">
            <view class="flex-1">
                <u-search v-model="keyword" placeholder="搜会员编号/昵称/手机号" :showAction="false" bgColor="#f3f4f6" height="30" @search="reload" @clear="reload"></u-search>
            </view>
            <view class="ml-[20rpx] flex flex-col items-center" @click="openFilter">
                <u-icon name="list-dot" :color="filterCount ? 'var(--primary-color)' : '#666'" size="22"></u-icon>
                <text class="text-[20rpx] mt-[2rpx]" :class="filterCount ? 'text-[var(--primary-color)]' : 'text-[#666]'">筛选{{ filterCount ? '·' + filterCount : '' }}</text>
            </view>
        </view>

        <z-paging ref="pagingRef" v-model="memberList" @query="queryList" :fixed="true" :auto="true" :default-page-size="15" :paging-style="{ top: '96rpx' }">
            <view class="sidebar-margin pt-[var(--top-m)]">
                <view class="mb-[var(--top-m)] card-template" v-for="(item, index) in memberList" :key="index">
                    <view class="flex items-center" @click="toLink(item)">
                        <u-avatar :default-url="img('static/resource/images/default_headimg.png')" :src="img(item.headimg)" size="84rpx" leftIcon="none"></u-avatar>
                        <view class="ml-[20rpx] flex-1 min-w-0">
                            <view class="flex items-center flex-wrap">
                                <text class="text-[30rpx] font-500 truncate max-w-[240rpx]">{{ item.nickname || '未命名' }}</text>
                                <u-tag v-if="item.member_level_name" :text="item.member_level_name" type="warning" size="mini" plain plainFill class="ml-[12rpx]"></u-tag>
                                <u-tag :text="Number(item.status) === 1 ? '正常' : '锁定'" :type="Number(item.status) === 1 ? 'success' : 'error'" size="mini" class="ml-[8rpx]"></u-tag>
                            </view>
                            <view class="text-[24rpx] text-[#999] mt-[8rpx]">
                                <text>{{ item.member_no }}</text>
                                <text v-if="item.mobile" class="ml-[16rpx]">{{ item.mobile }}</text>
                            </view>
                        </view>
                        <view class="px-[12rpx] py-[10rpx]" @click.stop="openActions(item)">
                            <u-icon name="more-dot-fill" color="#bbb" size="22"></u-icon>
                        </view>
                    </view>

                    <view class="flex flex-wrap mt-[16rpx]" v-if="item.member_label_array && item.member_label_array.length">
                        <u-tag v-for="(lb, k) in item.member_label_array" :key="k" :text="lb.label_name" type="info" size="mini" plain class="mr-[12rpx] mb-[8rpx]"></u-tag>
                    </view>

                    <view class="grid grid-cols-3 bg-[#fafafa] rounded-[14rpx] py-[20rpx] mt-[16rpx]" @click="toLink(item)">
                        <view class="flex flex-col items-center">
                            <text class="price-font text-[34rpx] fnt-500">{{ item.comsum_num || 0 }}</text>
                            <view class="text-[22rpx] text-[#999] mt-[6rpx]">消费次数</view>
                        </view>
                        <view class="flex flex-col items-center">
                            <text class="price-font text-[34rpx] fnt-500">{{ parseFloat(item.comsum_money || 0).toFixed(2) }}</text>
                            <view class="text-[22rpx] text-[#999] mt-[6rpx]">累计消费</view>
                        </view>
                        <view class="flex flex-col items-center">
                            <text class="price-font text-[34rpx] fnt-500">{{ Number(item.comsum_num) ? (Number(item.comsum_money) / Number(item.comsum_num)).toFixed(2) : '0.00' }}</text>
                            <view class="text-[22rpx] text-[#999] mt-[6rpx]">客单价</view>
                        </view>
                    </view>
                </view>
            </view>

            <template #empty>
                <u-empty mode="data" text="暂无会员" marginTop="160"></u-empty>
            </template>
        </z-paging>

        <!-- 添加会员 -->
        <view class="add-fab" @click="openAdd"><u-icon name="plus" color="#fff" size="28"></u-icon></view>

        <!-- 筛选 -->
        <u-popup :show="filterShow" mode="top" :round="16" @close="filterShow = false" zIndex="100">
            <view class="px-[30rpx] pt-[40rpx] pb-[30rpx]">
                <view class="text-[28rpx] font-500 mb-[20rpx]">会员等级</view>
                <view class="flex flex-wrap">
                    <u-tag text="全部" :type="tmpLevel === '' ? 'primary' : 'info'" :plain="tmpLevel !== ''" size="medium" class="mr-[16rpx] mb-[16rpx]" @click="tmpLevel = ''"></u-tag>
                    <u-tag v-for="lv in levelOptions" :key="lv.level_id" :text="lv.level_name" :type="tmpLevel === lv.level_id ? 'primary' : 'info'" :plain="tmpLevel !== lv.level_id" size="medium" class="mr-[16rpx] mb-[16rpx]" @click="tmpLevel = lv.level_id"></u-tag>
                </view>
                <view class="text-[28rpx] font-500 mb-[20rpx] mt-[20rpx]">会员标签</view>
                <view class="flex flex-wrap">
                    <u-tag text="全部" :type="tmpLabel === '' ? 'primary' : 'info'" :plain="tmpLabel !== ''" size="medium" class="mr-[16rpx] mb-[16rpx]" @click="tmpLabel = ''"></u-tag>
                    <u-tag v-for="lb in labelOptions" :key="lb.label_id" :text="lb.label_name" :type="tmpLabel === lb.label_id ? 'primary' : 'info'" :plain="tmpLabel !== lb.label_id" size="medium" class="mr-[16rpx] mb-[16rpx]" @click="tmpLabel = lb.label_id"></u-tag>
                </view>
                <view class="text-[28rpx] font-500 mb-[20rpx] mt-[20rpx]">注册时间</view>
                <view class="flex items-center">
                    <view class="flex-1 h-[72rpx] flex items-center px-[24rpx] rounded-[12rpx] bg-[#f3f4f6] text-[26rpx]" :class="tmpStart ? 'text-[#333]' : 'text-[#bbb]'" @click="openTimePicker('start')">{{ tmpStart || '开始日期' }}</view>
                    <text class="mx-[16rpx] text-[#bbb]">至</text>
                    <view class="flex-1 h-[72rpx] flex items-center px-[24rpx] rounded-[12rpx] bg-[#f3f4f6] text-[26rpx]" :class="tmpEnd ? 'text-[#333]' : 'text-[#bbb]'" @click="openTimePicker('end')">{{ tmpEnd || '结束日期' }}</view>
                </view>
                <view class="flex mt-[30rpx]">
                    <view class="flex-1 mr-[20rpx]"><u-button text="重置" shape="circle" @click="resetFilter"></u-button></view>
                    <view class="flex-1"><u-button type="primary" text="确定" shape="circle" @click="applyFilter"></u-button></view>
                </view>
            </view>
        </u-popup>

        <!-- 添加会员 -->
        <u-popup :show="addShow" mode="center" :round="20" @close="addShow = false" zIndex="100">
            <view class="w-[620rpx] px-[40rpx] py-[40rpx]">
                <view class="text-[32rpx] font-500 text-center mb-[20rpx]">添加会员</view>
                <u-form :model="addForm" labelWidth="120">
                    <u-form-item label="昵称"><u-input v-model="addForm.nickname" placeholder="请输入昵称" border="none" maxlength="30"></u-input></u-form-item>
                    <u-form-item label="手机号"><u-input v-model="addForm.mobile" type="number" placeholder="请输入手机号" border="none" maxlength="11"></u-input></u-form-item>
                    <u-form-item label="密码"><u-input v-model="addForm.password" type="password" placeholder="请输入密码" border="none" maxlength="30"></u-input></u-form-item>
                </u-form>
                <view class="flex mt-[30rpx]">
                    <view class="flex-1 mr-[20rpx]"><u-button text="取消" shape="circle" @click="addShow = false"></u-button></view>
                    <view class="flex-1"><u-button type="primary" text="确定" shape="circle" :loading="addLoading" @click="submitAdd"></u-button></view>
                </view>
            </view>
        </u-popup>

        <!-- 快捷操作 -->
        <u-popup :show="actionShow" mode="bottom" :round="20" @close="actionShow = false" zIndex="100">
            <view class="py-[10rpx]">
                <u-cell title="改等级" :center="true" titleStyle="text-align:center;width:100%;" :border="true" @click="openLevelPicker"></u-cell>
                <u-cell title="打标签" :center="true" titleStyle="text-align:center;width:100%;" :border="true" @click="openLabelPicker"></u-cell>
                <u-cell :title="Number(curMember.status) === 1 ? '锁定会员' : '解锁会员'" :center="true" :titleStyle="`text-align:center;width:100%;color:${Number(curMember.status) === 1 ? '#E54D42' : '#1AAD55'};`" :border="false" @click="toggleStatus"></u-cell>
                <view class="h-[16rpx] bg-[#f4f5f7]"></view>
                <u-cell title="取消" :center="true" titleStyle="text-align:center;width:100%;color:#999;" :border="false" @click="actionShow = false"></u-cell>
            </view>
        </u-popup>

        <!-- 改等级 -->
        <u-popup :show="levelPickerShow" mode="bottom" :round="20" @close="levelPickerShow = false" zIndex="101">
            <view class="py-[16rpx]">
                <view class="text-[30rpx] font-500 text-center py-[20rpx]">选择等级</view>
                <u-cell title="普通会员" :center="true" titleStyle="text-align:center;width:100%;color:#999;" @click="chooseLevel({ level_id: 0 })"></u-cell>
                <u-cell v-for="lv in levelOptions" :key="lv.level_id" :title="lv.level_name" :center="true" titleStyle="text-align:center;width:100%;" @click="chooseLevel(lv)"></u-cell>
                <view class="h-[16rpx] bg-[#f4f5f7]"></view>
                <u-cell title="取消" :center="true" titleStyle="text-align:center;width:100%;color:#999;" :border="false" @click="levelPickerShow = false"></u-cell>
            </view>
        </u-popup>

        <!-- 打标签（多选） -->
        <u-popup :show="labelPickerShow" mode="bottom" :round="20" @close="labelPickerShow = false" zIndex="101">
            <view class="py-[20rpx] px-[30rpx]">
                <view class="flex items-center justify-between py-[10rpx] mb-[16rpx]">
                    <text class="text-[30rpx] font-500">选择标签（可多选）</text>
                    <text class="text-[26rpx] text-[var(--primary-color)]" @click="openLabelManage">管理标签</text>
                </view>
                <view class="flex flex-wrap" v-if="labelOptions.length">
                    <u-tag v-for="lb in labelOptions" :key="lb.label_id" :text="lb.label_name" :type="tmpLabels.includes(lb.label_id) ? 'primary' : 'info'" :plain="!tmpLabels.includes(lb.label_id)" size="medium" class="mr-[16rpx] mb-[16rpx]" @click="toggleTmpLabel(lb.label_id)"></u-tag>
                </view>
                <view v-else class="text-[26rpx] text-[#999] text-center py-[30rpx]">还没有标签，点右上「管理标签」新建</view>
                <view class="flex mt-[20rpx]">
                    <view class="flex-1 mr-[20rpx]"><u-button text="取消" shape="circle" @click="labelPickerShow = false"></u-button></view>
                    <view class="flex-1"><u-button type="primary" text="保存" shape="circle" :loading="labelSaving" @click="saveLabels"></u-button></view>
                </view>
            </view>
        </u-popup>

        <!-- 标签管理（CRUD） -->
        <u-popup :show="labelManageShow" mode="bottom" :round="20" @close="labelManageShow = false" zIndex="102">
            <view class="py-[20rpx] px-[30rpx]" style="max-height:70vh;">
                <view class="text-[30rpx] font-500 text-center py-[10rpx] mb-[10rpx]">标签管理</view>
                <!-- 新建 -->
                <view class="flex items-center mb-[24rpx]">
                    <view class="flex-1 mr-[16rpx]">
                        <u-input v-model="newLabelName" placeholder="输入新标签名" border="surround" :clearable="true" maxlength="30" :customStyle="{ height: '46rpx' }"></u-input>
                    </view>
                    <u-button type="primary" text="新建" :loading="labelCrudLoading" @click="createLabel" :customStyle="{ width: '150rpx', height: '72rpx', flexShrink: 0 }"></u-button>
                </view>
                <scroll-view scroll-y style="max-height:48vh;">
                    <u-cell v-for="lb in labelOptions" :key="lb.label_id" :title="lb.label_name">
                        <template #value>
                            <view class="flex items-center">
                                <text class="text-[26rpx] text-[var(--primary-color)] mr-[30rpx]" @click="renameLabel(lb)">改名</text>
                                <text class="text-[26rpx] text-[#E54D42]" @click="removeLabel(lb)">删除</text>
                            </view>
                        </template>
                    </u-cell>
                    <view v-if="!labelOptions.length" class="text-[26rpx] text-[#999] text-center py-[40rpx]">暂无标签</view>
                </scroll-view>
                <view class="mt-[20rpx]"><u-button text="关闭" shape="circle" @click="labelManageShow = false"></u-button></view>
            </view>
        </u-popup>

        <!-- 标签改名 -->
        <u-popup :show="renameShow" mode="center" :round="20" @close="renameShow = false" zIndex="103">
            <view class="w-[600rpx] px-[40rpx] py-[40rpx]">
                <view class="text-[32rpx] font-500 text-center mb-[24rpx]">重命名标签</view>
                <u-input v-model="renameName" placeholder="请输入标签名" :clearable="true" shape="circle" maxlength="30"></u-input>
                <view class="flex mt-[40rpx]">
                    <view class="flex-1 mr-[20rpx]"><u-button text="取消" shape="circle" @click="renameShow = false"></u-button></view>
                    <view class="flex-1"><u-button type="primary" text="保存" shape="circle" :loading="labelCrudLoading" @click="confirmRename"></u-button></view>
                </view>
            </view>
        </u-popup>

        <!-- 删除标签确认 -->
        <u-modal :show="delShow" title="提示" :content="`确定删除标签「${delLabel.label_name || ''}」？`" :showCancelButton="true" @confirm="confirmDelete" @cancel="delShow = false"></u-modal>

        <!-- 注册时间选择 -->
        <u-datetime-picker :show="timePickerShow" v-model="timePickerValue" mode="date" :closeOnClickOverlay="true" @confirm="onTimeConfirm" @cancel="timePickerShow = false" @close="timePickerShow = false"></u-datetime-picker>
    </view>
</template>

<script setup lang="ts">
import { reactive, ref, computed } from 'vue'
import { redirect, img } from '@/utils/common';
import { getShopMember, getMemberLevelAll, getMemberLabelAll, getMemberNo, addShopMember, editMemberField, setMemberStatus, addMemberLabel, updateMemberLabel, deleteMemberLabel } from '@/app/api/member'
import { onLoad } from '@dcloudio/uni-app'

const pagingRef = ref<any>(null);
const keyword = ref('')
const memberList = ref<any>([]);

// 筛选
const levelOptions = ref<any[]>([]);
const labelOptions = ref<any[]>([]);
const filterShow = ref(false);
const tmpLevel = ref<any>('');
const tmpLabel = ref<any>('');
const memberLevel = ref<any>('');
const memberLabel = ref<any>('');
// 注册时间
const tmpStart = ref('');
const tmpEnd = ref('');
const memberCreateTime = ref<any>('');
const timePickerShow = ref(false);
const timeField = ref<'start' | 'end'>('start');
const timePickerValue = ref<number>(Date.now());
const filterCount = computed(() => (memberLevel.value !== '' ? 1 : 0) + (memberLabel.value !== '' ? 1 : 0) + (memberCreateTime.value && memberCreateTime.value.length ? 1 : 0));

const pad = (n: number) => (n < 10 ? '0' + n : '' + n);
const fmtDate = (ts: number) => {
    const d = new Date(ts);
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
};
const openTimePicker = (field: 'start' | 'end') => {
    timeField.value = field;
    const cur = field === 'start' ? tmpStart.value : tmpEnd.value;
    timePickerValue.value = cur ? new Date(cur + ' 00:00:00').getTime() : Date.now();
    timePickerShow.value = true;
};
const onTimeConfirm = (e: any) => {
    const ts = typeof e === 'object' ? (e.value ?? e) : e;
    const day = fmtDate(Number(ts));
    if (timeField.value === 'start') tmpStart.value = day; else tmpEnd.value = day;
    timePickerShow.value = false;
};

const reload = () => { pagingRef.value && pagingRef.value.reload(); };

const queryList = (pageNo: number, pageSize: number) => {
    getShopMember({
        page: pageNo,
        limit: pageSize,
        keyword: keyword.value,
        member_level: memberLevel.value,
        member_label: memberLabel.value,
        create_time: memberCreateTime.value
    }).then((res: any) => {
        pagingRef.value.complete(res.data.data || []);
    }).catch(() => {
        pagingRef.value.complete(false);
    });
};

const loadOptions = () => {
    getMemberLevelAll().then((res: any) => { levelOptions.value = res.data || []; }).catch(() => {});
    loadLabels();
};
const loadLabels = () => {
    getMemberLabelAll().then((res: any) => { labelOptions.value = res.data || []; }).catch(() => {});
};

const openFilter = () => { tmpLevel.value = memberLevel.value; tmpLabel.value = memberLabel.value; filterShow.value = true; };
const applyFilter = () => {
    memberLevel.value = tmpLevel.value;
    memberLabel.value = tmpLabel.value;
    memberCreateTime.value = (tmpStart.value && tmpEnd.value) ? [tmpStart.value + ' 00:00:00', tmpEnd.value + ' 23:59:59'] : '';
    filterShow.value = false;
    reload();
};
const resetFilter = () => { tmpLevel.value = ''; tmpLabel.value = ''; tmpStart.value = ''; tmpEnd.value = ''; };

const toLink = (data: any) => {
    redirect({ url: '/app/pages/member/detail', param: { member_id: data.member_id } });
}

/* ---- 添加会员 ---- */
const addShow = ref(false);
const addLoading = ref(false);
const addForm = reactive({ nickname: '', mobile: '', password: '' });
const openAdd = () => { addForm.nickname = ''; addForm.mobile = ''; addForm.password = ''; addShow.value = true; };
const submitAdd = () => {
    if (!addForm.mobile) { uni.showToast({ title: '请填写手机号', icon: 'none' }); return; }
    if (!/^1\d{10}$/.test(addForm.mobile)) { uni.showToast({ title: '手机号格式不正确', icon: 'none' }); return; }
    if (!addForm.nickname) { uni.showToast({ title: '请填写昵称', icon: 'none' }); return; }
    if (!addForm.password) { uni.showToast({ title: '请填写密码', icon: 'none' }); return; }
    addLoading.value = true;
    getMemberNo().then((res: any) => {
        return addShopMember({ member_no: res.data, nickname: addForm.nickname, mobile: addForm.mobile, password: addForm.password, password_copy: addForm.password });
    }).then(() => {
        addShow.value = false;
        reload();
    }).catch(() => {}).finally(() => { addLoading.value = false; });
};

/* ---- 快捷操作 ---- */
const actionShow = ref(false);
const curMember = ref<any>({});
const openActions = (item: any) => { curMember.value = item; actionShow.value = true; };

const toggleStatus = () => {
    const next = Number(curMember.value.status) === 1 ? 0 : 1;
    setMemberStatus(next, [curMember.value.member_id]).then(() => {
        actionShow.value = false;
        reload();
    }).catch(() => {});
};

const levelPickerShow = ref(false);
const openLevelPicker = () => { actionShow.value = false; levelPickerShow.value = true; };
const chooseLevel = (lv: any) => {
    editMemberField(curMember.value.member_id, 'member_level', lv.level_id).then(() => {
        levelPickerShow.value = false;
        reload();
    }).catch(() => {});
};

/* ---- 打标签 ---- */
const labelPickerShow = ref(false);
const labelSaving = ref(false);
const tmpLabels = ref<number[]>([]);
const openLabelPicker = () => {
    actionShow.value = false;
    tmpLabels.value = (curMember.value.member_label_array || []).map((l: any) => l.label_id);
    labelPickerShow.value = true;
};
const toggleTmpLabel = (id: number) => {
    const i = tmpLabels.value.indexOf(id);
    if (i >= 0) tmpLabels.value.splice(i, 1); else tmpLabels.value.push(id);
};
const saveLabels = () => {
    labelSaving.value = true;
    editMemberField(curMember.value.member_id, 'member_label', tmpLabels.value).then(() => {
        labelPickerShow.value = false;
        reload();
    }).catch(() => {}).finally(() => { labelSaving.value = false; });
};

/* ---- 标签 CRUD ---- */
const labelManageShow = ref(false);
const newLabelName = ref('');
const labelCrudLoading = ref(false);
const openLabelManage = () => { newLabelName.value = ''; labelManageShow.value = true; };
const createLabel = () => {
    const name = newLabelName.value.trim();
    if (!name) { uni.showToast({ title: '请输入标签名', icon: 'none' }); return; }
    labelCrudLoading.value = true;
    addMemberLabel({ label_name: name, memo: '', sort: 0 }).then(() => {
        newLabelName.value = '';
        uni.showToast({ title: '已新建', icon: 'none' });
        loadLabels();
    }).catch(() => {}).finally(() => { labelCrudLoading.value = false; });
};

// 改名（app 内弹窗）
const renameShow = ref(false);
const renameName = ref('');
const renameTarget = ref<any>({});
const renameLabel = (lb: any) => { renameTarget.value = lb; renameName.value = lb.label_name; renameShow.value = true; };
const confirmRename = () => {
    const name = renameName.value.trim();
    if (!name) { uni.showToast({ title: '请输入标签名', icon: 'none' }); return; }
    labelCrudLoading.value = true;
    updateMemberLabel(renameTarget.value.label_id, { label_id: renameTarget.value.label_id, label_name: name, memo: renameTarget.value.memo || '', sort: renameTarget.value.sort || 0 }).then(() => {
        renameShow.value = false;
        loadLabels();
    }).catch(() => {}).finally(() => { labelCrudLoading.value = false; });
};

// 删除（app 内确认）
const delShow = ref(false);
const delLabel = ref<any>({});
const removeLabel = (lb: any) => { delLabel.value = lb; delShow.value = true; };
const confirmDelete = () => {
    deleteMemberLabel(delLabel.value.label_id).then(() => {
        delShow.value = false;
        loadLabels();
    }).catch(() => { delShow.value = false; });
};

onLoad(() => { loadOptions(); });
</script>

<style scoped>
.add-fab {
    position: fixed;
    right: 40rpx;
    bottom: calc(60rpx + env(safe-area-inset-bottom));
    width: 96rpx;
    height: 96rpx;
    border-radius: 50%;
    background: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, 0.18);
    z-index: 90;
}
</style>
