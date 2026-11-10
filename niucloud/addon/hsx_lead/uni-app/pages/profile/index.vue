<template>
    <view class="lead-profile-page lead-diy-page" :style="themeColor()">
        <CoopDiyPageStatus :loading="pageLoad.loading.value" :error="pageLoad.error.value" @retry="retryPage" />
        <view v-show="pageLoad.ready.value" class="diy-template-wrap bg-index" :style="diy.pageStyle() + (!context.preview.value && !context.displayable.value ? 'min-height:0;' : '')"><diy-group ref="groupRef" :data="diy.data" /></view>
        <template v-if="pageLoad.ready.value && !context.preview.value">
            <view v-if="!context.loggedIn.value" class="lead-profile-page__status"><CoopState title="欢迎来到商户协作" hint="登录后查看自己的门店、权益与协作收益" icon="account" /><u-button :custom-style="cooperationButtonStyle" type="primary" shape="circle" text="使用会员身份登录" @click="context.login(loginParams)" /></view>
            <view v-else-if="context.loading.value || context.error.value" class="lead-profile-page__status"><CoopState :loading="context.loading.value" :error="context.error.value" @retry="context.refresh()" /></view>
            <view v-else-if="context.ready.value && !context.profile.value.id && context.pendingEntity.value.status === 'pending'" class="lead-profile-page__status"><CoopState title="等待负责人确认" :hint="context.pendingEntity.value.name" icon="clock" /><u-button :custom-style="cooperationButtonStyle" type="primary" shape="circle" text="查看加入申请" @click="openEntity" /></view>
            <view v-else-if="context.ready.value && !context.profile.value.id" class="lead-profile-page__status"><CoopState title="创建或加入门店" hint="已有门店的同事，使用负责人分享的主体码加入" icon="home" /><u-button :custom-style="cooperationButtonStyle" type="primary" shape="circle" text="创建我的门店" @click="context.action('profile')" /><button class="lead-profile-page__join" @tap="openEntity">加入已有门店</button></view>
            <view v-if="context.profile.value.blocked_at" class="lead-profile-page__blocked" role="alert">合作已停用：{{ context.profile.value.block_reason || '请联系平台核对' }}。历史交付与已形成收益仍保留。</view>
        </template>
    </view>
</template>
<script setup lang="ts">
import { onBeforeUnmount, provide, ref, unref } from 'vue'
import { onHide, onLoad, onPullDownRefresh, onShow } from '@dcloudio/uni-app'
import { useDiy } from '@/hooks/useDiy'
import { useShare } from '@/hooks/useShare'
import { parseJsonValue } from '@/utils/json'
import { getToken, redirect } from '@/utils/common'
import diyGroup from '@/addon/components/diy/group/index.vue'
import CoopState from '../../components/cooperation/CoopState.vue'
import { createProfileDiyContext, profileDiyKey } from '../../composables/useProfileDiy'
import { openCooperationTask } from '../../utils/cooperation-navigation'
import { cooperationButtonStyle } from '../../utils/mobile-ui'
import { applyCooperationDiyTitle } from '../../utils/diy-title'
import { entityPageUrl } from '../../utils/entity-membership'
import { cooperationInviteParams } from '../../utils/cooperation-invite'
import { useCooperationDiyLoad } from '../../composables/useCooperationDiyLoad'
import CoopDiyPageStatus from '../../components/cooperation/CoopDiyPageStatus.vue'
const pageLoad = useCooperationDiyLoad('个人中心')
const diy = useDiy({ name:'DIY_HSX_LEAD_MEMBER_INDEX', ...pageLoad.requestOptions }), groupRef = ref<any>(null)
const context = createProfileDiyContext(), loginParams = ref<Record<string,any>>({}), { setShare, onShareAppMessage } = useShare()
provide(profileDiyKey,context)
onShareAppMessage()
let pendingLead=0, pendingPromotion=false, pendingInviter=0, disposed=false, redirectingInvitation=false
const positiveId=(value:unknown)=>{const number=Number(value);return Number.isSafeInteger(number)&&number>0&&number<=4294967295?number:0}
diy.onLoad()
onLoad(raw=>{
    const options=cooperationInviteParams(raw)
    pendingLead=positiveId(options?.collaboration_id);pendingPromotion=String(options?.promotion_invite||'')==='1';pendingInviter=positiveId(options?.mid)
    loginParams.value={...(pendingLead?{collaboration_id:pendingLead}:{}),...(pendingPromotion?{promotion_invite:1,...(pendingInviter?{mid:pendingInviter}:{})}:{})}
    // Already-issued promotion QR images may still point to this old route.
    // A promotion scan is login entry, never an instruction to join a store.
    if(options?.mode!=='decorate' && pendingPromotion && !getToken()){
        redirectingInvitation=true
        redirect({url:'/addon/hsx_lead/pages/invite/index',param:loginParams.value,mode:'redirectTo'})
        return
    }
    if(options?.mode==='decorate')pageLoad.complete();else pageLoad.start()
})
diy.onShow((data:any)=>{
    if(redirectingInvitation)return
    if(!pageLoad.complete())return
    // Keep the framework's selected-page contract, rather than forcing the
    // component layout when the administrator has chosen a different page.
    if(!data.value&&data.page){redirect({url:data.page,mode:'reLaunch'});return}
    applyCooperationDiyTitle(unref(diy.data))
    setShare(parseJsonValue(data.share,{}));groupRef.value?.refresh?.()
})
diy.onHide();diy.onUnload();diy.onPageScroll()
onShow(async()=>{
    if(redirectingInvitation)return
    const success=await context.refresh()
    if(!success||disposed||context.preview.value)return
    if(pendingPromotion){pendingPromotion=false;openCooperationTask('promotion',{tab:'overview',...(pendingInviter?{mid:pendingInviter}:{})})}
    else if(pendingLead&&context.profile.value.id){const id=pendingLead;pendingLead=0;openCooperationTask('detail',{collaboration_id:id})}
})
onHide(()=>{context.pause();pageLoad.pause()})
onPullDownRefresh(async()=>{try{await context.refresh()}finally{uni.stopPullDownRefresh()}})
onBeforeUnmount(()=>{disposed=true;context.dispose();pageLoad.dispose()})
function retryPage(){uni.redirectTo({url:'/addon/hsx_lead/pages/profile/index'})}
function openEntity(){uni.navigateTo({url:entityPageUrl+'?mode=join'})}
</script>
<style scoped lang="scss">
@import '@/styles/diy.scss';
@import '../../components/cooperation/cooperation-diy-page';
.lead-profile-page { min-height: 100vh; background:#f6f6f6; }
.lead-profile-page__status { padding: 48rpx 30rpx 24rpx; }
.lead-profile-page__status>.u-button { margin-top:24rpx; }
.lead-profile-page__blocked { margin:20rpx 24rpx;padding:24rpx;border-radius:10rpx;background:#fff1e9;color:#a3654d;font-size:24rpx;line-height:1.8; }
.lead-profile-page__join { margin:12px 0 0;line-height:44px;font-size:14px;color:#6685ae;background:transparent; }.lead-profile-page__join::after { border:0; }
</style>
