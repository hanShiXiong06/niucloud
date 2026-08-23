<template>
    <view class="project-page">
        <view v-if="loading" class="loading-wrap"><u-loading-icon text="正在加载项目" /></view>
        <template v-else-if="project.id">
            <view class="hero">
                <image v-if="project.cover" class="hero-cover" :src="img(project.cover)" mode="aspectFill" />
                <view class="hero-mask"></view>
                <view class="hero-content">
                    <view class="hero-eyebrow">PROJECT SERVICE</view>
                    <view class="hero-title">{{ project.title }}</view>
                    <view class="hero-subtitle">{{ project.subtitle || '项目了解、资料提交和审核进度，一页完成' }}</view>
                    <view class="hero-tags"><text>项目说明透明</text><text>资料逐项审核</text><text>进度随时可查</text></view>
                </view>
            </view>

            <view v-if="status.status && status.status !== 'not_submitted'" class="status-card" :class="`status-${status.status}`">
                <view class="status-icon"><u-icon :name="statusIcon" :color="statusColor" size="28" /></view>
                <view class="status-body"><view class="status-title">{{ status.status_name }}</view><view class="status-desc">{{ statusDescription }}</view><view v-if="status.group_no" class="status-group">群编号 {{ status.group_no }}</view></view>
            </view>

            <view v-if="status.status === 'rejected'" class="issue-panel">
                <view class="section-heading"><view><view class="section-title">需要修改的资料</view><view class="section-subtitle">按下面的说明修改后重新提交，无需联系人员询问哪里有问题</view></view></view>
                <view v-for="(item,index) in status.field_issues || []" :key="index" class="issue-row">
                    <view class="issue-index">{{ index + 1 }}</view><view class="issue-content"><view class="issue-label">{{ item.field_label || '资料项' }}</view><view class="issue-message">{{ item.message }}</view><view v-if="item.example" class="issue-example">正确示例：{{ item.example }}</view></view>
                </view>
                <view v-if="status.review_remark" class="review-remark">补充说明：{{ status.review_remark }}</view>
            </view>

            <view v-if="needsForm" class="content-card action-card">
                <view class="payment-steps">
                    <view class="payment-step" :class="{ active: !showGroupGate && !paymentDeclared, done: showGroupGate || paymentDeclared }"><text class="step-dot">1</text><text>扫码付款</text></view>
                    <view class="payment-step" :class="{ active: showGroupGate && !paymentDeclared, done: paymentDeclared }"><text class="step-dot">2</text><text>核对群号</text></view>
                    <view class="payment-step" :class="{ active: paymentDeclared }"><text class="step-dot">3</text><text>填写资料</text></view>
                </view>

                <view v-if="!paymentDeclared && !showGroupGate" class="payment-panel">
                    <view v-if="paymentQrcodeUrl" class="qrcode-thumb" @click="previewPaymentQrcode">
                        <image :src="paymentQrcodeUrl" mode="aspectFit" :show-menu-by-longpress="true" />
                        <view class="qrcode-zoom"><u-icon name="scan" color="#fff" size="14" /><text>点击放大</text></view>
                    </view>
                    <view v-else class="qrcode-thumb qrcode-empty"><u-icon name="photo" color="#98a2b3" size="28" /><text>付款码暂未配置</text></view>
                    <view class="payment-summary">
                        <view class="payment-summary-label">本次付款</view>
                        <view v-if="Number(project.payment_amount) > 0" class="payment-amount">¥{{ Number(project.payment_amount).toFixed(2) }}</view>
                        <view class="payment-guide">点击二维码放大，长按识别进入小程序付款</view>
                        <view class="payment-proof">付款后请截图，并把付款截图发送到当前客户群</view>
                    </view>
                    <view class="payment-tip-line">{{ project.payment_tips || '请核对收款方与金额，保存完整付款凭证。' }}</view>
                    <view class="button-wrap"><u-button type="primary" shape="circle" :customStyle="primaryButtonStyle" :disabled="!paymentQrcodeUrl" text="我已付款，下一步" @click="beginForm" /></view>
                    <view class="declare-warning">付款状态由群内财务核对；下一步需要填写客户群编号。</view>
                </view>

                <view v-else-if="!paymentDeclared" class="group-gate">
                    <view class="gate-title">
                        <view class="gate-main">
                            <view class="gate-required-row"><text class="gate-required-badge">必填</text><view class="section-title">填写您所在的运营群数字编号</view></view>
                            <view class="gate-lock-tip"><u-icon name="lock-fill" color="#665900" size="15" /><text>编号核对通过后，才能进入资料填写</text></view>
                        </view>
                        <view class="back-payment" @click="backToPayment">返回付款码</view>
                    </view>
                    <view class="group-input compact-group-input" :class="{ 'group-input-error': !!groupError }">
                        <view class="group-field-line">
                            <u-icon name="edit-pen" color="#756500" size="20" />
                            <input v-model="groupNo" maxlength="50" placeholder="请输入数字编号，例如 0819-1" placeholder-class="input-placeholder" confirm-type="done" @input="handleGroupInput" @blur="normalizeGroupInput" @confirm="verifyGroup(true)" />
                        </view>
                        <view class="group-example">群名“美团闪购 0819-1 ×××”，此处只填写 <text>0819-1</text></view>
                        <view v-if="groupError" class="group-error-message">
                            <u-icon name="error-circle" color="#d92d20" size="16" />
                            <text>{{ groupError }}</text>
                        </view>
                    </view>
                    <view class="verify-button"><u-button type="primary" shape="circle" :customStyle="primaryButtonStyle" :loading="verifying" loading-color="#181818" text="确认编号，进入资料填写" @click="verifyGroup(true)" /></view>
                </view>

                <view v-else class="form-area">
                    <view class="form-ready"><u-icon name="checkmark-circle-fill" color="#12b76a" size="21" /><text>群编号 {{ groupNo }} · 请按示例提交清晰、完整的资料</text><text v-if="status.status === 'not_submitted'" class="change-group" @click="changeGroup">更换</text></view>
                    <ProjectFormProgress :components="formComponents" />
                    <diy-form v-if="project.form_id" ref="formRef" :form_id="project.form_id" form_border="none" :storage_name="`project_center_${project.id}_${groupNo}`" />
                    <view v-if="submitError" class="submit-error-panel">
                        <u-icon name="error-circle" color="#d92d20" size="17" />
                        <view><view class="submit-error-title">资料提交未完成</view><view class="submit-error-message">{{ submitError }}</view></view>
                    </view>
                    <view class="submit-wrap"><u-button type="primary" shape="circle" :customStyle="primaryButtonStyle" :loading="submitting" loading-color="#181818" text="提交资料审核" @click="submit" /></view>
                    <view class="submit-tip">提交后会通知资料审核员；审核结果将优先通过小程序或公众号通知。</view>
                </view>
            </view>

            <view v-if="project.ai_enabled && !hasAiDiyEntry" class="ai-fallback-wrap">
                <AiAssistantEntry :component="fallbackAiComponent" />
            </view>

            <view v-if="hasIntroDiy" class="project-diy-wrap" :style="introPageStyle">
                <diy-group :data="introDiy" />
            </view>

            <template v-else>
            <view class="content-card">
                <view class="section-heading"><view><view class="section-title">项目介绍</view><view class="section-subtitle">先了解规则，再决定是否参与</view></view><view class="section-badge">透明办理</view></view>
                <view class="intro-text">{{ project.config_json?.intro || project.payment_tips || '请阅读项目说明，如有疑问可在客户群内咨询工作人员。' }}</view>
            </view>

            <view v-if="steps.length" class="content-card">
                <view class="section-heading"><view><view class="section-title">办理流程</view><view class="section-subtitle">每一步都知道下一步由谁处理</view></view></view>
                <view class="steps"><view v-for="(item,index) in steps" :key="index" class="step"><view class="step-no">{{ index + 1 }}</view><view class="step-line" v-if="index < steps.length - 1"></view><view class="step-text">{{ item }}</view></view></view>
            </view>

            <view v-if="project.income_board?.length" class="content-card income-card">
                <view class="section-heading"><view><view class="section-title">近期项目案例</view><view class="section-subtitle">{{ incomeDate }} 运营展示数据，不构成收益承诺</view></view><view class="live-dot">每日更新</view></view>
                <swiper class="income-swiper page-scroll-priority" circular autoplay :interval="2600" vertical :disable-touch="true">
                    <swiper-item v-for="item in project.income_board" :key="item.rank_no"><view class="income-row"><view class="income-rank">TOP {{ String(item.rank_no).padStart(2,'0') }}</view><view class="income-store">{{ item.store_name }}</view><view class="income-value">¥{{ Number(item.income_amount).toFixed(2) }}</view></view></swiper-item>
                </swiper>
            </view>

            <view v-if="faqs.length" class="content-card">
                <view class="section-heading"><view><view class="section-title">常见问题</view><view class="section-subtitle">点击问题查看答案</view></view></view>
                <view v-for="(item,index) in faqs" :key="index" class="faq-item" @click="toggleFaq(index)">
                    <view class="faq-question"><text>{{ item.question }}</text><u-icon :name="faqOpen === index ? 'arrow-up' : 'arrow-down'" color="#98a2b3" size="16" /></view>
                    <view v-if="faqOpen === index" class="faq-answer">{{ item.answer }}</view>
                </view>
            </view>
            </template>

            <view v-if="legacyDirect" class="content-card legacy-card">
                <u-icon name="info-circle-fill" color="#f79009" size="28" />
                <view><view class="legacy-title">历史申请尚未绑定客户群</view><view class="legacy-desc">该工单仅供查看。需要修改或继续办理时，请联系工作人员先绑定客户群编号。</view></view>
            </view>

            <view v-if="!needsForm" class="content-card completed-card"><u-icon :name="statusIcon" :color="statusColor" size="42"/><view class="completed-title">{{ status.status_name }}</view><view class="completed-desc">{{ statusDescription }}</view></view>
            <view class="safe-bottom"></view>
        </template>
    </view>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import DiyForm from '@/addon/components/diy-form/index.vue'
import DiyGroup from '@/addon/components/diy/group/index.vue'
import ProjectFormProgress from '@/addon/hsx_project_center/components/ProjectFormProgress.vue'
import AiAssistantEntry from '@/addon/hsx_ai/components/diy/ai-assistant-entry/index.vue'
import { addFormRecord } from '@/app/api/diy_form'
import { useSubscribeMessage } from '@/hooks/useSubscribeMessage'
import { useLogin } from '@/hooks/useLogin'
import { useShare } from '@/hooks/useShare'
import useMemberStore from '@/stores/member'
import useSystemStore from '@/stores/system'
import { getSiteId, img } from '@/utils/common'
import { getProjectCenterApplicationStatus, getProjectCenterProject, resolveProjectCenterGroup, reviseProjectCenterApplication, submitProjectCenterApplication } from '@/addon/hsx_project_center/api'

const loading = ref(true), submitting = ref(false), verifying = ref(false), projectId = ref(0), project = ref<any>({}), status = ref<any>({ status:'not_submitted', status_name:'未提交' }), groupNo = ref(''), groupError = ref(''), submitError = ref(''), groupValidated = ref(false), showGroupGate = ref(false), paymentDeclared = ref(false), faqOpen = ref(-1), formRef = ref<any>()
const memberStore = useMemberStore(), systemStore = useSystemStore(), subscribe = useSubscribeMessage()
const { setShare } = useShare()
const primaryButtonStyle = { height:'82rpx', border:'none', background:'#fee502', color:'#181818', fontWeight:'700', boxShadow:'0 10rpx 24rpx rgba(210,185,0,.24)' }
const steps = computed<string[]>(() => project.value.config_json?.steps || [])
const faqs = computed<any[]>(() => project.value.config_json?.faqs || [])
const introDiy = computed<any>(() => project.value.intro_diy || { global: {}, value: [] })
const hasIntroDiy = computed(() => Array.isArray(introDiy.value?.value) && introDiy.value.value.length > 0)
const hasAiDiyEntry = computed(() => (introDiy.value?.value || []).some((item:any) => item?.componentName === 'AiAssistantEntry'))
const fallbackAiComponent = computed(() => ({
    componentName:'AiAssistantEntry', assistantType:'project_center', assistantMode:'popup', projectId:projectId.value, groupNo:groupNo.value,
    layout:'card', title:project.value.config_json?.ai_title || 'AI 项目顾问', subtitle:'项目费用、资料、流程、审核和退款问题，随时问我', buttonText:'立即咨询',
    showVoiceHint:Number(project.value.config_json?.ai_voice_enabled ?? 1), voiceHint:'支持语音朗读', panelColor:'#FFFFFF', accentColor:'#315CF5', titleColor:'#172033', subtitleColor:'#667085', buttonTextColor:'#FFFFFF'
}))
const introPageStyle = computed<Record<string,string>>(() => {
    const global = introDiy.value?.global || {}
    const style: Record<string,string> = {}
    if (global.pageStartBgColor) {
        style.background = global.pageEndBgColor
            ? `linear-gradient(${global.pageGradientAngle || 'to bottom'},${global.pageStartBgColor},${global.pageEndBgColor})`
            : String(global.pageStartBgColor)
    }
    if (global.bgUrl) {
        const url = String(img(global.bgUrl)).replace(/'/g, "\\'")
        style.backgroundImage = `url('${url}')`
        style.backgroundRepeat = 'no-repeat'
        style.backgroundPosition = 'top center'
    }
    if (global.bgHeightScale) style.backgroundSize = `100% ${Number(global.bgHeightScale)}%`
    return style
})
const legacyDirect = computed(() => !!status.value.legacy_direct)
const needsForm = computed(() => !legacyDirect.value && !['submitted','reviewing','approved','refund_pending','refunded','abandoned','dissolved','completed'].includes(status.value.status))
const groupVerified = computed(() => groupValidated.value && !!groupNo.value)
const paymentQrcodeUrl = computed(() => project.value.payment_qrcode ? String(img(project.value.payment_qrcode)) : '')
const formComponents = computed<any[]>(() => {
    try {
        const value = formRef.value?.getData?.()?.value
        return Array.isArray(value) ? value : []
    } catch (_) {
        return []
    }
})
const statusColor = computed(() => ['approved','refunded','completed'].includes(status.value.status) ? '#12b76a' : ['rejected','abandoned','dissolved'].includes(status.value.status) ? '#f04438' : '#f79009')
const statusIcon = computed(() => ['approved','refunded','completed'].includes(status.value.status) ? 'checkmark-circle-fill' : ['rejected','abandoned','dissolved'].includes(status.value.status) ? 'close-circle-fill' : 'clock-fill')
const statusDescription = computed(() => ({ submitted:'资料已进入审核队列，请留意审核通知。', reviewing:'审核人员正在逐项核验资料，请耐心等待。', rejected:'部分资料需要修改，请按页面逐项说明重新提交。', approved:'资料审核已通过，请留意后续办理通知。', refund_pending:'本次办理已终止，退款正在处理中，请留意群内进度和退款通知。', refunded:'退款已经完成，本次办理已关闭；如有疑问请凭群编号联系工作人员。', completed:'本次项目已经办理完成。', abandoned:'本次办理已经结束；如需重新参与，请联系工作人员。', dissolved:'客户群已经解散，本次办理已关闭。' } as any)[status.value.status] || '')
const incomeDate = computed(() => { const value = String(project.value.income_date || ''); return value.length === 8 ? `${value.slice(0,4)}-${value.slice(4,6)}-${value.slice(6)}` : '' })

function toggleFaq(index:number) { faqOpen.value = faqOpen.value === index ? -1 : index }
function setProjectShare() {
    const title = String(project.value.title || '项目合作')
    const desc = String(project.value.subtitle || '查看项目介绍、办理流程和参与方式')
    const cover = String(project.value.cover || '')
    const query: string[] = [`id=${encodeURIComponent(String(projectId.value))}`]
    const memberId = Number(memberStore.info?.member_id || uni.getStorageSync('wap_member_id') || 0)
    if (memberId > 0) query.push(`mid=${memberId}`)
    const queryString = query.join('&')
    const pagePath = `/addon/hsx_project_center/pages/project/detail?${queryString}`
    let h5Link = ''
    // #ifdef H5
    h5Link = `${location.origin}${location.pathname}?${queryString}`
    // #endif
    // #ifdef APP-PLUS
    const wapUrl = String(systemStore.site?.wap_url || '').replace(/\/$/, '')
    if (wapUrl) h5Link = `${wapUrl}${pagePath}`
    // #endif
    const share: any = {
        wechat: { title, desc, url:cover },
        weapp: { title, url:cover, path:pagePath }
    }
    if (h5Link) share.wechat.link = h5Link
    // 分享链接只保留项目 ID 和推荐人，不携带客户群编号、付款步骤等私有参数。
    setShare(share)
}
async function loadProject() { loading.value = true; try { const res:any = await getProjectCenterProject(projectId.value); project.value = res.data || {}; project.value.intro_diy = normalizeDiy(project.value.intro_diy); setProjectShare(); await loadStatus() } catch (e:any) { uni.showToast({ title:e?.message || '项目加载失败', icon:'none' }) } finally { loading.value = false } }
function prepareRevisionCache(value:any) {
    if (!project.value.form_id || !Array.isArray(value)) return
    const components = value.filter((item:any) => item?.componentType === 'diy_form' && item?.componentName !== 'FormSubmit')
    if (!components.length) return
    uni.setStorageSync(`diyFormStorage_${project.value.form_id}`, {
        validTime: Math.floor(Date.now() / 1000) + 3600,
        components
    })
}
async function loadStatus() { if (!memberStore.token) return; try { const res:any = await getProjectCenterApplicationStatus(projectId.value, groupNo.value); const nextStatus = res.data || status.value; if (nextStatus.status === 'rejected' && !nextStatus.legacy_direct) prepareRevisionCache(nextStatus.revision_value); status.value = nextStatus; if (status.value.group_no) { groupNo.value = status.value.group_no; groupError.value = ''; groupValidated.value = true; uni.setStorageSync(groupStorageKey(), groupNo.value) } if (status.value.status === 'rejected' && !status.value.legacy_direct) paymentDeclared.value = true; if (status.value.legacy_direct) { paymentDeclared.value = false; groupValidated.value = false } } catch (_) {} }
function requireLogin() { if (memberStore.token) return true; useLogin().setLoginBack({ url:'/addon/hsx_project_center/pages/project/detail', param:{ id:projectId.value, group_no:groupNo.value, gate:showGroupGate.value ? 1 : 0 } }); return false }
function storageScope() {
    const siteId = getSiteId(import.meta.env.VITE_SITE_ID || uni.getStorageSync('wap_site_id')) || uni.getStorageSync('wap_site_id') || 0
    const memberId = Number(memberStore.info?.member_id || uni.getStorageSync('wap_member_id') || 0)
    return `${siteId}_${memberId}`
}
function groupStorageKey() { return `project_center_group_no_${storageScope()}_${projectId.value}` }
function gateStorageKey() { return `project_center_group_gate_${storageScope()}_${projectId.value}` }
function extractGroupNo(value:string) { const normalized = String(value || '').trim().replace(/[－—–]/g, '-'); const matched = normalized.match(/(?:^|\D)(\d{4}(?:\d{4})?-\d+)(?:\D|$)/); return matched?.[1] || normalized.replace(/\s+/g, '') }
function normalizeGroupInput() { const next = extractGroupNo(groupNo.value); if (next !== groupNo.value) groupNo.value = next }
function handleGroupInput() { groupError.value = ''; groupValidated.value = false; paymentDeclared.value = false }
function beginForm() { showGroupGate.value = true; uni.setStorageSync(gateStorageKey(), 1); if (!requireLogin()) return; setTimeout(() => uni.pageScrollTo({ selector:'.action-card', duration:220 }), 50) }
function backToPayment() { showGroupGate.value = false; uni.removeStorageSync(gateStorageKey()); setTimeout(() => uni.pageScrollTo({ selector:'.action-card', duration:180 }), 30) }
function changeGroup() { groupError.value = ''; groupValidated.value = false; paymentDeclared.value = false; showGroupGate.value = true; groupNo.value = ''; uni.removeStorageSync(groupStorageKey()); uni.setStorageSync(gateStorageKey(), 1); setTimeout(() => uni.pageScrollTo({ selector:'.action-card', duration:220 }), 50) }
async function verifyGroup(showSuccess = true) {
    if (!requireLogin()) return false
    normalizeGroupInput()
    if (!/^(?:\d{4}|\d{8})-[1-9]\d{0,2}$/.test(groupNo.value)) {
        groupError.value = '群编号格式不正确，请只填写群名称中的编号，例如 0819-1。'
        return false
    }
    groupError.value = ''
    verifying.value = true
    try {
        const res:any = await resolveProjectCenterGroup(projectId.value, groupNo.value)
        groupNo.value = String(res.data?.group_no || groupNo.value)
        groupValidated.value = !!res.data?.verified
        if (!groupValidated.value) throw new Error('群编号核对失败')
        groupError.value = ''
        uni.setStorageSync(groupStorageKey(), groupNo.value)
        // 只保存“已进入资料步骤”的本地流程位置；真正业务事实仍以服务端工单为准。
        uni.setStorageSync(gateStorageKey(), 1)
        await loadStatus()
        if (status.value.status === 'not_submitted') paymentDeclared.value = true
        if (showSuccess) uni.showToast({ title:'群编号已确认', icon:'success' })
        setTimeout(() => uni.pageScrollTo({ selector:'.form-area', duration:260 }), 80)
        return true
    } catch (e:any) {
        groupValidated.value = false
        const message = String(e?.msg || e?.message || '')
        groupError.value = message || '群编号暂时无法确认，请检查网络后重试；已保留本次输入。'
        return false
    } finally { verifying.value = false }
}
function previewPaymentQrcode() { if (!paymentQrcodeUrl.value) return; uni.previewImage({ current:paymentQrcodeUrl.value, urls:[paymentQrcodeUrl.value] }) }
async function submit() {
    submitError.value = ''
    normalizeGroupInput()
    if (!groupVerified.value || !groupNo.value) { uni.showToast({ title:'请先核对客户群编号', icon:'none' }); uni.pageScrollTo({ selector:'.action-card', duration:220 }); return }
    if (!requireLogin() || !formRef.value?.verify()) return
    subscribe.request('project_center_application_approved,project_center_application_rejected,project_center_refund_completed')
    submitting.value = true
    let submitStage = '校验资料'
    try {
        const formData = formRef.value.getData()
        if (status.value.status === 'rejected') {
            submitStage = '更新资料工单'
            await reviseProjectCenterApplication(projectId.value, {
                group_no: groupNo.value.trim(),
                value: formData.value,
                payment_declared: 1
            })
        } else {
            submitStage = '保存资料表单'
            const formRes:any = await addFormRecord(formData)
            const recordId = Number(formRes.data?.record_id || formRes.data?.id || formRes.data)
            if (!recordId) throw new Error('资料表单保存失败，未返回有效记录编号')
            submitStage = '创建审核工单'
            await submitProjectCenterApplication(projectId.value, { group_no:groupNo.value.trim(), form_record_id:recordId, payment_declared:1 })
        }
        formRef.value.clearStorage()
        uni.showToast({ title: status.value.status === 'rejected' ? '资料已重新提交' : '资料已提交', icon:'success' })
        await loadStatus()
        uni.pageScrollTo({ scrollTop:0, duration:280 })
    } catch(e:any) {
        const originalMessage = String(e?.msg || e?.data?.msg || e?.message || e?.errMsg || '').trim()
        const message = originalMessage || `${submitStage}失败，请检查网络后重试`
        submitError.value = `${submitStage}：${message}`
        uni.showModal({ title:'资料提交未完成', content:submitError.value, showCancel:false, confirmText:'知道了' })
    } finally {
        submitting.value = false
    }
}
function normalizeDiy(raw:any) {
    if (!raw || !Array.isArray(raw.value)) return { global:{}, value:[] }
    const data = JSON.parse(JSON.stringify(raw))
    data.global = data.global && typeof data.global === 'object' ? data.global : {}
    const runtimeBoard = Array.isArray(project.value?.income_board) ? project.value.income_board : []
    const runtimeDate = String(project.value?.income_date || '')
    data.value = data.value.map((item:any,index:number) => {
        const margin = { top:0, bottom:0, both:0, ...(item.margin || {}) }
        let pageStyle = ''
        if (item.pageStartBgColor) {
            pageStyle += item.pageEndBgColor
                ? `background:linear-gradient(${item.pageGradientAngle || '180deg'},${item.pageStartBgColor},${item.pageEndBgColor});`
                : `background-color:${item.pageStartBgColor};`
        }
        if (Number(margin.top) > 0) pageStyle += `padding-top:${Number(margin.top) * 2}rpx;`
        if (Number(margin.bottom) > 0) pageStyle += `padding-bottom:${Number(margin.bottom) * 2}rpx;`
        if (Number(margin.both) > 0) pageStyle += `padding-right:${Number(margin.both) * 2}rpx;padding-left:${Number(margin.both) * 2}rpx;`
        const runtimeData = item.componentName === 'ProjectCenterIncomeBoard'
            ? { runtimeBoard, runtimeDate }
            : (item.componentName === 'AiAssistantEntry' ? { assistantType:'project_center', assistantMode:'popup', projectId:projectId.value, groupNo:groupNo.value } : {})
        return { ...item, ...runtimeData, id:item.id || `project-diy-${index}`, margin, pageStyle, componentIsShow:true }
    })
    return data
}
watch(groupNo, (value) => {
    for (const item of (project.value?.intro_diy?.value || [])) {
        if (item?.componentName === 'AiAssistantEntry') item.groupNo = value
    }
})
onLoad((options:any) => { projectId.value = Number(options?.id || 0); groupNo.value = extractGroupNo(decodeURIComponent(options?.group_no || '') || String(uni.getStorageSync(groupStorageKey()) || '')); showGroupGate.value = Number(options?.gate || 0) === 1 || !!uni.getStorageSync(gateStorageKey()); if (!projectId.value) { uni.showToast({ title:'缺少项目参数', icon:'none' }); return } loadProject() })
onShow(() => { if (projectId.value && !loading.value) loadStatus() })
</script>

<style scoped>
.project-page{min-height:100vh;background:#f5f7fb;color:#26334d}.loading-wrap{padding:260rpx 0}.hero{position:relative;min-height:390rpx;overflow:hidden;background:linear-gradient(135deg,#214fd7,#5267ef)}.hero-cover{position:absolute;inset:0;width:100%;height:100%;opacity:.28}.hero-mask{position:absolute;inset:0;background:linear-gradient(135deg,rgba(22,54,170,.94),rgba(66,74,222,.82))}.hero-content{position:relative;padding:82rpx 32rpx 48rpx;color:#fff}.hero-eyebrow{font-size:22rpx;letter-spacing:4rpx;opacity:.7}.hero-title{margin-top:18rpx;font-size:48rpx;font-weight:700;line-height:1.25}.hero-subtitle{margin-top:15rpx;font-size:27rpx;line-height:42rpx;opacity:.9}.hero-tags{display:flex;flex-wrap:wrap;gap:12rpx;margin-top:30rpx}.hero-tags text{padding:9rpx 16rpx;border:1rpx solid rgba(255,255,255,.3);border-radius:30rpx;background:rgba(255,255,255,.12);font-size:22rpx}.content-card,.status-card,.issue-panel{margin:22rpx 24rpx 0;padding:28rpx;border-radius:24rpx;background:#fff;box-shadow:0 8rpx 26rpx rgba(40,56,95,.05)}.section-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:18rpx}.section-title{font-size:31rpx;font-weight:650;color:#26334d}.section-subtitle{margin-top:7rpx;font-size:23rpx;line-height:34rpx;color:#8a94a6}.section-badge,.live-dot{flex:none;padding:7rpx 14rpx;border-radius:30rpx;background:#eef4ff;color:#315cf5;font-size:21rpx}.intro-text{margin-top:24rpx;font-size:27rpx;line-height:48rpx;color:#475467;white-space:pre-wrap}.steps{margin-top:26rpx}.step{position:relative;display:flex;min-height:84rpx;gap:22rpx}.step-no{z-index:1;display:flex;width:46rpx;height:46rpx;flex:none;align-items:center;justify-content:center;border-radius:50%;background:#315cf5;color:#fff;font-size:23rpx;font-weight:700}.step-line{position:absolute;top:46rpx;left:22rpx;width:2rpx;height:42rpx;background:#dbe5ff}.step-text{padding-top:6rpx;font-size:27rpx;color:#344054}.income-card{background:linear-gradient(135deg,#fff,#f7f9ff)}.income-swiper{height:88rpx;margin-top:18rpx}.income-row{display:flex;height:88rpx;align-items:center;gap:18rpx}.income-rank{flex:none;color:#315cf5;font-size:22rpx;font-weight:700}.income-store{min-width:0;flex:1;overflow:hidden;color:#344054;font-size:26rpx;text-overflow:ellipsis;white-space:nowrap}.income-value{flex:none;color:#ef4b3f;font-size:29rpx;font-weight:700}.faq-item{border-bottom:1rpx solid #edf0f5}.faq-item:last-child{border-bottom:0}.faq-question{display:flex;align-items:center;justify-content:space-between;gap:20rpx;padding:25rpx 0;color:#344054;font-size:27rpx}.faq-answer{padding:0 0 24rpx;color:#667085;font-size:25rpx;line-height:41rpx}.status-card{display:flex;gap:20rpx}.status-icon{padding-top:2rpx}.status-title{font-size:30rpx;font-weight:650}.status-desc{margin-top:8rpx;color:#667085;font-size:24rpx;line-height:38rpx}.status-group{display:inline-block;margin-top:12rpx;padding:7rpx 13rpx;border-radius:20rpx;background:#f2f4f7;color:#475467;font-size:21rpx}.issue-panel{border:1rpx solid #fecdca;background:#fff8f7}.issue-row{display:flex;gap:18rpx;margin-top:22rpx}.issue-index{display:flex;width:40rpx;height:40rpx;flex:none;align-items:center;justify-content:center;border-radius:50%;background:#f04438;color:#fff;font-size:21rpx}.issue-label{font-size:26rpx;font-weight:600}.issue-message{margin-top:6rpx;color:#d92d20;font-size:24rpx;line-height:37rpx}.issue-example{margin-top:7rpx;color:#667085;font-size:23rpx;line-height:36rpx}.review-remark{margin-top:20rpx;padding:18rpx;border-radius:14rpx;background:#fff;color:#b42318;font-size:24rpx;line-height:38rpx}.group-input{margin-top:24rpx;padding:22rpx;border:1rpx solid #e4eaf3;border-radius:18rpx}.group-label{font-size:25rpx;font-weight:600}.group-input input{height:76rpx;margin-top:10rpx;border-bottom:1rpx solid #e4e7ec;font-size:31rpx;font-weight:650}.input-placeholder{color:#c0c7d2;font-weight:400}.group-tip{margin-top:12rpx;color:#98a2b3;font-size:21rpx;line-height:33rpx}.payment-declare{margin-top:22rpx;padding:24rpx;border-radius:20rpx;background:#f7f9fc}.declare-icon{float:left;margin-right:18rpx}.declare-main{min-height:80rpx}.declare-title{font-size:28rpx;font-weight:650}.declare-desc{margin-top:6rpx;color:#667085;font-size:23rpx;line-height:35rpx}.declare-amount{margin-top:18rpx;color:#f04438;font-size:38rpx;font-weight:700}.button-wrap,.submit-wrap{margin-top:24rpx}.declare-warning,.submit-tip{margin-top:14rpx;text-align:center;color:#98a2b3;font-size:21rpx;line-height:32rpx}.form-area{margin-top:22rpx}.form-ready{display:flex;align-items:center;gap:10rpx;padding:18rpx;border-radius:14rpx;background:#ecfdf3;color:#027a48;font-size:23rpx}.completed-card{padding:70rpx 30rpx;text-align:center}.completed-title{margin-top:18rpx;font-size:32rpx;font-weight:650}.completed-desc{margin-top:10rpx;color:#667085;font-size:24rpx;line-height:38rpx}.safe-bottom{height:calc(30rpx + env(safe-area-inset-bottom))}
.ai-fallback-wrap{margin:20rpx 24rpx}.project-diy-wrap{margin-top:22rpx;overflow:hidden;background:#fff}.group-option{display:flex;align-items:center;justify-content:space-between;gap:20rpx;margin-top:22rpx;padding:20rpx 22rpx;border-radius:18rpx;background:#f8fafc}.group-option-title{color:#344054;font-size:25rpx;font-weight:600}.group-option-desc{margin-top:5rpx;color:#98a2b3;font-size:21rpx;line-height:32rpx}
.group-input-error{border-color:#fda29b!important;background:#fffbfa!important}.group-error-message{display:flex;align-items:flex-start;gap:8rpx;margin-top:14rpx;padding:14rpx 16rpx;border-radius:12rpx;background:#fff1f0;color:#b42318;font-size:22rpx;line-height:34rpx}.group-error-message text{min-width:0;flex:1}
.hero{min-height:238rpx}.hero-content{padding:38rpx 32rpx 30rpx}.hero-title{margin-top:10rpx;font-size:40rpx}.hero-subtitle{margin-top:9rpx;font-size:24rpx;line-height:35rpx}.hero-tags{display:none}.action-card{padding:24rpx}.payment-steps{display:flex;align-items:center;justify-content:space-between;padding:0 4rpx 18rpx;border-bottom:1rpx solid #edf0f5}.payment-step{position:relative;display:flex;min-width:0;flex:1;align-items:center;justify-content:center;gap:8rpx;color:#98a2b3;font-size:22rpx}.payment-step:not(:last-child)::after{position:absolute;right:-16rpx;width:32rpx;height:2rpx;background:#e4e7ec;content:''}.payment-step .step-dot{display:flex;width:34rpx;height:34rpx;align-items:center;justify-content:center;border-radius:50%;background:#f2f4f7;color:#667085;font-size:19rpx;font-weight:700}.payment-step.active{color:#315cf5;font-weight:650}.payment-step.active .step-dot{background:#315cf5;color:#fff;box-shadow:0 6rpx 14rpx rgba(49,92,245,.2)}.payment-step.done{color:#12b76a}.payment-step.done .step-dot{background:#ecfdf3;color:#12b76a}.payment-panel{display:grid;grid-template-columns:174rpx minmax(0,1fr);gap:18rpx;margin-top:20rpx;padding:18rpx;border-radius:20rpx;background:linear-gradient(135deg,#f8faff,#f4f7ff)}.qrcode-thumb{position:relative;width:174rpx;height:174rpx;overflow:hidden;border:1rpx solid #e1e7f0;border-radius:18rpx;background:#fff;box-shadow:0 8rpx 20rpx rgba(31,53,109,.08)}.qrcode-thumb image{width:100%;height:100%}.qrcode-zoom{position:absolute;right:8rpx;bottom:8rpx;left:8rpx;display:flex;height:38rpx;align-items:center;justify-content:center;gap:5rpx;border-radius:20rpx;background:rgba(17,24,39,.72);color:#fff;font-size:19rpx}.qrcode-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8rpx;color:#98a2b3;font-size:20rpx}.payment-summary{min-width:0;padding-top:2rpx}.payment-summary-label{color:#667085;font-size:21rpx}.payment-amount{margin-top:2rpx;color:#f04438;font-size:35rpx;font-weight:750;line-height:48rpx}.payment-guide{margin-top:6rpx;color:#344054;font-size:22rpx;font-weight:600;line-height:32rpx}.payment-proof{margin-top:6rpx;color:#667085;font-size:20rpx;line-height:29rpx}.payment-tip-line{display:-webkit-box;grid-column:1/-1;overflow:hidden;padding:12rpx 14rpx;border-radius:12rpx;background:#fff;color:#667085;font-size:20rpx;line-height:29rpx;-webkit-box-orient:vertical;-webkit-line-clamp:2}.payment-panel .button-wrap,.payment-panel .declare-warning{grid-column:1/-1}.payment-panel .button-wrap{margin-top:0}.payment-panel .declare-warning{margin-top:-4rpx}.group-gate{margin-top:20rpx}.gate-title{display:flex;align-items:flex-start;justify-content:space-between;gap:16rpx}.back-payment{flex:none;padding:8rpx 0;color:#315cf5;font-size:21rpx}.compact-group-input{margin-top:18rpx;padding:18rpx 20rpx;border-color:#dbe4f3;background:#fbfcff}.group-label{display:flex;align-items:center;gap:9rpx}.required-mark{padding:3rpx 9rpx;border-radius:12rpx;background:#fff0ef;color:#f04438;font-size:18rpx;font-weight:500}.group-example{margin-top:8rpx;color:#667085;font-size:21rpx;line-height:31rpx}.group-example text{color:#315cf5;font-weight:700}.compact-group-input input{height:68rpx;margin-top:10rpx;font-size:29rpx}.verify-button{margin-top:16rpx}.group-required-tip{display:flex;align-items:center;justify-content:center;gap:8rpx;margin-top:12rpx;color:#667085;font-size:20rpx}.form-ready{flex-wrap:wrap}.form-ready text:nth-child(2){min-width:0;flex:1}.change-group{flex:none;color:#315cf5;font-weight:600}
.legacy-card{display:flex;align-items:flex-start;gap:18rpx;border:1rpx solid #fedf89;background:#fffaeb}.legacy-title{color:#93370d;font-size:27rpx;font-weight:650}.legacy-desc{margin-top:7rpx;color:#b54708;font-size:23rpx;line-height:36rpx}
.submit-error-panel{display:flex;align-items:flex-start;gap:12rpx;margin-top:18rpx;padding:17rpx 18rpx;border:1rpx solid #fecdca;border-radius:15rpx;background:#fff6f5;color:#b42318}.submit-error-panel>view{min-width:0;flex:1}.submit-error-title{font-size:23rpx;font-weight:650}.submit-error-message{margin-top:5rpx;font-size:21rpx;line-height:32rpx;word-break:break-all}
.income-swiper.page-scroll-priority{pointer-events:none;touch-action:pan-y}

/* 项目详情专属亮黄主题；不覆盖内嵌低代码组件自己的配色。 */
.project-page{background:#f7f6ef;color:#262626}
.hero{background:#fee502}
.hero-cover{opacity:.18;mix-blend-mode:multiply}
.hero-mask{background:linear-gradient(125deg,rgba(254,229,2,.97),rgba(255,240,88,.88))}
.hero-content{color:#181818}
.hero-eyebrow{font-weight:650;opacity:.62}
.hero-title{font-weight:750}
.content-card,.status-card,.issue-panel{box-shadow:0 8rpx 26rpx rgba(71,62,0,.06)}
.section-badge,.live-dot{background:#fff8bd;color:#6b5d00}
.step-no{background:#fee502;color:#181818;font-weight:750}
.step-line{background:#eee39a}
.income-card{border:1rpx solid #f3e99c;background:linear-gradient(135deg,#fff,#fffdf0)}
.income-rank{color:#756500}
.action-card{border:1rpx solid #f1e58a}
.payment-steps{border-bottom-color:#efebd2}
.payment-step:not(:last-child)::after{background:#e8e3c5}
.payment-step.active{color:#665900;font-weight:700}
.payment-step.active .step-dot{background:#fee502;color:#181818;box-shadow:0 6rpx 14rpx rgba(210,185,0,.26)}
.payment-panel{background:linear-gradient(135deg,#fffef5,#fff9c9)}
.qrcode-thumb{border-color:#eadf79;box-shadow:0 8rpx 20rpx rgba(94,80,0,.1)}
.qrcode-zoom{background:rgba(24,24,24,.76)}
.payment-tip-line{background:rgba(255,255,255,.78)}
.back-payment,.change-group{color:#756500;font-weight:700}
.compact-group-input{border-color:#e8de83;background:#fffef5}
.group-example text{color:#756500}
.gate-main{min-width:0;flex:1}
.gate-required-row{display:flex;align-items:center;gap:12rpx}
.gate-required-badge{flex:none;padding:5rpx 12rpx;border-radius:8rpx;background:#fee502;color:#181818;font-size:19rpx;font-weight:750;line-height:1.3}
.gate-lock-tip{display:flex;align-items:center;gap:7rpx;margin-top:10rpx;color:#665900;font-size:21rpx;line-height:30rpx}
.compact-group-input{border-width:2rpx;box-shadow:0 0 0 6rpx rgba(254,229,2,.12)}
.group-field-line{display:flex;height:74rpx;align-items:center;gap:12rpx;border-bottom:1rpx solid #e2d779}
.group-field-line input{min-width:0;height:74rpx;flex:1;margin-top:0;border-bottom:0;font-size:31rpx;font-weight:700;letter-spacing:1rpx}
.group-example{margin-top:13rpx}
</style>
