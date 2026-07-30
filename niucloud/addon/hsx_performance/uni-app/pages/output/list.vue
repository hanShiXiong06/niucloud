<template>
    <view class="page">
        <view class="head">
            <view><text class="title">员工产出</text><text class="subtitle">{{ rangeText }}</text></view>
            <view class="icon-btn" @click="load"><u-icon name="reload" color="#2563eb" size="20" /></view>
        </view>
        <scroll-view scroll-x class="periods" :show-scrollbar="false"><view class="period-inner"><view v-for="item in periodOptions" :key="item.value" class="period" :class="{ active: query.period === item.value }" @click="changePeriod(item.value)">{{ item.label }}</view></view></scroll-view>
        <view class="summary">
            <view><strong>{{ data.summary.employee_count || 0 }}</strong><text>有产出员工</text></view>
            <view><strong>{{ data.summary.completed_count || 0 }}</strong><text>完成事项</text></view>
            <view><strong>{{ data.summary.metric_count || 0 }}</strong><text>产出指标</text></view>
        </view>
        <scroll-view v-if="plugins.length" scroll-x class="plugins" :show-scrollbar="false"><view class="plugin-inner"><view class="plugin" :class="{ active: !query.source_plugin }" @click="changePlugin('')">全部业务</view><view v-for="item in plugins" :key="item.value" class="plugin" :class="{ active: query.source_plugin === item.value }" @click="changePlugin(item.value)">{{ item.label }}</view></view></scroll-view>

        <view v-if="loading" class="state"><u-loading-icon size="28" /><text>正在核对产出数据</text></view>
        <view v-else-if="!data.employees.length" class="state"><u-empty mode="data" text="当前周期暂无员工产出" /></view>
        <view v-else>
            <view class="section-title"><text>员工排行</text><text>按有效完成事项</text></view>
            <view class="employee-list">
                <view v-for="(row, index) in data.employees" :key="row.employee_uid" class="employee" @click="openEmployee(row)">
                    <view class="rank">{{ index + 1 }}</view>
                    <view class="employee-main"><text class="employee-name">{{ row.employee_name || `员工${row.employee_uid}` }}</text><text class="employee-meta">{{ row.metric_count }} 类事项 · 最近 {{ shortTime(row.last_occurred_at) }}</text></view>
                    <view class="employee-count"><strong>{{ row.completed_count }}</strong><text>项</text></view>
                    <u-icon name="arrow-right" color="#94a3b8" size="16" />
                </view>
            </view>
            <view class="section-title"><text>事项分布</text><text>动作和结果分开</text></view>
            <view class="metric-list">
                <view v-for="row in data.metrics" :key="`${row.source_plugin}:${row.metric_key}`" class="metric">
                    <view><text class="metric-name">{{ row.metric_name }}</text><text class="metric-meta">{{ pluginName(row.source_plugin) }} · {{ scopeName(row.fact_scope) }}</text></view>
                    <view class="metric-value"><strong>{{ row.completed_count }}</strong><text>{{ unitName(row.unit) }}</text></view>
                </view>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getMobilePerformanceOutputOverview } from '@/addon/hsx_performance/api'

const periodOptions = [{ label: '今日', value: 'today' }, { label: '本周', value: 'week' }, { label: '近7天', value: 'last7' }, { label: '本月', value: 'month' }]
const query = reactive({ period: 'month', source_plugin: '' })
const data = reactive<any>({ summary: {}, employees: [], metrics: [], range: {}, filters: { plugins: [] } })
const loading = ref(false)
const plugins = computed(() => data.filters?.plugins || [])
const rangeText = computed(() => data.range?.start_date ? `${data.range.start_date} 至 ${data.range.end_date}` : '员工工作事实')
const load = async () => { loading.value = true; try { Object.assign(data, (await getMobilePerformanceOutputOverview({ ...query })).data || {}) } finally { loading.value = false } }
const changePeriod = (value: string) => { query.period = value; load() }
const changePlugin = (value: string) => { query.source_plugin = value; load() }
const openEmployee = (row: any) => uni.navigateTo({ url: `/addon/hsx_performance/pages/output/detail?uid=${row.employee_uid}&period=${query.period}&source_plugin=${query.source_plugin}` })
const shortTime = (value: any) => value ? new Date(Number(value) * 1000).toLocaleDateString('zh-CN').replace(/\//g, '-') : '—'
const pluginName = (value: string) => ({ hsx_recycle: '回收业务', hsx_erp: 'ERP', hsx_member_card: '会员卡' }[value] || value)
const scopeName = (value: string) => ({ action: '工作动作', outcome: '有效结果', quality: '质量事项' }[value] || value)
const unitName = (value: string) => ({ device: '台', order: '单', card: '张', service: '次', settlement: '笔', item: '项' }[value] || value)
onShow(load)
</script>

<style scoped lang="scss">
.page{min-height:100vh;padding:28rpx;box-sizing:border-box;background:#f5f7fa}.head{display:flex;align-items:center;justify-content:space-between}.title{display:block;color:#111827;font-size:38rpx;font-weight:700}.subtitle{display:block;margin-top:6rpx;color:#64748b;font-size:22rpx}.icon-btn{width:68rpx;height:68rpx;display:flex;align-items:center;justify-content:center;background:#fff;border:2rpx solid #e5e7eb;border-radius:12rpx}.periods,.plugins{margin:24rpx 0 18rpx;white-space:nowrap}.period-inner,.plugin-inner{display:inline-flex;gap:12rpx}.period,.plugin{padding:13rpx 22rpx;color:#475569;font-size:24rpx;background:#fff;border:2rpx solid #e5e7eb;border-radius:10rpx}.period.active,.plugin.active{color:#fff;background:#2563eb;border-color:#2563eb}
.summary{display:grid;grid-template-columns:repeat(3,1fr);background:#fff;border:2rpx solid #e5e7eb;border-radius:12rpx}.summary view{padding:22rpx 12rpx;display:flex;flex-direction:column;align-items:center;border-right:2rpx solid #edf0f4}.summary view:last-child{border-right:0}.summary strong{color:#111827;font-size:34rpx}.summary text{margin-top:6rpx;color:#64748b;font-size:20rpx}.section-title{margin:30rpx 2rpx 14rpx;display:flex;justify-content:space-between;align-items:center}.section-title text:first-child{color:#111827;font-size:28rpx;font-weight:650}.section-title text:last-child{color:#94a3b8;font-size:21rpx}
.employee-list,.metric-list{background:#fff;border:2rpx solid #e5e7eb;border-radius:12rpx}.employee,.metric{min-height:108rpx;padding:20rpx;box-sizing:border-box;display:flex;align-items:center;border-bottom:2rpx solid #edf0f4}.employee:last-child,.metric:last-child{border-bottom:0}.rank{width:48rpx;color:#64748b;font-size:24rpx}.employee-main{min-width:0;flex:1}.employee-name,.metric-name{display:block;overflow:hidden;color:#111827;font-size:27rpx;font-weight:600;text-overflow:ellipsis;white-space:nowrap}.employee-meta,.metric-meta{display:block;margin-top:6rpx;color:#94a3b8;font-size:20rpx}.employee-count,.metric-value{margin-right:18rpx;display:flex;align-items:baseline;gap:4rpx}.employee-count strong,.metric-value strong{color:#111827;font-size:31rpx}.employee-count text,.metric-value text{color:#64748b;font-size:20rpx}.metric{justify-content:space-between}.metric>view:first-child{min-width:0;flex:1}.state{height:520rpx;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:18rpx;color:#94a3b8;font-size:24rpx}
</style>
