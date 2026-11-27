<template>
    <view class="lead-profile-page lead-diy-page" :style="themeColor()">
        <CoopDiyPageStatus :loading="pageLoad.loading.value" :error="pageLoad.error.value" @retry="retryPage" />
        <view
            v-show="pageLoad.ready.value"
            class="diy-template-wrap bg-index"
            :style="diy.pageStyle() + (!context.preview.value && !context.displayable.value && !guestHeader ? 'min-height:0;' : '')"
            ><diy-group ref="groupRef" :data="diy.data"
        /></view>
        <template v-if="pageLoad.ready.value && !context.preview.value">
            <view v-if="!context.loggedIn.value && !guestHeader" class="lead-profile-page__status">
                <view class="lead-profile-welcome">
                    <view class="lead-profile-welcome__avatar"><u-icon name="account-fill" size="30" color="currentColor" /></view>
                    <text class="lead-profile-welcome__title">欢迎来到商户协作</text>
                    <text class="lead-profile-welcome__hint">登录后管理门店、承接客户线索、查看协作收益</text>
                    <button class="lead-profile-welcome__cta" hover-class="is-pressed" @tap="context.login(loginParams)">立即登录</button>
                </view>
            </view>
            <view v-else-if="context.loading.value || context.error.value" class="lead-profile-page__status"
                ><CoopState :loading="context.loading.value" :error="context.error.value" @retry="context.refresh()"
            /></view>
            <view
                v-else-if="context.ready.value && !context.profile.value.id && context.pendingEntity.value.status === 'pending'"
                class="lead-profile-page__status"
                ><CoopState title="等待负责人确认" :hint="context.pendingEntity.value.name" icon="clock" /><view
                    class="lead-profile-page__action"
                    ><u-button
                        :custom-style="cooperationButtonStyle"
                        type="primary"
                        shape="circle"
                        text="查看加入申请"
                        @click="openEntity" /></view
            ></view>
            <view v-else-if="context.ready.value && !context.profile.value.id" class="lead-profile-page__status"
                ><CoopState
                    title="创建或加入门店"
                    hint="已有门店的同事，使用负责人分享的主体码加入"
                    icon="home"
                /><view class="lead-profile-page__action"
                    ><u-button
                        :custom-style="cooperationButtonStyle"
                        type="primary"
                        shape="circle"
                        text="创建我的门店"
                        @click="context.action('profile')"
                /></view
                ><button class="lead-profile-page__join" @tap="openEntity">加入已有门店</button></view
            >
            <view v-if="context.profile.value.blocked_at" class="lead-profile-page__blocked" role="alert"
                >合作已停用：{{ context.profile.value.block_reason || '请联系平台核对'
                }}。历史交付与已形成收益仍保留。</view
            >
        </template>
    </view>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, provide, ref, unref } from 'vue'
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
const context = createProfileDiyContext(), loginParams = context.loginParams, { setShare, onShareAppMessage } = useShare()
provide(profileDiyKey,context)
// When the decorated page has the identity block, it shows the sign-in header itself (CoopGuestIdentity);
// the standalone welcome card below is only a fallback for layouts without that block.
const hasIdentityBlock = computed(() => { const list = (diy.data as any)?.value?.value; return Array.isArray(list) && list.some((item: any) => item?.componentName === 'LeadProfileIdentity') })
const guestHeader = computed(() => !context.preview.value && !context.loggedIn.value && hasIdentityBlock.value)
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
@import '../../styles/tokens.scss';
.lead-profile-page { min-height: 100vh; background: $hsx-surface-page; }
.lead-profile-page__status { padding: $hsx-space-6 $hsx-space-4 $hsx-space-4; }
// u-button's root cannot be targeted from a scoped parent selector in mini programs; space it with a wrapper.
.lead-profile-page__action { margin-top: $hsx-space-4; }
.lead-profile-page__blocked { margin: $hsx-space-3 $hsx-space-4; @include hsx-notice-bar(danger); }
.lead-profile-page__join {
    @include hsx-button-reset;
    display: block;
    width: 100%;
    min-height: $hsx-tap-min;
    margin-top: $hsx-space-2;
    line-height: $hsx-tap-min;
    font-size: $hsx-font-body;
    color: $hsx-accent-text;
}

// Fallback welcome card, only for decorated layouts that have no identity block.
.lead-profile-welcome {
    @include hsx-card;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: $hsx-space-6 $hsx-space-5 $hsx-space-5;
    text-align: center;
}
.lead-profile-welcome__avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 64px;
    height: 64px;
    border-radius: $hsx-radius-pill;
    background: $hsx-accent-soft;
    color: $hsx-accent;
}
.lead-profile-welcome__title {
    margin-top: $hsx-space-4;
    font-size: $hsx-font-title;
    font-weight: $hsx-weight-bold;
    line-height: $hsx-line-height-tight;
    color: $hsx-text-primary;
}
.lead-profile-welcome__hint {
    margin-top: $hsx-space-2;
    font-size: $hsx-font-body-sm;
    line-height: $hsx-line-height;
    color: $hsx-text-secondary;
}
.lead-profile-welcome__cta {
    @include hsx-button-primary;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    margin-top: $hsx-space-5;
    transition: transform $hsx-duration-fast $hsx-ease-out, opacity $hsx-duration-fast $hsx-ease-out;
    &.is-pressed { transform: scale(.98); opacity: .9; }
}
</style>
