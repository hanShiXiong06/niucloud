<template>
    <view class="distribution-page">
        <project-distribution-poster v-if="overview.capability?.eligible && inviteToken" ref="sharePosterRef" :project-id="projectId" :invite-token="inviteToken" :poster-id="0" copy-url="/addon/hsx_project_center/pages/project/detail" :copy-url-param="copyUrlParam" @loading="posterGenerating = $event" />
        <view class="hero-card">
            <view class="hero-label">PROJECT PARTNER</view><view class="hero-title">我的项目推广</view>
            <view class="hero-desc">邀请关系、待结算佣金和退款冲红都以系统账目为准</view>
        </view>
        <view v-if="loading" class="loading"><u-loading-icon text="正在读取推广数据" /></view>
        <template v-else>
            <view v-if="!overview.capability?.eligible" class="notice-card">
                <u-icon name="info-circle-fill" color="#f79009" size="24" />
                <view><view class="notice-title">当前账号暂不能推广</view><view class="notice-desc">{{ overview.capability?.reason || '请联系管理员检查当前项目的推广资格配置' }}</view></view>
            </view>
            <view v-else class="share-card">
                <view><view class="card-title">分享当前项目</view><view class="card-desc">{{ overview.capability.eligibility_mode === 'all_member' ? '所有会员均可推广' : overview.capability.level_name }} · 一级系数 {{ overview.capability.coefficient }}%</view></view>
                <view class="share-actions">
                    <button class="share-button" :loading="posterGenerating" :disabled="posterGenerating" @click="openPoster">{{ posterGenerating ? '生成中' : '分享海报' }}</button>
                    <!-- #ifdef MP -->
                    <button class="share-link-button" open-type="share">分享卡片</button>
                    <!-- #endif -->
                    <text class="notice-button" @click="subscribeNotice">开启佣金提醒</text>
                </view>
            </view>
            <view class="stats-grid">
                <view class="stat"><text>佣金余额</text><strong>¥{{ money(overview.commission_balance) }}</strong></view>
                <view class="stat"><text>待结算</text><strong>¥{{ money(overview.pending_amount) }}</strong></view>
                <view class="stat"><text>已入账</text><strong>¥{{ money(overview.settled_amount) }}</strong></view>
                <view class="stat"><text>一级 / 二级客户</text><strong>{{ overview.direct_count || 0 }} / {{ overview.second_count || 0 }}</strong></view>
            </view>
            <view v-if="Number(overview.debt_amount) > 0" class="debt-tip">退款待抵扣 ¥{{ money(overview.debt_amount) }}，未来新佣金会优先自动抵扣。</view>
            <view class="rule-card"><view class="card-title">当前项目规则</view><view class="rule-text">{{ overview.project?.rule_text || '项目未开启分销或规则暂不可用' }}</view><view class="rule-tip">{{ overview.project?.eligibility_mode === 'all_member' ? '所有正常会员按 100% 系数计算；规则变化不追溯已经生成的佣金单。' : '会员等级变化只影响以后审核通过的新工单，不追溯已经生成的佣金单。' }}</view></view>
            <view class="list-card">
                <view class="card-title">佣金明细</view>
                <view v-if="!rows.length" class="empty">还没有推广佣金记录</view>
                <view v-for="item in rows" :key="item.id" class="detail-row">
                    <view class="detail-main"><view class="detail-title">{{ item.project_title }} · {{ item.relation_level_name }}</view><view class="detail-sub">{{ item.beneficiary_level_name }}（系数 {{ item.coefficient }}%） · {{ item.status_name }}</view></view>
                    <view class="detail-money">¥{{ money(item.commission_amount) }}</view>
                </view>
                <view v-if="hasMore" class="load-more" @click="loadMore">加载更多</view>
            </view>
        </template>
        <view class="safe-bottom"></view>
    </view>
</template>
<script setup lang="ts">
import { computed, ref } from 'vue'
import { onLoad, onShareAppMessage } from '@dcloudio/uni-app'
import { useSubscribeMessage } from '@/hooks/useSubscribeMessage'
import { useShare } from '@/hooks/useShare'
import ProjectDistributionPoster from '@/addon/hsx_project_center/components/ProjectDistributionPoster.vue'
import { createProjectCenterDistributionInvite, getProjectCenterDistributionDetails, getProjectCenterDistributionOverview, getProjectCenterProject } from '@/addon/hsx_project_center/api'
const loading=ref(true), posterGenerating=ref(false), projectId=ref(0), overview=ref<any>({}), rows=ref<any[]>([]), page=ref(1), hasMore=ref(false), inviteToken=ref(''), project=ref<any>({}), sharePosterRef=ref<any>()
const { setShare }=useShare()
const money=(value:any)=>Number(value||0).toFixed(2)
const copyUrlParam=computed(()=>`?id=${encodeURIComponent(String(projectId.value))}&invite=${encodeURIComponent(inviteToken.value)}`)
async function refreshShare(){
    const query=[`id=${projectId.value}`]
    if(inviteToken.value) query.push(`invite=${encodeURIComponent(inviteToken.value)}`)
    const path=`/addon/hsx_project_center/pages/project/detail?${query.join('&')}`
    setShare({wechat:{title:project.value.title||'项目合作邀请',desc:project.value.subtitle||'查看项目详情和参与方式',url:project.value.cover||''},weapp:{title:project.value.title||'项目合作邀请',url:project.value.cover||'',path}})
}
async function loadData(){
    loading.value=true
    try{
        const [overviewRes,projectRes]:any=await Promise.all([getProjectCenterDistributionOverview(projectId.value),getProjectCenterProject(projectId.value)])
        overview.value=overviewRes.data||{};project.value=projectRes.data||{}
        inviteToken.value=''
        if(overview.value.capability?.eligible){const invite:any=await createProjectCenterDistributionInvite(projectId.value);inviteToken.value=String(invite.data?.share_code||invite.data?.token||'')}
        await refreshShare();await loadDetails(true)
    }finally{loading.value=false}
}
async function loadDetails(reset=false){if(reset){page.value=1;rows.value=[]}const res:any=await getProjectCenterDistributionDetails({project_id:projectId.value,page:page.value,limit:15});const data=res.data?.data||res.data?.list||[];rows.value=reset?data:[...rows.value,...data];hasMore.value=rows.value.length<Number(res.data?.total||0)}
async function loadMore(){page.value++;await loadDetails(false)}
function openPoster(){if(posterGenerating.value)return;if(!overview.value.capability?.eligible||!inviteToken.value){uni.showToast({title:'当前账号暂不能生成推广海报',icon:'none'});return}sharePosterRef.value?.open()}
async function subscribeNotice(){await useSubscribeMessage().request('project_center_distribution_pending,project_center_distribution_settled,project_center_distribution_reversed');uni.showToast({title:'佣金提醒设置已完成',icon:'none'})}
onShareAppMessage(()=>({title:project.value.title||'项目合作邀请',path:`/addon/hsx_project_center/pages/project/detail?id=${projectId.value}${inviteToken.value?`&invite=${encodeURIComponent(inviteToken.value)}`:''}`,imageUrl:project.value.cover||''}))
onLoad((options:any)=>{projectId.value=Number(options?.project_id||0);if(!projectId.value){uni.showToast({title:'缺少项目参数',icon:'none'});return}loadData()})
</script>
<style scoped>
.distribution-page{min-height:100vh;padding:24rpx;background:#f7f6ef;color:#262626}.hero-card{padding:38rpx 30rpx;border-radius:24rpx;background:linear-gradient(135deg,#fee502,#fff07a)}.hero-label{font-size:20rpx;font-weight:700;letter-spacing:3rpx;opacity:.55}.hero-title{margin-top:10rpx;font-size:40rpx;font-weight:750}.hero-desc{margin-top:10rpx;font-size:23rpx;line-height:34rpx;opacity:.72}.loading{padding:220rpx 0}.notice-card,.share-card,.rule-card,.list-card{margin-top:20rpx;padding:26rpx;border-radius:22rpx;background:#fff;box-shadow:0 8rpx 25rpx rgba(71,62,0,.05)}.notice-card{display:flex;gap:16rpx;border:1rpx solid #fedf89;background:#fffaeb}.notice-title,.card-title{font-size:29rpx;font-weight:700}.notice-desc,.card-desc,.rule-text,.rule-tip{margin-top:7rpx;color:#667085;font-size:23rpx;line-height:36rpx}.share-card{display:flex;align-items:center;justify-content:space-between;gap:20rpx}.share-actions{display:flex;flex:none;flex-direction:column;align-items:center;gap:10rpx}.share-button{flex:none;margin:0;padding:0 25rpx;border:0;border-radius:32rpx;background:#fee502;color:#181818;font-size:24rpx;font-weight:700;line-height:64rpx}.share-button::after,.share-link-button::after{border:0}.share-link-button{margin:0;padding:0;border:0;background:transparent;color:#756500;font-size:20rpx;font-weight:650;line-height:32rpx}.notice-button{color:#756500;font-size:20rpx;text-decoration:underline}.stats-grid{display:grid;grid-template-columns:1fr 1fr;gap:16rpx;margin-top:20rpx}.stat{display:flex;min-height:122rpx;flex-direction:column;justify-content:center;padding:22rpx;border-radius:20rpx;background:#fff}.stat text{color:#667085;font-size:22rpx}.stat strong{margin-top:8rpx;font-size:32rpx}.debt-tip{margin-top:16rpx;padding:18rpx;border-radius:16rpx;background:#fff1f0;color:#b42318;font-size:22rpx}.rule-tip{padding-top:12rpx;border-top:1rpx solid #f0f2f5}.empty{padding:60rpx 0;text-align:center;color:#98a2b3;font-size:24rpx}.detail-row{display:flex;align-items:center;justify-content:space-between;gap:18rpx;padding:23rpx 0;border-bottom:1rpx solid #f0f2f5}.detail-main{min-width:0}.detail-title{font-size:25rpx;font-weight:650}.detail-sub{margin-top:7rpx;color:#98a2b3;font-size:21rpx}.detail-money{flex:none;font-size:27rpx;font-weight:700}.load-more{padding:24rpx 0 2rpx;text-align:center;color:#756500;font-size:23rpx}.safe-bottom{height:calc(30rpx + env(safe-area-inset-bottom))}
</style>
