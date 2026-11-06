<template>
    <section class="coop-surface business-settings">
        <div class="coop-section-caption"><div><h3>多业务与客户表单</h3><p class="coop-form-note">按业务维护需求、表单与收费；只影响之后新增的线索。</p></div><div class="business-toolbar"><el-tag effect="plain">{{ enabledCount }} 项已启用</el-tag><el-dropdown trigger="click" :disabled="disabled || modelValue.length >= 30" @command="choosePreset"><el-button :icon="Plus" :disabled="disabled || modelValue.length >= 30">新增业务</el-button><template #dropdown><el-dropdown-menu><el-dropdown-item v-for="preset in availablePresets" :key="preset.key" :command="preset.key">{{ preset.name }}</el-dropdown-item><el-dropdown-item command="custom" :divided="!!availablePresets.length">自定义业务</el-dropdown-item></el-dropdown-menu></template></el-dropdown></div></div>
        <el-empty v-if="!modelValue.length" description="未读取到业务定义，请核对配套后端并重新读取" :image-size="60" />
        <el-tabs v-else v-model="activeKey" class="business-tabs">
            <el-tab-pane v-for="business in modelValue" :key="business.key" :name="business.key" :label="business.name || '未命名业务'">
                <div class="business-heading"><strong>{{ business.name || '未命名业务' }}</strong><el-switch v-model="business.enabled" active-text="开放发单" inactive-text="暂停新发单" @change="emit('fee-change')" /></div>
                <div class="coop-rule-grid">
                    <el-form-item label="业务名称"><el-input v-model="business.name" maxlength="100" placeholder="客户和商户看到的名称" /></el-form-item>
                    <el-form-item label="需求维度"><el-select v-model="business.dimension"><el-option v-for="(label,key) in businessDimensionLabels" :key="key" :value="key" :label="label" /></el-select><p class="coop-form-note">调整维度不会清空目录，请同时核对现有选项是否适用。</p></el-form-item>
                    <el-form-item label="客户需求选择方式"><el-radio-group v-model="business.selection_mode"><el-radio value="single">单选</el-radio><el-radio value="multiple">多选</el-radio></el-radio-group></el-form-item>
                    <el-form-item label="每条线索最多选择项数"><el-input-number v-model="business.selection_limit" :min="0" :max="5000" :precision="0" controls-position="right" :disabled="business.selection_mode==='single'" /><p class="coop-form-note">{{ business.selection_mode==='single' ? '单选时仅能选1项；保留多选上限草稿。' : '填0不设业务数量上限，目录与单次请求最多5000项。' }}不限制商户声明能力数量。</p></el-form-item>
                    <el-form-item label="成交最低协作费用（元）"><el-input-number v-model="business.commission_minimum" :min="0.01" :max="99999999.99" :precision="2" controls-position="right" @change="emit('fee-change')" /><p class="coop-form-note">新线索保存时冻结此金额；未成交不收取协作费用。</p></el-form-item>
                    <el-form-item label="需求目录"><div class="business-directory-action"><span>{{ businessDimensionLabels[business.dimension] }} · {{ business.options.length ? `${business.options.length}项独立配置` : business.dimension==='model' ? '使用已有设备目录' : '尚未配置选项' }}</span><el-button :disabled="!canEditCatalog" @click="emit('catalog',business.key)">查看 / 导入三级目录</el-button></div><p class="coop-form-note">目录独立保存。请先保存本页修改，再打开目录导入。</p></el-form-item>
                </div>
                <div class="business-fee-rule">
                    <el-form-item label="平台维护费规则"><el-radio-group :model-value="business.fee_override" @update:model-value="setFeeOverride(business, $event)"><el-radio :value="false">使用通用规则</el-radio><el-radio :value="true">本业务单独设置</el-radio></el-radio-group></el-form-item>
                    <div v-if="business.fee_override" class="coop-rule-grid"><el-form-item label="平台维护费比例（%）"><el-input-number v-model="business.fee_percent" :min="0" :max="99.99" :precision="2" controls-position="right" @change="emit('fee-change')" /></el-form-item><el-form-item label="每笔最低平台维护费（元）"><el-input-number v-model="business.fee_minimum" :min="0" :max="99999999.99" :precision="2" controls-position="right" @change="emit('fee-change')" /></el-form-item></div>
                    <div v-if="feePreview(business).valid" class="business-fee-preview">按最低付佣 {{ money(business.commission_minimum) }} 试算：发布方 {{ money(feePreview(business).net) }}，平台 {{ money(feePreview(business).fee) }}<span v-if="!business.fee_override">（使用通用规则）</span></div><el-alert v-else :title="feePreview(business).error" type="warning" :closable="false" show-icon />
                </div>
                <el-collapse class="business-more">
                    <el-collapse-item :title="`补充表单字段 · ${business.fields.length}项`" name="fields">
                        <p class="coop-form-note">可添加文本、数字、下拉或上门地址。客户称呼、电话和省市区已包含，无需重复添加。字段标识启用后请保持稳定。</p>
                        <div v-for="(field,index) in business.fields" :key="index" class="business-field">
                            <div class="business-field-grid"><el-form-item :label="`字段${index+1}名称`"><el-input v-model="field.label" maxlength="100" placeholder="例如：故障描述" /></el-form-item><el-form-item label="字段标识"><el-input v-model="field.key" maxlength="16" placeholder="例如 fault_note" /></el-form-item><el-form-item label="字段类型"><el-select v-model="field.type"><el-option label="文本" value="text" /><el-option label="数字" value="number" /><el-option label="下拉选择" value="select" /><el-option label="上门地址" value="address" /></el-select></el-form-item><div class="business-field-actions"><el-checkbox v-model="field.required">必填</el-checkbox><el-button link type="danger" @click="business.fields.splice(index,1)">移除</el-button></div></div>
                            <el-form-item v-if="field.type!=='select'" label="输入框提示（选填）"><el-input v-model="field.placeholder" maxlength="120" :placeholder="field.type==='address' ? '例如：小区、街道或附近地标，无需精确门牌' : '例如：简述需要处理的问题'" /></el-form-item>
                            <div v-if="field.type==='select'" class="business-choices"><div v-for="(option,optionIndex) in field.options" :key="optionIndex" class="business-choice"><el-input v-model="option.value" placeholder="选项值（保持稳定）" aria-label="下拉选项值" maxlength="100" /><el-input v-model="option.label" placeholder="选项显示名称" aria-label="下拉选项名称" maxlength="100" /><el-button link type="danger" @click="field.options.splice(optionIndex,1)">移除</el-button></div><el-button :disabled="field.options.length>=100" @click="field.options.push({value:'',label:''})">添加下拉选项</el-button></div>
                        </div>
                        <el-button :disabled="business.fields.length>=20" @click="business.fields.push({key:'',label:'',type:'text',required:false,placeholder:'',options:[]})">添加补充字段</el-button>
                    </el-collapse-item>
                    <el-collapse-item v-if="business.dimension!=='model'" :title="`手工维护${businessDimensionLabels[business.dimension]}选项 · ${business.options.length}项`" name="options">
                        <p class="coop-form-note">稳定ID不要随名称调整；大量选项建议使用上方三级目录导入。这里只预览前100项，完整清单在目录中查看。</p>
                        <div v-for="(option,index) in business.options.slice(0,100)" :key="index" class="business-option"><el-input v-model="option.id" placeholder="唯一ID，如 operator_mobile" aria-label="目录稳定ID" maxlength="100" /><el-input v-model="option.title" :placeholder="`${businessDimensionLabels[business.dimension]}名称`" aria-label="目录选项名称" maxlength="200" /><el-button link type="danger" @click="business.options.splice(index,1)">移除</el-button></div>
                        <el-button :disabled="business.options.length>=100" @click="business.options.push({id:'',title:'',brand:'',category_name:'',path:[]})">添加{{ businessDimensionLabels[business.dimension] }}</el-button>
                    </el-collapse-item>
                    <el-collapse-item title="生效范围与已有线索" name="scope"><p class="coop-muted">业务标识 {{ business.key }} 不修改。暂停业务只阻止新发单和新配置能力，已有记录、待办和账目保留。业务名称、需求选项、补充表单和最低费用由线索快照展示，不用新规则重算旧单。</p></el-collapse-item>
                </el-collapse>
            </el-tab-pane>
        </el-tabs>
        <el-dialog v-model="adding" title="新增自定义业务" width="min(480px, 92vw)" :close-on-click-modal="false">
            <el-form label-position="top"><el-form-item label="业务名称"><el-input v-model="custom.name" maxlength="100" placeholder="商户与客户看到的名称" /></el-form-item><el-form-item label="业务标识"><el-input v-model="custom.key" maxlength="16" placeholder="例如 phone_clean" /><p class="coop-form-note">小写字母开头，支持数字和下划线；保存后保持不变。</p></el-form-item><el-form-item label="需求维度"><el-select v-model="custom.dimension"><el-option v-for="(label,key) in businessDimensionLabels" :key="key" :value="key" :label="label" /></el-select></el-form-item></el-form>
            <el-alert v-if="addError" :title="addError" type="error" :closable="false" show-icon />
            <template #footer><el-button @click="adding=false">取消</el-button><el-button type="primary" :disabled="disabled" @click="addCustom">添加并设置</el-button></template>
        </el-dialog>
    </section>
</template>
<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { addBusinessDraft, businessDimensionLabels, businessFeeRules, cooperationFeePreview } from '../../composables/business'
import type { CoopRow } from '../../composables/cooperation'
const props=defineProps<{modelValue:CoopRow[];canEditCatalog:boolean;presets?:CoopRow[];feePercent?:number;feeMinimum?:number;disabled?:boolean}>()
const emit=defineEmits<{(event:'catalog',key:string):void;(event:'fee-change'):void}>()
const activeKey=ref(''),enabledCount=computed(()=>props.modelValue.filter(row=>row.enabled).length)
const availablePresets=computed(()=>(props.presets||[]).filter(preset=>!props.modelValue.some(row=>row.key===preset.key)))
const adding=ref(false),addError=ref(''),custom=reactive({key:'',name:'',dimension:'model'})
const money=(value:unknown)=>`¥${Number(value||0).toFixed(2)}`
function feePreview(business:CoopRow){const rule=businessFeeRules(business,{fee_percent:props.feePercent,fee_minimum:props.feeMinimum});return cooperationFeePreview(business.commission_minimum,rule.percent,rule.minimum)}
function setFeeOverride(business:CoopRow,value:unknown){if(props.disabled)return;if(value===true&&!business.fee_override){business.fee_percent=Number(props.feePercent||0);business.fee_minimum=Number(props.feeMinimum||0)}business.fee_override=value===true;emit('fee-change')}
function append(definition:CoopRow){if(props.disabled)return false;const error=addBusinessDraft(props.modelValue,definition);if(error){addError.value=error;ElMessage.warning(error);return false}activeKey.value=definition.key;emit('fee-change');return true}
function choosePreset(key:string){if(props.disabled)return;addError.value='';if(key==='custom'){Object.assign(custom,{key:'',name:'',dimension:'model'});adding.value=true;return}const preset=availablePresets.value.find(row=>row.key===key);if(preset&&append(preset))ElMessage.success('已添加为暂停状态，请核对表单、费用后开启并保存')}
function addCustom(){if(append({...custom,key:custom.key.trim(),name:custom.name.trim(),enabled:false,selection_mode:'multiple',selection_limit:5,commission_minimum:100,fee_override:false,fields:[],options:[]}))adding.value=false}
watch(()=>props.modelValue,rows=>{if(!rows.some(row=>row.key===activeKey.value))activeKey.value=rows[0]?.key||''},{immediate:true})
</script>
<style scoped>
.business-toolbar{display:flex;align-items:center;gap:12px;flex-wrap:wrap}.business-fee-rule{padding:16px;border:1px solid var(--el-border-color-lighter);border-radius:8px;background:var(--el-fill-color-extra-light);margin-bottom:16px}.business-fee-preview{font-size:13px;line-height:1.7;color:var(--el-text-color-regular)}.business-fee-preview span{color:var(--el-text-color-secondary);margin-left:8px}.business-fee-rule :deep(.el-form-item:last-child){margin-bottom:12px}
.business-heading,.business-directory-action{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.business-heading{padding:4px 0 18px}.business-heading strong{font-size:16px}.business-directory-action{width:100%}.business-more{margin-top:8px}.business-field{padding:16px 0;border-top:1px solid var(--el-border-color-lighter)}.business-field-grid{display:grid;grid-template-columns:minmax(140px,1fr) minmax(130px,1fr) minmax(120px,.8fr) auto;gap:12px}.business-field-actions{display:flex;align-items:center;gap:12px}.business-choices{padding:0 0 12px}.business-choice,.business-option{display:grid;grid-template-columns:minmax(150px,1fr) minmax(150px,1fr) 50px;gap:12px;margin:10px 0}.business-more :deep(.el-collapse-item__header){font-weight:500}.business-settings :deep(.el-select){width:100%}.business-settings :deep(.el-form-item__content > .coop-form-note){flex-basis:100%;width:100%}@media(max-width:1280px){.business-field-grid{grid-template-columns:1fr 1fr}.business-field-actions{align-self:center}.business-directory-action{align-items:flex-start;flex-direction:column}}
</style>
