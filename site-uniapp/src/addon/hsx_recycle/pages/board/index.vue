<template>
    <view class="min-h-[100vh] bg-[var(--page-bg-color)] pb-[40rpx]" :style="themeColor()">
        <!-- 今日数字 -->
        <view class="card">
            <view class="card-head">
                <text class="card-title">今日经营</text>
                <view class="rebuild-btn" @click="doRebuild">
                    <u-icon name="reload" color="var(--primary-color)" size="14"></u-icon>
                    <text class="ml-[6rpx]">回填</text>
                </view>
            </view>
            <view class="num-grid">
                <view class="num-item">
                    <text class="num">{{ board.today.paid_count || 0 }}</text>
                    <text class="num-label">今日打款(台)</text>
                </view>
                <view class="num-item">
                    <text class="num num--money">¥{{ money(board.today.paid_amount) }}</text>
                    <text class="num-label">今日打款额</text>
                </view>
                <view class="num-item">
                    <text class="num">{{ board.today.recycled || 0 }}</text>
                    <text class="num-label">今日回收(台)</text>
                </view>
                <view class="num-item">
                    <text class="num">{{ board.today.enter_check || 0 }}</text>
                    <text class="num-label">新进质检(台)</text>
                </view>
            </view>
        </view>

        <!-- 各环节在途 -->
        <view class="card">
            <view class="card-title mb-[24rpx]">各环节在途</view>
            <view class="stage-grid">
                <view class="stage-item" v-for="s in board.stages" :key="s.stage_key" @click="toTask(s.stage_key)">
                    <text class="stage-count" :style="{ color: stageColor(s.stage_key) }">{{ s.count }}</text>
                    <text class="stage-name">{{ s.name }}</text>
                </view>
            </view>
        </view>

        <!-- 趋势 -->
        <view class="card">
            <view class="card-title mb-[20rpx]">近 7 天趋势</view>
            <view class="chart-box">
                <qiun-data-charts v-if="hasTrend" type="line" :chartData="trendChart" :opts="trendOpts" :canvas2d="true" canvasId="recycleBoardTrend" />
                <view v-else class="chart-empty">暂无趋势数据</view>
            </view>
            <view class="legend">
                <text class="lg lg--a">— 打款台数</text>
                <text class="lg lg--b">— 新进质检</text>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { onShow } from '@dcloudio/uni-app';
import { redirect } from '@/utils/common';
import { getStatBoard, rebuildStat } from '@/addon/hsx_recycle/api/task';

const board = ref<any>({ stages: [], today: {}, trend: [] });

const stageColors: Record<string, string> = {
    sign: '#5b6b7a', check: '#2563eb', price: '#0f9d6b', confirm: '#d48806', pay: '#7c3aed', abnormal: '#cf1322'
};
const stageColor = (k: string) => stageColors[k] || '#333';
const money = (v: any) => Number(v || 0).toFixed(2);

const hasTrend = computed(() => Array.isArray(board.value.trend) && board.value.trend.some((t: any) => (t.pay_count || t.check_count)));
const trendChart = computed(() => ({
    categories: (board.value.trend || []).map((t: any) => t.label),
    series: [
        { name: '打款台数', data: (board.value.trend || []).map((t: any) => t.pay_count || 0) },
        { name: '新进质检', data: (board.value.trend || []).map((t: any) => t.check_count || 0) }
    ]
}));
const trendOpts = {
    color: ['#7c3aed', '#2563eb'],
    padding: [12, 12, 0, 12],
    legend: { show: false },
    xAxis: { disableGrid: true, fontColor: '#9098A3' },
    yAxis: { gridType: 'dash', dashLength: 2, data: [{ min: 0 }] },
    extra: { line: { type: 'curve', width: 2 } }
};

const load = () => {
    getStatBoard({ days: 7 }).then((res: any) => {
        board.value = res.data || { stages: [], today: {}, trend: [] };
    }).catch(() => {});
};

const doRebuild = () => {
    rebuildStat().then(() => load()).catch(() => {});
};
const toTask = (stage: string) => {
    redirect({ url: `/addon/hsx_recycle/pages/task/index?stage=${ stage }` });
};

onShow(() => load());
</script>

<style lang="scss" scoped>
.card { background: #fff; margin: 24rpx 24rpx 0; padding: 28rpx; border-radius: 24rpx; }
.card-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24rpx; }
.card-title { font-size: 30rpx; font-weight: 600; color: #333; }
.rebuild-btn { display: flex; align-items: center; font-size: 24rpx; color: var(--primary-color); padding: 8rpx 22rpx; background: var(--primary-color-light, #E6FFF5); border-radius: 26rpx; }
.num-grid { display: flex; flex-wrap: wrap; }
.num-item { width: 50%; display: flex; flex-direction: column; align-items: center; padding: 18rpx 0; }
.num { font-size: 46rpx; font-weight: 700; color: #333; }
.num--money { color: var(--primary-color); font-size: 40rpx; }
.num-label { font-size: 24rpx; color: #9098A3; margin-top: 8rpx; }
.stage-grid { display: flex; flex-wrap: wrap; }
.stage-item { width: 33.33%; display: flex; flex-direction: column; align-items: center; padding: 20rpx 0; }
.stage-count { font-size: 44rpx; font-weight: 700; }
.stage-name { font-size: 24rpx; color: #666; margin-top: 8rpx; }
.chart-box { height: 420rpx; }
.chart-empty { height: 420rpx; display: flex; align-items: center; justify-content: center; color: #9098A3; font-size: 26rpx; }
.legend { display: flex; justify-content: center; gap: 40rpx; margin-top: 10rpx; }
.lg { font-size: 22rpx; }
.lg--a { color: #7c3aed; }
.lg--b { color: #2563eb; }
</style>
