<template>
    <view class="task-page" :style="themeColor()">
        <!-- 搜索 + 环节 tabs -->
        <view class="top-bar">
            <u-search
                v-model="keyword"
                :placeholder="searchPlaceholder"
                :show-action="false"
                shape="round"
                bg-color="#ffffff"
                @search="reload"
                @clear="reload"
            ></u-search>
            <u-tabs
                :list="tabList"
                :current="tabIndex"
                lineColor="var(--primary-color)"
                :activeStyle="{ color: '#1f2937', fontWeight: 600 }"
                :inactiveStyle="{ color: '#8a9099' }"
                itemStyle="height: 72rpx; padding: 0 28rpx;"
                @click="onTab"
            ></u-tabs>
        </view>

        <mescroll-body ref="mescrollRef" @init="mescrollInit" :down="{ use: true }" @down="downCallback" @up="getListFn" :up="{ noMoreSize: 4, empty: { tip: '暂无待处理任务' } }" top="208">
            <view class="list-wrap">
                <view class="task-card" v-for="(item, idx) in list" :key="item.device_id + '_' + item.stage_key">
                    <!-- 头部：环节标签 + 标题 + 序号 -->
                    <view class="card-head">
                        <u-tag :text="stageName[item.stage_key] || item.stage_key" size="mini" :color="stageColor(item.stage_key).fg" :bgColor="stageColor(item.stage_key).bg" border-color="transparent" shape="circle"></u-tag>
                        <text class="card-title">{{ item.is_order ? ('订单 ' + (item.order_no || item.order_id)) : (item.model || '未识别型号') }}</text>
                        <text class="card-idx">#{{ idx + 1 }}</text>
                    </view>

                    <!-- 订单级（待签收） -->
                    <view v-if="item.is_order" class="card-body">
                        <view class="info-row">
                            <u-icon name="account" color="#b4b8bf" size="14"></u-icon>
                            <text class="info-text">{{ item.customer_name || '客户' }}</text>
                            <text v-if="item.device_count" class="info-badge">{{ item.device_count }} 台</text>
                        </view>
                        <view class="info-row" v-if="item.express_no">
                            <u-icon name="car" color="#b4b8bf" size="14"></u-icon>
                            <text class="info-text truncate">{{ item.express_company }} {{ item.express_no }}</text>
                        </view>
                    </view>

                    <!-- 设备级 -->
                    <view v-else class="card-body">
                        <view class="info-row" v-if="price(item)">
                            <text class="price">¥{{ price(item) }}</text>
                        </view>
                        <view class="info-row" v-if="item.imei || item.sn">
                            <u-icon name="scan" color="#b4b8bf" size="14"></u-icon>
                            <text class="info-text truncate">{{ item.imei || item.sn }}</text>
                        </view>
                    </view>

                    <!-- 底部：认领态 + 操作 -->
                    <view class="card-foot">
                        <text class="claim-state" :class="claimClass(item)">{{ claimText(item) }}</text>
                        <view class="btns">
                            <u-button v-if="item.assignee_uid == 0" text="认领" size="mini" :plain="true" type="primary" shape="circle" @click="doClaim(item)"></u-button>
                            <u-button v-else-if="item.is_mine" text="释放" size="mini" :plain="true" type="warning" shape="circle" @click="doRelease(item)"></u-button>
                            <u-button text="处理" size="mini" type="primary" shape="circle" @click="toProcess(item)"></u-button>
                        </view>
                    </view>
                </view>
            </view>
        </mescroll-body>
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { onShow, onPageScroll, onReachBottom } from '@dcloudio/uni-app';
import { redirect } from '@/utils/common';
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue';
import useMescroll from '@/components/mescroll/hooks/useMescroll.js';
import { getMyStages, getTaskList, claimTask, releaseTask } from '@/addon/hsx_recycle/api/task';

const { mescrollInit, downCallback, getMescroll } = useMescroll(onPageScroll, onReachBottom);

const stageName: Record<string, string> = {
    sign: '待签收', check: '质检', price: '定价', confirm: '报价确认', pay: '打款', abnormal: '异常'
};
const stageColors: Record<string, any> = {
    sign: { bg: '#EEF1F5', fg: '#5b6b7a' },
    check: { bg: '#E8F3FF', fg: '#3c9cff' },
    price: { bg: '#E6FFF5', fg: '#10b981' },
    confirm: { bg: '#FFF4E8', fg: '#f97316' },
    pay: { bg: '#F0EBFF', fg: '#7c3aed' },
    abnormal: { bg: '#FFF1F0', fg: '#dc2626' }
};
const stageColor = (k: string) => stageColors[k] || { bg: '#F0F1F3', fg: '#666' };

const list = ref<any[]>([]);
const keyword = ref('');
const activeStage = ref('');
const myStages = ref<string[]>([]);
const firstLoaded = ref(false);

const tabList = computed(() => {
    const arr = [{ name: '全部', key: '' }];
    myStages.value.forEach(k => arr.push({ name: stageName[k] || k, key: k }));
    return arr;
});
const tabIndex = computed(() => Math.max(0, tabList.value.findIndex(t => t.key === activeStage.value)));
const searchPlaceholder = computed(() => activeStage.value === 'sign' ? '搜索 订单号 / 客户 / 快递' : '搜索 IMEI / SN / 型号');

const price = (item: any) => {
    const p = Number(item.final_price) > 0 ? item.final_price : (Number(item.initial_price) > 0 ? item.initial_price : 0);
    return p > 0 ? p : '';
};
const claimText = (item: any) => {
    if (item.assignee_uid == 0) return '待认领';
    return item.is_mine ? '我处理中' : `${ item.assignee_name } 处理中`;
};
const claimClass = (item: any) => item.assignee_uid == 0 ? 'cs-free' : (item.is_mine ? 'cs-mine' : 'cs-other');

const loadStages = async () => {
    try {
        const res: any = await getMyStages();
        myStages.value = res.data || [];
    } catch (e) {}
};

const getListFn = (mescroll: any) => {
    getTaskList({ stage: activeStage.value, keyword: keyword.value.trim(), page: mescroll.num, limit: mescroll.size }).then((res: any) => {
        const data = res.data?.list || [];
        if (mescroll.num == 1) list.value = [];
        list.value = list.value.concat(data);
        mescroll.endSuccess(data.length);
        firstLoaded.value = true;
    }).catch(() => mescroll.endErr());
};

const reload = () => getMescroll() && getMescroll().resetUpScroll();
const onTab = (tab: any) => {
    const key = tab.key ?? '';
    if (activeStage.value === key) return;
    activeStage.value = key;
    reload();
};

const doClaim = (item: any) => {
    claimTask({ device_id: item.device_id, stage_key: item.stage_key }).then(() => reload());
};
const doRelease = (item: any) => {
    releaseTask({ device_id: item.device_id, stage_key: item.stage_key }).then(() => reload());
};
const toProcess = (item: any) => {
    const imei = item.is_order ? '' : (item.imei || item.sn || '');
    redirect({ url: `/addon/hsx_recycle/pages/order/detail?id=${ item.order_id }&imei=${ encodeURIComponent(imei) }` });
};

onShow(async () => {
    if (!firstLoaded.value) {
        await loadStages();
    } else if (getMescroll()) {
        getMescroll().resetUpScroll();
    }
});
</script>

<style lang="scss" scoped>
.task-page { min-height: 100vh; background: #f3f6fb; }
.top-bar { position: fixed; top: 0; left: 0; right: 0; z-index: 10; background: #f3f6fb; padding: 20rpx 24rpx 0; }
.top-bar :deep(.u-tabs) { margin-top: 8rpx; }
.list-wrap { padding: 24rpx; }

.task-card { background: #fff; border-radius: 24rpx; padding: 26rpx; margin-bottom: 20rpx; box-shadow: 0 4rpx 20rpx rgba(31, 45, 61, 0.04); }
.card-head { display: flex; align-items: center; }
.card-title { flex: 1; margin-left: 14rpx; font-size: 30rpx; font-weight: 600; color: #1f2937; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.card-idx { font-size: 22rpx; color: #c4c8cf; margin-left: 12rpx; }

.card-body { margin-top: 16rpx; }
.info-row { display: flex; align-items: center; margin-top: 8rpx; }
.info-text { font-size: 24rpx; color: #64748b; margin-left: 8rpx; }
.info-badge { font-size: 22rpx; color: #3c9cff; background: #E8F3FF; border-radius: 8rpx; padding: 2rpx 12rpx; margin-left: 14rpx; }
.price { font-size: 32rpx; font-weight: 700; color: var(--primary-color); }
.truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 520rpx; }

.card-foot { display: flex; align-items: center; justify-content: space-between; margin-top: 22rpx; padding-top: 22rpx; border-top: 2rpx solid #f1f3f6; }
.claim-state { font-size: 24rpx; }
.cs-free { color: #94a3b8; }
.cs-mine { color: var(--primary-color); font-weight: 600; }
.cs-other { color: #f59e0b; }
.btns { display: flex; gap: 16rpx; }
.btns :deep(.u-button) { padding: 0 28rpx; }
</style>
