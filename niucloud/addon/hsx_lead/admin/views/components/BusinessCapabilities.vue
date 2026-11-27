<template>
    <section class="business-capabilities">
        <h3>商户可承接业务</h3
        ><p class="lead-muted"
            >可同时登记多种业务，逐项选择已核对的型号、品牌或运营商；声明能力不代表实物现货、价格或锁货承诺。</p
        >
        <el-alert
            v-if="!definitions.length"
            title="请先保存商户资料，再打开资料登记业务能力。若已有商户仍无选项，请核对配套后端。"
            type="info"
            :closable="false"
            show-icon
        />
        <el-collapse v-else>
            <el-collapse-item
                v-for="business in definitions"
                :key="business.key"
                :name="business.key"
                :title="`${business.name} · 已选${selectedIds(business.key).length}项${business.enabled ? '' : '（业务已暂停）'}`"
            >
                <el-select
                    :model-value="selectedIds(business.key)"
                    multiple
                    filterable
                    clearable
                    collapse-tags
                    collapse-tags-tooltip
                    :disabled="!business.enabled"
                    :placeholder="`选择可承接${businessDimensionLabels[business.dimension] || '需求'}`"
                    :aria-label="`${business.name}承接能力`"
                    @update:model-value="update(business.key,$event)"
                >
                    <el-option
                        v-for="option in optionsFor(business)"
                        :key="option.id"
                        :value="option.id"
                        :label="option.title"
                        :disabled="option.unavailable"
                        ><span>{{ option.title }}</span
                        ><small
                            v-if="option.path?.length"
                            class="capability-path"
                            >{{ option.path.slice(0,-1).join(' / ') }}</small
                        ></el-option
                    >
                </el-select>
                <p
                    class="lead-muted"
                    >{{ business.enabled ? '不勾选表示未登记本业务能力，不会视为全型号或全品类。' : '已暂停业务的能力保留但不可新增或编辑；恢复业务后可继续维护。' }}</p
                >
                <p
                    v-if="selectedIds(business.key).length"
                    class="capability-selected"
                    >{{ selectedNames(business).join('、') }}</p
                >
            </el-collapse-item>
        </el-collapse>
    </section>
</template>
<script setup lang="ts">
import { businessDimensionLabels } from '../../composables/business'
import type { Row } from '../../composables/useLeadUi'
const props=defineProps<{modelValue:Row[];definitions:Row[]}>()
const emit=defineEmits<{(event:'update:modelValue',value:Row[]):void}>()
function selectedIds(key:string):string[]{return props.modelValue.find(row=>row.business_type===key)?.option_ids||[]}
function optionsFor(business:Row):Row[]{const options=business.options||[],known=new Set(options.map((row:Row)=>row.id)),current=props.modelValue.find(row=>row.business_type===business.key);return [...options,...(current?.items||[]).filter((row:Row)=>!known.has(row.id)).map((row:Row)=>({...row,title:`${row.title}（目录已变化）`,unavailable:true}))]}
function selectedNames(business:Row){const lookup=new Map(optionsFor(business).map(row=>[row.id,row.title]));return selectedIds(business.key).map(id=>lookup.get(id)||`${id}（目录未返回）`)}
function update(key:string,ids:string[]){const rows=props.modelValue.filter(row=>row.business_type!==key);if(ids.length)rows.push({business_type:key,option_ids:ids});emit('update:modelValue',rows)}
</script>
<style scoped>
.business-capabilities{margin:20px 0;padding:18px 0;border-top:1px solid var(--el-border-color-lighter)}.business-capabilities h3{font-size:15px;margin:0 0 8px}.business-capabilities :deep(.el-select){width:100%}.business-capabilities :deep(.el-collapse){margin-top:12px}.capability-path{float:right;margin-left:12px;color:var(--el-text-color-secondary)}.capability-selected{line-height:1.7;overflow-wrap:anywhere;color:var(--el-text-color-regular);margin-top:8px;max-height:120px;overflow:auto}
</style>
