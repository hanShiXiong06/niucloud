<template>
    <HsxPage title="型号与模板来源" content-width="standard">
        <CatalogSourceNotice :policy="catalogPolicy" :error="catalogError" @retry="loadCatalog" />
        <el-card v-if="catalogPolicy" shadow="never">
            <div class="source-switch">
                <div><h3>使用公共资料库</h3><p>开启只用公共库，关闭只用本站库。两份资料不合并，不删除。</p></div>
                <el-switch :model-value="catalogPolicy.effective" :loading="saving" :disabled="!catalogPolicy.initialized || restoringPrint || checkingPrint" @change="changeSource" />
            </div>
            <el-alert v-if="!catalogPolicy.initialized" title="平台尚未初始化公共资料库，请联系平台管理员。" type="info" :closable="false" />
            <p v-if="catalogPolicy.site_id === 100005" class="hint">100005 的原有型号与模板转为公共库后，不再留一份本站副本；关闭公共库后需重新维护本站库。</p>
            <p class="hint">公共库的维护权限由平台授权。没有授权时，员工仍可选择型号、完成质检；缺少资料请联系站点管理员，由管理员联系平台补充。</p>
        </el-card>
        <el-card v-if="catalogPolicy?.effective" shadow="never" class="print-recovery">
            <div class="source-switch">
                <div><h3>恢复原本站打印关联</h3><p>公共标签可直接使用，也可复制后自行修改。手工转公共库后原有本站标签未显示，可在这里检查并恢复原关联。</p></div>
                <el-button :loading="checkingPrint" :disabled="restoringPrint" @click="checkPrint">检查原打印关联</el-button>
            </div>
            <el-alert v-if="printError" :title="printError" type="error" :closable="false" show-icon />
            <el-alert v-if="printResult" :title="printResult" type="success" :closable="true" show-icon @close="printResult = ''" />
            <template v-if="printPreview">
                <p v-if="!printPreview.items.length" class="hint">未发现误放在公共库、且可以归还本站的打印关联。本检查不代表全部打印场景已经配置完成。</p>
                <template v-else>
                    <p>可恢复 {{ printPreview.restore }} 条 · 已有相同配置 {{ printPreview.clean }} 条 · 需人工核对 {{ printPreview.blocked }} 条</p>
                    <el-table :data="printPreview.items" max-height="320" border>
                        <el-table-column prop="target_name" label="型号 / 分类" min-width="200" show-overflow-tooltip />
                        <el-table-column prop="template_name" label="本站标签" min-width="150" />
                        <el-table-column prop="reason" label="处理说明" min-width="260" />
                    </el-table>
                    <p class="hint">仅恢复本站拥有的标签关联；公共质检保留，已有不同配置不覆盖。此操作不会打印，也不会修改订单或设备。</p>
                    <el-button type="primary" :loading="restoringPrint" :disabled="checkingPrint || !(printPreview.restore + printPreview.clean)" @click="restorePrint">恢复本站打印关联</el-button>
                </template>
            </template>
        </el-card>
    </HsxPage>
</template>
<script setup lang="ts">
import { ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { HsxPage } from '@/addon/hsx_components/core'
import { setDeviceCatalogChoice, previewCatalogPrintRecovery, restoreCatalogPrintRecovery, type PrintRecoveryPreview } from '@/addon/hsx_recycle/api/device_catalog'
import { useDeviceCatalog } from '@/addon/hsx_recycle/composables/useDeviceCatalog'
import CatalogSourceNotice from '@/addon/hsx_recycle/components/device-entry/CatalogSourceNotice.vue'
const { catalogPolicy, catalogError, loadCatalog } = useDeviceCatalog()
const saving = ref(false)
const checkingPrint = ref(false), restoringPrint = ref(false)
const printPreview = ref<PrintRecoveryPreview>()
const printError = ref(''), printResult = ref('')
async function checkPrint() {
    checkingPrint.value = true; printError.value = ''; printPreview.value = undefined
    try {
        const response = await previewCatalogPrintRecovery()
        if (!response?.data || !Array.isArray(response.data.items)) throw new Error('未取得检查结果，请重试')
        printPreview.value = response.data
    } catch (e: any) { printError.value = e?.msg || e?.message || '检查失败，请重试' }
    finally { checkingPrint.value = false }
}
async function restorePrint() {
    const preview = printPreview.value
    if (!preview || restoringPrint.value) return
    try {
        await ElMessageBox.confirm(`恢复 ${preview.restore} 条本站打印关联，并清理 ${preview.clean} 条已有相同配置的公共打印引用；${preview.blocked} 条需人工核对的记录不改。公共质检不变，不触发打印。`, '确认恢复本站打印关联', { type: 'warning', confirmButtonText: '确认恢复', cancelButtonText: '取消' })
    } catch { return }
    restoringPrint.value = true; printError.value = ''; printResult.value = ''
    try {
        const response = await restoreCatalogPrintRecovery(preview.fingerprint)
        if (!response?.data?.message) throw new Error('未取得恢复结果，请重新检查，勿盲目重复操作')
        printResult.value = response.data.message
        await checkPrint()
    } catch (e: any) { printError.value = e?.msg || e?.message || '恢复未完成，请重新检查' }
    finally { restoringPrint.value = false }
}
async function changeSource(value: boolean | string | number) {
    const enabled = Boolean(value)
    try {
        await ElMessageBox.confirm(enabled ? '开启后，型号与模板只显示公共库，本站原有资料隐藏，不会合并或删除。' : '关闭后，型号与模板只显示本站库，公共资料不再供新增选择。已录入设备不重新关联。', '切换资料来源', { type: 'warning', confirmButtonText: '确认切换', cancelButtonText: '不切换' })
    } catch { return }
    saving.value = true
    try { await setDeviceCatalogChoice(enabled); await loadCatalog(); ElMessage.success('资料来源已切换，请重新打开正在编辑的录入页面') }
    catch { /* 请求层展示失败原因，开关保留服务器状态。 */ }
    finally { saving.value = false }
}
</script>
<style scoped>
.source-switch { display:flex; align-items:center; justify-content:space-between; gap:24px; }
.print-recovery { margin-top:16px; }.print-recovery .source-switch { flex-wrap:wrap; }
h3 { margin:0 0 8px; } p { line-height:1.8; margin:0 0 14px; } .hint { color:var(--el-text-color-secondary); font-size:13px; margin-top:16px; }
</style>
