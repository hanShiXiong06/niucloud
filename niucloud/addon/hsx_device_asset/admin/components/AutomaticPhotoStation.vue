<template>
    <section class="station">
        <div class="station__head">
            <div><strong>自动拍摄</strong><p>放好设备，一键拍摄；翻面后继续，最后只提交选中的图片。</p></div>
            <el-tag :type="capabilities.simulated ? 'warning' : capabilities.ready ? 'success' : 'info'">
                {{ capabilities.simulated ? '开发模拟' : capabilities.ready ? '工位就绪' : '未连接工位' }}
            </el-tag>
        </div>
        <el-alert v-if="capabilities.simulated" title="开发模拟仅用于验证流程，图片不能提交 ERP，模拟耗时不能作为生产效率。" type="warning" :closable="false" show-icon />
        <el-alert v-if="error" :title="error" type="error" closable show-icon @close="error = ''" />
        <el-button v-if="error && job.inspection_id" text type="warning" @click="offlineFallback">工位失联？确认转台停止后，用手机补拍</el-button>
        <el-alert v-if="completed" :title="completed" type="success" :closable="false" show-icon />

        <el-collapse v-model="settingsOpen">
            <el-collapse-item title="工位连接与拍摄设置 · 首次配置，之后自动记住" name="settings">
                <el-form label-position="top" class="station__settings">
                    <el-form-item label="本机拍摄服务"><el-input v-model.trim="settings.url" placeholder="http://127.0.0.1:5200" :disabled="busy || !!job.inspection_id" /></el-form-item>
                    <el-form-item label="工位连接码"><el-input v-model.trim="settings.token" type="password" show-password placeholder="在拍照电脑「ERP 连接」中复制" :disabled="busy" /></el-form-item>
                    <el-form-item label="拍摄方式">
                        <el-radio-group v-model="settings.profile" :disabled="busy || !!job.inspection_id">
                            <el-radio label="fast">快速单帧</el-radio><el-radio label="hdr">多曝光 HDR（较慢）</el-radio>
                        </el-radio-group>
                        <small>先用单帧验证合格率；只有实测需要时再启用 HDR。沿用工位调色预设，原图另存。</small>
                    </el-form-item>
                    <el-button :loading="connecting" :disabled="busy" @click="connect">连接 / 恢复任务</el-button>
                    <small>浏览器询问本地网络访问时需允许；无法连接时可用手机继续。灯光需人工确认。</small>
                    <el-button v-if="job.inspection_id" text :disabled="busy" @click="releaseBinding">结束本轮绑定（保留照片）</el-button>
                </el-form>
            </el-collapse-item>
        </el-collapse>

        <div class="station__progress" aria-live="polite">
            <strong>{{ stateLabel }}</strong>
            <span>已拍 {{ job.photos?.length || 0 }} 张 · 已选 {{ selected.length }} 张</span>
            <small v-if="job.capture_seconds">拍摄与处理 {{ Number(job.capture_seconds).toFixed(1) }} 秒<span v-if="uploadSeconds"> · 本次上传与交接 {{ uploadSeconds.toFixed(1) }} 秒</span></small>
        </div>
        <div class="station__actions">
            <el-button v-if="!completed && job.state !== 'done' && job.state !== 'error'" type="primary" :loading="shooting" :disabled="uploading || !capabilities.ready" @click="shoot">
                {{ job.state === 'wait_flip' ? '已翻面，继续拍摄' : job.inspection_id ? '继续自动拍摄' : '开始自动拍摄' }}
            </el-button>
            <el-button v-if="shooting" @click="stopRequested = true">拍完当前张后暂停</el-button>
            <el-button v-if="job.inspection_id && !completed" :disabled="busy" @click="stopAndUsePhone">停止自动拍摄，手机补拍</el-button>
            <el-button v-if="!job.inspection_id" text @click="$emit('fallback')">工位不可用？用手机拍摄</el-button>
            <el-checkbox v-if="errorCode === 'empty_tray'" v-model="skipObjectCheck">已检查托盘有设备，本次继续</el-checkbox>
        </div>

        <template v-if="job.photos?.length">
            <div class="station__selection"><span>{{ completed ? '本轮已确认；需要更换图片时，请开始新一轮拍摄。' : '默认保留全部，取消勾选不需要的图片。' }}</span><el-button v-if="!completed" text :disabled="busy" @click="selected = orderedPhotos.map(p => p.id); persist()">全选</el-button></div>
            <div class="station__photos">
                <article v-for="photo in orderedPhotos" :key="photo.id" :class="{ selected: selected.includes(photo.id) }">
                    <el-image v-if="previews[photo.id]" :src="previews[photo.id]" fit="contain" :preview-src-list="Object.values(previews)" />
                    <div v-else class="station__placeholder">{{ previewFailures[photo.id] ? '预览失败，可重试' : '正在加载预览…' }}</div>
                    <div class="station__photo-label"><el-checkbox :model-value="selected.includes(photo.id)" :disabled="busy || !!completed" @change="toggle(photo.id)">{{ faceName(photo.face) }} {{ photo.angle }}°</el-checkbox></div>
                    <div class="station__photo-tools">
                        <el-button text size="small" :disabled="busy || !!completed" @click="makeCover(photo.id)">{{ selected[0] === photo.id ? '首图' : '设为首图' }}</el-button>
                        <el-button v-if="photo.has_original" text size="small" @click="viewOriginal(photo)">看原图</el-button>
                        <el-button v-if="previewFailures[photo.id]" text size="small" @click="loadPreview(photo)">重试预览</el-button>
                    </div>
                </article>
            </div>
            <div class="station__submit">
                <small>{{ isErp ? '提交会用选中图片替换 ERP 当前销售图片；不会改采购成本、销售价格，也不会自动上架。' : '确认选中的商品图片后，交给本应用定价。' }}</small>
                <el-button v-if="!completed" type="primary" :disabled="shooting || !selected.length || capabilities.simulated || job.simulated" :loading="uploading" @click="submit">
                    {{ uploading ? uploadHint : `采用 ${selected.length} 张，${isErp ? '提交 ERP' : '完成拍摄'}` }}
                </el-button>
                <el-button v-else type="primary" @click="$emit('completed')">拍下一台</el-button>
            </div>
        </template>
        <el-dialog v-model="originalVisible" title="处理前源图" width="min(900px, 94vw)" append-to-body><p v-if="job.profile === 'hdr'">当前为 HDR 合成源图，各曝光原始帧也保存在工位本地。</p><el-image v-if="originalUrl" :src="originalUrl" fit="contain" class="station__original" /></el-dialog>
    </section>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { ElMessageBox } from 'element-plus'
import { createPhotoTask, saveAssetMedia, confirmAssetPhotos } from '../api/device_asset'
import { getToken } from '@/utils/common'
import storage from '@/utils/storage'

const props = defineProps<{ asset: Record<string, any> }>()
const emit = defineEmits(['fallback', 'completed', 'uploaded'])
const siteId = Number(storage.get('siteId') || 0)
const isErp = computed(() => Number(props.asset.ext_json?.erp_asset_id || 0) > 0)
const settingsKey = `hsx_photo_station_settings_${siteId}`
const read = (key: string) => { try { return JSON.parse(localStorage.getItem(key) || '{}') } catch { return {} } }
const settings = reactive({ url: 'http://127.0.0.1:5200', token: '', profile: 'fast', ...read(settingsKey) })
const settingsOpen = ref(settings.token ? [] : ['settings'])
const capabilities = reactive<any>({ ready: false, simulated: false })
const job = reactive<any>({ inspection_id: 0, state: 'idle', photos: [] })
const selected = ref<number[]>([])
const task = ref<any>(null)
const saved = reactive<Record<number, { url: string; mediaId?: number }>>({})
const previews = reactive<Record<number, string>>({})
const previewFailures = reactive<Record<number, boolean>>({})
const error = ref(''), errorCode = ref(''), completed = ref(''), uploadHint = ref('')
const connecting = ref(false), shooting = ref(false), uploading = ref(false), stopRequested = ref(false), skipObjectCheck = ref(false)
const originalVisible = ref(false), originalUrl = ref(''), uploadSeconds = ref(0)
let disposed = false
const busy = computed(() => shooting.value || uploading.value || connecting.value)
const normalizedUrl = () => settings.url.trim().replace(/\/+$/, '')
const cacheKey = () => `hsx_photo_job_${siteId}_${props.asset.id}_${normalizedUrl()}`
const persist = () => {
    try { localStorage.setItem(cacheKey(), JSON.stringify({ task: task.value, job, selected: selected.value, saved, completed: completed.value })) }
    catch { error.value = '浏览器未能保存恢复信息，请不要关闭当前页面；工位原图和已回传图片仍保留。' }
}
const faceName = (face: string) => (({ front: '正面', back: '背面', top: '顶部' } as Record<string, string>)[face] || face)
const orderedPhotos = computed<any[]>(() => [...(job.photos || [])].sort((a, b) => {
    const ia = selected.value.indexOf(a.id), ib = selected.value.indexOf(b.id)
    return (ia < 0 ? 9999 : ia) - (ib < 0 ? 9999 : ib) || a.id - b.id
}))
const stateLabel = computed(() => completed.value ? (isErp.value ? 'ERP 交接已确认' : '拍摄已确认') : ({
    idle: '准备：将当前设备放到拍摄台', shooting_front: shooting.value ? '正面拍摄中' : '正面已暂停，可继续拍摄', wait_flip: '正面已完成，请翻面',
    shooting_back: shooting.value ? '背面拍摄中' : '背面已暂停，可继续拍摄', done: '拍摄已结束，请选图提交', error: '自动拍摄已停止，已拍图片仍可选用或手机补拍'
} as Record<string, string>)[job.state as string] || '请检查工位状态')

async function stationRequest(path: string, body?: any, blob = false) {
    const controller = new AbortController()
    const timer = setTimeout(() => controller.abort(), body ? 120000 : 15000)
    try {
        const response = await fetch(`${normalizedUrl()}/api/erp-photo${path}`, {
            method: body === undefined ? 'GET' : 'POST', signal: controller.signal,
            headers: { 'X-Station-Token': settings.token, ...(body ? { 'Content-Type': 'application/json' } : {}) },
            body: body === undefined ? undefined : JSON.stringify({ ...body, system_origin: window.location.origin })
        })
        if (blob && response.ok) return response.blob()
        const result = await response.json()
        if (!response.ok || !result.ok) { const e: any = new Error(result.error || '工位请求失败'); e.code = result.code; throw e }
        return result.data
    } finally { clearTimeout(timer) }
}
function showError(e: any) {
    errorCode.value = e?.code || ''
    error.value = e?.name === 'AbortError' ? '等待工位超时，先连接并恢复任务确认进度；不要重复新建或重新拍摄。' : e?.msg || e?.message || '工位连接失败，请确认本机服务已启动；可用手机继续。'
}
async function offlineFallback() {
    try {
        await ElMessageBox.confirm('请先确认转台已经停止、设备可以安全取下。尚未回传的自动照片仍在工位本地；恢复连接后可继续取图，或先用手机补拍。', '安全切换到手机', { confirmButtonText: '已确认停止，用手机拍摄', cancelButtonText: '返回检查' })
        stopRequested.value = true
        emit('fallback')
    } catch { /* 未确认则保持原任务。 */ }
}
async function releaseBinding() {
    try {
        await ElMessageBox.confirm('结束当前自动拍摄绑定，原始照片与已上传图片均保留。新一轮会重新拍摄，请勿把上一台设备留在托盘。', '重新开始一轮拍摄', { confirmButtonText: '结束本轮', cancelButtonText: '继续本轮' })
        if (!['done', 'error'].includes(job.state)) await stationRequest(`/inspections/${job.inspection_id}/abort`, {})
        localStorage.removeItem(cacheKey())
        Object.values(previews).forEach(URL.revokeObjectURL)
        for (const key of Object.keys(previews)) delete previews[Number(key)]
        for (const key of Object.keys(saved)) delete saved[Number(key)]
        Object.assign(job, { inspection_id: 0, state: 'idle', photos: [], capture_seconds: 0 })
        selected.value = []; task.value = null; completed.value = ''; error.value = ''
    } catch (e) { if (e !== 'cancel' && e !== 'close') showError(e) }
}
const previewPending = new Set<number>()
async function loadPreview(photo: any) {
    if (previews[photo.id] || disposed || previewPending.has(photo.id)) return
    const inspectionId = job.inspection_id
    previewPending.add(photo.id)
    try {
        const blob = await stationRequest(`/inspections/${inspectionId}/photos/${photo.id}`, undefined, true)
        if (!disposed && job.inspection_id === inspectionId) { previews[photo.id] = URL.createObjectURL(blob); delete previewFailures[photo.id] }
    } catch { if (job.inspection_id === inspectionId) previewFailures[photo.id] = true }
    finally { previewPending.delete(photo.id) }
}
function applyJob(data: any) {
    const known = new Set((job.photos || []).map((p: any) => p.id))
    Object.assign(job, data)
    if (data.receipt?.message) completed.value = data.receipt.message
    for (const photo of job.photos || []) {
        if (!known.has(photo.id) && !selected.value.includes(photo.id)) selected.value.push(photo.id)
        void loadPreview(photo)
    }
    persist()
}
async function connect() {
    connecting.value = true; error.value = ''
    try {
        const url = new URL(normalizedUrl())
        if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password) throw new Error('请输入有效的工位服务地址')
        const caps = await stationRequest('/capabilities')
        if (caps.protocol_version !== 1) throw new Error('工位版本不匹配，请先更新 phone-inspector')
        Object.assign(capabilities, caps)
        localStorage.setItem(settingsKey, JSON.stringify(settings))
        const cached = read(cacheKey())
        if (cached.job?.inspection_id && !job.inspection_id) {
            task.value = cached.task; Object.assign(job, cached.job); Object.assign(saved, cached.saved || {}); selected.value = cached.selected || []
            completed.value = cached.completed || ''
        }
        if (job.inspection_id) {
            const restored = await stationRequest('/prepare', { ...job.context, inspection_id: job.inspection_id })
            applyJob(restored)
        }
        if (!caps.ready) error.value = caps.message
        else settingsOpen.value = []
    } catch (e) { capabilities.ready = false; showError(e) }
    finally { connecting.value = false }
}
async function prepare() {
    if (job.inspection_id) return
    if (!task.value) {
        if (capabilities.simulated) task.value = { id: 1 } // 模拟不创建 ERP 业务任务。
        else { const response: any = await createPhotoTask(props.asset.id, { source: 'auto', station_id: capabilities.station_id }); task.value = response.data }
    }
    const data = await stationRequest('/prepare', {
        site_id: siteId, asset_id: Number(props.asset.id), task_id: Number(task.value.id), profile: settings.profile,
        device: { model: props.asset.model, sn: props.asset.sn, imei: props.asset.imei, imei2: props.asset.imei2,
            capacity: props.asset.ext_json?.capacity, color: props.asset.ext_json?.color }
    })
    applyJob(data)
}
async function shoot() {
    shooting.value = true; stopRequested.value = false; error.value = ''
    try {
        await prepare()
        if (job.state === 'wait_flip') applyJob(await stationRequest(`/inspections/${job.inspection_id}/flip`, {}))
        while (!disposed && !stopRequested.value && !['wait_flip', 'done', 'error'].includes(job.state)) {
            applyJob(await stationRequest(`/inspections/${job.inspection_id}/step`, { skip_object_check: skipObjectCheck.value }))
            skipObjectCheck.value = false
        }
    } catch (e) {
        showError(e)
        if (job.inspection_id) { try { applyJob(await stationRequest(`/inspections/${job.inspection_id}`)) } catch { /* 原错误保留，可通过连接恢复。 */ } }
    } finally { shooting.value = false }
}
async function stopAndUsePhone() {
    try {
        if (selected.value.length && !job.simulated) {
            await ElMessageBox.confirm('先将已选图片回传保存，再用手机补拍；补齐后统一确认交给 ERP。原始照片仍保存在工位。', '保留照片并补拍', { confirmButtonText: '保存并手机补拍', cancelButtonText: '继续选图' })
            uploading.value = true
            await uploadSelected()
            emit('uploaded')
        }
        applyJob(await stationRequest(`/inspections/${job.inspection_id}/abort`, {}))
        emit('fallback')
    } catch (e) { if (e !== 'cancel' && e !== 'close') showError(e) }
    finally { uploading.value = false }
}
function toggle(id: number) { selected.value = selected.value.includes(id) ? selected.value.filter(p => p !== id) : [...selected.value, id]; persist() }
function makeCover(id: number) { selected.value = [id, ...selected.value.filter(p => p !== id)]; persist() }
async function viewOriginal(photo: any) {
    try {
        const blob = await stationRequest(`/inspections/${job.inspection_id}/photos/${photo.id}?variant=original`, undefined, true)
        if (originalUrl.value) URL.revokeObjectURL(originalUrl.value)
        originalUrl.value = URL.createObjectURL(blob); originalVisible.value = true
    } catch (e) { showError(e) }
}
async function uploadPhoto(photo: any) {
    const blob = await stationRequest(`/inspections/${job.inspection_id}/photos/${photo.id}`, undefined, true)
    const form = new FormData()
    form.append('file', new File([blob], `asset_${props.asset.id}_${photo.id}.jpg`, { type: 'image/jpeg' }))
    form.append('cate_id', '0'); form.append('is_attachment', '1')
    const base = import.meta.env.VITE_APP_BASE_URL.replace(/\/?$/, '/')
    const controller = new AbortController()
    const timer = setTimeout(() => controller.abort(), 120000)
    try {
        const response = await fetch(`${base}sys/image`, { method: 'POST', body: form, signal: controller.signal, headers: {
            [import.meta.env.VITE_REQUEST_HEADER_TOKEN_KEY]: String(getToken() || ''),
            [import.meta.env.VITE_REQUEST_HEADER_SITEID_KEY]: String(siteId)
        } })
        const result = await response.json()
        if (!response.ok || result.code !== 1 || !result.data?.url) throw new Error(result.msg || '图片上传失败，已完成部分保留，请重试')
        return result.data.url
    } catch (e: any) {
        if (e?.name === 'AbortError') throw new Error('图片回传超时，已确认保存的图片不会重复上传，请检查网络后重试')
        throw e
    } finally { clearTimeout(timer) }
}
async function uploadSelected(): Promise<number[]> {
    const ids: number[] = []
    for (const [index, id] of selected.value.entries()) {
        uploadHint.value = `回传 ${index + 1}/${selected.value.length}`
        const photo = job.photos.find((p: any) => p.id === id)
        if (!saved[id]?.url) { saved[id] = { url: await uploadPhoto(photo) }; persist() }
        if (!saved[id].mediaId) {
            const response: any = await saveAssetMedia(props.asset.id, { task_id: task.value.id, simulated: false, media: [{
                url: saved[id].url, source: 'auto', scene: photo.face, media_type: 'image', sort: index + 1,
                client_key: `${capabilities.station_id}:${job.inspection_id}:${id}`
            }] })
            saved[id].mediaId = Number(response.data?.saved_media?.[0]?.id || 0)
            if (!saved[id].mediaId) throw new Error('图片已上传，但业务绑定未确认，请更新服务端后重试，无需重拍')
            persist()
        }
        ids.push(saved[id].mediaId!)
    }
    return ids
}
async function submit() {
    if (capabilities.simulated || job.simulated || !selected.value.length) return
    if (job.state !== 'done') {
        try { await ElMessageBox.confirm('本次尚未完成全部角度。只采用当前选中的图片继续？缺少的角度可以先用手机补拍。', '确认使用现有图片', { confirmButtonText: '采用已选图片', cancelButtonText: '继续拍摄' }) }
        catch { return }
    }
    uploading.value = true; error.value = ''; const started = performance.now()
    try {
        const ids = await uploadSelected()
        uploadHint.value = '等待业务系统确认'
        const response: any = await confirmAssetPhotos(props.asset.id, { media_ids: ids, task_id: task.value.id, simulated: false })
        if (isErp.value && !response.data?.photo_handoff?.erp_asset_id) throw new Error('图片已保存，但 ERP 未确认接收，请更新 ERP 插件后重试')
        completed.value = response.data?.message || '图片已确认'
        try { applyJob(await stationRequest(`/inspections/${job.inspection_id}/receipt`, { photo_ids: selected.value, message: completed.value })) }
        catch { error.value = '业务系统已确认接收，本机回执暂未保存；无需重复提交，请稍后恢复工位连接。' }
        uploadSeconds.value = (performance.now() - started) / 1000
        persist()
    } catch (e) { showError(e) }
    finally { uploading.value = false }
}
onMounted(() => { if (settings.token) void connect() })
onBeforeUnmount(() => { disposed = true; stopRequested.value = true; Object.values(previews).forEach(URL.revokeObjectURL); if (originalUrl.value) URL.revokeObjectURL(originalUrl.value) })
</script>

<style scoped>
.station{display:flex;flex-direction:column;gap:14px;min-width:0}.station__head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.station p,.station small{color:#64748b;font-size:12px;line-height:1.6}.station p{margin:5px 0 0}.station__settings{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 16px}.station__settings small{display:block}.station__progress{display:flex;flex-wrap:wrap;align-items:center;gap:8px 18px;padding:14px;background:#f5f7fb;border-radius:8px}.station__progress span,.station__selection{font-size:13px;color:#64748b}.station__actions,.station__selection,.station__submit{display:flex;flex-wrap:wrap;align-items:center;gap:10px}.station__photos{display:grid;grid-template-columns:repeat(auto-fill,minmax(145px,1fr));gap:12px}.station__photos article{border:1px solid #e2e8f0;border-radius:8px;overflow:hidden}.station__photos article.selected{border-color:var(--el-color-primary)}.station__photos .el-image,.station__placeholder{width:100%;height:150px;background:#f8fafc}.station__placeholder{display:grid;place-items:center;color:#64748b;font-size:12px}.station__photo-label,.station__photo-tools{padding:6px 9px}.station__photo-tools{display:flex;justify-content:space-between;flex-wrap:wrap}.station__submit{justify-content:space-between;border-top:1px solid #e2e8f0;padding-top:14px}.station__submit small{max-width:440px}.station__original{display:block;width:100%;max-height:70vh}@media(max-width:700px){.station__settings{grid-template-columns:1fr}.station__submit .el-button{width:100%;margin:0}}
</style>
