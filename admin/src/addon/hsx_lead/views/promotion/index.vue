<template>
    <PageShell permission="hsx_lead_promotion_manage">
        <header class="coop-head"><div><h2>推广管理</h2><p class="promotion-subtitle">普通邀请、服务商审核与奖励结算</p></div><div class="coop-actions"><el-button :icon="Refresh" :loading="activeLoading" @click="reloadActive">刷新当前页</el-button></div></header>
        <el-tabs v-model="tab" @tab-change="loadTab">
            <el-tab-pane v-if="canProviderRead" label="服务商" name="providers" />
            <el-tab-pane v-if="canRewards" label="奖励记录" name="rewards" />
            <el-tab-pane v-if="canConfigRead" label="推广规则" name="rules" />
        </el-tabs>

        <template v-if="tab==='providers'">
            <div class="promotion-filters"><HsxSearchInput v-model="providerQuery.keyword" placeholder="搜索会员、门店或手机号" class="promotion-keyword" :disabled="providerLoading" :show-button="false" @search="searchProviders" /><el-select v-model="providerQuery.application_status" clearable placeholder="全部申请状态" :disabled="providerLoading" aria-label="申请状态"><el-option v-for="item in applicationStatuses" :key="item.value" :label="item.label" :value="item.value" /></el-select><el-select v-model="providerQuery.role" clearable placeholder="全部推广身份" :disabled="providerLoading" aria-label="推广身份"><el-option label="普通会员" value="normal" /><el-option label="服务商" value="provider" /></el-select><el-button type="primary" :disabled="providerLoading" @click="searchProviders">查询</el-button><el-button :disabled="providerLoading" @click="showPendingProviders">查看待审核</el-button></div>
            <el-alert v-if="providerError" :title="providerError" type="error" :closable="false" show-icon class="coop-error" />
            <div class="coop-surface"><HsxTable :columns="providerColumns" :data="providers" :loading="providerLoading" row-key="member_id" empty-text="没有符合条件的会员，可调整搜索条件">
                <template #member="{row}"><div class="coop-cell"><strong>{{ row.merchant_name || row.name || '本站会员' }}</strong><small>{{ row.name }} · {{ row.mobile_masked || '未留电话' }}</small><small>{{ row.region_text || '门店资料待完善' }}</small></div></template>
                <template #qualification="{row}"><div class="coop-cell"><div class="promotion-badges"><el-tag :type="applicationTag(row)" effect="plain">{{ applicationLabel(row) }}</el-tag><el-tag type="info" effect="plain">{{ row.role==='provider'?'服务商档':'普通会员档' }}</el-tag></div><small>{{ row.city || row.application?.city || '未指定推广城市' }}</small><small v-if="applicationStatus(row)==='disabled'">{{ row.application?.disabled_mode==='blocked'?'已禁止新的推广奖励':'后续按普通会员规则参与推广' }}</small><small v-if="applicationStatus(row)==='pending'">申请于 {{ coopTime(row.application?.submitted_at) }}</small></div></template>
                <template #upstream="{row}"><div class="coop-cell"><strong>{{ row.upstream_name || '暂无邀请人' }}</strong><small>直属关系由框架会员归属确定</small></div></template>
                <template #action="{row}"><el-button link type="primary" @click="editProvider(row)">{{ canProviderReview&&applicationStatus(row)==='pending'?'审核申请':'查看详情' }}</el-button></template>
            </HsxTable><div class="coop-pagination"><HsxPagination :current-page="providerQuery.page" :page-size="providerQuery.limit" :total="providerTotal" :disabled="providerLoading" @change="providerPage" /></div></div>
        </template>

        <template v-else-if="tab==='rewards'">
            <div class="promotion-filters"><HsxSearchInput v-model="rewardQuery.keyword" class="promotion-keyword" placeholder="搜索推广人、来源会员或业务单号" :disabled="rewardLoading" :show-button="false" @search="searchRewards" /><el-select v-model="rewardQuery.status" clearable placeholder="全部奖励状态" :disabled="rewardLoading"><el-option v-for="item in statuses" :key="item.value" :label="item.label" :value="item.value" /></el-select><el-select v-model="rewardQuery.source" clearable placeholder="全部收益来源" :disabled="rewardLoading"><el-option v-for="item in sources" :key="item.key" :label="item.label" :value="item.key" /></el-select><el-date-picker v-model="rewardDates" type="datetimerange" value-format="X" start-placeholder="产生时间起" end-placeholder="产生时间止" :disabled="rewardLoading" /><el-button type="primary" :disabled="rewardLoading" @click="searchRewards">查询</el-button><el-button v-if="canSettle" :loading="settling" :disabled="rewardLoading || settling || holding || retrying>0" @click="settle">结算已到期奖励</el-button></div>
            <el-alert v-if="rewardError" :title="rewardError" type="error" :closable="false" show-icon class="coop-error" />
            <el-collapse v-if="paymentReviewTotal>0" v-model="reviewExpanded" class="promotion-explainer promotion-payment-reviews"><el-collapse-item :title="`待核对付款 · ${paymentReviewTotal} 笔`" name="payments"><HsxTable :columns="paymentReviewColumns" :data="paymentReviews" row-key="id"><template #business="{row}"><div class="coop-cell"><strong>{{ purposeLabel(row.purpose) }} · {{ coopMoney(row.amount) }}</strong><small>{{ row.order_no }}</small><small>已付款 {{ coopTime(row.pay_time) }}</small></div></template><template #issue="{row}"><span>{{ row.message || '推广奖励尚未核对完成' }}</span></template><template #action="{row}"><el-button v-if="canRetry&&row.can_retry" link type="primary" :disabled="settling||holding||retrying>0||retryReadRequired" :loading="retrying===Number(row.id)" @click="retryPayment(row)">{{ retryReadRequired?'请先刷新核对':'按原付款快照恢复' }}</el-button><span v-else class="coop-muted">{{ row.can_retry?'需结算与恢复权限':'需人工核对原单' }}</span></template></HsxTable><p class="coop-form-note">这里只列最近10笔待核对付款，不受下方奖励筛选影响；恢复不会重新扣款，也不按当前比例补算。没有原快照的付款需人工核对。</p></el-collapse-item></el-collapse>
            <div class="coop-surface"><HsxTable :columns="rewardColumns" :data="rewards" :loading="rewardLoading" row-key="id" empty-text="暂无符合条件的奖励记录">
                <template #people="{row}"><div class="coop-cell"><strong>{{ row.provider_name || '推广会员' }}</strong><small>来源：{{ row.payer_name || '本站会员' }}</small></div></template>
                <template #business="{row}"><div class="coop-cell"><strong>{{ sourceLabel(row.source, row.source_label) }}</strong><small>{{ row.order_no || row.source_order_no || '来源单据详见记录' }}</small></div></template>
                <template #amount="{row}"><div class="coop-cell"><strong>{{ coopMoney(row.amount) }}</strong><small>基数 {{ coopMoney(row.base_amount) }} × {{ row.rate }}%</small></div></template>
                <template #state="{row}"><div class="coop-cell"><el-tag :type="row.status==='settled'?'success':row.status==='held'?'warning':'info'" effect="plain">{{ row.status_label || statusLabel(row.status) }}</el-tag><small v-if="row.reason">{{ row.reason }}</small></div></template>
                <template #time="{row}"><div class="coop-cell"><small>产生 {{ coopTime(row.create_time) }}</small><small>可结算 {{ coopTime(row.available_at) }}</small></div></template>
                <template #action="{row}"><el-button v-if="canHold && row.status==='pending'" link type="warning" :disabled="holding || settling || retrying>0" @click="hold(row)">暂缓结算</el-button><el-button v-else-if="canResume && row.status==='held'" link type="primary" :disabled="holding || settling || retrying>0" @click="resume(row)">核实并恢复</el-button><span v-else class="coop-muted">{{ row.status==='settled' ? '已入会员佣金' : '保留记录' }}</span></template>
            </HsxTable><div class="coop-pagination"><HsxPagination :current-page="rewardQuery.page" :page-size="rewardQuery.limit" :total="rewardTotal" :disabled="rewardLoading" @change="rewardPage" /></div></div>
        </template>

        <template v-else>
            <el-alert v-if="configError" :title="configError" type="error" :closable="false" show-icon class="coop-error" />
            <el-alert v-if="configReady && configRequiresReview" :title="configReviewMessage" type="warning" :closable="false" show-icon class="coop-error" />
            <el-skeleton v-if="configLoading && !configReady" :rows="7" animated />
            <el-form v-if="configReady" label-position="top" class="coop-form" :disabled="configSaving || configLoading || !canConfigWrite">
                <section class="coop-surface"><div class="coop-section-caption"><h3>一级推广</h3><el-switch v-model="config.enabled" active-text="允许产生新的推广奖励" /></div><div class="coop-rule-grid"><el-form-item label="结算等待期（天）"><el-input-number v-model="config.settlement_days" :min="0" :max="365" :precision="0" controls-position="right" /></el-form-item><el-form-item label="获佣期限"><el-radio-group v-model="permanentEligibility"><el-radio :value="true">长期有效</el-radio><el-radio :value="false">指定月数</el-radio></el-radio-group><el-input-number v-if="!permanentEligibility" v-model="config.eligibility_months" :min="1" :max="120" :precision="0" controls-position="right" /></el-form-item></div></section>
                <section class="coop-surface promotion-rule-section"><div class="coop-section-caption"><h3>参与收入与默认比例</h3></div><p class="promotion-rule-intro">普通用户无需购买会员即可邀请得佣；服务商通过审核后，按服务商比例计算，不与会员比例叠加。</p><HsxTable :columns="ruleColumns" :data="sourceRows" row-key="key">
                    <template #source="{row}"><div class="coop-cell"><strong>{{ row.label }}</strong><small>{{ row.key==='maintenance'?'按平台维护费计算，不减少发布方净收益':'仅按实付服务费计算，不含押金、经验费' }}</small></div></template>
                    <template #enabled="{row}"><el-switch :model-value="row.enabled" @update:model-value="config.sources[row.key].enabled=$event" /></template>
                    <template #normal_rate="{row}"><el-input-number :model-value="row.normal_rate" :min="0" :max="100" :precision="2" controls-position="right" :disabled="!row.enabled" :aria-label="`${row.label}普通会员比例`" @update:model-value="config.sources[row.key].normal_rate=$event" /><span class="promotion-unit">%</span></template>
                    <template #provider_rate="{row}"><el-input-number :model-value="row.provider_rate" :min="0" :max="100" :precision="2" controls-position="right" :disabled="!row.enabled" :aria-label="`${row.label}服务商比例`" @update:model-value="config.sources[row.key].provider_rate=$event" /><span class="promotion-unit">%</span></template>
                    <template #renewal="{row}"><el-switch v-if="row.key!=='maintenance'" :model-value="row.renewal" :disabled="!row.enabled" active-text="续费也参与" @update:model-value="config.sources[row.key].renewal=$event" /><span v-else class="coop-muted">每笔符合条件的业务</span></template>
                </HsxTable></section>
                <section class="coop-surface promotion-rule-section">
                    <div class="coop-section-caption"><h3>会员等级分佣比例</h3><el-tag effect="plain" type="info">按邀请人等级</el-tag></div>
                    <p class="promotion-rule-intro">沿用普通比例，或为某一等级单独设置。服务商始终使用上方服务商比例。</p>
                    <HsxTable v-if="memberLevels.length" :columns="levelRateColumns" :data="levelRateRows" row-key="code">
                        <template #level="{row}"><div class="coop-cell"><strong>{{ row.name }}</strong><small>有效主体会员权益</small></div></template>
                        <template #membership="{row}"><div class="promotion-level-rate"><el-switch :model-value="row.membership.overridden" active-text="单独设置" inactive-text="沿用普通" :disabled="!config.sources.membership.enabled" :aria-label="`${row.name}会员服务费单独设置`" @update:model-value="setLevelOverride(row.code,'membership',$event)" /><div v-if="row.membership.overridden" class="promotion-level-rate-input"><el-input-number :model-value="row.membership.rate" :min="0" :max="100" :precision="2" controls-position="right" :disabled="!config.sources.membership.enabled" :aria-label="`${row.name}会员服务费推广比例`" @update:model-value="setLevelRate(row.code,'membership',$event)" /><span class="promotion-unit">%</span></div><span v-else class="coop-muted">当前 {{ row.membership.rate }}%</span></div></template>
                        <template #maintenance="{row}"><div class="promotion-level-rate"><el-switch :model-value="row.maintenance.overridden" active-text="单独设置" inactive-text="沿用普通" :disabled="!config.sources.maintenance.enabled" :aria-label="`${row.name}平台维护费单独设置`" @update:model-value="setLevelOverride(row.code,'maintenance',$event)" /><div v-if="row.maintenance.overridden" class="promotion-level-rate-input"><el-input-number :model-value="row.maintenance.rate" :min="0" :max="100" :precision="2" controls-position="right" :disabled="!config.sources.maintenance.enabled" :aria-label="`${row.name}平台维护费推广比例`" @update:model-value="setLevelRate(row.code,'maintenance',$event)" /><span class="promotion-unit">%</span></div><span v-else class="coop-muted">当前 {{ row.maintenance.rate }}%</span></div></template>
                    </HsxTable>
                    <el-empty v-else description="尚未配置可购买的主体会员等级" :image-size="64" />
                    <el-collapse class="promotion-level-help"><el-collapse-item title="等级未单独设置、已到期时，如何计算？" name="level-fallback"><p class="coop-form-note">未购买会员、会员已到期或该等级未单独配置时，沿用普通用户比例。单独设为0%表示该等级不参与该项分佣。停用等级后保留既有配置，不补算历史付款。</p></el-collapse-item></el-collapse>
                </section>
            </el-form>
            <section v-if="configReady" class="coop-surface promotion-trial"><div class="coop-section-caption"><h3>保存前金额试算</h3><el-tag effect="plain" type="info">只预览，不产生账目</el-tag></div><div class="promotion-trial-controls"><el-select v-model="trialSource" aria-label="试算收入来源"><el-option v-for="item in sources" :key="item.key" :label="item.label" :value="item.key" /></el-select><el-input-number v-model="trialAmounts[trialSource]" :min="0" :max="99999999.99" :precision="2" controls-position="right" :aria-label="trialSource==='maintenance'?'本笔平台维护费金额':'本笔会员服务费金额（不含押金、经验费）'" /><span class="coop-muted">{{ trialSource==='maintenance'?'元平台维护费（不是整笔协作佣金）':'元会员服务费（不含押金、经验费）' }}</span></div><el-alert v-if="trialError" :title="trialError" type="warning" :closable="false" show-icon /><template v-else><div v-if="!config.enabled||!config.sources[trialSource].enabled" class="promotion-trial-note">当前收入来源或推广总开关已关闭，不产生新的推广奖励。</div><HsxTable :columns="trialColumns" :data="trialRows" row-key="role"><template #identity="{row}"><div class="coop-cell"><strong>{{ row.label }}</strong><small v-if="row.inherited">沿用普通比例</small></div></template><template #rate="{row}">{{ row.rate }}%</template><template #reward="{row}"><strong class="promotion-money">{{ coopMoney(row.reward) }}</strong></template><template #retained="{row}">{{ coopMoney(row.retained) }}</template></HsxTable><p class="coop-form-note">每笔只按邀请人当时的一个身份计算，不叠加。参与收入 {{ coopMoney(trialAmounts[trialSource]) }} = 该档推广奖励 + 平台留存。</p></template></section>
            <el-collapse class="promotion-explainer"><el-collapse-item title="保存、结算和提现分别意味着什么？" name="rules"><div class="promotion-help">配置只影响新业务的奖励资格；历史记录保留付款时快照。结算仅将已到期、可核对的奖励记入框架会员佣金账户，不代替提现审核，不代表银行打款。若业务存在退款或争议，先暂缓待结算奖励并核对事实；已经入账的款项不能在这里假装撤回。</div></el-collapse-item></el-collapse>
            <footer v-if="configReady" class="coop-config-footer"><p>保存不会发起支付、结算或真实打款。</p><el-button v-if="canConfigWrite" type="primary" :loading="configSaving" :disabled="configLoading" @click="saveRules">保存推广规则</el-button><span v-else class="coop-muted">当前仅有查看权限</span></footer>
        </template>

        <HsxDrawer v-model="providerVisible" :title="providerMode==='manual'?'管理服务商资格':applicationStatus(selectedProvider)==='pending'?'服务商申请审核':'服务商资料'" :subtitle="selectedProvider.merchant_name || selectedProvider.name || ''" size="sm" :before-close="beforeProviderClose" :confirm-loading="providerSaving">
            <el-alert v-if="providerSaveError" :title="providerSaveError" type="error" :closable="false" show-icon class="coop-error" />
            <div class="promotion-application"><div class="promotion-badges"><el-tag :type="applicationTag(selectedProvider)">{{ applicationLabel(selectedProvider) }}</el-tag><el-tag effect="plain" type="info">{{ selectedProvider.role==='provider'?'服务商档':'普通会员档' }}</el-tag></div><dl><dt>申请门店</dt><dd>{{ selectedProvider.application?.merchant_name || selectedProvider.merchant_name || '未登记门店' }}</dd><dt>联系人</dt><dd>{{ selectedProvider.application?.contact_name || selectedProvider.name || '未填写' }}</dd><dt>联系电话</dt><dd>{{ selectedProvider.application?.mobile_masked || selectedProvider.mobile_masked || '未填写' }}</dd><dt>负责城市</dt><dd>{{ selectedProvider.application?.city || selectedProvider.city || '未填写' }}</dd><dt>申请时间</dt><dd>{{ coopTime(selectedProvider.application?.submitted_at) }}</dd><dt>处理时间</dt><dd>{{ coopTime(selectedProvider.application?.reviewed_at) }}</dd><template v-if="selectedProvider.application?.reason"><dt>上次处理说明</dt><dd>{{ selectedProvider.application.reason }}</dd></template></dl></div>
            <el-form label-position="top" :disabled="providerSaving||providerReadRequired">
                <template v-if="providerMode==='manual'"><el-form-item label="服务商资格"><el-radio-group v-model="providerForm.status"><el-radio value="approved">开通服务商</el-radio><el-radio value="disabled">停用服务商</el-radio></el-radio-group></el-form-item><el-form-item v-if="providerForm.status==='disabled'" label="停用后如何处理"><el-radio-group v-model="providerForm.disabled_mode" class="promotion-disabled-modes"><el-radio value="normal">降为普通会员，仍可按普通档推广</el-radio><el-radio value="blocked">禁止产生新的推广奖励</el-radio></el-radio-group></el-form-item><el-form-item v-else label="负责城市"><el-input v-model="providerForm.city" maxlength="120" placeholder="填写服务商负责的城市" :disabled="!!selectedProvider.application?.city" /></el-form-item><el-form-item label="后台备注（选填）"><el-input v-model="providerForm.remark" maxlength="1000" placeholder="长期管理备注，不向会员展示" /></el-form-item></template>
                <el-form-item v-if="providerMode==='manual'||(canProviderReview&&applicationStatus(selectedProvider)==='pending')" :label="providerMode==='manual'?'本次操作原因（必填）':'审核说明（拒绝时必填）'"><el-input v-model="providerForm.reason" type="textarea" :rows="3" maxlength="1000" :placeholder="providerMode==='manual'?'说明开通或停用的原因':'拒绝时请说明需要补充或调整的内容'" /></el-form-item>
            </el-form>
            <el-collapse class="promotion-drawer-help"><el-collapse-item title="查看对推广、历史收益的影响" name="scope"><p class="coop-muted">开通后，新业务按服务商档计算；停用后的新业务按所选处理方式执行。不改变上下级，也不补算历史奖励；既有奖励和已入账余额保留。服务商资格与靠谱商家认证是两回事。</p></el-collapse-item></el-collapse>
            <template #footer="{close}"><div class="promotion-drawer-actions"><el-button :disabled="providerSaving" @click="close">{{ providerReadRequired?'关闭后刷新列表':'取消' }}</el-button><template v-if="!providerReadRequired"><template v-if="canReviewSelected&&providerMode!=='manual'"><el-button :disabled="providerSaving" @click="reviewProvider('reject')">拒绝申请</el-button><el-button type="primary" :loading="providerSaving" @click="reviewProvider('approve')">通过申请</el-button></template><el-button v-else-if="providerMode==='manual'" type="primary" :loading="providerSaving" @click="saveProvider">保存资格</el-button><el-button v-else-if="canProviderWrite&&applicationStatus(selectedProvider)!=='pending'" type="primary" @click="providerMode='manual'">管理资格</el-button></template></div></template>
        </HsxDrawer>
    </PageShell>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Refresh } from '@element-plus/icons-vue'
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import HsxPagination from '@/addon/hsx_components/components/HsxPagination/index.vue'
import HsxDrawer from '@/addon/hsx_components/components/HsxDrawer/index.vue'
import HsxSearchInput from '@/addon/hsx_components/components/HsxSearchInput/index.vue'
import useUserStore from '@/stores/modules/user'
import PageShell from '../components/PageShell.vue'
import { hasLeadPagePermission } from '../../composables/navigation'
import { coopError, coopMoney, coopTime, type CoopRow } from '../../composables/cooperation'
import { getPromotionConfig, savePromotionConfig, getPromotionProviders, savePromotionProvider, reviewPromotionProvider, getPromotionRewards, settlePromotionRewards, holdPromotionReward, resumePromotionReward, retryPromotionPayment } from '../../api/promotion'
const user=useUserStore(),allows=(key:string)=>hasLeadPagePermission(user,key)
const canConfigRead=computed(()=>allows('hsx_lead_promotion_config')),canConfigWrite=computed(()=>allows('hsx_lead_promotion_config_save'))
const canProviderRead=computed(()=>allows('hsx_lead_promotion_providers')),canProviderWrite=computed(()=>allows('hsx_lead_promotion_providers_save'))
const canProviderReview=computed(()=>allows('hsx_lead_promotion_provider_review'))
const canRewards=computed(()=>allows('hsx_lead_promotion_rewards')),canSettle=computed(()=>allows('hsx_lead_promotion_settle')),canHold=computed(()=>allows('hsx_lead_promotion_hold')),canResume=computed(()=>allows('hsx_lead_promotion_resume'))
const canRetry=computed(()=>allows('hsx_lead_promotion_retry')&&canSettle.value)
const tab=ref('providers'),providers=ref<CoopRow[]>([]),providerTotal=ref(0),providerLoading=ref(false),providerError=ref(''),providerQuery=reactive<CoopRow>({keyword:'',application_status:'',role:'',page:1,limit:20})
const rewards=ref<CoopRow[]>([]),rewardTotal=ref(0),rewardLoading=ref(false),rewardError=ref(''),rewardQuery=reactive<CoopRow>({keyword:'',status:'',source:'',page:1,limit:20}),rewardDates=ref<string[]>([])
const paymentReviews=ref<CoopRow[]>([]),paymentReviewTotal=ref(0),reviewExpanded=ref<string[]>(['payments']),retrying=ref(0),retryReadRequired=ref(false)
const config=reactive<CoopRow>({sources:{}}),configReady=ref(false),configLoading=ref(false),configSaving=ref(false),configError=ref(''),settling=ref(false),holding=ref(false)
const configRequiresReview=ref(false),configReviewMessage=ref('')
const activeLoading=computed(()=>tab.value==='providers'?providerLoading.value:tab.value==='rewards'?rewardLoading.value:configLoading.value)
const sources=[{key:'membership',label:'会员服务费'},{key:'maintenance',label:'平台维护费'}]
const memberLevels=ref<CoopRow[]>([])
// Table cells must receive the edited fields as row data, not just static metadata plus an outer slot closure.
const sourceRows=computed(()=>sources.map(source=>({...source,...config.sources[source.key]})))
function levelRateState(code:string,source:string){const rule=config.sources[source],configured=rule.level_rates[code],overridden=configured!==undefined;return {overridden,rate:overridden?configured:rule.normal_rate}}
const levelRateRows=computed(()=>memberLevels.value.map(level=>({...level,membership:levelRateState(level.code,'membership'),maintenance:levelRateState(level.code,'maintenance')})))
function setLevelOverride(code:string,source:string,enabled:unknown){if(!memberLevels.value.some(level=>level.code===code)||!config.sources[source])return;const rule=config.sources[source];if(enabled===true){if(!Object.prototype.hasOwnProperty.call(rule.level_rates,code))rule.level_rates[code]=rule.normal_rate}else delete rule.level_rates[code]}
function setLevelRate(code:string,source:string,value:unknown){if(!memberLevels.value.some(level=>level.code===code)||!config.sources[source]||!Object.prototype.hasOwnProperty.call(config.sources[source].level_rates,code))return;config.sources[source].level_rates[code]=value}
function validRate(value:unknown){return cents(value)!==null&&Number(value)<=100}
function levelRateError(rule:CoopRow){return Object.values(rule.level_rates||{}).some(value=>!validRate(value))?'会员等级推广比例须为0—100%，最多两位小数':''}
const applicationStatuses=[{value:'pending',label:'待审核'},{value:'approved',label:'已开通'},{value:'rejected',label:'已拒绝'},{value:'disabled',label:'已停用'},{value:'none',label:'未申请'}]
const applicationStatus=(row:CoopRow)=>String(row.application?.status||row.provider?.status||'')
const applicationLabel=(row:CoopRow)=>String(row.application?.status_label||applicationStatuses.find(item=>item.value===applicationStatus(row))?.label||'状态待核对')
const applicationTag=(row:CoopRow)=>applicationStatus(row)==='approved'?'success':applicationStatus(row)==='pending'?'warning':applicationStatus(row)==='rejected'?'danger':'info'
const statuses=[{value:'pending',label:'待结算'},{value:'settled',label:'已入账'},{value:'held',label:'待核对'}]
const sourceLabel=(value:string,fallback='')=>sources.find(item=>item.key===value)?.label||fallback||'推广奖励',statusLabel=(value:string)=>statuses.find(item=>item.value===value)?.label||'状态待核对'
const purposeLabel=(value:string)=>(({membership:'门店会员付款',publishing:'入驻权益',certification:'靠谱认证',commission:'成交付佣'} as Record<string,string>)[value]||'业务付款')
const permanentEligibility=computed({get:()=>Number(config.eligibility_months)===0,set:(value:boolean)=>{config.eligibility_months=value?0:12}})
const trialSource=ref('membership'),trialAmounts=reactive<CoopRow>({membership:99,maintenance:9.9})
function cents(value: unknown): number | null {
    if ((typeof value !== 'number' && typeof value !== 'string') || !/^(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?$/.test(String(value))) return null
    const [whole, fraction = ''] = String(value).split('.')
    return Number(whole) * 100 + Number(fraction.padEnd(2, '0'))
}
const trialError = computed(() => {
    if (cents(trialAmounts[trialSource.value]) === null) return '请填写有效试算金额，最多两位小数'
    const rule = config.sources[trialSource.value]
    if (!rule) return '当前收入规则未读取完成'
    return ['normal_rate', 'provider_rate'].some(key => !validRate(rule[key])) ? '请填写0—100%的普通用户及服务商比例' : levelRateError(rule)
})
const trialRows = computed(() => {
    if (trialError.value) return []
    const base = cents(trialAmounts[trialSource.value])!, rule = config.sources[trialSource.value], enabled = config.enabled && rule.enabled
    // These are alternative inviter identities, never two recipients of the same payment.
    return [{role:'normal',label:'普通用户（无需付费）',rate:rule.normal_rate,inherited:false},{role:'provider',label:'审核通过的服务商',rate:rule.provider_rate,inherited:false},...memberLevels.value.map(level=>{const state=levelRateState(level.code,trialSource.value);return {role:level.code,label:level.name,rate:state.rate,inherited:!state.overridden}})].map(row => {
        const basis = cents(row.rate)!, reward = enabled ? Math.floor(base * basis / 10000) : 0
        return { ...row, rate: Number(row.rate).toFixed(2), reward: reward / 100, retained: (base - reward) / 100 }
    })
})
let disposed=false,providerSeq=0,rewardSeq=0,configSeq=0,configBaseline=''
const resumeReasons=new Map<number,string>()
async function loadProviders(){const token=++providerSeq;providerLoading.value=true;providerError.value='';try{const result=await getPromotionProviders({...providerQuery});if(disposed||token!==providerSeq)return;if(!Array.isArray(result.data?.data))throw new Error('未返回有效会员列表，请核对配套后端');providers.value=result.data.data;providerTotal.value=Number(result.data.total||0)}catch(cause){if(!disposed&&token===providerSeq)providerError.value=coopError(cause,'服务商读取失败')}finally{if(token===providerSeq)providerLoading.value=false}}
async function loadRewards(){const token=++rewardSeq;rewardLoading.value=true;rewardError.value='';try{const result=await getPromotionRewards({...rewardQuery,start_time:rewardDates.value?.[0]||'',end_time:rewardDates.value?.[1]||''});if(disposed||token!==rewardSeq)return;if(!Array.isArray(result.data?.data))throw new Error('未返回有效奖励列表，请核对配套后端');rewards.value=result.data.data;rewardTotal.value=Number(result.data.total||0);if(result.data.payment_reviews){if(!Array.isArray(result.data.payment_reviews.data))throw new Error('付款核对清单未完整返回，请刷新核对');paymentReviews.value=result.data.payment_reviews.data;paymentReviewTotal.value=Number(result.data.payment_reviews.total||0)}else{paymentReviews.value=[];paymentReviewTotal.value=0}retryReadRequired.value=false}catch(cause){if(!disposed&&token===rewardSeq)rewardError.value=coopError(cause,'奖励记录读取失败')}finally{if(token===rewardSeq)rewardLoading.value=false}}
function normalizeConfig(data:CoopRow){
    if(!data||typeof data.enabled!=='boolean'||!data.sources)throw new Error('未返回完整推广规则')
    const next:CoopRow={enabled:data.enabled,settlement_days:data.settlement_days,eligibility_months:data.eligibility_months,sources:{}}
    for(const item of sources){const rule=data.sources[item.key];if(!rule||typeof rule.enabled!=='boolean'||!['normal_rate','provider_rate'].every(key=>validRate(rule[key])))throw new Error('普通用户及服务商推广规则缺失或无效');const levelRates:CoopRow={};if(rule.level_rates!==undefined&&(rule.level_rates===null||typeof rule.level_rates!=='object'||(Array.isArray(rule.level_rates)&&rule.level_rates.length>0)))throw new Error('会员等级分佣规则格式无效');for(const [code,rate] of Object.entries(rule.level_rates||{})){if(!/^ML[1-9][0-9]*$/.test(code)||!validRate(rate))throw new Error('会员等级分佣规则缺失或无效');levelRates[code]=Number(rate)}next.sources[item.key]={enabled:rule.enabled,normal_rate:Number(rule.normal_rate),provider_rate:Number(rule.provider_rate),level_rates:levelRates,renewal:item.key==='maintenance'?true:rule.renewal===true}}
    return next
}
function applyConfigRead(data:CoopRow){
    const next=normalizeConfig(data)
    if(data.member_levels!==undefined&&!Array.isArray(data.member_levels))throw new Error('会员等级列表未完整返回，请刷新核对')
    const levels:CoopRow[]=[],seen=new Set<string>()
    for(const row of data.member_levels||[]){if(!row||!/^ML[1-9][0-9]*$/.test(String(row.code||''))||String(row.code)!==`ML${row.level_id}`||typeof row.name!=='string'||!row.name.trim()||seen.has(row.code))throw new Error('会员等级资料无效，请刷新核对');seen.add(row.code);levels.push({code:row.code,level_id:Number(row.level_id),name:row.name,display_rank:Number(row.display_rank||0)})}
    memberLevels.value=levels
    Object.assign(config,next)
    configRequiresReview.value=data.requires_review===true
    configReviewMessage.value=configRequiresReview.value?(typeof data.review_message==='string'&&data.review_message.trim()?data.review_message:'检测到旧版推广收入项，请核对会员服务费与平台维护费后保存；原配置未自动修改。'):''
    configBaseline=JSON.stringify(config);configReady.value=true
}
async function loadConfig(){const token=++configSeq;configLoading.value=true;configError.value='';try{const result=await getPromotionConfig();if(disposed||token!==configSeq)return;applyConfigRead(result.data)}catch(cause){if(!disposed&&token===configSeq)configError.value=coopError(cause,'推广规则读取失败')}finally{if(token===configSeq)configLoading.value=false}}
function searchProviders(){providerQuery.page=1;return loadProviders()}function searchRewards(){rewardQuery.page=1;return loadRewards()}function providerPage(value:{page:number;limit:number}){Object.assign(providerQuery,value);return loadProviders()}function rewardPage(value:{page:number;limit:number}){Object.assign(rewardQuery,value);return loadRewards()}
function showPendingProviders(){Object.assign(providerQuery,{application_status:'pending',role:'',page:1});return loadProviders()}
function loadTab(){if(tab.value==='providers'&&canProviderRead.value)return loadProviders();if(tab.value==='rewards'&&canRewards.value)return loadRewards();if(tab.value==='rules'&&canConfigRead.value&&!configReady.value)return loadConfig()}
async function reloadActive(){if(tab.value==='rules'&&configReady.value&&JSON.stringify(config)!==configBaseline){try{await ElMessageBox.confirm('重新读取会覆盖尚未保存的推广规则，是否继续？','保留修改或重新读取',{confirmButtonText:'放弃修改并读取',cancelButtonText:'继续编辑',type:'warning'})}catch{return}}if(tab.value==='rules')await loadConfig();else await loadTab()}
async function saveRules(){
    if(configSaving.value||!canConfigWrite.value||!configReady.value)return
    configError.value=''
    for(const item of sources){const rule=config.sources[item.key];if(!rule||!['normal_rate','provider_rate'].every(key=>validRate(rule[key]))){configError.value='普通用户及服务商推广比例须为0—100%，最多两位小数';return}const issue=levelRateError(rule);if(issue){configError.value=issue;return}}
    configSaving.value=true
    try{
        if(configRequiresReview.value){try{await ElMessageBox.confirm('请核对会员服务费与平台维护费的开关、普通用户、会员等级、服务商比例及续费规则。保存后只影响新业务，不改动历史付款或奖励记录。','确认当前推广规则',{confirmButtonText:'已核对，保存当前规则',cancelButtonText:'继续核对',type:'warning'})}catch{return}}
        // Explicitly write only the current two-source contract. Read-only server
        // metadata or obsolete keys must not hitchhike on the configuration PUT.
        const payload:CoopRow={enabled:config.enabled,settlement_days:config.settlement_days,eligibility_months:config.eligibility_months,sources:{}}
        for(const item of sources){const rule=config.sources[item.key];payload.sources[item.key]={enabled:rule.enabled,normal_rate:Number(rule.normal_rate).toFixed(2),provider_rate:Number(rule.provider_rate).toFixed(2),level_rates:Object.fromEntries(Object.entries(rule.level_rates||{}).map(([code,rate])=>[code,Number(rate).toFixed(2)])),renewal:item.key==='maintenance'?true:rule.renewal===true}}
        const result=await savePromotionConfig(payload);applyConfigRead(result.data);ElMessage.success('推广规则已保存，只影响新的奖励资格')
    }catch(cause){configError.value=coopError(cause,'保存未完成，已保留填写内容')}finally{configSaving.value=false}
}
const providerVisible=ref(false),selectedProvider=ref<CoopRow>({}),providerSaving=ref(false),providerSaveError=ref(''),providerReadRequired=ref(false),providerMode=ref<'view'|'manual'>('view')
const providerForm=reactive({status:'approved',disabled_mode:'normal',city:'',reason:'',remark:''})
const canReviewSelected=computed(()=>canProviderReview.value&&applicationStatus(selectedProvider.value)==='pending'&&!providerReadRequired.value)
let providerBaseline=''
function editProvider(row:CoopRow){if(providerSaving.value)return;selectedProvider.value=JSON.parse(JSON.stringify(row));Object.assign(providerForm,{status:row.enabled?'approved':'disabled',disabled_mode:row.application?.disabled_mode==='blocked'?'blocked':'normal',city:String(row.application?.city||row.city||''),reason:'',remark:String(row.remark||'')});providerBaseline=JSON.stringify(providerForm);providerMode.value='view';providerSaveError.value='';providerReadRequired.value=false;providerVisible.value=true}
function selectedVersion():number|null{const value=selectedProvider.value.application?.version;if((typeof value!=='number'&&typeof value!=='string')||!/^(?:0|[1-9][0-9]{0,9})$/.test(String(value))||Number(value)>=2147483647)return null;return Number(value)}
async function beforeProviderClose(done:()=>void){if(providerSaving.value)return;if(JSON.stringify(providerForm)!==providerBaseline){try{await ElMessageBox.confirm('本次填写尚未保存，关闭将放弃这些修改。是否关闭？','保留填写或关闭',{confirmButtonText:'放弃填写并关闭',cancelButtonText:'继续填写',type:'warning'})}catch{return}}const refresh=providerReadRequired.value;done();if(refresh)void loadProviders()}
function verifyProviderResult(data:CoopRow,status:string,version:number){const approved=status==='approved';if(Number(data?.member_id)!==Number(selectedProvider.value.member_id)||data?.application?.status!==status||Number(data?.application?.version)!==version+1||data?.enabled!==approved||data?.role!==(approved?'provider':'normal')||(status==='disabled'&&data?.application?.disabled_mode!==providerForm.disabled_mode))throw new Error('资格处理结果未完整返回，请关闭并刷新列表核对，不要重复提交');selectedProvider.value=JSON.parse(JSON.stringify(data));providers.value=providers.value.map(row=>Number(row.member_id)===Number(data.member_id)?data:row);providerBaseline=JSON.stringify(providerForm);providerVisible.value=false}
async function reviewProvider(decision:'approve'|'reject'){
    if(providerSaving.value||!canReviewSelected.value)return
    providerSaveError.value=''
    if(decision==='reject'&&!providerForm.reason.trim()){providerSaveError.value='请填写拒绝原因，让申请人知道需要如何调整';return}
    const version=selectedVersion();if(version===null){providerSaveError.value='未取得有效申请版本，请关闭并重新读取列表';providerReadRequired.value=true;return}
    providerSaving.value=true
    try{await ElMessageBox.confirm(decision==='approve'?'通过后，该会员后续符合条件的新业务按服务商档计算奖励，不补算历史奖励。':'拒绝后仍保留普通会员身份；申请人可看到拒绝原因，并在调整后重新申请。',decision==='approve'?'通过服务商申请':'拒绝服务商申请',{confirmButtonText:decision==='approve'?'确认通过':'确认拒绝',cancelButtonText:'继续核对',type:decision==='approve'?'info':'warning'})}catch{providerSaving.value=false;return}
    try{const result=await reviewPromotionProvider(Number(selectedProvider.value.member_id),{decision,version,reason:providerForm.reason.trim()});verifyProviderResult(result.data,decision==='approve'?'approved':'rejected',version);ElMessage.success(decision==='approve'?'已通过服务商申请':'已拒绝申请并保存原因');await loadProviders()}catch(cause){providerReadRequired.value=true;providerSaveError.value=coopError(cause,'处理结果待核对，请关闭并刷新列表，已保留填写内容')}finally{providerSaving.value=false}
}
async function saveProvider(){
    if(providerSaving.value||!canProviderWrite.value||providerMode.value!=='manual'||providerReadRequired.value||applicationStatus(selectedProvider.value)==='pending')return
    providerSaveError.value=''
    if(!providerForm.reason.trim()){providerSaveError.value='请填写本次开通或停用的原因';return}
    if(providerForm.status==='approved'&&!providerForm.city.trim()){providerSaveError.value='请填写服务商负责的城市';return}
    const version=selectedVersion();if(version===null){providerSaveError.value='未取得有效资格版本，请关闭并重新读取列表';providerReadRequired.value=true;return}
    providerSaving.value=true
    const consequence=providerForm.status==='approved'?'后续符合条件的新业务按服务商档计算。':providerForm.disabled_mode==='blocked'?'此会员将不再产生新的推广奖励，也不能通过重新申请绕过。':'后续符合条件的新业务按普通会员档计算。'
    try{await ElMessageBox.confirm(`${consequence}既有关系、奖励和已入账余额保留，不自动打款。`,'确认调整推广资格',{confirmButtonText:'确认保存资格',cancelButtonText:'继续编辑',type:'warning'})}catch{providerSaving.value=false;return}
    try{const result=await savePromotionProvider(Number(selectedProvider.value.member_id),{...providerForm,version});verifyProviderResult(result.data,providerForm.status,version);ElMessage.success('服务商资格已保存');await loadProviders()}catch(cause){providerReadRequired.value=true;providerSaveError.value=coopError(cause,'保存结果待核对，请关闭并刷新列表，已保留填写内容')}finally{providerSaving.value=false}
}
async function retryPayment(row: CoopRow) {
    if (!canRetry.value || !row.can_retry || retrying.value || retryReadRequired.value || settling.value || holding.value) return
    retrying.value = Number(row.id)
    try {
        await ElMessageBox.confirm('仅按这笔已付款订单的原推广快照核对和恢复奖励，不重新扣款、不使用当前比例；原单退款或快照缺失时不能恢复。', '恢复待核对推广付款', { confirmButtonText: '确认按原单恢复', cancelButtonText: '取消', type: 'warning' })
    } catch { retrying.value = 0; return }
    rewardError.value = ''
    try {
        const result = await retryPromotionPayment(Number(row.id))
        if (Number(result.data?.order_id) !== Number(row.id) || result.data?.status !== 'paid' || result.data?.promotion_review_required !== false || typeof result.data?.reward_created !== 'boolean') throw new Error('恢复结果未完整返回，请刷新待核对付款，勿重复操作')
        paymentReviews.value = paymentReviews.value.filter(item => Number(item.id) !== Number(row.id))
        paymentReviewTotal.value = Math.max(0, paymentReviewTotal.value - 1)
        ElMessage.success(result.data.reward_created ? '原单推广奖励已恢复，仍需按到期规则结算' : '原单已核对完成，没有重复生成奖励')
        await loadRewards()
    } catch (cause) {
        retryReadRequired.value = true
        rewardError.value = coopError(cause, '恢复结果待核对，请先刷新原单，不要重复操作')
    } finally { retrying.value = 0 }
}
async function settle(){if(settling.value||holding.value||!canSettle.value)return;try{await ElMessageBox.confirm('仅处理已到期且未冻结的推广奖励，每批最多50条。核实通过后记入会员佣金账户；不会自动提现或向银行转账。','结算已到期奖励',{confirmButtonText:'确认结算本批',cancelButtonText:'取消',type:'warning'})}catch{return}settling.value=true;rewardError.value='';try{const result=await settlePromotionRewards();if(!result.data||!['settled','held'].every(key=>/^(?:0|[1-9][0-9]*)$/.test(String(result.data[key]))))throw new Error('结算响应未完整返回，请先刷新原奖励记录核对，勿重复操作');ElMessage.success(`本批已入账 ${Number(result.data.settled)} 条，待核对 ${Number(result.data.held)} 条`);await loadRewards()}catch(cause){rewardError.value=coopError(cause,'结算结果待核对，请先刷新记录，不要重复操作')}finally{settling.value=false}}
async function hold(row:CoopRow){if(holding.value||settling.value||!canHold.value)return;holding.value=true;let reason='';try{const result=await ElMessageBox.prompt('只暂缓这笔尚未入账的奖励，不会撤回已入会员账户的钱。请记录需要核对的事实。','暂缓结算',{inputType:'textarea',inputPattern:/\S.{3,}/,inputErrorMessage:'请填写至少4个字符的原因',confirmButtonText:'确认暂缓',cancelButtonText:'取消'});reason=result.value}catch{holding.value=false;return}try{const result=await holdPromotionReward(Number(row.id),reason);if(Number(result.data?.id)!==Number(row.id)||!['held','settled'].includes(result.data?.status))throw new Error('暂缓响应未完整返回，请刷新原记录核对，勿重复操作');if(result.data.status==='settled')ElMessage.warning('奖励已入账，本次只记录核对事项，没有扣回会员佣金');else ElMessage.success('已暂缓，核实后可从本页恢复待结算');await loadRewards()}catch(cause){rewardError.value=coopError(cause,'暂缓未完成，请刷新核对')}finally{holding.value=false}}
async function resume(row:CoopRow){if(holding.value||settling.value||!canResume.value||row.status!=='held')return;holding.value=true;let reason='';try{const result=await ElMessageBox.prompt('请先核实原付款、暂停原因及退款情况。恢复后按原金额、比例、上级和到期时间重新参与结算；已到期的奖励可能在下一批处理中记入会员佣金，不代表银行打款。','核实并恢复待结算',{inputType:'textarea',inputValue:resumeReasons.get(Number(row.id))||'',inputPattern:/\S.{3,}/,inputErrorMessage:'请填写至少4个字符的核实结论与恢复理由',confirmButtonText:'确认恢复待结算',cancelButtonText:'取消'});reason=result.value;resumeReasons.set(Number(row.id),reason)}catch{holding.value=false;return}rewardError.value='';try{const result=await resumePromotionReward(Number(row.id),reason);if(Number(result.data?.id)!==Number(row.id)||result.data?.status!=='pending')throw new Error('恢复结果未完整返回，请先刷新原奖励记录核对，勿重复操作');resumeReasons.delete(Number(row.id));ElMessage.success('已恢复待结算，将按原规则参与下一批到期结算');await loadRewards()}catch(cause){rewardError.value=coopError(cause,'恢复未完成，请先刷新记录核对，已保留核实理由')}finally{holding.value=false}}
const providerColumns=[{prop:'member_id',label:'会员 / 商户',slot:'member',minWidth:220},{prop:'enabled',label:'申请状态 / 推广身份',slot:'qualification',minWidth:240},{prop:'upstream_name',label:'邀请归属',slot:'upstream',minWidth:190},{prop:'action',label:'操作',slot:'action',width:140,fixed:'right' as const}]
const rewardColumns=[{prop:'provider_name',label:'推广人 / 来源会员',slot:'people',minWidth:190},{prop:'source',label:'参与业务',slot:'business',minWidth:210},{prop:'amount',label:'奖励 / 计算快照',slot:'amount',minWidth:180},{prop:'status',label:'状态',slot:'state',minWidth:170},{prop:'create_time',label:'产生 / 可结算时间',slot:'time',minWidth:230},{prop:'action',label:'操作',slot:'action',width:130,fixed:'right' as const}]
const ruleColumns=[{prop:'label',label:'收入来源',slot:'source',minWidth:240},{prop:'enabled',label:'参与推广',slot:'enabled',width:110},{prop:'normal_rate',label:'普通用户比例（无需付费）',slot:'normal_rate',minWidth:230},{prop:'provider_rate',label:'服务商独立比例',slot:'provider_rate',minWidth:200},{prop:'renewal',label:'续费规则',slot:'renewal',minWidth:180}]
const levelRateColumns=[{prop:'name',label:'邀请人会员等级',slot:'level',minWidth:180},{prop:'membership',label:'会员服务费分佣',slot:'membership',minWidth:320},{prop:'maintenance',label:'平台维护费分佣',slot:'maintenance',minWidth:320}]
const trialColumns=[{prop:'role',label:'邀请人身份',slot:'identity',minWidth:150},{prop:'rate',label:'本档比例',slot:'rate',minWidth:130},{prop:'reward',label:'推广奖励',slot:'reward',minWidth:160},{prop:'retained',label:'平台留存',slot:'retained',minWidth:160}]
const paymentReviewColumns=[{prop:'order_no',label:'待核对付款',slot:'business',minWidth:230},{prop:'message',label:'核对事项',slot:'issue',minWidth:250},{prop:'action',label:'处理',slot:'action',width:190}]
onMounted(()=>{tab.value=canConfigRead.value?'rules':canProviderRead.value?'providers':canRewards.value?'rewards':'none';void loadTab()});onBeforeUnmount(()=>{disposed=true;providerSeq++;rewardSeq++;configSeq++})
</script>
<style scoped>
.promotion-subtitle{margin-top:8px;font-size:13px;color:#8a96a8}.promotion-filters{display:flex;align-items:center;flex-wrap:wrap;gap:12px;padding:20px;background:#fff;border:1px solid #e8edf4;border-radius:10px;margin:8px 0 18px}.promotion-filters .promotion-keyword{width:300px;max-width:100%}.promotion-filters .el-select{width:180px}.promotion-filters .el-date-editor{max-width:100%;width:340px}.promotion-rule-section{margin-top:16px}.promotion-unit{padding-left:8px;color:#8a96a8}.promotion-explainer{margin-top:16px;background:#fff;padding:0 20px;border:1px solid #e8edf4;border-radius:10px}.promotion-help{font-size:13px;color:#7f8da2;line-height:1.9;max-width:1000px}@media(max-width:1280px){.promotion-filters{padding:16px;gap:10px}.promotion-filters .promotion-keyword{width:260px}}
.promotion-badges{display:flex;align-items:center;flex-wrap:wrap;gap:6px}.promotion-trial{margin-top:16px}.promotion-trial-controls{display:flex;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px}.promotion-trial-controls .el-select{width:190px}.promotion-trial-note{padding:10px 0;color:var(--el-text-color-secondary);font-size:13px}.promotion-money{color:var(--el-color-primary)}.promotion-application{padding-bottom:12px}.promotion-application dl{display:grid;grid-template-columns:100px minmax(0,1fr);gap:14px 12px;margin:22px 0}.promotion-application dt{color:var(--el-text-color-secondary)}.promotion-application dd{margin:0;overflow-wrap:anywhere;line-height:1.5}.promotion-disabled-modes{display:flex;align-items:flex-start;flex-direction:column;gap:8px}.promotion-disabled-modes :deep(.el-radio){height:auto;white-space:normal;line-height:1.5}.promotion-drawer-help{margin-top:16px}.promotion-drawer-actions{display:flex;align-items:center;justify-content:flex-end;flex-wrap:wrap;gap:8px}.promotion-drawer-actions .el-button+.el-button{margin-left:0}.promotion-payment-reviews{margin:0 0 18px}
.promotion-rule-intro{margin:0 0 16px;color:var(--el-text-color-secondary);font-size:13px;line-height:1.7}.promotion-level-rate{display:flex;flex-direction:column;align-items:flex-start;gap:8px;padding:6px 0}.promotion-level-rate-input{display:flex;align-items:center}.promotion-level-help{margin-top:16px;border-bottom:0}.promotion-level-help :deep(.el-collapse-item__header){font-size:13px;color:var(--el-text-color-secondary)}
</style>
