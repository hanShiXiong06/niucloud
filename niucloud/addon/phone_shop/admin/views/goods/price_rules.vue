<template>
    <div class="main-container" v-loading="loading">
        <el-card shadow="never">
            <h2 class="text-lg font-semibold mb-4">基准售价与会员加价</h2>
            <el-alert v-if="loadFailed" title="规则加载失败，当前不能保存，请重新加载后操作。" type="error" :closable="false" class="mb-5"><el-button link @click="load">重新加载</el-button></el-alert>
            <el-alert title="员工只填最低销售价，系统计算普通客户及各会员售价。关闭后恢复手动定价；保存规则不会批量改动现有商品，也不改变已成交订单。" type="info" :closable="false" class="mb-5" />
            <el-form label-width="130px">
                <el-form-item label="自动加价"><el-switch v-model="form.enabled" :active-value="1" :inactive-value="0" /></el-form-item>
                <el-form-item label="基准会员等级">
                    <el-select v-model="form.base_level_no" placeholder="选择最高等级会员">
                        <el-option v-for="lv in levels" :key="lv.level_no" :value="lv.level_no" :label="lv.level_name" :disabled="Number(lv.growth) < highestGrowth" />
                    </el-select>
                    <span class="ml-3 text-xs text-gray-500">按成长值门槛判断，不按等级 ID 大小判断</span>
                </el-form-item>
            </el-form>
            <div v-for="lv in targets" :key="lv.level_no" class="rule-card">
                <div class="rule-card__head"><strong>{{ lv.level_name }}</strong><el-tag v-if="lv.level_no === form.base_level_no">基准价，不加价</el-tag></div>
                <template v-if="lv.level_no !== form.base_level_no">
                    <div class="rule-row"><span>默认加价</span><el-select v-model="form.rules[lv.level_no].type"><el-option label="固定金额（元）" value="fixed" /><el-option label="百分比（%）" value="percent" /></el-select><el-input-number v-model="form.rules[lv.level_no].value" :min="0" :precision="2" /></div>
                    <el-collapse><el-collapse-item title="按基准价区间设置（可选）">
                        <div v-for="(band, index) in form.rules[lv.level_no].bands" :key="index" class="rule-row">
                            <el-input-number v-model="band.min" :min="0" :precision="2" placeholder="含下限" /><span>至</span><el-input-number v-model="band.max" :min="0" :precision="2" placeholder="不含上限，空为不限" />
                            <el-select v-model="band.type"><el-option label="加固定金额" value="fixed" /><el-option label="加百分比" value="percent" /></el-select><el-input-number v-model="band.value" :min="0" :precision="2" /><el-button type="danger" link @click="form.rules[lv.level_no].bands.splice(index, 1)">移除</el-button>
                        </div>
                        <el-button link type="primary" @click="form.rules[lv.level_no].bands.push({min:0,max:null,type:'fixed',value:0})">添加区间</el-button>
                        <p class="text-xs text-gray-500">区间含下限、不含上限，不可重叠；未命中使用默认加价。例如 1000～3000 元 +200，基准 2000 → 售价 2200。</p>
                    </el-collapse-item></el-collapse>
                </template>
            </div>
            <el-alert v-if="!levels.length" title="请先在会员等级中配置并启用会员等级，再开启自动加价。" type="warning" :closable="false" />
            <el-button class="mt-5" type="primary" :disabled="loadFailed || loading" :loading="saving" @click="save">保存规则</el-button>
            <div class="mt-5"><strong>已保存规则试算</strong><div class="rule-row"><el-input-number v-model="sample" :min="0.01" :precision="2" /><el-button :disabled="loadFailed" :loading="previewing" @click="preview">试算</el-button></div><div class="rule-row"><el-tag v-for="row in prices" :key="row.level_no">{{ row.name }}：¥{{ row.price }}</el-tag></div></div>
        </el-card>
    </div>
</template>
<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { getTierPricing, saveTierPricing, previewTierPricing } from '@/addon/phone_shop/api/tier_pricing'
const loading = ref(true), saving = ref(false), levels = ref<any[]>([]), sample = ref(2000), prices = ref<any[]>([])
const loadFailed = ref(false), previewing = ref(false)
const form = reactive<any>({ enabled: 0, base_level_no: 0, rules: {} })
const highestGrowth = computed(() => Math.max(0, ...levels.value.map(v => Number(v.growth))))
const targets = computed(() => [{level_no:0,level_name:'普通客户（无会员等级）'}, ...levels.value])
async function load() {
    loading.value = true; loadFailed.value = false; prices.value = []
    try {
        const { data } = await getTierPricing(); levels.value = data.levels || []; Object.assign(form, data)
        targets.value.forEach(lv => { form.rules[lv.level_no] ||= {type:'fixed',value:0,bands:[]} })
    } catch (_) { loadFailed.value = true } finally { loading.value = false }
}
async function save() { if (loadFailed.value || saving.value) return; saving.value = true; try { await saveTierPricing(form); ElMessage.success('规则已保存；下次定价按新规则计算'); await load() } catch (_) { /* 请求层显示服务端原因，保留未保存输入。 */ } finally { saving.value = false } }
async function preview() { previewing.value = true; prices.value = []; try { const res = await previewTierPricing(sample.value); prices.value = res.data.prices || []; if (!res.data.enabled) ElMessage.info('自动加价未开启') } catch (_) { /* 请求层显示规则错误。 */ } finally { previewing.value = false } }
load()
</script>
<style scoped>
.rule-card{border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-top:12px}.rule-card__head{display:flex;gap:12px;align-items:center;margin-bottom:12px}.rule-row{display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin:10px 0}.rule-row .el-select{width:170px}
</style>
