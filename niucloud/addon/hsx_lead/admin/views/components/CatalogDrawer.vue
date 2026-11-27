<template>
    <HsxDrawer
        v-model="visible"
        :title="`${businessName || '设备需求'}目录`"
        subtitle="维护当前业务的分类与选项"
        size="lg"
        :body-loading="loading"
        :confirm-loading="saving"
        :show-footer="activeTab==='import'"
        confirm-text="确认替换当前业务目录"
        :confirm-disabled="!previewReady || loading || !!fileError || !confirmed"
        @confirm="save"
    >
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="lead-error-banner" />
        <el-tabs v-model="activeTab" @tab-change="changeTab">
            <el-tab-pane label="目录管理" name="items" />
            <el-tab-pane v-if="canImport" label="批量导入" name="import" />
            <el-tab-pane v-if="canLogs" label="操作日志" name="logs" />
        </el-tabs>
        <template v-if="activeTab==='items'">
            <div class="catalog-toolbar">
                <el-input
                    v-model="keyword"
                    clearable
                    placeholder="搜索名称、ID、品牌或分类"
                    class="catalog-search"
                    @keyup.enter="search"
                    @clear="search"
                />
                <el-button :loading="loading" :disabled="busy" @click="search">查询</el-button>
                <el-button v-if="canAdd" type="primary" :disabled="busy || !revision" @click="editItem()"
                    >新增选项</el-button
                >
            </div>
            <HsxTable
                :columns="itemColumns"
                :data="items"
                row-key="id"
                :loading="loading"
                :empty-text="error ? '目录读取失败，请重试' : '暂无目录选项，可新增或批量导入'"
            >
                <template #name="{row}"
                    ><div class="catalog-cell"
                        ><strong>{{ row.title }}</strong
                        ><small>{{ row.brand || '未设置品牌' }}</small></div
                    ></template
                >
                <template #path="{row}">{{ (row.path || []).join(' / ') || row.category_name || '—' }}</template>
                <template #action="{row}"
                    ><el-button v-if="canEdit" link type="primary" :disabled="busy" @click="editItem(row)"
                        >编辑</el-button
                    ><el-button v-if="canDelete" link type="danger" :disabled="busy" @click="removeItem(row)"
                        >删除</el-button
                    ><span v-if="!canEdit&&!canDelete" class="lead-muted">仅查看</span></template
                >
            </HsxTable>
            <div class="catalog-pagination"
                ><HsxPagination
                    :current-page="page"
                    :page-size="limit"
                    :total="total"
                    :disabled="busy"
                    @change="changePage"
            /></div>
            <p class="catalog-scope">仅影响此后新增选择；不会改写已保存的线索、商户能力或库存。</p>
        </template>
        <template v-else-if="activeTab==='import'">
            <el-alert
                v-if="!loading && !previewSupported"
                title="服务器未确认支持预览，导入已禁用，请更新配套后端。"
                type="warning"
                :closable="false"
                show-icon
                class="lead-error-banner"
            />
            <div class="catalog-toolbar"
                ><el-upload
                    accept=".xlsx,.csv,.json"
                    :auto-upload="false"
                    :show-file-list="false"
                    :on-change="chooseFile"
                    :disabled="busy || !previewSupported"
                    ><el-button>选择 Excel / CSV / JSON</el-button></el-upload
                ><el-button @click="downloadTemplate">下载三级目录模板</el-button></div
            >
            <el-collapse class="catalog-help"
                ><el-collapse-item title="导入规则与影响范围" name="format"
                    ><p class="lead-muted"
                        >仅替换本站「{{ businessName || businessType || '二手机'
                        }}」目录，不修改其他业务及既有记录。Excel原文件不超过2
                        MB，一个工作表、纯文本、最多5000项；不支持公式或外链。CSV / JSON不超过1
                        MB。可用“一级分类、二级分类、三级分类、名称、id、品牌、别名”，ID可留空，系统自动生成；已有ID导入后保持不变。CSV请用UTF-8。</p
                    ></el-collapse-item
                ></el-collapse
            >
            <el-alert
                v-if="fileError"
                :title="fileError"
                type="error"
                show-icon
                :closable="false"
                class="lead-error-banner"
            />
            <section v-if="filename" class="catalog-pending"
                ><div class="catalog-pending-head"
                    ><div
                        ><strong>{{ filename }}</strong
                        ><p
                            class="lead-muted"
                            >{{ previewReady ? `校验通过，共${previewItems.length}项，尚未保存` : '文件已读取，尚未校验或保存' }}</p
                        ></div
                    ><el-button :loading="previewing" :disabled="saving || !content || !!fileError" @click="preview"
                        >校验并预览</el-button
                    ></div
                ><template v-if="previewReady"
                    ><HsxTable
                        :columns="columns"
                        :data="previewItems.slice(0,100)"
                        row-key="id"
                        empty-text="文件没有可导入项"
                        ><template
                            #path="{row}"
                            >{{ (row.path || []).join(' / ') || row.category_name || '—' }}</template
                        ></HsxTable
                    ><p v-if="previewItems.length>100" class="lead-muted"
                        >预览前100项，确认后替换完整{{ previewItems.length }}项。</p
                    ><el-checkbox v-model="confirmed"
                        >已核对{{ previewItems.length }}项，确认替换当前业务目录</el-checkbox
                    ></template
                ></section
            >
        </template>
        <template v-else-if="activeTab==='logs'">
            <div class="catalog-toolbar"
                ><el-select
                    v-model="logAction"
                    clearable
                    placeholder="全部操作"
                    style="width:180px"
                    @change="searchLogs"
                    ><el-option label="新增" value="created" /><el-option label="修改" value="updated" /><el-option
                        label="删除"
                        value="deleted" /><el-option label="导入替换" value="replaced" /></el-select
                ><el-button :loading="logsLoading" :disabled="busy" @click="searchLogs">刷新日志</el-button></div
            >
            <HsxTable
                :columns="logColumns"
                :data="logs"
                row-key="id"
                :loading="logsLoading"
                empty-text="暂无目录操作日志"
            >
                <template #operator="{row}"
                    ><div class="catalog-cell"
                        ><strong>{{ row.operator_name }}</strong
                        ><small>{{ displayTime(row.create_time) }}</small></div
                    ></template
                >
                <template #change="{row}"
                    ><div class="catalog-cell"
                        ><strong>{{ row.action_label }}</strong
                        ><small>{{ logSummary(row) }}</small></div
                    ></template
                >
                <template #action="{row}"
                    ><el-button link type="primary" @click="selectedLog=row;logVisible=true"
                        >查看变更</el-button
                    ></template
                >
            </HsxTable>
            <div class="catalog-pagination"
                ><HsxPagination
                    :current-page="logPage"
                    :page-size="20"
                    :total="logTotal"
                    :disabled="logsLoading"
                    @change="changeLogPage"
            /></div>
        </template>
    </HsxDrawer>
    <el-dialog
        v-model="editing"
        :title="editingId ? '编辑目录选项' : '新增目录选项'"
        width="min(600px, 94vw)"
        append-to-body
        :close-on-click-modal="false"
        :close-on-press-escape="!saving"
        :show-close="!saving"
        :before-close="closeEditor"
    >
        <el-alert
            v-if="itemError"
            :title="itemError"
            type="error"
            :closable="false"
            show-icon
            class="lead-error-banner"
        />
        <el-form label-position="top" :disabled="saving" @submit.prevent>
            <el-form-item label="名称 / 型号" required
                ><el-input v-model="itemForm.title" maxlength="200" placeholder="填写客户看到的名称"
            /></el-form-item>
            <div class="catalog-form-grid"
                ><el-form-item label="一级分类"
                    ><el-input v-model="itemForm.first" maxlength="100" placeholder="例如 苹果" /></el-form-item
                ><el-form-item label="二级分类"
                    ><el-input v-model="itemForm.second" maxlength="100" placeholder="例如 18系列" /></el-form-item
                ><el-form-item label="三级分类"
                    ><el-input
                        v-model="itemForm.third"
                        maxlength="100"
                        placeholder="例如 iPhone 18 Pro" /></el-form-item
                ><el-form-item label="品牌（选填）"><el-input v-model="itemForm.brand" maxlength="100" /></el-form-item
            ></div>
            <el-form-item label="别名（选填）"
                ><el-input
                    v-model="itemForm.aliases"
                    type="textarea"
                    :rows="2"
                    placeholder="多个别名用分号分隔，最多20个"
            /></el-form-item>
            <el-collapse class="catalog-help"
                ><el-collapse-item title="稳定ID" name="id"
                    ><el-input
                        v-model="itemForm.id"
                        maxlength="100"
                        :disabled="!!editingId"
                        placeholder="新增时可留空，由系统生成"
                    /><p class="lead-muted">保存后ID保持不变，修改名称和分类不会更换ID。</p></el-collapse-item
                ></el-collapse
            >
        </el-form>
        <template #footer
            ><el-button :disabled="saving" @click="closeEditor()">取消</el-button
            ><el-button type="primary" :loading="saving" @click="saveItem">保存选项</el-button></template
        >
    </el-dialog>
    <el-dialog v-model="logVisible" title="目录变更详情" width="min(760px, 94vw)" append-to-body>
        <div class="catalog-log-meta"
            >{{ selectedLog.operator_name }} · {{ displayTime(selectedLog.create_time) }} ·
            {{ selectedLog.action_label }}</div
        >
        <template v-if="selectedLog.action==='replaced'"
            ><p class="catalog-scope">{{ logSummary(selectedLog) }}</p
            ><p v-if="selectedLog.detail?.changes_truncated" class="lead-muted"
                >以下为前20项变更样例，数量统计包含本次全部变更。</p
            ><div v-for="(change,index) in selectedLog.detail?.changes||[]" :key="index" class="catalog-log-change"
                ><strong>{{ changeLabels[change.action] || change.action }}</strong
                ><div class="catalog-change-grid"
                    ><section
                        ><span class="lead-muted">修改前</span><p>{{ itemDescription(change.before) }}</p></section
                    ><section
                        ><span class="lead-muted">修改后</span><p>{{ itemDescription(change.after) }}</p></section
                    ></div
                ></div
            ></template
        >
        <div v-else class="catalog-change-grid"
            ><section
                ><h4>修改前</h4><p>{{ itemDescription(selectedLog.detail?.before) }}</p></section
            ><section
                ><h4>修改后</h4><p>{{ itemDescription(selectedLog.detail?.after) }}</p></section
            ></div
        >
        <template #footer><el-button @click="logVisible=false">关闭</el-button></template>
    </el-dialog>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { ElMessage, ElMessageBox, type UploadFile } from 'element-plus'
import useUserStore from '@/stores/modules/user'
import HsxDrawer from '@/addon/hsx_components/components/HsxDrawer/index.vue'
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import HsxPagination from '@/addon/hsx_components/components/HsxPagination/index.vue'
import { getCatalogItems, addCatalogItem, updateCatalogItem, deleteCatalogItem, getCatalogLogs, saveCatalog } from '../../api'
import { hasLeadPagePermission } from '../../composables/navigation'
import { errorText, type Row } from '../../composables/useLeadUi'
const props = withDefaults(defineProps<{ modelValue:boolean; businessType?:string; businessName?:string }>(),{businessType:'',businessName:''})
const emit = defineEmits<{(e:'update:modelValue',value:boolean):void;(e:'changed'):void}>()
const user=useUserStore(),allowed=(key:string)=>hasLeadPagePermission(user,key)
const canAdd=computed(()=>allowed('hsx_lead_catalog_add')),canEdit=computed(()=>allowed('hsx_lead_catalog_edit')),canDelete=computed(()=>allowed('hsx_lead_catalog_delete')),canLogs=computed(()=>allowed('hsx_lead_catalog_logs')),canImport=computed(()=>allowed('hsx_lead_manage_catalog_save'))
const activeTab=ref('items'),items=ref<Row[]>([]),loading=ref(false),saving=ref(false),previewing=ref(false),previewSupported=ref(false),error=ref(''),fileError=ref(''),content=ref(''),format=ref<'csv'|'json'|'xlsx'>('csv'),filename=ref(''),keyword=ref(''),previewItems=ref<Row[]>([]),previewReady=ref(false),confirmed=ref(false),revision=ref(''),page=ref(1),limit=ref(20),total=ref(0)
const logs=ref<Row[]>([]),logsLoading=ref(false),logAction=ref(''),logPage=ref(1),logTotal=ref(0),logVisible=ref(false),selectedLog=ref<Row>({})
const editing=ref(false),editingId=ref(''),itemError=ref(''),itemForm=reactive({id:'',title:'',first:'',second:'',third:'',brand:'',aliases:''})
const busy=computed(()=>saving.value||previewing.value)
const visible=computed({get:()=>props.modelValue,set:value=>{if(!busy.value)emit('update:modelValue',value)}})
const columns=[{prop:'title',label:'名称 / 型号',minWidth:180},{prop:'path',label:'分类路径',slot:'path',minWidth:220},{prop:'id',label:'稳定ID',minWidth:150}]
const itemColumns=[{prop:'title',label:'名称 / 品牌',slot:'name',minWidth:180},{prop:'path',label:'分类路径',slot:'path',minWidth:240},{prop:'id',label:'稳定ID',minWidth:150},{prop:'action',label:'操作',slot:'action',width:130,fixed:'right' as const}]
const logColumns=[{prop:'operator_uid',label:'操作人 / 时间',slot:'operator',minWidth:200},{prop:'action',label:'变更内容',slot:'change',minWidth:280},{prop:'action',label:'操作',slot:'action',width:110}]
const changeLabels:Record<string,string>={created:'新增',updated:'修改',deleted:'删除'}
let sequence=0,fileSequence=0,logSequence=0
const scope=()=>({business_type:props.businessType||'purchase'})
function clearPreview(){previewItems.value=[];previewReady.value=false;confirmed.value=false}
async function load(){const token=++sequence;loading.value=true;error.value='';try{const result=await getCatalogItems({...scope(),keyword:keyword.value,page:page.value,limit:limit.value});if(token!==sequence||!visible.value)return;const data=result.data;if(!Array.isArray(data?.data)||!Number.isInteger(Number(data.total))||!data.supports_item_management||!/^[a-f0-9]{64}$/.test(String(data.revision||'')))throw new Error('目录接口未完整返回，请更新配套后端');items.value=data.data;total.value=Number(data.total);revision.value=data.revision;previewSupported.value=data.supports_preview===true;if(!items.value.length&&page.value>1){page.value=Math.max(1,Math.ceil(total.value/limit.value));await load()}}catch(cause){if(token===sequence)error.value=errorText(cause,'目录读取失败，请重试')}finally{if(token===sequence)loading.value=false}}
function search(){page.value=1;return load()}
function changePage(value:{page:number;limit:number}){if(busy.value)return;page.value=value.page;limit.value=value.limit;return load()}
async function loadLogs(){if(!canLogs.value)return;const token=++logSequence;logsLoading.value=true;error.value='';try{const result=await getCatalogLogs({...scope(),action:logAction.value,page:logPage.value,limit:20});if(token!==logSequence||!visible.value)return;if(!Array.isArray(result.data?.data))throw new Error('未返回有效操作日志');logs.value=result.data.data;logTotal.value=Number(result.data.total||0)}catch(cause){if(token===logSequence)error.value=errorText(cause,'操作日志读取失败，请重试')}finally{if(token===logSequence)logsLoading.value=false}}
function searchLogs(){logPage.value=1;return loadLogs()}
function changeLogPage(value:{page:number;limit:number}){logPage.value=value.page;return loadLogs()}
function changeTab(){error.value='';if(activeTab.value==='logs')void loadLogs()}
function editItem(row?:Row){if(busy.value||(!row&&!canAdd.value)||(row&&!canEdit.value))return;editingId.value=String(row?.id||'');Object.assign(itemForm,{id:String(row?.id||''),title:String(row?.title||''),first:String(row?.path?.[0]||row?.category_name||''),second:String(row?.path?.[1]||''),third:String(row?.path?.[2]||''),brand:String(row?.brand||''),aliases:(row?.aliases||[]).join(';')});itemError.value='';editing.value=true}
function closeEditor(done?:()=>void){if(saving.value)return;editing.value=false;if(typeof done==='function')done()}
async function saveItem(){if(saving.value||!editing.value||!revision.value||(editingId.value?!canEdit.value:!canAdd.value))return;itemError.value='';if(!itemForm.title.trim()){itemError.value='请填写名称或型号';return}if((itemForm.second.trim()&&!itemForm.first.trim())||(itemForm.third.trim()&&!itemForm.second.trim())){itemError.value='分类请从一级开始连续填写';return}const item={id:itemForm.id.trim(),title:itemForm.title.trim(),brand:itemForm.brand.trim(),category_name:itemForm.first.trim(),path:[itemForm.first,itemForm.second,itemForm.third].map(value=>value.trim()).filter(Boolean),aliases:itemForm.aliases};saving.value=true;try{const data={...scope(),revision:revision.value,item};const result=editingId.value?await updateCatalogItem(editingId.value,data):await addCatalogItem(data);if(!result.data?.item?.id||(editingId.value&&result.data.item.id!==editingId.value)||result.data.item.title!==item.title||!/^[a-f0-9]{64}$/.test(String(result.data.revision||'')))throw new Error('保存结果未完整返回，请关闭后刷新核对，不要重复提交');revision.value=result.data.revision;editing.value=false;emit('changed');clearPreview();await load();ElMessage.success('目录选项已保存')}catch(cause){itemError.value=errorText(cause,'保存未完成，已保留填写内容；若提示版本变化，请关闭并刷新目录')}finally{saving.value=false}}
async function removeItem(row:Row){if(busy.value||!canDelete.value||!revision.value)return;saving.value=true;try{await ElMessageBox.confirm(`仅从当前业务目录移除“${row.title}”，不改写已有线索和商户能力记录。是否删除？`,'删除目录选项',{confirmButtonText:'确认删除',cancelButtonText:'取消',type:'warning'})}catch{saving.value=false;return}error.value='';try{const result=await deleteCatalogItem(String(row.id),{...scope(),revision:revision.value});if(result.data?.deleted!==true||!/^[a-f0-9]{64}$/.test(String(result.data.revision||'')))throw new Error('删除结果未完整返回，请先刷新核对，不要重复提交');revision.value=result.data.revision;clearPreview();emit('changed');await load();ElMessage.success('目录选项已移除')}catch(cause){error.value=errorText(cause,'删除结果待核对，请刷新目录，勿重复操作')}finally{saving.value=false}}
async function chooseFile(file:UploadFile){const token=++fileSequence;content.value='';filename.value='';fileError.value='';clearPreview();if(!file.raw){fileError.value='无法读取文件，请重新选择';return}if(!/\.(xlsx|csv|json)$/i.test(file.name)){fileError.value='只支持xlsx、CSV或JSON文件';return}const xlsx=/\.xlsx$/i.test(file.name);if(file.raw.size>(xlsx?2:1)*1024*1024){fileError.value=xlsx?'Excel超过2 MB，请精简后重试':'文件超过1 MB，请精简后重试';return}try{let value='';if(xlsx){const bytes=new Uint8Array(await file.raw.arrayBuffer());let binary='';for(let offset=0;offset<bytes.length;offset+=8192)binary+=String.fromCharCode(...bytes.subarray(offset,offset+8192));value=btoa(binary)}else value=(await file.raw.text()).replace(/^\uFEFF/,'');if(token!==fileSequence)return;if(!value.trim()){fileError.value='文件内容为空，请检查后重试';return}content.value=value;filename.value=file.name;format.value=xlsx?'xlsx':/\.json$/i.test(file.name)?'json':'csv'}catch(cause){if(token===fileSequence)fileError.value=errorText(cause,'文件读取失败，请重新选择文件')}}
function payload(previewOnly:boolean){return{content:content.value,format:format.value,...scope(),revision:revision.value,preview:previewOnly}}
async function preview(){if(!content.value||busy.value||!previewSupported.value)return;const token=fileSequence;previewing.value=true;error.value='';clearPreview();try{const result=await saveCatalog(payload(true));if(token!==fileSequence||!visible.value)return;if(result.data?.preview!==true||!Array.isArray(result.data.items))throw new Error('服务器未返回有效预览，目录未确认保存');previewItems.value=result.data.items;previewReady.value=previewItems.value.length>0;if(!previewReady.value)error.value='文件中没有可导入项'}catch(cause){if(token===fileSequence)error.value=errorText(cause,'文件校验未通过，没有保存目录')}finally{previewing.value=false}}
function downloadTemplate(){const url=URL.createObjectURL(new Blob(['\uFEFF一级分类,二级分类,三级分类,名称,id,品牌,别名\r\n'],{type:'text/csv;charset=utf-8'}));const link=document.createElement('a');link.href=url;link.download='三级需求目录导入模板.csv';link.click();setTimeout(()=>URL.revokeObjectURL(url),1000)}
async function save(){if(!content.value||!previewReady.value||!confirmed.value||fileError.value||saving.value)return;saving.value=true;error.value='';try{await saveCatalog(payload(false));content.value='';filename.value='';clearPreview();emit('changed');await load();ElMessage.success(error.value?'目录已保存，请刷新核对':`当前业务目录已保存`)}catch(cause){error.value=errorText(cause,'目录未确认保存，已保留文件和预览，请核对后重试')}finally{saving.value=false}}
function displayTime(value:unknown){const date=new Date(Number(value)*1000);return Number.isFinite(date.getTime())?date.toLocaleString('zh-CN',{hour12:false}):'—'}
function logSummary(row:Row){const detail=row.detail||{};if(row.action==='replaced'){const count=detail.change_counts;return count?`新增 ${count.created||0} · 修改 ${count.updated||0} · 删除 ${count.deleted||0} · 导入后 ${detail.items_count||0} 项`:`导入后 ${detail.items_count||0} 项`}return String(detail.after?.title||detail.before?.title||detail.item_id||'目录维护')}
function itemDescription(item:Row|null|undefined){if(!item)return '无';return [item.title,`ID：${item.id}`,item.path?.length?`分类：${item.path.join(' / ')}`:'',item.brand?`品牌：${item.brand}`:'',item.aliases?.length?`别名：${item.aliases.join('；')}`:''].filter(Boolean).join('\n')}
watch(()=>[props.modelValue,props.businessType],()=>{if(props.modelValue){activeTab.value='items';page.value=1;keyword.value='';revision.value='';content.value='';filename.value='';fileError.value='';editing.value=false;logVisible.value=false;clearPreview();void load()}else{sequence++;fileSequence++;logSequence++;loading.value=false;logsLoading.value=false;editing.value=false;logVisible.value=false}},{immediate:true})
onBeforeUnmount(()=>{sequence++;fileSequence++;logSequence++})
</script>
<style scoped>
.catalog-toolbar{display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin:8px 0 18px}.catalog-search{max-width:420px;flex:1;min-width:200px}.catalog-cell{display:flex;flex-direction:column;gap:6px;line-height:1.5}.catalog-cell small{font-size:12px;color:var(--el-text-color-secondary)}.catalog-pagination{display:flex;justify-content:flex-end;padding-top:18px}.catalog-scope{font-size:13px;line-height:1.7;color:var(--el-text-color-secondary);margin:16px 0 0}.catalog-help{margin:14px 0}.catalog-pending{margin:16px 0;padding:16px 0;border-top:1px solid var(--el-border-color-lighter)}.catalog-pending-head{display:flex;gap:12px;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap}.catalog-pending :deep(.el-checkbox){height:auto;margin-top:14px;white-space:normal}.catalog-pending :deep(.el-checkbox__label){white-space:normal}.catalog-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}.catalog-change-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.catalog-change-grid section{min-width:0;padding:14px;border:1px solid var(--el-border-color-lighter);border-radius:6px}.catalog-change-grid p{white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.8;margin:8px 0 0}.catalog-log-meta{color:var(--el-text-color-secondary);font-size:13px;margin-bottom:16px}.catalog-log-change{padding:14px 0;border-bottom:1px solid var(--el-border-color-lighter)}.catalog-log-change>strong{display:block;margin-bottom:10px}@media(max-width:640px){.catalog-form-grid,.catalog-change-grid{grid-template-columns:1fr}}
</style>
