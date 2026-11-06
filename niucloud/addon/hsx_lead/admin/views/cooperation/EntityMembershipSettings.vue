<template>
    <section class="coop-surface entity-level-settings">
        <div class="coop-section-caption"><div><h3>主体会员等级</h3><p class="coop-muted">统一使用会员等级中的“商户主体权益”</p></div><el-button type="primary" plain :disabled="disabled" @click="router.push('/member/level')">管理会员等级</el-button></div>
        <el-alert v-if="modelValue.length && entryCount!==1" :title="entryCount===0?'尚未明确设置入门等级，请在会员等级中指定；其他等级仍按成长值门槛核验。':'入门等级配置不唯一，请在会员等级中核对。'" type="warning" :closable="false" show-icon class="entity-level-warning" />
        <el-empty v-if="!modelValue.length" description="尚未配置可用的商户主体权益" :image-size="64" />
        <HsxTable v-else :columns="columns" :data="modelValue" row-key="code">
            <template #name="{row}"><div class="coop-cell"><div class="entity-level-name"><span v-if="row.level_color" class="entity-level-color" :style="{backgroundColor:row.level_color}" aria-hidden="true" /><strong>{{ row.name }}</strong><el-tag v-if="row.is_entry" size="small" effect="plain">入门</el-tag></div><small>{{ row.code }}</small></div></template>
            <template #experience="{row}"><span>{{ experienceText(row) }}</span></template>
            <template #eligibility="{row}"><span class="coop-muted">{{ eligibilityText(row) }}</span></template>
            <template #accounts="{row}">{{ row.unlimited?'不限人数':row.account_limit+' 人' }}</template>
            <template #cycles="{row}"><div class="coop-tags"><el-tag v-for="cycle in row.cycles.filter(item=>item.enabled)" :key="cycle.key" effect="plain">{{ cycle.name }} · {{ cycle.months }}个月 · ¥{{ cycle.price }}</el-tag><span v-if="!row.cycles.some(item=>item.enabled)" class="coop-muted">未开启购买周期</span></div></template>
            <template #costs="{row}"><div class="coop-cell"><span>押金 ¥{{ row.deposit_amount }}</span><small>{{ row.experience_purchase_enabled ? '补足经验 ¥'+row.experience_unit_price+' / 点' : '未开放付费补足经验' }}</small></div></template>
            <template #capabilities="{row}"><span>{{ capabilityText(row) }}</span></template>
        </HsxTable>
        <el-collapse><el-collapse-item title="等级与经验门槛在哪里调整？" name="entity-level-rules"><p class="coop-muted">在“会员 → 会员等级”新增或编辑等级，页面顶部“成长值”同时定义框架个人成长和本插件升级门槛。仅明确打开“入门等级”的一档不设经验门槛；同一站点最多一个，关闭低档不会把高档自动变成入门。开启“商户主体权益”后设置账号名额、权限及购买周期。本页只读，不另存价格或门槛。</p><p class="coop-muted">门店经验来自承接门店实际支付的协作佣金，1元计1点，会员费不计，成功退款扣回；不向个人账户增加成长值。开通、升级、续费均按实时经验核验，符合条件后由商户自愿支付；退款扣经验不提前终止已生效权益。全站目录未指定门店时不判断具体资格，最终能否购买以实时服务端报价为准。账号名额包含负责人；服务商仍独立审核管理。</p></el-collapse-item></el-collapse>
    </section>
</template>
<script setup lang="ts">
import { useRouter } from 'vue-router'
import { computed } from 'vue'
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import { subjectCapabilityLabels, type EntityLevel } from '../../composables/entity'
const props=withDefaults(defineProps<{modelValue:EntityLevel[];disabled?:boolean}>(),{disabled:false})
const router=useRouter()
const entryCount=computed(()=>props.modelValue.filter(row=>row.is_entry).length)
const columns=[{prop:'name',label:'等级名称',slot:'name',minWidth:180},{prop:'experience_required',label:'经验门槛',slot:'experience',minWidth:155},{prop:'experience_eligible',label:'经验资格',slot:'eligibility',minWidth:160},{prop:'account_limit',label:'账号名额（含负责人）',slot:'accounts',minWidth:170},{prop:'cycles',label:'已开启周期 / 服务费',slot:'cycles',minWidth:260},{prop:'deposit_amount',label:'押金 / 补足经验',slot:'costs',minWidth:210},{prop:'capabilities',label:'协作权限',slot:'capabilities',minWidth:200}]
function experienceText(row:EntityLevel){return row.experience_required===null?'门槛待核对':row.is_entry&&row.experience_required==='0.00'?'不设经验门槛':row.experience_required+' 点'}
function eligibilityText(row:EntityLevel){return row.experience_eligible===true?'符合经验规则':row.experience_eligible===false?'暂不符合经验规则':'按门店经验核验'}
function capabilityText(row:EntityLevel){return Object.entries(subjectCapabilityLabels).filter(([key])=>row.capabilities[key]===true).map(([,label])=>label).join('、')||'未授予协作权限'}
</script>
<style scoped>
.entity-level-settings{margin-bottom:20px}.entity-level-settings>.el-collapse{margin-top:14px}.entity-level-settings .coop-section-caption{flex-wrap:wrap}.entity-level-settings .coop-tags{padding:6px 0}.entity-level-settings :deep(.cell){overflow-wrap:anywhere}
.entity-level-name{display:flex;align-items:center;flex-wrap:wrap;gap:6px}.entity-level-color{width:8px;height:8px;flex:none;border-radius:50%}.entity-level-warning{margin:0 0 14px}
</style>
