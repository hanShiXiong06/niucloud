<template>
    <view class="marketing-page">
        <view class="overview-card">
            <view class="overview-card__orb overview-card__orb--large" />
            <view class="overview-card__orb overview-card__orb--small" />
            <view class="overview-head">
                <view class="overview-title-wrap">
                    <view class="overview-kicker">MEMBER REWARDS</view>
                    <view class="overview-title">任务奖励中心</view>
                    <view class="overview-desc">完成真实业务，奖励及时到账</view>
                </view>
                <view class="notice-entry" @tap="subscribeNotice">
                    <view class="notice-entry__icon"><u-icon name="bell-fill" color="#ffffff" size="18" /></view>
                    <text>提醒</text>
                </view>
            </view>
            <view class="overview-stats">
                <view class="overview-stat">
                    <text class="overview-stat__value">{{ overview.active_task_count || 0 }}</text>
                    <text class="overview-stat__label">可参与任务</text>
                </view>
                <view class="overview-stat__divider" />
                <view class="overview-stat">
                    <text class="overview-stat__value">{{ overview.running_task_count || 0 }}</text>
                    <text class="overview-stat__label">进行中</text>
                </view>
                <view class="overview-stat__divider" />
                <view class="overview-stat">
                    <view class="overview-stat__value-wrap">
                        <text class="overview-stat__value">{{ overview.claimable_reward_count || 0 }}</text>
                        <view v-if="overview.claimable_reward_count" class="overview-stat__dot" />
                    </view>
                    <text class="overview-stat__label">待领取奖励</text>
                </view>
            </view>
        </view>

        <view class="tab-card">
            <view class="tab-item" :class="{ 'tab-item--active': activeTab === 'tasks' }" @tap="switchTab('tasks')">
                <u-icon name="list-dot" :color="activeTab === 'tasks' ? '#2468f2' : '#7d899d'" size="17" />
                <text>任务中心</text>
            </view>
            <view class="tab-item" :class="{ 'tab-item--active': activeTab === 'rewards' }" @tap="switchTab('rewards')">
                <u-icon name="gift" :color="activeTab === 'rewards' ? '#2468f2' : '#7d899d'" size="17" />
                <text>我的奖励</text>
                <text v-if="overview.claimable_reward_count" class="tab-badge">{{ overview.claimable_reward_count }}</text>
            </view>
        </view>

        <view class="section-head">
            <view>
                <view class="section-title">{{ activeTab === 'tasks' ? '本期任务' : '奖励记录' }}</view>
                <view class="section-desc">{{ activeTab === 'tasks' ? '完成目标后即可获得对应权益' : '查看奖励状态与领取进度' }}</view>
            </view>
            <view class="refresh-entry" @tap="refresh"><u-icon name="reload" color="#7d899d" size="17" /></view>
        </view>

        <view v-if="loading && !rows.length" class="state-card">
            <u-loading-icon mode="circle" color="#2468f2" />
            <text>正在加载业务数据...</text>
        </view>

        <template v-else-if="activeTab === 'tasks'">
            <view v-for="item in rows" :key="item.id" class="business-card task-card">
                <view class="business-card__head">
                    <view class="task-icon"><u-icon name="pushpin-fill" color="#2468f2" size="20" /></view>
                    <view class="business-card__heading">
                        <view class="business-card__title">{{ item.title }}</view>
                        <view class="business-card__subtitle">{{ item.subtitle || '完成任务即可获得奖励' }}</view>
                    </view>
                    <view class="cycle-chip">{{ cycleText(item.cycle_type) }}</view>
                </view>

                <view class="progress-panel">
                    <view class="progress-panel__head">
                        <view class="progress-copy">
                            <text class="progress-copy__label">当前进度</text>
                            <view class="progress-copy__value">
                                <text>{{ compact(item.progress_value) }}</text>
                                <text class="progress-copy__target"> / {{ compact(item.target_value) }}{{ item.target_unit }}</text>
                            </view>
                        </view>
                        <text class="progress-percent">{{ progressPercent(item) }}%</text>
                    </view>
                    <view class="progress-track"><view class="progress-track__bar" :style="{ width: progressPercent(item) + '%' }" /></view>
                </view>

                <view class="reward-box">
                    <view class="reward-box__icon"><u-icon name="gift-fill" color="#d97706" size="18" /></view>
                    <view class="reward-box__content">
                        <text class="reward-box__label">达标可得</text>
                        <view class="reward-chips">
                            <text v-for="reward in item.rewards || []" :key="reward.reward_name" class="reward-chip">{{ rewardText(reward) }}</text>
                        </view>
                    </view>
                </view>

                <view class="business-card__foot">
                    <view class="deadline"><u-icon name="clock" size="15" color="#98a2b3" /><text>{{ date(item.end_at) }} 截止</text></view>
                    <view v-if="!item.eligible" class="action-button action-button--warning" @tap="applyLevel(item)">
                        <text>{{ qualificationAction(item) }}</text><u-icon name="arrow-right" color="#b45309" size="14" />
                    </view>
                    <view v-else-if="item.claimed" class="action-status"><u-icon name="checkmark-circle-fill" color="#16a063" size="16" /><text>{{ taskStatus(item) }}</text></view>
                    <view v-else-if="item.participation_mode === 'manual'" class="action-button action-button--primary" @tap="claimTask(item)">
                        <text>领取任务</text><u-icon name="arrow-right" color="#ffffff" size="14" />
                    </view>
                    <view v-else class="action-status"><u-icon name="checkmark-circle-fill" color="#16a063" size="16" /><text>自动参与</text></view>
                </view>
            </view>
        </template>

        <template v-else>
            <view v-for="item in rows" :key="item.id" class="business-card reward-card">
                <view class="reward-icon" :class="'reward-icon--' + item.reward_type">
                    <u-icon :name="rewardIcon(item.reward_type)" color="#ffffff" size="21" />
                </view>
                <view class="reward-content">
                    <view class="reward-content__top">
                        <view class="reward-content__heading">
                            <view class="business-card__title">{{ item.reward_name }}</view>
                            <view class="business-card__subtitle">{{ item.campaign_title }}</view>
                        </view>
                        <view class="status-chip" :class="'status-chip--' + item.status">{{ item.status_text }}</view>
                    </view>
                    <view class="reward-detail">
                        <view class="reward-detail__value"><text class="reward-detail__label">奖励内容</text><text>{{ rewardOrderText(item) }}</text></view>
                        <view v-if="item.claim_expire_at" class="reward-detail__expire"><u-icon name="clock" color="#98a2b3" size="14" /><text>{{ date(item.claim_expire_at) }} 前领取</text></view>
                    </view>
                    <view v-if="item.failure_reason" class="failure-tip"><u-icon name="info-circle" color="#dc2626" size="14" /><text>{{ item.failure_reason }}</text></view>
                    <view v-if="['claimable', 'failed'].includes(item.status)" class="reward-action" @tap="claimReward(item)">
                        <text>{{ item.status === 'failed' ? '重新领取' : '立即领取' }}</text><u-icon name="arrow-right" color="#2468f2" size="14" />
                    </view>
                </view>
            </view>
        </template>

        <view v-if="!loading && !rows.length" class="empty-card">
            <view class="empty-card__icon"><u-icon :name="activeTab === 'tasks' ? 'list-dot' : 'gift'" size="34" color="#aeb8c8" /></view>
            <view class="empty-card__title">{{ activeTab === 'tasks' ? '暂无可参与任务' : '暂无奖励记录' }}</view>
            <view class="empty-card__desc">{{ activeTab === 'tasks' ? '新任务发布后会第一时间展示在这里' : '完成任务后，奖励记录会展示在这里' }}</view>
        </view>
        <view v-if="loading && rows.length" class="load-more"><u-loading-icon mode="circle" size="18" color="#98a2b3" /><text>加载中...</text></view>
        <view v-else-if="finished && rows.length" class="load-more"><text>没有更多内容了</text></view>
        <view class="safe-space" />
    </view>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { onPullDownRefresh, onReachBottom } from '@dcloudio/uni-app'
import { claimMarketingReward, claimMarketingTask, getMarketingOverview, getMarketingRewards, getMarketingTasks } from '../api'
import { useSubscribeMessage } from '@/hooks/useSubscribeMessage'

const NOTICE_KEYS = 'hsx_marketing_reward_available,hsx_marketing_reward_grant_success,hsx_marketing_reward_grant_failed,hsx_marketing_reward_expiring'
const overview = reactive<any>({}), activeTab = ref<'tasks' | 'rewards'>('tasks'), rows = ref<any[]>([]), loading = ref(false), page = ref(1), finished = ref(false)
const loadOverview = async () => Object.assign(overview, (await getMarketingOverview()).data || {})
const load = async (reset = false) => {
    if (loading.value || (!reset && finished.value)) return
    if (reset) { page.value = 1; rows.value = []; finished.value = false }
    loading.value = true
    try {
        const response: any = activeTab.value === 'tasks'
            ? await getMarketingTasks({ page: page.value, limit: 10 })
            : await getMarketingRewards({ page: page.value, limit: 10 })
        const data = response.data || {}, list = data.data || data.list || []
        rows.value.push(...list); finished.value = rows.value.length >= Number(data.total || 0) || !list.length; page.value++
    } finally { loading.value = false; uni.stopPullDownRefresh() }
}
const refresh = async () => { await Promise.all([loadOverview(), load(true)]) }
const switchTab = async (value: 'tasks' | 'rewards') => { if (activeTab.value === value) return; activeTab.value = value; await load(true) }
const subscribeNotice = async () => { await useSubscribeMessage().request(NOTICE_KEYS); uni.showToast({ title: '提醒设置已完成', icon: 'none' }) }
const claimTask = async (item: any) => { await subscribeNotice(); await claimMarketingTask(Number(item.id)); uni.showToast({ title: '任务领取成功', icon: 'success' }); await refresh() }
const claimReward = async (item: any) => { await subscribeNotice(); await claimMarketingReward(Number(item.id)); uni.showToast({ title: '奖励领取成功', icon: 'success' }); await refresh() }
const applyLevel = (item: any) => {
    if (item.qualification_status === 'pending') return uni.showToast({ title: item.qualification_message || '同行身份正在审核', icon: 'none' })
    if (item.can_apply && item.application_url) return uni.navigateTo({ url: item.application_url })
    uni.showToast({ title: item.qualification_message || '当前暂不满足参与条件', icon: 'none' })
}
const qualificationAction = (item: any) => item.qualification_status === 'pending' ? '同行身份审核中' : item.can_apply ? '申请同行后参与' : '暂不可参与'
const compact = (value: any) => Number(value || 0).toString()
const progressPercent = (item: any) => Math.min(100, Math.max(0, Math.round(Number(item.progress_percent || 0))))
const date = (value: any) => { const d = new Date(Number(value) * 1000); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}` }
const cycleText = (value: string) => ({ calendar_month: '每月任务', fixed: '活动任务', rolling_days: '限时任务' } as any)[value] || '任务'
const rewardText = (item: any) => ['point', 'growth'].includes(item.reward_type) ? `${item.reward_name} ${compact(Number(item.reward_value) * Number(item.reward_quantity || 1))}` : `${item.reward_name} ×${item.reward_quantity}`
const rewardOrderText = (item: any) => ['point', 'growth'].includes(item.reward_type) ? compact(Number(item.reward_value) * Number(item.reward_quantity || 1)) : `数量 ×${item.reward_quantity}`
const rewardIcon = (type: string) => type === 'coupon' ? 'coupon-fill' : type === 'growth' ? 'level' : 'integral-fill'
const taskStatus = (item: any) => ({ running: '进行中', completed: '已达标', rewarded: '奖励已发放' } as any)[item.claim?.status] || '已参与'
onMounted(refresh)
onPullDownRefresh(refresh)
onReachBottom(() => load())
</script>

<style scoped lang="scss">
.marketing-page {
    min-height: 100vh;
    padding: 24rpx;
    background: #f4f6fa;
    color: #26364f;
    box-sizing: border-box;
}

.overview-card {
    position: relative;
    overflow: hidden;
    padding: 34rpx 30rpx 26rpx;
    border-radius: 28rpx;
    color: #ffffff;
    background: linear-gradient(135deg, #1f67ef 0%, #4856e8 100%);
    box-shadow: 0 16rpx 36rpx rgba(36, 104, 242, .2);
}
.overview-card__orb { position: absolute; border-radius: 50%; background: rgba(255, 255, 255, .08); pointer-events: none; }
.overview-card__orb--large { width: 270rpx; height: 270rpx; right: -90rpx; top: -110rpx; }
.overview-card__orb--small { width: 150rpx; height: 150rpx; left: 270rpx; bottom: -105rpx; }
.overview-head { position: relative; z-index: 1; display: flex; align-items: flex-start; justify-content: space-between; }
.overview-title-wrap { min-width: 0; }
.overview-kicker { font-size: 19rpx; line-height: 1.2; letter-spacing: 3rpx; opacity: .66; }
.overview-title { margin-top: 10rpx; font-size: 40rpx; line-height: 1.25; font-weight: 700; }
.overview-desc { margin-top: 9rpx; font-size: 24rpx; line-height: 1.45; opacity: .82; }
.notice-entry { position: relative; display: flex; align-items: center; padding: 9rpx 14rpx 9rpx 9rpx; border: 1rpx solid rgba(255,255,255,.22); border-radius: 999rpx; background: rgba(255,255,255,.12); font-size: 22rpx; }
.notice-entry__icon { display: flex; align-items: center; justify-content: center; width: 42rpx; height: 42rpx; margin-right: 7rpx; border-radius: 50%; background: rgba(255,255,255,.14); }
.overview-stats { position: relative; z-index: 1; display: flex; align-items: center; margin-top: 30rpx; padding: 22rpx 12rpx 4rpx; border-top: 1rpx solid rgba(255,255,255,.16); }
.overview-stat { flex: 1; display: flex; align-items: center; flex-direction: column; }
.overview-stat__value-wrap { position: relative; }
.overview-stat__value { font-size: 37rpx; line-height: 1.2; font-weight: 700; }
.overview-stat__label { margin-top: 6rpx; font-size: 21rpx; opacity: .7; }
.overview-stat__divider { width: 1rpx; height: 45rpx; background: rgba(255,255,255,.18); }
.overview-stat__dot { position: absolute; width: 10rpx; height: 10rpx; right: -14rpx; top: 2rpx; border: 3rpx solid #5666ed; border-radius: 50%; background: #ffcb45; }

.tab-card { display: flex; margin-top: 22rpx; padding: 7rpx; border: 1rpx solid #e7ebf2; border-radius: 20rpx; background: #e9edf4; }
.tab-item { position: relative; flex: 1; display: flex; align-items: center; justify-content: center; height: 70rpx; border-radius: 15rpx; color: #7d899d; font-size: 27rpx; font-weight: 600; }
.tab-item text { margin-left: 9rpx; }
.tab-item--active { color: #2468f2; background: #ffffff; box-shadow: 0 4rpx 12rpx rgba(38,54,79,.06); }
.tab-badge { min-width: 27rpx; height: 27rpx; margin-left: 7rpx !important; padding: 0 6rpx; border-radius: 999rpx; color: #ffffff; background: #ff4d4f; font-size: 18rpx; line-height: 27rpx; text-align: center; box-sizing: border-box; }

.section-head { display: flex; align-items: center; justify-content: space-between; padding: 28rpx 4rpx 18rpx; }
.section-title { font-size: 30rpx; line-height: 1.3; font-weight: 700; color: #26364f; }
.section-desc { margin-top: 5rpx; font-size: 21rpx; color: #98a2b3; }
.refresh-entry { display: flex; align-items: center; justify-content: center; width: 62rpx; height: 62rpx; border: 1rpx solid #e7ebf2; border-radius: 50%; background: #ffffff; }

.business-card { margin-bottom: 20rpx; padding: 26rpx; border: 1rpx solid #e7ebf2; border-radius: 24rpx; background: #ffffff; box-shadow: 0 8rpx 22rpx rgba(38,54,79,.04); }
.business-card__head { display: flex; align-items: center; }
.task-icon { flex: none; display: flex; align-items: center; justify-content: center; width: 68rpx; height: 68rpx; border-radius: 19rpx; background: #edf4ff; }
.business-card__heading { flex: 1; min-width: 0; margin-left: 17rpx; }
.business-card__title { overflow: hidden; color: #26364f; font-size: 29rpx; line-height: 1.4; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
.business-card__subtitle { overflow: hidden; margin-top: 5rpx; color: #8b97aa; font-size: 22rpx; line-height: 1.4; text-overflow: ellipsis; white-space: nowrap; }
.cycle-chip { flex: none; margin-left: 14rpx; padding: 8rpx 13rpx; border-radius: 999rpx; color: #2468f2; background: #edf4ff; font-size: 20rpx; }

.progress-panel { margin-top: 24rpx; padding: 20rpx; border-radius: 18rpx; background: #f7f9fc; }
.progress-panel__head { display: flex; align-items: flex-end; justify-content: space-between; }
.progress-copy { display: flex; align-items: baseline; }
.progress-copy__label { margin-right: 14rpx; color: #7d899d; font-size: 21rpx; }
.progress-copy__value { color: #26364f; font-size: 31rpx; line-height: 1.2; font-weight: 700; }
.progress-copy__target { color: #8b97aa; font-size: 22rpx; font-weight: 500; }
.progress-percent { color: #2468f2; font-size: 23rpx; font-weight: 650; }
.progress-track { height: 10rpx; margin-top: 17rpx; overflow: hidden; border-radius: 999rpx; background: #e5eaf2; }
.progress-track__bar { height: 100%; border-radius: 999rpx; background: linear-gradient(90deg, #2468f2, #6c5ce7); transition: width .25s ease; }

.reward-box { display: flex; align-items: flex-start; margin-top: 18rpx; padding: 18rpx 20rpx; border-radius: 18rpx; background: #fff8eb; }
.reward-box__icon { flex: none; display: flex; align-items: center; justify-content: center; width: 48rpx; height: 48rpx; border-radius: 14rpx; background: #ffedc8; }
.reward-box__content { flex: 1; min-width: 0; margin-left: 14rpx; }
.reward-box__label { display: block; color: #9a6b2f; font-size: 20rpx; }
.reward-chips { display: flex; flex-wrap: wrap; margin-top: 7rpx; }
.reward-chip { margin: 0 10rpx 6rpx 0; color: #714b1f; font-size: 23rpx; font-weight: 600; }
.reward-chip:not(:last-child)::after { content: '·'; margin-left: 10rpx; color: #d7ad74; }

.business-card__foot { display: flex; align-items: center; justify-content: space-between; margin-top: 22rpx; }
.deadline { display: flex; align-items: center; color: #98a2b3; font-size: 21rpx; }
.deadline text { margin-left: 7rpx; }
.action-button { display: flex; align-items: center; padding: 13rpx 20rpx; border-radius: 999rpx; font-size: 23rpx; font-weight: 650; }
.action-button text { margin-right: 5rpx; }
.action-button--primary { color: #ffffff; background: #2468f2; box-shadow: 0 7rpx 14rpx rgba(36,104,242,.15); }
.action-button--warning { color: #b45309; background: #fff4dc; }
.action-status { display: flex; align-items: center; color: #16a063; font-size: 23rpx; font-weight: 600; }
.action-status text { margin-left: 6rpx; }

.reward-card { display: flex; align-items: flex-start; }
.reward-icon { flex: none; display: flex; align-items: center; justify-content: center; width: 72rpx; height: 72rpx; border-radius: 21rpx; background: linear-gradient(135deg, #2468f2, #5c59e8); }
.reward-icon--coupon { background: linear-gradient(135deg, #ff9f43, #f97316); }
.reward-icon--growth { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }
.reward-content { flex: 1; min-width: 0; margin-left: 18rpx; }
.reward-content__top { display: flex; align-items: flex-start; justify-content: space-between; }
.reward-content__heading { flex: 1; min-width: 0; }
.status-chip { flex: none; margin-left: 12rpx; padding: 7rpx 12rpx; border-radius: 999rpx; color: #d97706; background: #fff4dc; font-size: 20rpx; }
.status-chip--success { color: #138a52; background: #eaf9f0; }
.status-chip--failed { color: #dc2626; background: #fff0f0; }
.status-chip--expired, .status-chip--cancelled { color: #7d899d; background: #f0f2f5; }
.reward-detail { display: flex; align-items: center; justify-content: space-between; margin-top: 20rpx; padding-top: 18rpx; border-top: 1rpx solid #edf0f4; color: #526078; font-size: 22rpx; }
.reward-detail__value { display: flex; align-items: center; }
.reward-detail__label { margin-right: 10rpx; color: #98a2b3; }
.reward-detail__expire { display: flex; align-items: center; color: #98a2b3; font-size: 20rpx; }
.reward-detail__expire text { margin-left: 5rpx; }
.failure-tip { display: flex; align-items: flex-start; margin-top: 14rpx; padding: 12rpx 14rpx; border-radius: 12rpx; color: #dc2626; background: #fff5f5; font-size: 20rpx; line-height: 1.45; }
.failure-tip text { flex: 1; min-width: 0; margin-left: 7rpx; }
.reward-action { display: flex; align-items: center; justify-content: flex-end; margin-top: 18rpx; color: #2468f2; font-size: 23rpx; font-weight: 650; }
.reward-action text { margin-right: 5rpx; }

.state-card, .empty-card { display: flex; align-items: center; justify-content: center; flex-direction: column; min-height: 310rpx; border: 1rpx solid #e7ebf2; border-radius: 24rpx; background: #ffffff; color: #98a2b3; }
.state-card text { margin-top: 18rpx; font-size: 23rpx; }
.empty-card__icon { display: flex; align-items: center; justify-content: center; width: 92rpx; height: 92rpx; border-radius: 28rpx; background: #f0f3f8; }
.empty-card__title { margin-top: 22rpx; color: #526078; font-size: 27rpx; font-weight: 650; }
.empty-card__desc { margin-top: 9rpx; color: #a3adbc; font-size: 21rpx; }
.load-more { display: flex; align-items: center; justify-content: center; padding: 26rpx 0 8rpx; color: #a3adbc; font-size: 21rpx; }
.load-more text { margin-left: 8rpx; }
.safe-space { height: calc(36rpx + env(safe-area-inset-bottom)); }
</style>
