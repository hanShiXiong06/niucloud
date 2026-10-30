<template>
    <HsxDrawer v-model="visible" title="设备清单" subtitle="客服从清单勾选机型；清单不代表实际库存或价格。" size="md" :body-loading="loading" :confirm-loading="saving" show-footer confirm-text="保存并替换清单" :confirm-disabled="!content || loading || !!fileError" @confirm="save">
        <el-alert v-if="error" :title="error" type="error" :closable="false" class="lead-error-banner" />
        <el-alert :title="source === 'configured' ? '正在使用自定义设备清单。替换清单只影响以后选择，不覆盖已录入的客户需求。' : source === 'inventory' ? '尚未配置自定义清单，暂使用当前已同步库存的型号作为选项。可上传清单，后续客服直接勾选。' : '尚未上传自定义清单，先提供常用机型，并补充当前已同步库存型号。这些只是需求选项，不代表门店有实际库存；可随后上传自定义清单。'" type="info" show-icon :closable="false" class="lead-banner" />
        <div class="lead-actions"><el-upload accept=".csv,.json" :auto-upload="false" :show-file-list="false" :on-change="chooseFile" :disabled="saving"><el-button type="primary" plain>选择 CSV / JSON 文件</el-button></el-upload><el-button @click="downloadTemplate">下载 CSV 模板</el-button><el-button :loading="loading" :disabled="saving" @click="load">刷新当前清单</el-button></div>
        <p class="lead-muted" style="margin: 14px 0">最多 5000 项、文件不超过 1 MB。CSV 列：brand、model、category、aliases；model 必填，别名用 ; 或 | 分隔。JSON 使用同名字段的设备数组，或包含 items 的对象。Excel 请先另存为 CSV，本页不直接解析 xlsx。</p>
        <div v-if="fileError" class="lead-error">{{ fileError }}</div>
        <div v-if="filename" class="lead-card" style="margin-top: 16px"><h3>待保存：{{ filename }}</h3><p class="lead-muted">仅已读取本地文本，尚未保存。点击下方“保存并替换清单”后，由服务器校验并保存。</p><el-input :model-value="content.slice(0, 2500)" readonly type="textarea" :rows="5" style="margin-top: 12px" /><p v-if="content.length > 2500" class="lead-muted">这里只预览前 2500 个字符，提交时使用完整文件。</p></div>
        <div class="lead-form-section"><h3>当前可选设备 · {{ items.length }} 项</h3><el-input v-model="keyword" clearable placeholder="搜索品牌、机型或别名" style="margin: 14px 0" /><HsxTable :columns="columns" :data="filtered.slice(0, 100)" empty-text="暂无设备选项，请先同步库存或上传自定义清单"><template #aliases="{ row }">{{ (row.aliases || []).join('、') || '—' }}</template></HsxTable><p v-if="filtered.length > 100" class="lead-muted" style="margin-top: 12px">匹配 {{ filtered.length }} 项，当前预览前 100 项；录入时可搜索完整清单。</p></div>
    </HsxDrawer>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { ElMessage, type UploadFile } from 'element-plus'
import HsxDrawer from '@/addon/hsx_components/components/HsxDrawer/index.vue'
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import { getCatalog, saveCatalog } from '../../api'
import { errorText, type Row } from '../../composables/useLeadUi'
const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{ (e: 'update:modelValue', value: boolean): void, (e: 'changed'): void }>()
const visible = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) })
const items = ref<Row[]>([]), source = ref('inventory'), loading = ref(false), saving = ref(false), error = ref(''), fileError = ref(''), content = ref(''), format = ref<'csv' | 'json'>('csv'), filename = ref(''), keyword = ref('')
const filtered = computed(() => items.value.filter(item => [item.title, item.brand, item.category_name, ...(item.aliases || [])].join(' ').toLowerCase().includes(keyword.value.trim().toLowerCase())))
const columns = [{ prop: 'brand', label: '品牌', width: 110 }, { prop: 'title', label: '设备型号', minWidth: 210 }, { prop: 'category_name', label: '分类', width: 110 }, { prop: 'aliases', label: '匹配别名', slot: 'aliases', minWidth: 150 }]
let sequence = 0, fileSequence = 0
async function load() { const token = ++sequence; loading.value = true; error.value = ''; try { const result = await getCatalog(); if (token === sequence) { items.value = result.data.items || []; source.value = result.data.source || 'inventory' } } catch (cause) { if (token === sequence) error.value = errorText(cause, '设备清单读取失败，请重试。') } finally { if (token === sequence) loading.value = false } }
async function chooseFile(file: UploadFile) {
    const token = ++fileSequence; content.value = ''; filename.value = ''; fileError.value = ''
    if (!file.raw) { fileError.value = '无法读取文件，请重新选择。'; return }
    if (!/\.(csv|json)$/i.test(file.name)) { fileError.value = '只支持 CSV 或 JSON。Excel 请先另存为 CSV。'; return }
    if (file.raw.size > 1024 * 1024) { fileError.value = '文件超过 1 MB，请精简后重试。'; return }
    try { const text = await file.raw.text(); if (token !== fileSequence) return; if (!text.trim()) { fileError.value = '文件内容为空，请检查后重试。'; return }; content.value = text.replace(/^\uFEFF/, ''); filename.value = file.name; format.value = /\.json$/i.test(file.name) ? 'json' : 'csv' }
    catch (cause) { if (token === fileSequence) fileError.value = errorText(cause, '文件读取失败，请使用 UTF-8 编码的 CSV 或 JSON。') }
}
function downloadTemplate() { const url = URL.createObjectURL(new Blob(['\uFEFFbrand,model,category,aliases\r\n'], { type: 'text/csv;charset=utf-8' })); const link = document.createElement('a'); link.href = url; link.download = '设备清单导入模板.csv'; link.click(); setTimeout(() => URL.revokeObjectURL(url), 1000) }
async function save() { if (!content.value || fileError.value || saving.value) return; saving.value = true; error.value = ''; try { await saveCatalog({ content: content.value, format: format.value }); content.value = ''; filename.value = ''; emit('changed'); await load(); ElMessage.success(error.value ? '清单已保存，读取校验失败，请刷新确认' : `设备清单已保存，当前 ${items.value.length} 项`) } catch (cause) { error.value = errorText(cause, '清单未保存，请检查列名、格式及条目数量。') } finally { saving.value = false } }
watch(visible, value => { if (value) { content.value = ''; filename.value = ''; fileError.value = ''; keyword.value = ''; void load() } else { sequence++; fileSequence++; loading.value = false } })
onBeforeUnmount(() => { sequence++; fileSequence++ })
</script>
