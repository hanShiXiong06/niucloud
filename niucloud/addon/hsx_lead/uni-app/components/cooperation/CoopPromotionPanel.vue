<template>
    <CoopTaskSheet :page="page" :show="show" :title="heading" :busy="ui.saving.value" full @close="closePanel">
        <view class="live-promotion">
            <template v-if="task === 'bind'">
                <view v-if="ui.confirmedUpstream.value" class="promo-section promo-note">服务端已确认邀请人：{{ ui.confirmedUpstream.value.name || '已确认的直属上级' }}。若下方刷新失败，请重新读取，不要重复提交绑定。</view>
                <CoopState v-if="ui.invitationLoading.value || ui.invitationError.value" :loading="ui.invitationLoading.value" :error="ui.invitationError.value" @retry="inspectInvitation" />
                <view v-else class="promo-section">
                    <view class="promo-person"><view class="promo-avatar"><u-icon name="account" size="28" color="#6484af" /></view><view class="promo-grow"><view class="promo-title">{{ ui.invitation.value.inviter?.name || '邀请信息' }}</view><view class="promo-muted">{{ ui.invitation.value.inviter?.city || '' }}</view></view></view>
                    <view v-if="ui.invitation.value.current_upstream" class="promo-note">当前邀请人：{{ ui.invitation.value.current_upstream.name }}。已有关系不会因再次打开链接被覆盖。</view>
                    <view v-if="!ui.invitation.value.can_bind" class="promo-note">{{ ui.invitation.value.reason || '当前不能建立新的邀请关系，请核对已有关系。' }}</view>
                    <view v-else class="promo-consent" @tap="!ui.saving.value && (bindConfirmed = !bindConfirmed)"><u-icon :name="bindConfirmed ? 'checkmark-circle-fill' : 'checkmark-circle'" size="22" :color="bindConfirmed ? 'var(--primary-color,#3478cc)' : '#9daec1'" /><text>我确认由{{ ui.invitation.value.inviter?.name }}邀请，同意建立邀请关系。</text></view>
                    <view class="promo-muted">关系只用于直属推广归属，不向邀请人开放你的客户线索或 ERP 凭据。</view>
                </view>
            </template>
            <CoopState v-else-if="ui.loading.value && !ui.ready.value || ui.error.value" :loading="ui.loading.value" :error="ui.error.value" @retry="refresh" />
            <template v-else-if="ui.ready.value">
                <template v-if="task === 'upstream'">
                    <view class="promo-section"><template v-if="data.upstream"><view class="promo-person"><view class="promo-avatar"><u-icon name="account" size="28" color="#6484af" /></view><view class="promo-grow"><view class="promo-title">{{ data.upstream.name }}</view><view class="promo-muted">{{ data.upstream.mobile_masked }}<text v-if="data.upstream.city"> · {{ data.upstream.city }}</text></view></view><text class="promo-tag">{{ data.upstream.provider_enabled ? '城市服务商' : '本站会员' }}</text></view><view class="promo-kv"><text>邀请关系</text><text>直属邀请人</text></view></template><CoopState v-else title="还没有邀请人" hint="确认会员邀请关系后，可在这里查看归属。" icon="account" /></view>
                </template>
                <template v-else-if="task === 'application'">
                    <CoopState v-if="ui.applicationLoading.value && !ui.applicationReady.value" :loading="true" />
                    <template v-if="ui.applicationReady.value">
                        <view class="promo-section promo-application-status" :class="'is-' + application.status"><view class="promo-person"><view class="promo-avatar is-provider"><u-icon :name="application.status === 'approved' ? 'checkmark-circle' : application.status === 'pending' ? 'clock' : 'account'" size="28" color="#a48349" /></view><view class="promo-grow"><view class="promo-title">{{ application.status_label || applicationStatusLabel }}</view><view class="promo-muted">{{ applicationStatusHint }}</view></view></view><view v-if="application.reason && ['rejected','disabled'].includes(application.status)" class="promo-note">{{ application.reason }}</view><view v-if="application.submitted_at" class="promo-application-time">提交于 {{ coopDate(application.submitted_at) }}<text v-if="application.reviewed_at"> · 审核于 {{ coopDate(application.reviewed_at) }}</text></view></view>
                        <view class="promo-section"><view class="promo-title">本次申请资料</view><view class="promo-kv"><text>门店</text><text>{{ application.merchant_name || '尚未完善' }}</text></view><view class="promo-kv"><text>所在城市</text><text>{{ application.city || '尚未完善' }}</text></view><view class="promo-kv"><text>联系人</text><text>{{ application.contact_name || '尚未完善' }}</text></view><view class="promo-kv"><text>联系电话</text><text>{{ application.mobile_masked || '尚未完善' }}</text></view><view v-if="!application.can_apply && application.apply_reason && !['pending','approved'].includes(application.status)" class="promo-note">{{ application.apply_reason }}</view></view>
                    </template>
                    <view v-if="ui.applicationError.value" class="promo-section promo-application-error" role="alert">{{ ui.applicationError.value }}</view>
                    <view class="promo-explainer"><u-collapse :border="false"><u-collapse-item title="服务商与推广规则" name="provider"><view class="promo-help">普通会员也可以邀请。服务商申请免费、不要求先购买入驻权益，提交后由平台人工审核，不保证通过。通过后按服务商档位计算新的有效付款奖励，两档不叠加。服务商不自动获得门店会员权益，也不免除发单、接单所需的入驻权益费。</view></u-collapse-item></u-collapse></view>
                </template>
                <template v-else-if="task === 'rules'">
                    <view class="promo-section">
                        <view class="promo-row"><view class="promo-title">我的推广权益</view><text class="promo-tag is-good">{{ currentRoleLabel }}</text></view>
                        <view v-for="item in sourceOptions" :key="item.key" class="promo-record"><view class="promo-row"><text>{{ item.label }}</text><text v-if="!data.rules.enabled || !data.rules.sources?.[item.key]?.enabled" class="promo-muted">未参与</text></view><view v-if="data.rules.enabled && data.rules.sources?.[item.key]?.enabled" class="promo-kv"><text>直接邀请奖励</text><text class="promo-value">{{ data.current_rates?.[item.key] ?? '—' }}%</text></view><view class="promo-muted">{{ item.key === 'maintenance' ? '按平台实收维护费计算' : '只按实付服务费计算，不含押金和经验费' }}<text v-if="item.key === 'membership'"> · {{ data.rules.sources?.[item.key]?.renewal ? '首次及续费' : '仅首次购买' }}</text></view></view>
                        <view class="promo-kv"><text>结算等待期</text><text>{{ data.rules.settlement_days }} 天</text></view><view class="promo-kv"><text>获佣期限</text><text>{{ Number(data.rules.eligibility_months) ? data.rules.eligibility_months + '个月' : '长期有效' }}</text></view>
                    </view>
                    <view class="promo-explainer"><u-collapse :border="false"><u-collapse-item title="规则、入账与退款说明" name="rules"><view class="promo-help">奖励以服务端核实的真实业务付款为准。订单创建时保存计佣规则，付款时核验邀请关系与资格，不因后续调规则回算旧单。退款按原奖励对应冲回；待核对或冻结的奖励不能当作已到账，已入账不代表已提现。</view></u-collapse-item></u-collapse></view>
                </template>
                <template v-else>
                    <view class="promo-tabs"><view v-for="item in tabs" :key="item.key" :class="{ active: tab === item.key }" @tap="changeTab(item.key)">{{ item.label }}</view><button class="promo-refresh" :disabled="ui.loading.value" aria-label="刷新推广数据" @tap="refresh"><u-icon name="reload" size="17" color="#7b8faa" /></button></view>
                    <template v-if="tab === 'overview'">
                        <view class="promo-section promo-identity"><view class="promo-kicker"><u-icon name="share" size="15" color="var(--primary-color,#3478cc)" /><text>分享，让好生意相遇</text></view><view class="promo-person"><view class="promo-avatar"><u-icon name="account" size="28" color="#6484af" /></view><view class="promo-grow"><view class="promo-title">{{ data.member.name }}</view><view class="promo-muted">{{ data.provider?.city || '连接本地生意' }}</view></view><text class="promo-tag" :class="{ 'is-good': providerRole }">{{ currentRoleLabel }}</text></view>
                            <button v-if="promotionId" class="promo-id" aria-label="复制我的推广ID" :disabled="ui.saving.value || ui.loading.value" @tap.stop="copyPromotionId"><text>推广 ID</text><text class="promo-id__value">{{ promotionId }}</text><text class="promo-id__action">复制</text><u-icon name="file-text" size="14" color="var(--primary-color,#3478cc)" /></button>
                            <view v-if="!data.promotion_allowed" class="promo-identity-notice">{{ !data.enabled ? '本站推广暂未开放' : '新的邀请与奖励已暂停，历史记录保留' }}</view><view v-else class="promo-current-rate"><view><text>直接邀请 · 会员服务费</text><text>{{ membershipRateLabel }}</text></view><text>无需先购买会员 · 实际有效付款后计佣</text></view></view>
                        <view class="promo-section promo-application-entry" role="button" @tap="openApplication"><view class="promo-menu-icon is-gold"><u-icon name="account" size="22" color="#a8884d" /></view><view class="promo-grow"><view class="promo-title">{{ applicationEntryLabel }}</view><view class="promo-muted">{{ application.status === 'pending' ? '资料已提交，等待平台审核' : providerRole ? '查看服务商资料与审核记录' : '使用已有门店资料，一键提交审核' }}</view></view><u-icon name="arrow-right" size="14" color="#9aabbe" /></view>
                        <view class="promo-section"><view class="promo-row"><view class="promo-title">推广收益</view><text class="promo-link" @tap="task='rules'">收益规则 ›</text></view><view class="promo-stats"><view @tap="showRewards('pending')"><text class="promo-stat-value">{{ coopMoney(data.stats.pending) }}</text><text>待结算</text></view><view @tap="showRewards('settled')"><text class="promo-stat-value">{{ coopMoney(data.stats.settled) }}</text><text>累计已入账</text></view><view @tap="showRewards('held')"><text class="promo-stat-value">{{ coopMoney(data.stats.held) }}</text><text>待核对</text></view></view></view>
                        <view class="promo-section promo-income-entry" @tap="openIncome()"><view><view class="promo-title">我的收益</view><view class="promo-muted">推广佣金、线索收益统一查看</view></view><u-icon name="arrow-right" size="14" color="#8a9db7" /></view>
                        <view class="promo-section promo-menu-group"><view class="promo-menu" @tap="changeTab('team')"><view class="promo-menu-icon"><u-icon name="man-add" size="22" color="#6484af" /></view><text class="promo-grow">我的邀请</text><text class="promo-muted">直接邀请 {{ data.stats.team_count }} 位</text><u-icon name="arrow-right" size="13" color="#8a9db7" /></view><view class="promo-menu" @tap="task='upstream'"><view class="promo-menu-icon is-gold"><u-icon name="account" size="22" color="#a58c5f" /></view><text class="promo-grow">邀请我的人</text><text class="promo-muted">{{ data.upstream?.name || '未绑定' }}</text><u-icon name="arrow-right" size="13" color="#8a9db7" /></view></view>
                    </template>
                    <template v-else-if="tab === 'invite'">
                        <view v-if="canInvite" class="promo-section promo-invite"><view class="promo-kicker">我的专属邀请</view><view class="promo-invite-title">一起把本地生意做好</view><view class="promo-muted">{{ data.member.name }}邀请你加入商户协作</view>
                            <button v-if="promotionId" class="promo-id" aria-label="复制我的推广ID" :disabled="ui.saving.value || ui.loading.value" @tap.stop="copyPromotionId"><text>推广 ID</text><text class="promo-id__value">{{ promotionId }}</text><text class="promo-id__action">复制</text><u-icon name="file-text" size="14" color="var(--primary-color,#3478cc)" /></button>
                            <view class="promo-invite-art"><u-icon name="share" size="44" color="#4b79b7" /></view>
                            <!-- #ifdef MP-WEIXIN -->
                            <button class="promo-share-button" open-type="share">分享给朋友 / 同行</button>
                            <!-- #endif -->
                            <!-- #ifndef MP-WEIXIN -->
                            <u-button :custom-style="cooperationButtonStyle" shape="circle" type="primary" text="复制邀请链接" @click="copyInvitation" />
                            <!-- #endif -->
                            <button class="promo-poster-button" @tap="posterOpen = true"><u-icon name="photo" size="18" color="#6683a8" /><text>生成邀请海报</text></button>
                        </view>
                        <CoopState v-else title="暂不能发送邀请" :hint="!data.enabled ? '本站暂未开放推广，请联系平台。' : '请核对当前推广状态；历史关系与已形成收益保留。'" icon="lock" />
                        <view class="promo-explainer"><u-collapse :border="false"><u-collapse-item title="邀请商户后会发生什么？" name="invite"><view class="promo-help">商户通过邀请入口使用本站会员身份加入，关系以服务器保存的直属上级为准。满足本站推广规则的真实付款，才会形成对应奖励。已有邀请关系不会被新链接覆盖。</view></u-collapse-item></u-collapse></view>
                    </template>
                    <template v-else-if="tab === 'team'">
                        <view class="promo-section promo-filter"><CooperationSearch v-model="ui.teamKeyword.value" placeholder="搜索会员编号、姓名、手机或门店" :loading="ui.teamLoading.value" @search="searchTeam" @custom="searchTeam" @clear="searchTeam('')" /><view class="promo-filter-count">由你直接邀请 · {{ ui.teamTotal.value }} 位<text v-if="ui.teamLoading.value"> · 正在更新</text></view></view>
                        <CoopState v-if="ui.teamLoading.value && !ui.team.value.length || ui.teamError.value" :loading="ui.teamLoading.value" :error="ui.teamError.value" @retry="ui.loadTeam()" />
                        <view v-else class="promo-section"><CoopState v-if="!ui.team.value.length" title="暂无符合条件的邀请记录" hint="邀请关系以会员本人确认及服务器记录为准。" icon="man-add" /><view v-for="item in ui.team.value" :key="item.member_id" class="promo-record"><view class="promo-row"><view class="promo-grow"><view class="promo-title">{{ item.merchant_name || item.name }}</view><view class="promo-muted">{{ item.name }}<text v-if="item.mobile_masked"> · {{ item.mobile_masked }}</text></view></view><text class="promo-tag">{{ item.stage_label || stageLabel(item.stage) }}</text></view><view class="promo-muted">{{ item.region_text || '门店资料待完善' }}</view><view class="promo-team-membership"><text>门店会员</text><text class="promo-tag" :class="'is-' + membershipStatus(item)">{{ membershipStatusLabel(item) }}</text><text v-if="item.membership?.level_name" class="promo-team-membership__level">{{ item.membership.level_name }}</text><text v-if="item.membership?.shared" class="promo-muted">共享门店权益</text></view></view><view v-if="ui.teamMore.value" class="promo-more" @tap="ui.loadTeam(true)">{{ ui.teamLoading.value ? '正在读取…' : '加载更多' }}</view></view>
                    </template>
                </template>
            </template>
        </view>
        <template v-if="footerKind" #footer><view v-if="footerKind==='bind'" class="promo-bind-actions"><view><u-button :custom-style="cooperationButtonStyle" shape="circle" text="取消" :disabled="ui.saving.value" @click="emit('close')" /></view><view><u-button :custom-style="cooperationButtonStyle" shape="circle" type="primary" text="确认邀请关系" :loading="ui.saving.value" :disabled="!bindConfirmed || ui.saving.value" @click="confirmInvitation" /></view></view><u-button v-else :custom-style="cooperationButtonStyle" shape="circle" type="primary" :text="footerLabel" :loading="ui.saving.value || ui.applicationLoading.value" :disabled="ui.saving.value || ui.applicationLoading.value" @click="footerAction" /></template>
    </CoopTaskSheet>
    <CoopInvitePoster :show="posterOpen" scene="promotion" :allowed="show && canInvite && tab === 'invite'" @close="posterOpen = false" />
</template>
<script lang="ts">
// Match Vue's transparent layout in native Mini Programs: an extra custom
// component host otherwise breaks the page's definite-height flex chain.
export default { options: { virtualHost: true } }
</script>
<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { onHide } from '@dcloudio/uni-app'
import { redirect } from '@/utils/common'
import { useShare } from '@/hooks/useShare'
import useSystemStore from '@/stores/system'
import { usePromotion } from '../../composables/usePromotion'
import { coopDate, coopMoney } from '../../api/cooperation'
import CoopTaskSheet from './CoopTaskSheet.vue'
import CoopState from './CoopState.vue'
import CooperationSearch from './CooperationSearch.vue'
import CoopInvitePoster from './CoopInvitePoster.vue'
import { openCooperationTask } from '../../utils/cooperation-navigation'
import { cooperationButtonStyle } from '../../utils/mobile-ui'
import { profileMemberNo } from '../../utils/profile-identity'
const props=withDefaults(defineProps<{show:boolean;page?:boolean;initialTab?:string}>(),{initialTab:'overview'})
const posterOpen=ref(false)
const emit=defineEmits<{(event:'close'|'changed'):void}>(),ui=usePromotion(),data=computed(()=>ui.overview.value),tab=ref('overview'),task=ref(''),inviterMid=ref(0),bindConfirmed=ref(false)
const tabs=[{key:'overview',label:'推广概览'},{key:'invite',label:'邀请同行'},{key:'team',label:'我的邀请'},{key:'earnings',label:'我的收益'}]
const sourceOptions=[{key:'membership',label:'会员服务费'},{key:'maintenance',label:'平台维护费'}]
const providerRole=computed(()=>data.value.role==='provider'),application=computed(()=>ui.application.value)
const promotionId=computed(()=>profileMemberNo(data.value.member || {}))
const currentRoleLabel=computed(()=>providerRole.value?'城市服务商':data.value.level?.active?data.value.level.name:'普通会员')
const membershipRateLabel=computed(()=>data.value.rules?.enabled&&data.value.rules?.sources?.membership?.enabled?(data.value.current_rates?.membership===undefined?'比例待核对':data.value.current_rates.membership+'%'):'暂未开启')
const canInvite=computed(()=>data.value.promotion_allowed===true&&!!data.value.share?.path)
const applicationEntryLabel=computed(()=>({pending:'服务商申请 · 待审核',approved:'城市服务商',rejected:'服务商申请 · 未通过',disabled:'服务商状态 · 已停用'}[application.value.status]||'申请成为服务商'))
const applicationStatusLabel=computed(()=>({none:'申请成为城市服务商',pending:'申请待审核',approved:'已成为城市服务商',rejected:'本次申请未通过',disabled:'服务商已停用'}[application.value.status]||'申请状态待核对'))
const applicationStatusHint=computed(()=>({none:'免费申请 · 平台人工审核',pending:'审核结果会在这里更新，勿重复申请',approved:'新的有效付款按服务商档位计算奖励',rejected:'核对原因后，可免费重新提交审核',disabled:application.value.disabled_mode==='blocked'?'新的邀请与奖励已暂停':'已切换普通推广身份，历史奖励保留'}[application.value.status]||''))
const heading=computed(()=>({upstream:'邀请我的人',rules:'推广规则',bind:'确认邀请关系',application:'服务商申请'}[task.value]||'推广中心'))
const footerKind=computed(()=>{
    if(task.value==='bind')return ui.invitation.value.can_bind&&!ui.confirmedUpstream.value?'bind':''
    if(task.value!=='application')return ''
    if(ui.applicationUncertain.value||ui.applicationError.value)return 'check'
    if(!ui.applicationReady.value)return ''
    if(application.value.can_apply)return 'apply'
    if(!application.value.merchant_name&&data.value.enabled&&data.value.promotion_allowed&&['none','rejected'].includes(application.value.status))return 'profile'
    return ''
})
const footerLabel=computed(()=>footerKind.value==='check'?'核对申请状态':footerKind.value==='profile'?'完善申请资料':application.value.status==='rejected'?'重新申请（免费）':'确认资料，免费申请')
const system=useSystemStore(),share=useShare();let previousShare:any=null,previousWeapp:any,previousCallback:any,sharePrepared=false
function stageLabel(value:string){return({registered:'已注册',onboarded:'已完善门店',paid:'已有有效付费','已登记门店':'已登记门店','待完善门店':'待完善门店','已加入门店':'已加入门店'} as Record<string,string>)[value]||'已加入'}
function membershipStatus(item:any){return ['not_opened','active','expired','inactive','review'].includes(item.membership?.status)?item.membership.status:'review'}
function membershipStatusLabel(item:any){if(!item.membership)return '状态待更新';return({not_opened:'未开通',active:'生效中',expired:'已到期',inactive:'当前未生效',review:'待核对'} as Record<string,string>)[membershipStatus(item)]}
function invitationUrl(){const base=String(system.site?.wap_url||'').replace(/\/$/,'');return /^https?:\/\//i.test(base)&&data.value.share?.path?base+data.value.share.path:''}
function prepareShare(){if(!canInvite.value){restoreShare();return}if(!sharePrepared){previousShare=JSON.parse(JSON.stringify(system.shareOptions||{}));previousWeapp=uni.getStorageSync('weappOptions');previousCallback=system.shareCallback;sharePrepared=true}const url=invitationUrl();share.setShare({isStop:true,weapp:{title:'一起把本地生意做好 · 商户协作邀请',path:data.value.share.path},...(url?{wechat:{title:'商户协作邀请',desc:'连接本地商户，一起承接好生意',link:url}}:{})})}
function restoreShare(){if(!sharePrepared)return;system.$patch({shareOptions:previousShare,shareCallback:previousCallback});if(previousWeapp)uni.setStorageSync('weappOptions',previousWeapp);else uni.removeStorageSync('weappOptions');sharePrepared=false}
async function copyInvitation(){
    const url=invitationUrl()
    if(!url){uni.showToast({title:'本站分享地址尚未配置，请联系平台核对',icon:'none'});return}
    uni.setClipboardData({
        data:url,
        fail(){uni.showToast({title:'复制失败，请重试',icon:'none'})}
    })
}
function copyPromotionId(){
    if(!props.show||!ui.resume()||!ui.ready.value||ui.saving.value||ui.loading.value||!promotionId.value)return
    uni.setClipboardData({
        data:promotionId.value,
        success(){uni.showToast({title:'推广 ID 已复制',icon:'none'})},
        fail(){uni.showToast({title:'复制失败，请重试',icon:'none'})}
    })
}
function openIncome(status=''){if(ui.saving.value)return;redirect({url:'/addon/hsx_lead/pages/wallet/index',param:status?{status}:{}})}
async function refresh(){const loaded=await ui.reload();if(loaded){prepareShare();if(tab.value==='team')await ui.loadTeam()}}
async function changeTab(value:string){if(ui.saving.value)return;if(value==='earnings'){openIncome();return}posterOpen.value=false;task.value='';tab.value=value;if(value==='team'){ui.teamLevel.value=1;await ui.loadTeam()}if(value==='invite')prepareShare()}
function searchTeam(value?:string|number){if(typeof value==='string'||typeof value==='number')ui.teamKeyword.value=String(value);return ui.loadTeam()}
function showRewards(status:string){openIncome(status)}
function back(){if(ui.saving.value)return;if(props.page&&props.initialTab==='application'&&task.value==='application'){emit('close');return}task.value='';bindConfirmed.value=false}
function closePanel(){if(ui.saving.value)return;if(task.value){back();return}emit('close')}
async function inspectInvitation(){if(inviterMid.value){await ui.inspectInvitation(inviterMid.value);if(ui.confirmedUpstream.value)await ui.reload()}}
async function openInvitation(mid:number){inviterMid.value=Number(mid);bindConfirmed.value=false;task.value='bind';await inspectInvitation()}
async function confirmInvitation(){if(!bindConfirmed.value)return;if(await ui.bind(inviterMid.value)){task.value='upstream';emit('changed');uni.showToast({title:'邀请关系已核对',icon:'none'})}}
async function openApplication(){if(ui.saving.value)return;if(props.page){openCooperationTask('promotion',{tab:'application'});return}task.value='application';await ui.readApplication()}
async function submitApplication(){if(await ui.applyProvider()){emit('changed');uni.showToast({title:ui.application.value.status==='approved'?'服务商状态已更新':'申请已提交，等待审核',icon:'none'})}}
function footerAction(){if(ui.saving.value||ui.applicationLoading.value)return;if(footerKind.value==='check'){ui.readApplication();return}if(footerKind.value==='profile'){openCooperationTask('profile-edit');return}if(footerKind.value==='apply')submitApplication()}
watch(()=>props.show,async show=>{if(!show){posterOpen.value=false;ui.stop();restoreShare();return}task.value=['upstream','application'].includes(props.initialTab)?props.initialTab:'';tab.value=tabs.some(item=>item.key===props.initialTab)&&props.initialTab!=='earnings'?props.initialTab:'overview';await refresh();if(props.show&&props.initialTab==='application')await ui.readApplication();if(props.show&&props.initialTab==='earnings')openIncome()},{immediate:true})
onHide(()=>{posterOpen.value=false})
onBeforeUnmount(()=>{ui.stop();restoreShare()})
defineExpose({openInvitation,refresh})
</script>
<style scoped lang="scss">
.promo-application-entry{display:flex;align-items:center;gap:20rpx;min-height:118rpx;box-sizing:border-box}.promo-application-entry .promo-menu-icon{width:76rpx;height:76rpx;border:1rpx solid #eaddc3;background:linear-gradient(145deg,#fff9ee,#f6ecd8)}.promo-application-entry .promo-title{font-size:27rpx}.promo-avatar.is-provider{background:#faf4e7;border-color:#ebdfc6}.promo-application-time{margin-top:20rpx;font-size:21rpx;line-height:1.8;color:#8793a3;overflow-wrap:anywhere}.promo-application-status.is-approved{border-color:#e5d9bb;background:linear-gradient(130deg,#fffdf7,#fff)}.promo-application-status.is-rejected .promo-note,.promo-application-status.is-disabled .promo-note{background:#fff1ed;color:#a0624c}.promo-application-error{color:#a55c4f;font-size:24rpx;line-height:1.8}.promo-application-error>view{margin-bottom:20rpx}.promo-rate-tiers{display:flex;gap:16rpx;margin:18rpx 0 12rpx}.promo-rate-tiers>view{flex:1;min-width:0;padding:14rpx 18rpx;background:#f4f6f9;border:1rpx solid #edf1f6;border-radius:10rpx;display:flex;flex-direction:column;gap:6rpx;font-size:22rpx;color:#8593a5}.promo-rate-tiers>view>text:last-child{font-size:32rpx;font-weight:650;line-height:1.5;color:#526780}.promo-rate-tiers>view.active{border-color:#ccdef5;background:#eef5ff}.promo-rate-tiers>view.active>text:last-child{color:var(--primary-color,#3478cc)}.promo-identity-notice{font-size:23rpx;line-height:1.7;color:#96754a;padding-top:18rpx;margin-top:20rpx;border-top:1rpx solid #e2eaf6}.promo-application-status .promo-person{align-items:flex-start}.promo-identity .promo-person{flex-wrap:wrap}.promo-identity .promo-person>.promo-grow{min-width:180rpx}
.live-promotion{color:#2b3c52;padding-bottom:24rpx}.promo-section{margin:20rpx 24rpx;background:#fff;border:1rpx solid #e6ecf4;border-radius:16rpx;padding:27rpx}.promo-back{display:flex;gap:12rpx;align-items:center;padding:22rpx 28rpx 4rpx;font-size:24rpx;color:#7185a0}.promo-title{font-size:29rpx;line-height:1.6;font-weight:600;color:#2d3d52;overflow-wrap:anywhere}.promo-muted{font-size:23rpx;color:#8391a5;line-height:1.8;overflow-wrap:anywhere}.promo-grow{flex:1;min-width:0}.promo-person{display:flex;align-items:center;gap:20rpx;min-height:90rpx}.promo-avatar{width:80rpx;height:80rpx;background:#edf3fb;border:1rpx solid #dce7f5;border-radius:24rpx;display:flex;align-items:center;justify-content:center;flex:none}.promo-tag{font-size:20rpx;padding:5rpx 11rpx;line-height:1.6;color:#7a8ca5;background:#f0f4f9;border-radius:6rpx;flex:none;max-width:220rpx;overflow-wrap:anywhere}.promo-tag.is-good{background:#f7edd8;color:#92713e}.promo-row{display:flex;align-items:center;justify-content:space-between;gap:20rpx;line-height:1.7}.promo-value{color:#3d6b9d;font-size:28rpx;font-weight:600;white-space:nowrap}.promo-kv{display:flex;justify-content:space-between;gap:24rpx;padding:23rpx 0;border-bottom:1rpx solid #edf1f6;font-size:25rpx;line-height:1.6}.promo-kv>text:first-child{color:#8694a7;flex:none}.promo-kv>text:last-child{text-align:right;overflow-wrap:anywhere}.promo-note{padding:23rpx;margin:20rpx 0;background:#fbf6ed;color:#a08a6b;border-radius:10rpx;font-size:24rpx;line-height:1.8}.promo-tabs{position:sticky;top:0;z-index:2;display:flex;align-items:center;background:#fff;padding:0 16rpx;border-bottom:1rpx solid #e4eaf4}.promo-tabs>view{flex:1;text-align:center;white-space:nowrap;font-size:25rpx;padding:24rpx 0 20rpx;color:#8b99ad;border-bottom:4rpx solid transparent}.promo-tabs>view.active{color:var(--primary-color,#3478cc);font-weight:600;border-bottom-color:var(--primary-color,#3478cc)}.promo-refresh{flex:none;width:56rpx;height:70rpx;display:flex;align-items:center;justify-content:center;background:transparent;padding:0;margin:0;border:0}.promo-refresh::after{border:0}.promo-identity{background:linear-gradient(110deg,#f6f9fe,#eef4fc);border-color:#dbe6f4}.promo-kicker{font-size:22rpx;letter-spacing:2rpx;color:#8193ab;display:flex;gap:10rpx;align-items:center;margin-bottom:20rpx}.promo-stats{display:flex;gap:10rpx;margin-top:24rpx;padding:24rpx 0 2rpx;border-top:1rpx solid #edf1f7}.promo-stats>view{flex:1;text-align:center;min-width:0;display:flex;gap:12rpx;flex-direction:column;font-size:21rpx;color:#8d9aab}.promo-stat-value{font-size:28rpx;font-weight:600;color:#354b66;overflow-wrap:anywhere}.promo-link{font-size:22rpx;color:var(--primary-color,#3478cc)}.promo-account{padding-top:29rpx}.promo-balance{font-size:60rpx;color:#2e4561;line-height:1.8;font-weight:650}.promo-account-actions{display:flex;gap:20rpx;margin-top:14rpx}.promo-account-actions>view{flex:1;min-width:0}.promo-account-note{margin-top:18rpx;font-size:21rpx}.promo-menu-group{padding-top:0;padding-bottom:0}.promo-menu{display:flex;align-items:center;gap:17rpx;min-height:105rpx;padding:15rpx 0;box-sizing:border-box;font-size:26rpx}.promo-menu+.promo-menu{border-top:1rpx solid #edf1f7}.promo-menu-icon{width:60rpx;height:60rpx;flex:none;background:#eff4fb;border-radius:17rpx;display:flex;align-items:center;justify-content:center}.promo-menu-icon.is-gold{background:#faf5ea}.promo-record{padding:25rpx 0;border-bottom:1rpx solid #edf1f6}.promo-record:last-child{border-bottom:0}.promo-record .promo-muted{margin-top:9rpx}.promo-explainer{margin:20rpx 24rpx;background:#fff;border-radius:12rpx;overflow:hidden}.promo-help{color:#7d8b9e;font-size:23rpx;line-height:1.9;padding:0 26rpx 20rpx}.promo-invite{text-align:center;padding:36rpx 30rpx;background:linear-gradient(145deg,#f6f9ff,#fff)}.promo-invite>.promo-kicker{justify-content:center}.promo-invite-title{font-size:35rpx;color:#2f496e;font-weight:650;line-height:1.8}.promo-invite-art{position:relative;width:200rpx;height:190rpx;display:flex;align-items:center;justify-content:center;margin:35rpx auto;background:#eaf2fc;border-radius:45rpx;border:1rpx solid #d6e4f7;transform:rotate(-4deg)}.promo-invite-plus{position:absolute;width:60rpx;height:60rpx;border:4rpx solid #fff;border-radius:50%;background:#faf2e2;bottom:-12rpx;right:-13rpx;display:flex;align-items:center;justify-content:center;transform:rotate(4deg)}.promo-share-button{border:0;border-radius:45rpx;color:#fff;background:var(--primary-color,#3478cc);font-size:28rpx;line-height:86rpx;margin:30rpx 0 0}.promo-share-button::after{border:0}.promo-filter{position:sticky;top:84rpx;z-index:1}.promo-filter-count{font-size:22rpx;line-height:1.6;color:#8b99ad;margin-top:18rpx}.promo-more{text-align:center;min-height:80rpx;line-height:80rpx;font-size:24rpx;color:var(--primary-color,#3478cc)}.promo-chips{white-space:nowrap}.promo-chips-inner{display:flex;gap:17rpx}.promo-chips-inner>text{padding:10rpx 20rpx;font-size:23rpx;border-radius:26rpx;color:#8391a5;background:#f4f6fa}.promo-chips-inner>text.active{color:var(--primary-color,#3478cc);background:var(--primary-color-light,#edf4ff)}.promo-consent{display:flex;align-items:flex-start;gap:16rpx;margin:25rpx 0;font-size:25rpx;line-height:1.8;color:#697e98}.promo-bind-actions{display:flex;gap:20rpx}.promo-bind-actions>view:first-child{flex:0 0 28%}.promo-bind-actions>view:last-child{flex:1;min-width:0}@media(max-width:340px){.promo-section{margin:18rpx 18rpx;padding:23rpx}.promo-tabs>view{font-size:23rpx}.promo-stat-value{font-size:25rpx}.promo-tabs{padding:0 10rpx}.promo-filter{top:82rpx}}
</style>
<style scoped lang="scss">
/* Task pages use fixed touch sizes rather than shrinking with rpx on narrow phones. */
.live-promotion { padding-bottom:12px; }.promo-section { margin:12px;padding:16px;border-radius:10px; }.promo-title { font-size:15px; }.promo-muted,.promo-help { font-size:12px;line-height:1.75; }.promo-note { font-size:13px;padding:12px;margin:12px 0; }
.promo-person { gap:12px; }.promo-row { gap:12px;align-items:flex-start; }.promo-row>.promo-title { min-width:0; }.promo-kv { font-size:13px;padding:12px 0;gap:16px; }.promo-kv>text:last-child { min-width:0; }.promo-tag { font-size:11px;max-width:110px; }
.promo-back { min-height:44px;box-sizing:border-box;padding:0 16px;font-size:13px; }.promo-tabs { padding:0 12px; }.promo-tabs>view { display:flex;align-items:center;justify-content:center;min-width:0;min-height:44px;padding:0;font-size:14px;box-sizing:border-box; }.promo-refresh { width:44px;height:44px; }.promo-filter { top:44px; }.promo-filter-count { font-size:12px;margin-top:8px; }
.promo-link { display:flex;align-items:center;min-height:44px;font-size:12px;white-space:nowrap;flex:none; }.promo-stats { margin-top:12px;padding-top:16px;gap:8px; }.promo-stats>view { min-height:64px;justify-content:center;font-size:12px; }.promo-stat-value { font-size:17px; }.promo-balance { font-size:30px;line-height:1.6; }.promo-account-actions { gap:12px; }.promo-account-note { font-size:12px;margin-top:12px; }
.promo-menu { min-height:52px;font-size:14px;gap:10px; }.promo-menu>.promo-muted { max-width:40%;text-align:right; }.promo-explainer { margin:12px;border-radius:10px; }.promo-help { padding:0 12px 12px; }.promo-consent { font-size:14px;min-height:44px; }.promo-more { min-height:44px;line-height:44px;font-size:13px; }.promo-chips-inner { gap:8px; }.promo-chips-inner>text { display:inline-flex;align-items:center;min-height:44px;box-sizing:border-box;padding:0 12px;font-size:13px; }
.promo-share-button { min-height:44px;line-height:44px;font-size:14px; }.promo-bind-actions { gap:12px; }.promo-application-entry { min-height:76px; }.promo-application-entry .promo-title { font-size:15px; }.promo-application-time { font-size:12px;margin-top:12px; }.promo-rate-tiers>view { padding:10px;font-size:12px; }.promo-rate-tiers>view>text:last-child { font-size:18px; }
.promo-poster-button { display:flex;align-items:center;justify-content:center;gap:8px;min-height:44px;line-height:44px;margin:12px 0 0;padding:0 12px;background:#eff4fb;color:#6683a8;border-radius:22px;font-size:14px; }.promo-poster-button::after { border:0; }
.promo-current-rate { padding-top:14px;margin-top:16px;border-top:1px solid #e1e9f4; }.promo-current-rate>view { display:flex;align-items:center;justify-content:space-between;gap:12px;font-size:13px;color:#657990; }.promo-current-rate>view>text:last-child { font-size:28px;line-height:1.4;color:var(--primary-color,#3478cc);font-weight:650; }.promo-current-rate>view>text:last-child>text { font-size:14px;margin-left:2px; }.promo-current-rate>text { display:block;margin-top:5px;font-size:12px;color:#8a96a8; }
.promo-income-entry { display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:76px;box-sizing:border-box; }.promo-level-rates { padding:0 16px 16px; }.promo-level-rates+.promo-level-rates { border-top:1px solid #edf1f6;padding-top:16px; }
.promo-team-membership { display:flex;align-items:center;flex-wrap:wrap;gap:6px;margin-top:10px;font-size:12px;color:#7d8795; }.promo-team-membership>.promo-tag { max-width:none; }.promo-team-membership>.promo-muted { margin:0;font-size:11px; }.promo-team-membership__level { color:#4f6076;font-weight:600; }.promo-team-membership .is-active { background:#edf6f1;color:#4f8065; }.promo-team-membership .is-expired,.promo-team-membership .is-review { background:#faf3e7;color:#9b7a43; }
.promo-invite-art { width:88px;height:88px;margin:20px auto;background:#eff4fb;border:0;border-radius:22px;transform:none; }
.promo-id {
    display:flex;align-items:center;gap:8px;min-height:44px;box-sizing:border-box;width:100%;
    margin:12px 0 0;padding:8px 12px;border-radius:8px;background:rgba(255,255,255,.78);
    text-align:left;font-size:12px;line-height:20px;color:#7b8b9e;
}
.promo-id::after { border:0; }
.promo-id__value { flex:1;min-width:0;overflow-wrap:anywhere;font-size:15px;font-weight:650;font-variant-numeric:tabular-nums;color:#354b66; }
.promo-id__action { flex:none;color:var(--primary-color,#3478cc); }
.promo-id[disabled] { opacity:.55; }
.live-promotion { --cds-text-primary:#28374a;--cds-text-secondary:#768294;--cds-surface-panel:#fff;--cds-surface-subtle:#f5f6f8;--cds-fill-accent:var(--primary-color,#3478cc);color:var(--cds-text-primary);background:var(--cds-surface-subtle); }
.promo-section { border:0;border-radius:14px;padding:18px; }.promo-identity { background:linear-gradient(135deg,var(--primary-color-light,#edf4ff),#fff); }.promo-title { color:var(--cds-text-primary);font-weight:600; }.promo-muted { color:var(--cds-text-secondary); }.promo-kicker { font-size:12px;letter-spacing:0;line-height:1.6; }.promo-tabs { border-bottom:0; }.promo-tabs>view { min-height:48px; }.promo-stats { padding-top:18px;margin-top:14px; }.promo-stat-value { font-size:20px;line-height:1.5;font-variant-numeric:tabular-nums; }.promo-stats>view { min-height:68px;gap:8px; }.promo-income-entry { min-height:80px; }.promo-current-rate { border-top-color:rgba(70,100,145,.12); }.promo-current-rate .promo-second-rate { margin-top:10px; }.promo-current-rate .promo-second-rate>text:last-child { font-size:18px;font-weight:600;color:var(--cds-text-primary); }.promo-team-levels { display:flex;gap:8px;margin-bottom:16px; }.promo-team-levels>button { display:flex;align-items:center;justify-content:center;gap:8px;flex:1;min-height:44px;margin:0;padding:0 10px;font-size:13px;line-height:1.6;border-radius:9px;background:var(--cds-surface-subtle);color:var(--cds-text-secondary); }.promo-team-levels>button::after { border:0; }.promo-team-levels>button.active { background:var(--primary-color-light,#edf4ff);color:var(--cds-fill-accent);font-weight:600; }.promo-team-levels>button>text { font-variant-numeric:tabular-nums; }.promo-invite-chain { display:flex;align-items:center;justify-content:space-between;gap:8px;margin:16px 0; }.promo-invite-chain>text { min-width:0;padding:8px 10px;border-radius:8px;background:var(--cds-surface-subtle);font-size:13px; }.promo-chain-note .promo-title { font-size:15px; }
@media(prefers-reduced-motion:reduce) { .live-promotion,.live-promotion button,.live-promotion view { animation:none!important;transition:none!important; } }
@media(max-width:340px) { .promo-tabs>view { font-size:13px; }.promo-section { margin:12px;padding:12px; }.promo-filter { top:44px; } }
</style>
