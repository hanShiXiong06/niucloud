<template>
    <section>
        <header class="coop-head"><h2>业务配置</h2><div class="coop-actions"><el-button :disabled="saving" @click="channelsVisible=true">ERP 登录渠道</el-button><el-button v-if="canViewNotice" :disabled="saving" @click="noticeVisible=true">通知渠道</el-button><el-button :icon="Refresh" :loading="loading" :disabled="saving" @click="reload">重新读取</el-button></div></header>
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="coop-error" />
        <el-skeleton v-if="loading&&!ready" :rows="8" animated />
        <template v-if="ready">
            <el-alert v-if="!form.fee_confirmed" title="平台费规则尚未确认，商户不能创建协作佣金支付单。请先核对下方比例和最低费用，再明确确认。" type="warning" show-icon closable class="coop-error" />
            <el-form label-position="top" class="coop-form" :disabled="saving||loading">
                <EntityMembershipSettings :model-value="form.membership_levels" :disabled="saving||loading" />
                <BusinessSettings :model-value="form.businesses" :presets="form.business_presets" :fee-percent="form.fee_percent" :fee-minimum="form.fee_minimum" :disabled="saving||loading" :can-edit-catalog="canEditCatalog" @catalog="openCatalog" @fee-change="form.fee_confirmed=false" />
                <h3 style="margin:24px 0 14px">通用业务与费用规则</h3>
                <section class="coop-surface" style="margin-bottom:14px"><el-form-item label="商户端显示 ERP 与库存参考"><el-switch v-model="form.erp_visible" /><p class="coop-form-note">关闭后隐藏授权、同步与库存参考入口，商户仍可手动登记交付能力。</p></el-form-item><el-collapse><el-collapse-item title="历史连接和自动同步如何处理？" name="erp-visibility"><p class="coop-muted">此开关只控制商户端可见性，不删除授权或库存，不更改后台同步。需要暂停采集时，请在「ERP 登录渠道」关闭对应渠道。</p></el-collapse-item></el-collapse></section>
                <section class="coop-surface"><div class="coop-section-caption"><h3>业务开关</h3><el-switch v-model="form.enabled" active-text="允许商户协作" /></div><el-form-item label="找客户／找门店进入时提示登录"><el-switch v-model="form.listing_login_required" /><p class="coop-form-note">默认关闭，访客可进入页面。开启后首次进入引导登录，仍可取消返回；发布、接单、付款及私人资料始终需要登录。</p></el-form-item><el-form-item label="发单与接单需有效主体会员"><el-switch v-model="form.publishing_required" /></el-form-item><el-collapse><el-collapse-item title="查看会员到期和服务商的适用范围" name="entity-scope"><p class="coop-muted">门店共享主体会员等级和有效期；未开通或到期仍可完善资料、查看历史、处理已接单结果和既有账款。服务商为独立人工审核的推广身份，不随购买主体会员自动开通。</p></el-collapse-item></el-collapse></section>

                <section class="coop-surface" style="margin-top:14px"><div class="coop-section-caption"><h3>成交付佣与平台维护费</h3><el-tag type="info" effect="plain">未成交不收费</el-tag></div><div class="coop-rule-grid"><el-form-item label="通用最低协作佣金（元）"><el-input-number v-model="form.commission_minimum" :min="0.01" :max="99999999.99" :precision="2" controls-position="right" @change="form.fee_confirmed=false" /><p class="coop-form-note">各业务可在上方单独设置，不要求填写成交额或利润。</p></el-form-item><el-form-item label="平台维护费比例（%）"><el-input-number v-model="form.fee_percent" :min="0" :max="99.99" :precision="2" controls-position="right" @change="form.fee_confirmed=false" /></el-form-item><el-form-item label="每笔最低平台维护费（元）"><el-input-number v-model="form.fee_minimum" :min="0" :max="99999999.99" :precision="2" controls-position="right" @change="form.fee_confirmed=false" /></el-form-item><el-form-item label="试算本笔付佣（元）"><el-input-number v-model="previewAmount" :min="0.01" :max="99999999.99" :precision="2" controls-position="right" /></el-form-item></div><div v-if="preview.valid" class="coop-readout">本笔付佣 {{ coopMoney(preview.amount) }} = 发布方净收益 {{ coopMoney(preview.net) }} + 平台维护费 {{ coopMoney(preview.fee) }}</div><el-alert v-else :title="preview.error" type="warning" :closable="false" show-icon /><el-collapse><el-collapse-item title="查看计算口径" name="fees"><p class="coop-muted">平台维护费取“本笔付佣 × 比例”与“最低平台维护费”中较高者，须低于本笔付佣；剩余金额归发布方。推广奖励另从平台收入中计算，不减少发布方净收益。以上只试算，不会付款。</p></el-collapse-item></el-collapse><el-checkbox v-model="form.fee_confirmed">我已核对金额分配，确认新支付单使用以上收费规则</el-checkbox></section>


                <section class="coop-surface" style="margin-top:14px"><div class="coop-section-caption"><h3>跟进提醒与库存有效性</h3></div><div class="coop-rule-grid"><el-form-item label="接单关注时限（分钟）"><el-input-number v-model="form.response_minutes" :min="0" :max="10080" :precision="0" controls-position="right" /><p class="coop-form-note">超过后供平台关注，不自动判定未成交或飞单。填0表示不设置此时限。</p></el-form-item><el-form-item label="联系客户关注时限（小时）"><el-input-number v-model="form.contact_hours" :min="0" :max="720" :precision="0" controls-position="right" /></el-form-item><el-form-item label="结果回填关注时限（小时）"><el-input-number v-model="form.review_hours" :min="0" :max="2160" :precision="0" controls-position="right" /><p class="coop-form-note">供客服筛选跟进，不据此自动停用门店。</p></el-form-item><el-form-item label="库存快照有效期（小时）"><el-input-number v-model="form.inventory_fresh_hours" :min="1" :max="168" :precision="0" controls-position="right" /><p class="coop-form-note">超期或来源状态不明的库存不作为可交付现货依据。</p></el-form-item></div></section>
            </el-form>
            <footer class="coop-config-footer"><p>只保存本站规则，不发起付款、通知、ERP同步或真实转账。</p><el-button type="primary" :loading="saving" :disabled="loading" @click="save">保存业务配置</el-button></footer>
        </template>
        <CatalogDrawer v-if="canEditCatalog" v-model="catalogVisible" :business-type="catalogBusiness" :business-name="catalogDefinition?.name" @changed="load" />
        <ProviderChannelsSettings v-model="channelsVisible" :can-edit="canManageChannels" />
        <NoticeSettings v-if="canViewNotice" v-model="noticeVisible" :can-edit="canEditNotice" />
    </section>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Refresh } from '@element-plus/icons-vue'
import useUserStore from '@/stores/modules/user'
import CatalogDrawer from '../components/CatalogDrawer.vue'
import ProviderChannelsSettings from '../components/ProviderChannelsSettings.vue'
import NoticeSettings from '../components/NoticeSettings.vue'
import BusinessSettings from './BusinessSettings.vue'
import EntityMembershipSettings from './EntityMembershipSettings.vue'
import { normalizeEntityLevels } from '../../composables/entity'
import { normalizeBusinesses, businessValidation, businessPayload, businessAmountCents, businessFeeRules, cooperationFeePreview } from '../../composables/business'
import { hasLeadPagePermission } from '../../composables/navigation'
import { getCooperationConfig, saveCooperationConfig } from '../../api/cooperation'
import { coopError, coopMoney, type CoopRow } from '../../composables/cooperation'
const loading=ref(false),saving=ref(false),ready=ref(false),error=ref(''),form=reactive<CoopRow>({});let sequence=0,disposed=false,baseline=''
const user=useUserStore(),catalogVisible=ref(false)
const catalogBusiness=ref('purchase'),catalogDefinition=computed(()=>form.businesses?.find((row:CoopRow)=>row.key===catalogBusiness.value))
const channelsVisible=ref(false),canManageChannels=computed(()=>hasLeadPagePermission(user,'hsx_lead_cooperation_config_save'))
const noticeVisible=ref(false),canViewNotice=computed(()=>hasLeadPagePermission(user,'hsx_lead_manage_notice_config'))
const canEditNotice=computed(()=>hasLeadPagePermission(user,'hsx_lead_manage_notice_config_save'))
const canEditCatalog=computed(()=>hasLeadPagePermission(user,'hsx_lead_catalog_items'))
const previewDraft=ref<number|null>(null),previewAmount=computed({get:()=>previewDraft.value??Number(form.commission_minimum||0),set:(value:number|null)=>{previewDraft.value=value}})
const preview=computed(()=>cooperationFeePreview(previewAmount.value,form.fee_percent,form.fee_minimum))
const configFields=['enabled','publishing_required','listing_login_required','erp_visible','fee_confirmed','commission_minimum','fee_percent','fee_minimum','response_minutes','contact_hours','review_hours','inventory_fresh_hours','demand_model_limit','businesses']
function normalize(data:CoopRow){data={erp_visible:true,...data};const result=JSON.parse(JSON.stringify(Object.fromEntries(configFields.filter(key=>key in data).map(key=>[key,data[key]]))));for(const field of ['enabled','publishing_required','listing_login_required','erp_visible','fee_confirmed'])result[field]=!!result[field];for(const field of ['commission_minimum','fee_percent','fee_minimum','response_minutes','contact_hours','review_hours','inventory_fresh_hours'])result[field]=Number(result[field]??0);result.membership_levels=normalizeEntityLevels(data.membership_levels);result.businesses=normalizeBusinesses(result.businesses);result.business_presets=normalizeBusinesses(data.business_presets);result.demand_model_limit=Number(result.demand_model_limit??5);return result}
function openCatalog(key:string){if(saving.value||loading.value||!canEditCatalog.value)return;if(JSON.stringify(form)!==baseline){ElMessage.warning('本页有未保存修改，请先保存业务配置，再查看或导入目录');return}catalogBusiness.value=key;catalogVisible.value=true}
async function load(){const token=++sequence;loading.value=true;error.value='';try{const result=await getCooperationConfig();if(token!==sequence||disposed)return;const data=normalize(result.data||{});Object.keys(form).forEach(key=>delete form[key]);Object.assign(form,data);baseline=JSON.stringify(form);ready.value=true}catch(cause){if(token===sequence&&!disposed)error.value=coopError(cause,'配置读取失败，请确认具备业务配置权限')}finally{if(token===sequence&&!disposed)loading.value=false}}
async function reload(){if(saving.value)return;if(ready.value&&JSON.stringify(form)!==baseline){try{await ElMessageBox.confirm('重新读取会覆盖本页尚未保存的修改，是否继续？','保留修改或重新读取',{confirmButtonText:'放弃修改并读取',cancelButtonText:'继续编辑',type:'warning'})}catch{return}}await load()}
function validation(){
    const businessError=businessValidation(form.businesses);if(businessError)return businessError
    for(const key of ['commission_minimum','fee_percent','fee_minimum'])if(businessAmountCents(form[key])===null)return'价格与费用请填写有效非负金额，最多两位小数'
    if(Number(form.commission_minimum)<0.01)return'通用最低协作佣金须大于0'
    if(Number(form.fee_percent)>=100||Number(form.fee_minimum)>=Number(form.commission_minimum))return'平台费比例须低于100%，最低平台费须小于通用最低佣金'
    for(const row of [{name:'通用规则',commission_minimum:form.commission_minimum},...form.businesses.filter((item:CoopRow)=>item.enabled)]){
        const rule=businessFeeRules(row,form)
        if(Number(rule.minimum)>=Number(row.commission_minimum))return`${row.name}：最低协作费用须高于最低平台维护费，确保发布方有可得收益`
        if(form.fee_confirmed&&!cooperationFeePreview(row.commission_minimum,rule.percent,rule.minimum).valid)return`${row.name}：按最低金额试算后平台费须低于本笔佣金`
    }
    return''
}
async function save(){if(saving.value||loading.value||!ready.value)return;error.value=validation();if(error.value)return;saving.value=true;try{const payload=JSON.parse(JSON.stringify(Object.fromEntries(configFields.map(key=>[key,form[key]]))));payload.businesses=businessPayload(form.businesses).map(row=>{const settings={...row};delete settings.options;delete settings.catalog_configured;return settings});for(const key of ['commission_minimum','fee_percent','fee_minimum'])payload[key]=Number(payload[key]).toFixed(2);const result=await saveCooperationConfig(payload);const saved=normalize(result.data||{});Object.assign(form,saved);baseline=JSON.stringify(form);ElMessage.success('本站业务配置已保存，已有线索与支付单快照不回写')}catch(cause){error.value=coopError(cause,'保存未完成，已保留全部修改')}finally{saving.value=false}}
onMounted(load);onBeforeUnmount(()=>{disposed=true;sequence++})
</script>
