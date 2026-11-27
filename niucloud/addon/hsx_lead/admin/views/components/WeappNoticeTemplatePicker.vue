<template>
    <div class="weapp-template-picker">
        <el-button type="primary" plain @click="open">{{ canEdit ? '从微信官方模板库选择' : '查看微信官方模板库' }}</el-button>
        <p class="lead-muted">只读取本站小程序已开通类目的真实模板；应用后仅回填当前草稿，不获取私有模板、不启用或保存。</p>
        <el-dialog v-model="visible" title="选择微信官方公共模板与关键词" width="min(920px, calc(100vw - 28px))" append-to-body :close-on-click-modal="false">
            <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="picker-banner" />
            <div class="picker-category">
                <el-select v-model="categoryId" aria-label="小程序服务类目" placeholder="先选择本站已开通的服务类目" :loading="categoryLoading" :disabled="categoryLoading" @change="changeCategory">
                    <el-option v-for="item in categories" :key="item.id" :label="item.name" :value="item.id" />
                </el-select>
                <el-button :loading="categoryLoading" :disabled="titleLoading || keywordLoading" @click="loadCategories">重新读取类目</el-button>
            </div>
            <el-empty v-if="!categoryLoading && !categories.length" description="本站未返回可用类目，请核对小程序配置、服务类目和接口权限" :image-size="65" />
            <div v-if="categoryId" class="picker-columns">
                <section class="picker-titles" v-loading="titleLoading">
                    <h4>1. 选择模板标题</h4>
                    <el-input v-model="titleFilter" aria-label="当前页模板标题搜索" placeholder="筛选当前页标题，可翻页继续查找" clearable />
                    <p class="lead-muted">每页最多 {{ limit }} 条；标题筛选仅针对当前页。</p>
                    <div class="picker-title-list">
                        <button v-for="item in filteredTitles" :key="item.tid" type="button" class="picker-title" :class="{ 'is-selected': selectedTitle?.tid === item.tid }" @click="loadKeywords(item)">
                            <strong>{{ item.title }}</strong><span>公共模板 {{ item.tid }}</span>
                        </button>
                        <el-empty v-if="!titleLoading && !filteredTitles.length" :description="titles.length ? '当前页没有匹配标题，可清空筛选或翻页' : '此类目暂未返回公共模板'" :image-size="50" />
                    </div>
                    <div class="picker-pagination"><el-button :disabled="titleLoading || start === 0" @click="loadTitles(start - limit)">上一页</el-button><span>第 {{ Math.floor(start / limit) + 1 }} 页 / 共 {{ total }} 条</span><el-button :disabled="titleLoading || start + limit >= total || start + limit > 9999" @click="loadTitles(start + limit)">下一页</el-button></div>
                </section>
                <section class="picker-keywords" v-loading="keywordLoading">
                    <h4>2. 选择真实关键词与通知变量</h4>
                    <template v-if="selectedTitle">
                        <strong>{{ selectedTitle.title }}</strong><p class="lead-muted">公共模板 {{ selectedTitle.tid }}；勾选需要的关键词，最多 10 项。语义建议请逐项核对，变量不能重复。</p>
                        <div v-for="item in keywords" :key="item.kid" class="picker-keyword" :class="{ 'is-unsupported': !item.supported }">
                            <el-checkbox v-model="item.selected" :disabled="!canEdit || !item.supported || (!item.selected && selectedKeywords.length >= 10)" @change="keywordSelected(item)">{{ item.name }}（{{ item.rule || '未知类型' }} / {{ item.kid }}）</el-checkbox>
                            <p v-if="item.example" class="lead-muted">官方示例：{{ item.example }}</p>
                            <el-select v-if="item.supported" v-model="item.variable" aria-label="关键词通知变量" placeholder="核对或选择安全通知变量" :disabled="!canEdit || !item.selected">
                                <el-option v-for="variable in variableOptions(item)" :key="variable.key" :label="variable.label" :value="variable.key" :disabled="selectedKeywords.some(row => row !== item && row.variable === variable.key)" />
                            </el-select>
                            <p v-else class="picker-warning">此官方字段类型暂不支持安全映射，只展示，不可选择。</p>
                        </div>
                        <el-empty v-if="!keywordLoading && !keywords.length" description="此模板未返回关键词，请重试或选择其他模板" :image-size="50" />
                        <el-form label-position="top"><el-form-item label="使用场景（保存前仍需核对确认）"><el-input v-model="sceneDesc" maxlength="100" show-word-limit :disabled="!canEdit" /></el-form-item></el-form>
                        <p v-if="selectionError" class="picker-warning">{{ selectionError }}</p>
                    </template>
                    <el-empty v-else description="选择左侧标题后，再读取该模板的真实关键词" :image-size="65" />
                </section>
            </div>
            <template #footer><div class="picker-footer"><span class="lead-muted">不会保存配置、获取私有模板或发送消息</span><div><el-button @click="visible = false">取消</el-button><el-button type="primary" :disabled="!canEdit || categoryLoading || titleLoading || keywordLoading || !!selectionError" @click="applySelection">应用到当前草稿</el-button></div></div></template>
        </el-dialog>
    </div>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { getNoticeTemplateLibrary } from '../../api'
import { errorText, type Row } from '../../composables/useLeadUi'
import { NOTICE_FIELD_PATTERN, noticeVariableOptions, suggestNoticeVariable } from '../../composables/noticeFields'
const props = withDefaults(defineProps<{ canEdit?: boolean; variables: Row[]; sceneName?: string; requestLibrary?: (params: Row) => Promise<any>; fieldPattern?: RegExp; variableOptions?: (keyword: unknown, variables: Row[]) => Row[]; suggestVariable?: (field: Row, variables: Row[]) => string }>(), { canEdit: false, sceneName: '商户工单提醒' })
const emit = defineEmits<{ (e: 'select', value: Row): void }>()
const visible = ref(false), error = ref(''), categoryLoading = ref(false), titleLoading = ref(false), keywordLoading = ref(false)
const categories = ref<Row[]>([]), categoryId = ref<number | ''>(''), titles = ref<Row[]>([]), titleFilter = ref(''), selectedTitle = ref<Row | null>(null), keywords = ref<Row[]>([]), sceneDesc = ref('')
const start = ref(0), limit = 30, total = ref(0)
let session = 0, titleRequest = 0, keywordRequest = 0
const fieldPattern = props.fieldPattern || NOTICE_FIELD_PATTERN
const readLibrary = (params: Row) => props.requestLibrary ? props.requestLibrary(params) : getNoticeTemplateLibrary(params)
const filteredTitles = computed(() => titles.value.filter(item => String(item.title).toLocaleLowerCase().includes(titleFilter.value.trim().toLocaleLowerCase())))
const selectedKeywords = computed(() => keywords.value.filter(item => item.selected))
function variableOptions(item: Row) {
    return props.variableOptions ? props.variableOptions(item.keyword, props.variables) : noticeVariableOptions(item.keyword, props.variables)
}
function keywordSelected(item: Row) {
    if (!props.canEdit || !item.supported) { item.selected = false; return }
    if (item.selected && selectedKeywords.value.some(row => row !== item && row.variable === item.variable)) item.variable = ''
}
const selectionError = computed(() => {
    if (!selectedTitle.value || !/^[1-9]\d{0,15}$/.test(String(selectedTitle.value.tid))) return '请先读取并选择一个真实公共模板'
    if (!selectedKeywords.value.length || selectedKeywords.value.length > 10) return '请选择 1—10 个支持的关键词'
    const kids = new Set<number>(), variables = new Set<string>()
    for (const item of selectedKeywords.value) {
        const match = String(item.keyword).match(fieldPattern)
        if (!item.supported || !match || Number(match[2]) !== Number(item.kid) || !String(item.name).trim()) return '所选关键词格式不受支持，请重新读取'
        if (kids.has(Number(item.kid)) || variables.has(item.variable)) return '关键词和通知变量都不能重复，请逐项核对'
        if (!variableOptions(item).some(variable => variable.key === item.variable)) return '请为每个关键词选择符合类型的安全通知变量'
        kids.add(Number(item.kid)); variables.add(item.variable)
    }
    return sceneDesc.value.trim() ? '' : '请填写本阶段的使用场景'
})
async function open() { visible.value = true; await loadCategories() }
function resetTitles() { titleRequest++; keywordRequest++; titles.value = []; total.value = 0; start.value = 0; selectedTitle.value = null; keywords.value = []; titleLoading.value = false; keywordLoading.value = false }
async function loadCategories() {
    if (!visible.value) return
    const token = ++session; resetTitles(); categoryId.value = ''; categories.value = []; error.value = ''; categoryLoading.value = true
    try {
        const result = await readLibrary({ operation: 'categories' })
        if (token !== session || !visible.value) return
        if (result.data?.operation !== 'categories' || !Array.isArray(result.data.items)) throw Error('模板库返回不完整，请核对接口权限后重试')
        categories.value = result.data.items.filter((item: Row) => Number.isInteger(Number(item.id)) && Number(item.id) > 0 && typeof item.name === 'string' && item.name.trim()).map((item: Row) => ({ id: Number(item.id), name: item.name }))
        if (!categories.value.length) error.value = '本站未返回可用服务类目，请核对小程序配置、已开通类目及接口权限；没有创建模拟模板。'
    } catch (cause) { if (token === session) error.value = errorText(cause, '微信官方模板库读取失败，请核对本站小程序配置和接口权限。') }
    finally { if (token === session) categoryLoading.value = false }
}
async function changeCategory(value: number | '') { categoryId.value = value; titleFilter.value = ''; await loadTitles(0) }
async function loadTitles(offset = 0) {
    if (!visible.value || !categories.value.some(item => item.id === categoryId.value)) return
    const token = ++titleRequest, currentSession = session; keywordRequest++; selectedTitle.value = null; keywords.value = []; keywordLoading.value = false; error.value = ''; titleLoading.value = true; titles.value = []
    try {
        const result = await readLibrary({ operation: 'titles', category_id: categoryId.value, start: Math.max(0, offset), limit })
        if (token !== titleRequest || currentSession !== session || !visible.value) return
        if (result.data?.operation !== 'titles' || !Array.isArray(result.data.items)) throw Error('模板标题返回不完整，请重新读取')
        titles.value = result.data.items.filter((item: Row) => /^[1-9]\d{0,15}$/.test(String(item.tid)) && typeof item.title === 'string' && item.title.trim()).map((item: Row) => ({ ...item, tid: String(item.tid) }))
        start.value = Math.max(0, offset); total.value = Math.max(0, Number(result.data.total) || 0)
    } catch (cause) { if (token === titleRequest && currentSession === session) error.value = errorText(cause, '官方模板标题读取失败，请重新选择类目或重试。') }
    finally { if (token === titleRequest && currentSession === session) titleLoading.value = false }
}
async function loadKeywords(title: Row) {
    if (!visible.value || !titles.value.some(item => item.tid === title.tid)) return
    const token = ++keywordRequest, currentSession = session; selectedTitle.value = { ...title }; keywords.value = []; error.value = ''; keywordLoading.value = true; sceneDesc.value = props.sceneName
    try {
        const result = await readLibrary({ operation: 'keywords', tid: title.tid })
        if (token !== keywordRequest || currentSession !== session || !visible.value) return
        if (result.data?.operation !== 'keywords' || !Array.isArray(result.data.items) || String(result.data.tid) !== title.tid) throw Error('模板关键词与所选标题不对应，请重新读取')
        const seen = new Set<number>()
        keywords.value = result.data.items.filter((item: Row) => Number.isInteger(Number(item.kid)) && Number(item.kid) > 0 && !seen.has(Number(item.kid)) && !!seen.add(Number(item.kid))).map((item: Row) => {
            const match = String(item.keyword || '').match(fieldPattern)
            const row = { ...item, kid: Number(item.kid), selected: false, supported: item.supported === true && !!match && match[1] === item.rule && Number(match[2]) === Number(item.kid), variable: '' }
            if (props.variableOptions && !variableOptions(row).length) row.supported = false
            row.variable = row.supported ? (props.suggestVariable ? props.suggestVariable(row, props.variables) : suggestNoticeVariable(row, props.variables)) : ''; return row
        })
    } catch (cause) { if (token === keywordRequest && currentSession === session) error.value = errorText(cause, '官方关键词读取失败，本次没有回填草稿。') }
    finally { if (token === keywordRequest && currentSession === session) keywordLoading.value = false }
}
function applySelection() {
    if (!props.canEdit || !visible.value || categoryLoading.value || titleLoading.value || keywordLoading.value || selectionError.value) return
    emit('select', { tid: String(selectedTitle.value!.tid), kid_list_text: selectedKeywords.value.map(item => item.kid).join(','), scene_desc: sceneDesc.value.trim(), fields: selectedKeywords.value.map(item => ({ label: String(item.name).slice(0, 30), keyword: item.keyword, variable: item.variable })) })
    visible.value = false
}
function close() { session++; titleRequest++; keywordRequest++; categoryLoading.value = false; titleLoading.value = false; keywordLoading.value = false }
watch(visible, value => { if (!value) close() }, { flush: 'sync' })
onBeforeUnmount(close)
</script>
<style scoped>
.weapp-template-picker > p { margin: 8px 0 16px; line-height: 1.7; }
.picker-banner { margin-bottom: 14px; }
.picker-category, .picker-pagination, .picker-footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.picker-category > .el-select { flex: 1; }
.picker-columns { display: grid; grid-template-columns: minmax(260px, .85fr) minmax(0, 1.15fr); gap: 22px; margin-top: 18px; }
.picker-columns h4 { margin: 0 0 12px; }
.picker-columns p { line-height: 1.65; margin: 7px 0 12px; }
.picker-title-list { min-height: 160px; max-height: 430px; overflow: auto; }
.picker-title { display: flex; flex-direction: column; width: 100%; text-align: left; background: var(--el-fill-color-blank); color: var(--el-text-color-primary); border: 1px solid var(--el-border-color-light); border-radius: 8px; padding: 12px; margin-bottom: 8px; cursor: pointer; gap: 6px; }
.picker-title span { font-size: 12px; color: var(--el-text-color-secondary); }
.picker-title.is-selected { border-color: var(--el-color-primary); background: var(--el-color-primary-light-9); }
.picker-pagination { margin-top: 12px; font-size: 12px; }
.picker-keywords { border-left: 1px solid var(--el-border-color-light); padding-left: 22px; }
.picker-keyword { margin-bottom: 12px; padding: 8px 10px; background: var(--el-fill-color-lighter); border-radius: 7px; }
.picker-keyword .el-select { width: 100%; }
.picker-keyword.is-unsupported { opacity: .75; }
.picker-warning { color: var(--el-color-warning-dark-2); line-height: 1.7; }
.picker-footer { flex-wrap: wrap; }
@media (max-width: 700px) { .picker-columns { grid-template-columns: 1fr; }.picker-keywords { border-left: 0; border-top: 1px solid var(--el-border-color-light); padding: 18px 0 0; }.picker-category { flex-wrap: wrap; }.picker-category > .el-select { min-width: 200px; } }
</style>
