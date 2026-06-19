<template>
    <div class="main-container device-asset-page">
        <el-card class="!border-none" shadow="never">
            <div class="page-head">
                <div class="flex items-center">
                    <div class="text-page-title">设备资产中台</div>
                    <div class=" ml-2 page-subtitle">承接 ERP 转入的设备，完成拍照、定价、归位与资料导出</div>
                </div>
                <div class="head-actions">
                    <el-button :icon="Download" :loading="exportLoading" @click="handleExport">导出 Excel</el-button>
                </div>
            </div>

            <!-- 状态(任务流)主标签：全部 / 待拍照 / 待定价 / 已完成（带数量） -->
            <el-tabs v-model="activeTaskTab" class="asset-task-tabs mt-2" @tab-change="handleTaskTab">
                <el-tab-pane name="" label="全部" />
                <el-tab-pane v-for="t in taskTabs" :key="t.key" :name="t.key" :label="`${t.label} ${t.count}`" />
            </el-tabs>
                    <el-card class="search-panel !border-none" shadow="never">
                        <el-form :inline="true" :model="assetSearch" ref="assetSearchRef">
                            <el-form-item label="关键词" prop="keyword">
                                <el-input v-model.trim="assetSearch.keyword" class="!w-[240px]" clearable placeholder="资产编号 / IMEI / 型号 / 设备ID" @keyup.enter="loadAssets" />
                            </el-form-item>
                            <template v-if="showAdvanced">
                                <el-form-item label="拍照状态" prop="photo_status">
                                    <el-select v-model="assetSearch.photo_status" clearable class="!w-[150px]" placeholder="全部">
                                        <el-option v-for="item in photoStatusOptions" :key="item.value" :label="item.label" :value="item.value" />
                                    </el-select>
                                </el-form-item>
                                <el-form-item label="定价状态" prop="price_status">
                                    <el-select v-model="assetSearch.price_status" clearable class="!w-[150px]" placeholder="全部">
                                        <el-option v-for="item in priceStatusOptions" :key="item.value" :label="item.label" :value="item.value" />
                                    </el-select>
                                </el-form-item>
                            </template>
                            <el-form-item>
                                <el-button type="primary" :icon="Search" @click="loadAssets">查询</el-button>
                                <el-button @click="resetAssetSearch">重置</el-button>
                                <el-button text type="primary" @click="showAdvanced = !showAdvanced">
                                    {{ showAdvanced ? '收起筛选' : '高级筛选' }}
                                </el-button>
                            </el-form-item>
                        </el-form>
                    </el-card>

                    <el-table
                        :data="assetTable.data"
                        v-loading="assetTable.loading"
                        size="large"
                        @selection-change="selectedAssetRows = $event"
                    >
                        <template #empty>
                            <span>{{ assetTable.loading ? '' : '暂无资产数据' }}</span>
                        </template>
                        <el-table-column type="selection" width="48" />
                        <el-table-column prop="asset_no" label="资产编号" min-width="170" show-overflow-tooltip />
                        <el-table-column prop="device_id" label="设备ID" width="90" />
                        <el-table-column label="设备信息" min-width="260" show-overflow-tooltip>
                            <template #default="{ row }">
                                <div class="asset-device-main">{{ row.model || '-' }}</div>
                                <div class="muted">IMEI {{ row.imei || '-' }} / SN {{ row.sn || '-' }}</div>
                                <div v-if="deviceSpecText(row)" class="muted">{{ deviceSpecText(row) }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column label="成本/售价" width="145" align="right">
                            <template #default="{ row }">
                                <div>成本 ¥{{ money(row.recycle_final_price) }}</div>
                                <div class="muted">售价 ¥{{ money(row.sale_price) }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column label="媒体" width="100" align="center">
                            <template #default="{ row }">
                                <el-tag size="small" type="info">图 {{ row.image_count || 0 }}</el-tag>
                                <el-tag size="small" class="ml-[4px]" type="info">视 {{ row.video_count || 0 }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="状态" width="180">
                            <template #default="{ row }">
                                <el-tag :type="statusType(row.status)" effect="light" round>{{ row.status_name || row.status }}</el-tag>
                                <div class="loc-line">
                                    <el-tag v-if="Number(row.location_id) > 0" type="success" size="small" effect="plain">库位：{{ row.location_name || ('#' + row.location_id) }}</el-tag>
                                    <el-tag v-else type="info" size="small" effect="plain">未归位</el-tag>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column prop="create_at" label="入库时间" width="170" />
                        <el-table-column label="操作" fixed="right" width="170" align="center">
                            <template #default="{ row }">
                                <!-- 主操作：当前阶段最该做的那一步 -->
                                <el-button
                                    v-if="primaryAction(row)"
                                    :type="primaryAction(row).type"
                                    size="small"
                                    :icon="primaryAction(row).icon"
                                    :loading="row._confirmingPhotos && primaryAction(row).label === '确认照片'"
                                    @click="primaryAction(row).run()"
                                >
                                    {{ primaryAction(row).label }}
                                </el-button>
                                <!-- 更多：详情/设库位 等次要操作 -->
                                <el-dropdown trigger="click" class="ml-1 align-middle">
                                    <el-button size="small" text :icon="MoreFilled" />
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            <el-dropdown-item
                                                v-for="(m, mi) in moreActions(row)"
                                                :key="mi"
                                                @click="m.run()"
                                            >
                                                {{ m.label }}
                                            </el-dropdown-item>
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>
                            </template>
                        </el-table-column>
                    </el-table>

                    <div class="pager">
                        <el-pagination
                            v-model:current-page="assetTable.page"
                            v-model:page-size="assetTable.limit"
                            layout="total, sizes, prev, pager, next, jumper"
                            :total="assetTable.total"
                            @size-change="loadAssets"
                            @current-change="loadAssets"
                        />
                    </div>
        </el-card>

        <el-drawer v-model="detailVisible" title="资产详情" size="760px" destroy-on-close>
            <div v-if="currentAsset" class="detail-wrap">
                <el-descriptions :column="2" border>
                    <el-descriptions-item label="资产编号">{{ currentAsset.asset_no }}</el-descriptions-item>
                    <el-descriptions-item label="设备ID">{{ currentAsset.device_id }}</el-descriptions-item>
                    <el-descriptions-item label="IMEI">{{ currentAsset.imei || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="SN">{{ currentAsset.sn || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="型号" :span="2">{{ currentAsset.model || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="回收价">¥{{ money(currentAsset.recycle_final_price) }}</el-descriptions-item>
                    <el-descriptions-item label="销售价">¥{{ money(currentAsset.sale_price) }}</el-descriptions-item>
                    <el-descriptions-item label="同行价">¥{{ money(currentAsset.peer_price) }}</el-descriptions-item>
                    <el-descriptions-item label="最低价">¥{{ money(currentAsset.min_price) }}</el-descriptions-item>
                </el-descriptions>

                <div class="section-title">回收质检信息</div>
                <div class="check-panel">
                    <div v-if="checkSummaryEntries(currentAsset).length" class="check-summary">
                        <div v-for="item in checkSummaryEntries(currentAsset)" :key="item.key" class="check-summary__item">
                            <span>{{ item.key }}</span>
                            <strong :class="{ 'is-clamped': isLongSummary(item.value) && !expandedSummaryKeys.has(item.key) }">{{ item.value }}</strong>
                            <a v-if="isLongSummary(item.value)" class="summary-toggle" @click="toggleSummary(item.key)">
                                {{ expandedSummaryKeys.has(item.key) ? '收起' : '展开' }}
                            </a>
                        </div>
                    </div>
                    <el-empty v-else description="暂无质检摘要" :image-size="70" />
                    <div v-if="recycleCheckImages(currentAsset).length" class="check-images">
                        <el-image
                            v-for="(url, index) in recycleCheckImages(currentAsset)"
                            :key="`${ url }-${ index }`"
                            :src="imgUrl(url)"
                            fit="cover"
                            :preview-src-list="recycleCheckImages(currentAsset).map(imgUrl)"
                        />
                    </div>
                </div>

                <div class="section-title">图片/视频</div>
                <div class="detail-actions">
                    <el-button :icon="Refresh" @click="refreshCurrentDetail">刷新</el-button>
                    <el-button
                        v-if="canConfirmPhotos(currentAsset)"
                        type="warning"
                        :icon="CircleCheck"
                        :loading="currentAsset._confirmingPhotos"
                        @click="handleConfirmPhotos(currentAsset, true)"
                    >
                        确认照片完成
                    </el-button>
                </div>
                <div v-if="currentAsset.media?.length" class="media-grid">
                    <div v-for="item in currentAsset.media" :key="item.id" class="media-card">
                        <el-image v-if="item.media_type !== 'video'" :src="imgUrl(item.url)" fit="cover" :preview-src-list="[imgUrl(item.url)]" />
                        <div v-else class="video-box">视频</div>
                        <div class="media-meta">
                            <el-tag size="small" :type="mediaStatusType(item.status)">{{ item.status_name || item.status }}</el-tag>
                            <span>{{ item.scene || 'common' }}</span>
                        </div>
                        <div v-if="item.status !== 'rejected'" class="media-actions">
                            <el-button size="small" type="danger" link @click="reviewMedia(item, 'rejected')">删除</el-button>
                        </div>
                    </div>
                </div>
                <el-empty v-else description="暂无图片/视频" />

                <div class="section-title">操作日志</div>
                <el-timeline>
                    <el-timeline-item v-for="log in currentAsset.logs || []" :key="log.id" :timestamp="log.create_at">
                        <div>{{ log.action_name || log.action }}</div>
                        <div class="muted">{{ log.operator_name || log.operator_uid || '系统' }}</div>
                    </el-timeline-item>
                </el-timeline>
            </div>
        </el-drawer>

        <el-dialog v-model="mediaDialogVisible" title="商品图片拍摄" width="860px" destroy-on-close>
            <div v-if="mediaAsset" class="dialog-body">
                <div class="photo-workflow">
                    <div v-for="item in photoWorkflowSteps(mediaAsset)" :key="item.key" class="photo-workflow__step" :class="{ active: item.active, done: item.done }">
                        <span>{{ item.index }}</span>
                        <div>
                            <strong>{{ item.title }}</strong>
                            <small>{{ item.desc }}</small>
                        </div>
                    </div>
                </div>
                <div class="mobile-capture-panel">
                    <div class="mobile-capture-panel__qr">
                        <img v-if="mobileQrCode" :src="mobileQrCode" alt="移动拍照二维码" />
                        <div v-else class="qr-empty">生成中</div>
                    </div>
                    <div class="mobile-capture-panel__content">
                        <div class="mobile-capture-panel__title">手机扫码拍照并回传</div>
                        <div class="mobile-capture-panel__desc">扫码后手机会进入当前资产的拍照页。照片先上传到系统 OSS，再回传到当前资产，PC 端会自动刷新，也可以手动刷新。</div>
                        <div class="mobile-capture-panel__status">
                            <el-tag :type="mediaAsset.image_count > 0 ? 'success' : 'warning'">{{ mediaAsset.image_count || 0 }} 张商品图</el-tag>
                            <el-tag :type="mediaAsset.photo_status === 'approved' ? 'success' : 'info'">{{ mediaAsset.photo_status_name || mediaAsset.photo_status }}</el-tag>
                            <span>{{ mobileSyncHint }}</span>
                        </div>
                        <div class="mobile-capture-panel__actions">
                            <el-button :icon="Refresh" :loading="mediaRefreshLoading" @click="refreshMediaAsset">刷新回传</el-button>
                            <el-button link type="primary" @click="copyMobileCaptureUrl">复制链接</el-button>
                        </div>
                    </div>
                </div>

                <div class="capture-gallery">
                    <div class="capture-gallery__head">
                        <div>
                            <strong>已回传商品图片</strong>
                            <small>拍照员删掉不清晰的，留下的即为可用图，确认后进入定价。</small>
                        </div>
                        <div class="capture-gallery__actions">
                            <el-checkbox v-model="showDiscardedMedia">显示已删除</el-checkbox>
                            <el-button size="small" :disabled="!selectableMediaList.length" @click="selectAllVisibleMedia">全选</el-button>
                            <el-button size="small" :disabled="!selectableMediaList.length" @click="invertVisibleMediaSelection">反选</el-button>
                            <el-button size="small" :disabled="!selectedMediaIds.length" @click="selectedMediaIds = []">清空</el-button>
                            <el-button size="small" type="danger" :disabled="!selectedMediaIds.length" @click="reviewSelectedMedia('rejected')">删除选中</el-button>
                            <el-button size="small" type="warning" :disabled="!canConfirmPhotos(mediaAsset)" @click="handleConfirmPhotos(mediaAsset, false)">确认拍照完成</el-button>
                        </div>
                    </div>
                    <div v-if="visibleMediaList.length" class="capture-media-grid">
                        <div v-for="item in visibleMediaList" :key="item.id" class="capture-media-card" :class="[item.status, { selected: selectedMediaIds.includes(item.id) }]">
                            <el-checkbox
                                v-if="item.status !== 'rejected'"
                                class="capture-media-card__check"
                                :model-value="selectedMediaIds.includes(item.id)"
                                @change="toggleMediaSelection(item.id)"
                            />
                            <el-image v-if="item.media_type !== 'video'" :src="imgUrl(item.url)" fit="cover" :preview-src-list="visibleMediaList.filter((media: any) => media.media_type !== 'video').map((media: any) => imgUrl(media.url))" />
                            <div v-else class="video-box">视频</div>
                            <div class="capture-media-card__meta">
                                <el-tag size="small" :type="mediaStatusType(item.status)">{{ item.status_name || item.status }}</el-tag>
                                <span>{{ sceneName(item.scene) }} / {{ sourceName(item.source) }}</span>
                            </div>
                            <div class="capture-media-card__actions">
                                <el-button size="small" type="danger" link :disabled="item.status === 'rejected'" @click="reviewDialogMedia(item, 'rejected')">删除</el-button>
                            </div>
                        </div>
                    </div>
                    <el-empty v-else description="等待手机扫码拍照上传" :image-size="72" />
                </div>

                <el-form label-width="96px" class="mt-[16px]">
                    <el-form-item label="图片链接">
                        <el-input
                            v-model.trim="mediaForm.url"
                            type="textarea"
                            :rows="3"
                            placeholder="兜底补录：每行一个图片/视频 URL。正常情况建议用手机扫码拍照。"
                        />
                    </el-form-item>
                    <el-form-item label="媒体类型">
                        <el-radio-group v-model="mediaForm.media_type">
                            <el-radio-button label="image">图片</el-radio-button>
                            <el-radio-button label="video">视频</el-radio-button>
                        </el-radio-group>
                    </el-form-item>
                    <el-form-item label="拍摄场景">
                        <el-select v-model="mediaForm.scene" class="!w-[220px]">
                            <el-option label="通用" value="common" />
                            <el-option label="正面" value="front" />
                            <el-option label="背面" value="back" />
                            <el-option label="侧边" value="side" />
                            <el-option label="瑕疵" value="flaw" />
                            <el-option label="视频" value="video" />
                        </el-select>
                    </el-form-item>
                </el-form>

                <el-collapse class="auto-camera-collapse">
                    <el-collapse-item name="auto">
                        <template #title>
                            <span>自动拍照设备</span>
                            <el-tag class="ml-[8px]" size="small" :type="localCamera.connected ? 'success' : 'info'">{{ localCamera.connected ? '已连接' : '可选' }}</el-tag>
                        </template>
                        <div class="auto-camera-panel">
                            <div class="auto-camera-panel__head">
                                <div>
                                    <div class="auto-camera-panel__title">自动拍照设备</div>
                                    <div class="auto-camera-panel__desc">设备拍完后会先同步到系统 OSS，再进入上面的商品图片列表，体验和手机拍照一致。</div>
                                </div>
                                <el-tag :type="localCamera.connected ? 'success' : 'info'">
                                    {{ localCamera.connected ? '已连接' : '未检测' }}
                                </el-tag>
                            </div>
                            <div class="auto-camera-panel__controls">
                                <el-input v-model.trim="localCamera.serviceUrl" class="auto-camera-panel__url" placeholder="本地服务地址，如 http://127.0.0.1:5200" />
                                <el-select
                                    v-model="localCamera.activePresetId"
                                    class="auto-camera-panel__preset"
                                    placeholder="相机预设"
                                    :loading="localCamera.presetLoading"
                                    @change="applyLocalCameraPreset(true)"
                                >
                                    <el-option
                                        v-for="preset in localCamera.presets"
                                        :key="preset.id"
                                        :label="preset.name"
                                        :value="preset.id"
                                    >
                                        <div class="auto-camera-panel__preset-option">
                                            <span>{{ preset.name }}</span>
                                            <small>{{ preset.description }}</small>
                                        </div>
                                    </el-option>
                                </el-select>
                                <el-button :icon="Refresh" :loading="localCamera.checking" @click="checkLocalCameraService">检测服务</el-button>
                                <el-button type="primary" :icon="Camera" :loading="localCamera.shooting" @click="handleAutoShoot">
                                    {{ localCamera.state === 'wait_flip' ? '确认翻面后继续' : '启动/继续拍摄' }}
                                </el-button>
                                <el-button type="success" :loading="localCamera.syncing" :disabled="!localCamera.photos.length" @click="syncAutoPhotosToAsset">
                                    同步照片 {{ localCamera.photos.length ? `(${ localCamera.photos.length })` : '' }}
                                </el-button>
                            </div>
                            <div v-if="localCamera.error" class="auto-camera-panel__error">{{ localCamera.error }}</div>
                            <div v-if="localCamera.hardware" class="auto-camera-panel__status">
                                <span>相机：{{ localCamera.hardware.camera_front || '-' }}</span>
                                <span>电机：{{ localCamera.hardware.motor || '-' }}</span>
                                <span>灯光：{{ localCamera.hardware.light || '-' }}</span>
                                <span>状态：{{ localCamera.state || 'idle' }}</span>
                            </div>
                            <div v-if="localCamera.photos.length" class="auto-camera-photos">
                                <div v-for="photo in localCamera.photos" :key="photo.id" class="auto-camera-photo">
                                    <el-image :src="localPhotoUrl(photo.id)" fit="cover" :preview-src-list="localCamera.photos.map(item => localPhotoUrl(item.id))" />
                                    <span>{{ photo.face || 'photo' }} {{ photo.angle ?? '' }}°</span>
                                </div>
                            </div>
                        </div>
                    </el-collapse-item>
                </el-collapse>
            </div>
            <template #footer>
                <el-button @click="mediaDialogVisible = false">取消</el-button>
                <el-button :loading="photoTaskLoading" @click="handleCreatePhotoTask">生成手机拍照任务</el-button>
                <el-button :disabled="!mediaForm.url" type="primary" :loading="mediaSaveLoading" @click="handleSaveMedia">保存补录链接</el-button>
                <el-button type="success" :disabled="!canConfirmPhotos(mediaAsset)" @click="handleConfirmPhotos(mediaAsset, false)">确认进入定价</el-button>
            </template>
        </el-dialog>

        <PriceDialog v-model="priceDialogVisible" :asset-row="priceRow" @success="onPriceSuccess" />

        <SetLocationDialog v-model="locationDialogVisible" :asset="locationDialogAsset" @success="loadAssets" />
    </div>
</template>

<script lang="ts" setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { ElMessage, ElMessageBox, FormInstance } from 'element-plus'
import { Camera, CircleCheck, Download, Money, Refresh, Search, MoreFilled } from '@element-plus/icons-vue'
import QRCode from 'qrcode'
import {
    confirmAssetPhotos,
    createPhotoTask,
    exportAssetExcel,
    getAssetInfo,
    getAssetList,
    getAssetStats,
    reviewAssetMedia,
    reviewAssetMediaBatch,
    saveAssetMedia,
    rePushAsset
} from '@/addon/hsx_device_asset/api/device_asset'
import { getToken, img } from '@/utils/common'
import storage from '@/utils/storage'
import SetLocationDialog from './components/SetLocationDialog.vue'
import PriceDialog from './components/PriceDialog.vue'
import { useAssetFormat } from './composables/useAssetFormat'

// 共享格式化/判定工具（同名解构，模板与方法里的调用点完全不变）
const {
    money, imgUrl,
    statusType, mediaStatusType,
    checkSummaryEntries, recycleCheckImages, deviceSpecText,
    canConfirmPhotos, canUploadMedia, canPrice,
    expandedSummaryKeys, isLongSummary, toggleSummary
} = useAssetFormat()

const locationDialogVisible = ref(false)
const locationDialogAsset = ref<Record<string, any> | null>(null)
const openLocationDialog = (row: Record<string, any>) => {
    locationDialogAsset.value = row
    locationDialogVisible.value = true
}
const activeTab = ref('asset')
const activeTaskTab = ref('photo')
const taskStats = reactive({ pending: 0, pool: 0, photo: 0, price: 0, completed: 0, total: 0 })
const exportLoading = ref(false)

const assetSearchRef = ref<FormInstance>()
const assetSearch = reactive({ keyword: '', status: '', photo_status: '', price_status: '', task_type: 'photo' })
const showAdvanced = ref(false)

const assetTable = reactive({ data: [] as any[], total: 0, page: 1, limit: 10, loading: false })
const selectedAssetRows = ref<any[]>([])

const detailVisible = ref(false)
const currentAsset = ref<any>(null)
const mediaDialogVisible = ref(false)
const mediaAsset = ref<any>(null)
const mobileCaptureUrl = ref('')
const mobileQrCode = ref('')
const photoTaskLoading = ref(false)
const mediaSaveLoading = ref(false)
const mediaRefreshLoading = ref(false)
let mediaRefreshTimer: ReturnType<typeof setInterval> | null = null
const selectedMediaIds = ref<number[]>([])
const showDiscardedMedia = ref(false)
const photoTaskForm = reactive({ station_id: '', camera_job_id: '' })
const mediaForm = reactive({ url: '', media_type: 'image', scene: 'common' })
const activePhotoTask = ref<any>(null)
const localCamera = reactive({
    serviceUrl: localStorage.getItem('hsx_device_asset_camera_url') || 'http://127.0.0.1:5200',
    connected: false,
    checking: false,
    shooting: false,
    syncing: false,
    error: '',
    state: 'idle',
    inspectionId: 0,
    hardware: null as Record<string, string> | null,
    photos: [] as any[],
    presets: [] as any[],
    activePresetId: 0,
    presetLoading: false
})

const priceDialogVisible = ref(false)
const priceRow = ref<Record<string, any> | null>(null)
const onPriceSuccess = () => { Promise.all([loadAssets(), loadStats()]) }

// 重新推送：把已定价资产最新数据再发一次事件，商城据此更新货源
// 按工作流阶段决定「主操作」（与 nextActionLabel 同口径），其余收进「更多」
const primaryAction = (row: any) => {
    const noImg = Number(row.image_count || 0) <= 0
    if (canUploadMedia(row) && (row.photo_status === 'rejected' || row.status === 'photo_rejected')) {
        return { label: '重新补图', icon: Camera, type: 'primary', run: () => openMediaDialog(row) }
    }
    if (canUploadMedia(row) && (['wait_photo', 'photoing'].includes(row.photo_status) || noImg)) {
        return { label: '拍照/补图', icon: Camera, type: 'primary', run: () => openMediaDialog(row) }
    }
    if (canConfirmPhotos(row)) {
        return { label: '确认照片', icon: CircleCheck, type: 'warning', run: () => handleConfirmPhotos(row) }
    }
    if (canPrice(row)) {
        return { label: '定价', icon: Money, type: 'success', run: () => openPriceDialog(row) }
    }
    if (row.price_status === 'completed') {
        return { label: '重新推送', icon: Refresh, type: 'primary', run: () => handleRePush(row) }
    }
    return null
}

// 「更多」里的次要操作（排除已作为主操作的那个）
const moreActions = (row: any) => {
    const p = primaryAction(row)
    const pk = p ? p.label : ''
    const items: Array<{ label: string; run: () => void }> = [
        { label: '详情', run: () => openDetail(row) },
        { label: '设库位', run: () => openLocationDialog(row) },
    ]
    if (canUploadMedia(row) && pk !== '拍照/补图' && pk !== '重新补图') {
        items.push({ label: '拍照/补图', run: () => openMediaDialog(row) })
    }
    if (canConfirmPhotos(row) && pk !== '确认照片') {
        items.push({ label: '确认照片', run: () => handleConfirmPhotos(row) })
    }
    if (canPrice(row) && pk !== '定价') {
        items.push({ label: '定价', run: () => openPriceDialog(row) })
    }
    if (row.price_status === 'completed' && pk !== '重新推送') {
        items.push({ label: '重新推送', run: () => handleRePush(row) })
    }
    return items
}

const handleRePush = (row: Record<string, any>) => {
    ElMessageBox.confirm('将把该设备最新的定价/型号/质检/图片重新推送给商城（幂等更新货源），是否继续？', '重新推送', {
        confirmButtonText: '推送',
        cancelButtonText: '取消',
        type: 'info'
    }).then(() => {
        rePushAsset(row.id).then(() => {
            ElMessage.success('已重新推送到商城')
        })
    }).catch(() => {})
}

const assetStatusOptions = [
    { label: '待拍照', value: 'wait_photo' },
    { label: '拍照中', value: 'photoing' },
    { label: '待确认', value: 'photo_review' },
    { label: '图片已删除', value: 'photo_rejected' },
    { label: '待定价', value: 'wait_price' },
    { label: '待导出', value: 'ready_export' },
    { label: '已导出', value: 'exported' }
]
const photoStatusOptions = [
    { label: '待拍照', value: 'wait_photo' },
    { label: '拍照中', value: 'photoing' },
    { label: '待确认', value: 'review' },
    { label: '已删除', value: 'rejected' },
    { label: '图片通过', value: 'approved' }
]
const priceStatusOptions = [
    { label: '待定价', value: 'wait_price' },
    { label: '待处理', value: 'pending' },
    { label: '已完成', value: 'completed' }
]

// 中台只做拍照+定价（入库是 ERP 的事，设备由 ERP 自动转入）：任务流 待拍照 → 待定价 → 已完成
const taskTabs = computed(() => [
    { key: 'photo', label: '待拍照', count: taskStats.photo },
    { key: 'price', label: '待定价', count: taskStats.price },
    { key: 'completed', label: '已完成', count: taskStats.completed },
])
const pageGrossProfit = computed(() => {
    return assetTable.data.reduce((total, item) => total + Number(item.sale_price || 0) - Number(item.recycle_final_price || 0), 0)
})
const visibleMediaList = computed(() => {
    const media = mediaAsset.value?.media || []
    return showDiscardedMedia.value ? media : media.filter((item: any) => item.status !== 'rejected')
})
const selectableMediaList = computed(() => visibleMediaList.value.filter((item: any) => item.status !== 'rejected'))
const mobileSyncHint = computed(() => {
    if (!mediaAsset.value) return ''
    if (mediaAsset.value.photo_status === 'approved') return '照片已确认，可以进入定价'
    if (Number(mediaAsset.value.image_count || 0) > 0) return '图片已回传，删掉不清晰的后点确认拍照完成'
    return '等待手机扫码拍照上传'
})

onMounted(() => {
    loadAssets()
    loadStats()
})

onUnmounted(() => {
    stopMediaAutoRefresh()
})

watch(mediaDialogVisible, (visible) => {
    visible ? startMediaAutoRefresh() : stopMediaAutoRefresh()
})

const loadStats = async () => {
    const res: any = await getAssetStats()
    Object.assign(taskStats, res.data || {})
}

const handleTaskTab = async (taskType: string) => {
    activeTaskTab.value = taskType
    selectedAssetRows.value = []
    activeTab.value = 'asset'
    assetSearch.task_type = taskType
    assetSearch.status = ''
    assetSearch.photo_status = ''
    assetSearch.price_status = ''
    assetTable.page = 1
    await loadAssets()
}

const loadAssets = async () => {
    assetTable.loading = true
    try {
        const res: any = await getAssetList({ ...assetSearch, page: assetTable.page, limit: assetTable.limit })
        assetTable.data = res.data?.data || []
        assetTable.total = res.data?.total || 0
    } finally {
        assetTable.loading = false
    }
}

const handleTabChange = () => {
    loadAssets()
}

const resetAssetSearch = () => {
    assetSearchRef.value?.resetFields()
    assetTable.page = 1
    loadAssets()
}


const openDetail = async (row: any) => {
    const res: any = await getAssetInfo(row.id)
    currentAsset.value = res.data
    detailVisible.value = true
}

const openMediaDialog = async (row: any) => {
    const res: any = await getAssetInfo(row.id)
    mediaAsset.value = res.data || row
    activePhotoTask.value = null
    photoTaskForm.station_id = ''
    photoTaskForm.camera_job_id = ''
    mediaForm.url = ''
    mediaForm.media_type = 'image'
    mediaForm.scene = 'common'
    selectedMediaIds.value = []
    showDiscardedMedia.value = false
    localCamera.error = ''
    localCamera.photos = []
    localCamera.state = 'idle'
    localCamera.inspectionId = Number(localStorage.getItem(localInspectionKey(row.id)) || 0)
    mediaDialogVisible.value = true
    generateMobileCaptureQr(mediaAsset.value)
    loadLocalCameraPresets()
}

const generateMobileCaptureQr = async (asset: any) => {
    mobileQrCode.value = ''
    const path = `/wap/#/addon/hsx_device_asset/pages/photo/capture?id=${ asset.id }`
    mobileCaptureUrl.value = `${ window.location.origin }${ path }`
    mobileQrCode.value = await QRCode.toDataURL(mobileCaptureUrl.value, {
        errorCorrectionLevel: 'L',
        margin: 1,
        width: 180
    })
}

const copyMobileCaptureUrl = async () => {
    if (!mobileCaptureUrl.value) return
    try {
        await navigator.clipboard.writeText(mobileCaptureUrl.value)
        ElMessage.success('拍照链接已复制')
    } catch {
        ElMessage.warning(mobileCaptureUrl.value)
    }
}

const refreshMediaAsset = async () => {
    if (!mediaAsset.value?.id) return
    mediaRefreshLoading.value = true
    try {
        const res: any = await getAssetInfo(mediaAsset.value.id)
        mediaAsset.value = res.data
        const ids = new Set((mediaAsset.value.media || []).map((item: any) => Number(item.id)))
        selectedMediaIds.value = selectedMediaIds.value.filter(id => ids.has(Number(id)))
        await loadAssets()
    } finally {
        mediaRefreshLoading.value = false
    }
}

const startMediaAutoRefresh = () => {
    stopMediaAutoRefresh()
    mediaRefreshTimer = setInterval(() => {
        if (mediaDialogVisible.value && mediaAsset.value?.id) refreshMediaAsset()
    }, 5000)
}

const stopMediaAutoRefresh = () => {
    if (!mediaRefreshTimer) return
    clearInterval(mediaRefreshTimer)
    mediaRefreshTimer = null
}

const handleCreatePhotoTask = async () => {
    if (!mediaAsset.value?.id) return
    photoTaskLoading.value = true
    try {
        const res: any = await createPhotoTask(mediaAsset.value.id, { ...photoTaskForm, source: 'pc' })
        activePhotoTask.value = res.data || null
        ElMessage.success('拍照任务已创建')
        await loadAssets()
    } finally {
        photoTaskLoading.value = false
    }
}

const localInspectionKey = (assetId: number) => `hsx_device_asset_camera_inspection_${ assetId }`

const normalizeLocalCameraUrl = () => {
    const url = (localCamera.serviceUrl || '').trim().replace(/\/+$/, '')
    localCamera.serviceUrl = url || 'http://127.0.0.1:5200'
    localStorage.setItem('hsx_device_asset_camera_url', localCamera.serviceUrl)
    return localCamera.serviceUrl
}

const localCameraRequest = async (path: string, options: RequestInit = {}) => {
    const headers: Record<string, string> = options.body ? { 'Content-Type': 'application/json' } : {}
    const response = await fetch(`${ normalizeLocalCameraUrl() }${ path }`, {
        ...options,
        headers: { ...headers, ...(options.headers as Record<string, string> || {}) }
    })
    if (!response.ok) throw new Error(`HTTP ${ response.status }`)
    return await response.json()
}

const checkLocalCameraService = async () => {
    localCamera.checking = true
    localCamera.error = ''
    try {
        const res = await localCameraRequest('/api/hw/status')
        localCamera.connected = !!res.ok
        localCamera.hardware = res.devices || null
        await loadLocalCameraPresets()
        await refreshLocalCameraState(false)
        ElMessage.success('本地拍照服务连接正常')
    } catch (error: any) {
        localCamera.connected = false
        localCamera.hardware = null
        localCamera.error = '本地拍照服务未连接。请确认拍照电脑已启动服务，并允许后台页面访问该服务。'
    } finally {
        localCamera.checking = false
    }
}

const loadLocalCameraPresets = async () => {
    localCamera.presetLoading = true
    try {
        const res = await localCameraRequest('/api/camera/presets')
        localCamera.presets = res.data || []
        const active = localCamera.presets.find((item: any) => item.is_active)
        localCamera.activePresetId = Number(active?.id || localCamera.presets[0]?.id || 0)
    } catch {
        localCamera.presets = []
        localCamera.activePresetId = 0
    } finally {
        localCamera.presetLoading = false
    }
}

const applyLocalCameraPreset = async (showMessage = false) => {
    if (!localCamera.activePresetId) return
    const res = await localCameraRequest(`/api/camera/presets/${ localCamera.activePresetId }/apply`, { method: 'POST' })
    if (!res.ok) throw new Error(res.error || '应用相机预设失败')
    if (showMessage) ElMessage.success(`已应用相机预设：${ res.data?.name || '' }`)
}

const refreshLocalCameraState = async (showMessage = true) => {
    try {
        const res = await localCameraRequest('/api/shoot/status')
        localCamera.state = res.state || 'idle'
        if (showMessage) ElMessage.success('拍照状态已刷新')
    } catch {
        localCamera.state = 'idle'
    }
}

const ensureLocalInspection = async () => {
    if (!mediaAsset.value?.id) throw new Error('缺少资产信息')
    if (localCamera.inspectionId > 0) return localCamera.inspectionId
    const asset = mediaAsset.value
    const res = await localCameraRequest('/api/inspection', {
        method: 'POST',
        body: JSON.stringify({
            input_method: 'manual',
            brand: guessBrand(asset.model),
            model_name: asset.model || asset.asset_no,
            serial_number: asset.sn || '',
            imei: asset.imei || '',
            imei2: asset.imei2 || '',
            storage: '',
            color: '',
            device_type: guessDeviceType(asset.model)
        })
    })
    if (!res.ok || !res.inspection_id) throw new Error(res.error || '创建本地拍照记录失败')
    localCamera.inspectionId = Number(res.inspection_id)
    localStorage.setItem(localInspectionKey(asset.id), String(localCamera.inspectionId))
    return localCamera.inspectionId
}

const ensureAssetPhotoTask = async () => {
    if (activePhotoTask.value?.id) return activePhotoTask.value
    if (!mediaAsset.value?.id) throw new Error('缺少资产信息')
    const res: any = await createPhotoTask(mediaAsset.value.id, {
        station_id: photoTaskForm.station_id,
        camera_job_id: localCamera.inspectionId ? String(localCamera.inspectionId) : '',
        source: 'auto',
        remark: `本地拍照服务：${ normalizeLocalCameraUrl() }`
    })
    activePhotoTask.value = res.data || null
    return activePhotoTask.value
}

const handleAutoShoot = async () => {
    if (!mediaAsset.value?.id) return
    localCamera.shooting = true
    localCamera.error = ''
    try {
        const inspectionId = await ensureLocalInspection()
        await ensureAssetPhotoTask()
        await applyLocalCameraPreset(false)
        const status = await localCameraRequest('/api/shoot/status')
        localCamera.state = status.state || 'idle'
        if (localCamera.state === 'wait_flip') {
            await ElMessageBox.confirm('当前正面已拍完，请把设备翻面后继续。确认已经翻面了吗？', '继续自动拍照', {
                type: 'warning',
                confirmButtonText: '已翻面，继续',
                cancelButtonText: '取消'
            })
            await localCameraRequest('/api/shoot/flip', { method: 'POST' })
        }
        const res = await localCameraRequest('/api/shoot/start', {
            method: 'POST',
            body: JSON.stringify({ inspection_id: inspectionId, force: true })
        })
        if (!res.ok) throw new Error(res.error || '启动拍摄失败')
        mergeLocalPhotos(res.photos || [])
        localCamera.state = res.state || localCamera.state
        ElMessage.success(localCamera.state === 'wait_flip' ? '当前面拍摄完成，请翻面后继续' : '拍摄完成')
    } catch (error: any) {
        localCamera.error = error?.message || '自动拍照失败'
    } finally {
        localCamera.shooting = false
    }
}

const mergeLocalPhotos = (photos: any[]) => {
    const exists = new Set(localCamera.photos.map(item => Number(item.id)))
    photos.forEach(photo => {
        if (!exists.has(Number(photo.id))) localCamera.photos.push(photo)
    })
}

const localPhotoUrl = (photoId: number) => `${ normalizeLocalCameraUrl() }/api/photo/${ photoId }`

const uploadLocalPhotoToNiucloud = async (photo: any) => {
    const blobRes = await fetch(localPhotoUrl(photo.id))
    if (!blobRes.ok) throw new Error(`读取本地照片失败：${ photo.id }`)
    const blob = await blobRes.blob()
    const formData = new FormData()
    formData.append('file', new File([blob], `asset_${ mediaAsset.value.id }_${ photo.face || 'photo' }_${ photo.angle ?? photo.id }.jpg`, { type: blob.type || 'image/jpeg' }))
    formData.append('cate_id', '0')
    formData.append('is_attachment', '1')

    const headers: Record<string, string> = {}
    headers[import.meta.env.VITE_REQUEST_HEADER_TOKEN_KEY] = String(getToken() || '')
    headers[import.meta.env.VITE_REQUEST_HEADER_SITEID_KEY] = String(storage.get('siteId') || 0)
    const baseURL = import.meta.env.VITE_APP_BASE_URL.substr(-1) === '/' ? import.meta.env.VITE_APP_BASE_URL : `${ import.meta.env.VITE_APP_BASE_URL }/`
    const uploadRes = await fetch(`${ baseURL }sys/image`, {
        method: 'POST',
        headers,
        body: formData
    })
    const uploadJson = await uploadRes.json()
    if (!uploadJson || uploadJson.code !== 1) throw new Error(uploadJson?.msg || '上传照片失败')
    return uploadJson.data?.url || ''
}

const syncAutoPhotosToAsset = async () => {
    if (!mediaAsset.value?.id || !localCamera.photos.length) return
    localCamera.syncing = true
    localCamera.error = ''
    try {
        const task = await ensureAssetPhotoTask()
        const media = []
        for (let index = 0; index < localCamera.photos.length; index++) {
            const photo = localCamera.photos[index]
            const url = await uploadLocalPhotoToNiucloud(photo)
            if (url) {
                media.push({
                    url,
                    media_type: 'image',
                    scene: photo.face || 'auto',
                    source: 'auto',
                    task_id: task?.id || 0,
                    sort: index + 1
                })
            }
        }
        if (!media.length) throw new Error('没有可同步的照片')
        await saveAssetMedia(mediaAsset.value.id, { task_id: task?.id || 0, media })
        ElMessage.success(`已同步 ${ media.length } 张自动拍照图片`)
        localCamera.photos = []
        await refreshMediaAsset()
    } catch (error: any) {
        localCamera.error = error?.message || '同步照片失败'
    } finally {
        localCamera.syncing = false
    }
}

const guessBrand = (model = '') => {
    const text = String(model).toLowerCase()
    if (text.includes('iphone') || text.includes('ipad')) return 'Apple'
    if (text.includes('huawei') || text.includes('mate') || text.includes('pura')) return 'Huawei'
    if (text.includes('xiaomi') || text.includes('redmi')) return 'Xiaomi'
    return ''
}

const guessDeviceType = (model = '') => {
    const text = String(model).toLowerCase()
    if (text.includes('iphone') || text.includes('ipad')) return 'iOS'
    if (text.includes('android')) return 'Android'
    return 'Unknown'
}

const handleSaveMedia = async () => {
    if (!mediaAsset.value?.id) return
    const urls = mediaForm.url.split(/\n|,/).map(item => item.trim()).filter(Boolean)
    if (!urls.length) {
        ElMessage.warning('请填写图片或视频链接')
        return
    }
    mediaSaveLoading.value = true
    try {
        await saveAssetMedia(mediaAsset.value.id, {
            media: urls.map((url, index) => ({
                url,
                media_type: mediaForm.media_type,
                scene: mediaForm.scene,
                source: 'manual',
                sort: index + 1
            }))
        })
        ElMessage.success('媒体已保存')
        mediaForm.url = ''
        await refreshMediaAsset()
        await loadAssets()
    } finally {
        mediaSaveLoading.value = false
    }
}

const reviewMedia = async (item: any, status: 'approved' | 'rejected') => {
    let rejectReason = ''
    if (status === 'rejected') {
        try {
            const { value } = await ElMessageBox.prompt('删除后该图片不展示、不参与导出。可填写删除原因。', '删除图片', {
                confirmButtonText: '确认删除',
                cancelButtonText: '取消',
                inputPlaceholder: '如：反光、模糊、角度不完整'
            })
            rejectReason = value || ''
        } catch {
            return
        }
    }
    await reviewAssetMedia(item.id, { status, reject_reason: rejectReason })
    ElMessage.success(status === 'approved' ? '已通过' : '已删除')
    if (currentAsset.value?.id) await openDetail(currentAsset.value)
    await loadAssets()
}

const reviewDialogMedia = async (item: any, status: 'approved' | 'rejected') => {
    await reviewMedia(item, status)
    if (mediaAsset.value?.id) await refreshMediaAsset()
}

const toggleMediaSelection = (mediaId: number) => {
    const id = Number(mediaId)
    selectedMediaIds.value = selectedMediaIds.value.includes(id)
        ? selectedMediaIds.value.filter(item => item !== id)
        : [...selectedMediaIds.value, id]
}

const selectAllVisibleMedia = () => {
    selectedMediaIds.value = selectableMediaList.value.map((item: any) => Number(item.id))
}

const invertVisibleMediaSelection = () => {
    const selected = new Set(selectedMediaIds.value.map(Number))
    selectedMediaIds.value = selectableMediaList.value
        .map((item: any) => Number(item.id))
        .filter((id: number) => !selected.has(id))
}

const reviewSelectedMedia = async (status: 'approved' | 'rejected') => {
    if (!mediaAsset.value?.id || !selectedMediaIds.value.length) return
    let rejectReason = ''
    if (status === 'rejected') {
        try {
            const { value } = await ElMessageBox.prompt('这些图片将被标记为已丢弃，默认不展示、不参与导出。可填写丢弃原因。', '丢弃图片', {
                confirmButtonText: '确认丢弃',
                cancelButtonText: '取消',
                inputPlaceholder: '如：反光、模糊、角度不完整'
            })
            rejectReason = value || ''
        } catch {
            return
        }
    }
    const count = selectedMediaIds.value.length
    await reviewAssetMediaBatch(mediaAsset.value.id, {
        media_ids: selectedMediaIds.value,
        status,
        reject_reason: rejectReason
    })
    selectedMediaIds.value = []
    ElMessage.success(status === 'approved' ? `已通过 ${ count } 张图片` : `已丢弃 ${ count } 张图片`)
    await refreshMediaAsset()
    await loadAssets()
}

const handleConfirmPhotos = async (asset: any, refreshDetail = false) => {
    if (!asset?.id || !canConfirmPhotos(asset)) return
    asset._confirmingPhotos = true
    try {
        await confirmAssetPhotos(asset.id)
        ElMessage.success('照片已确认，资产进入定价环节')
        await Promise.all([loadAssets(), loadStats()])
        if (refreshDetail) {
            const res: any = await getAssetInfo(asset.id)
            currentAsset.value = res.data
        }
        if (mediaAsset.value?.id === asset.id) {
            await refreshMediaAsset()
        }
    } finally {
        asset._confirmingPhotos = false
    }
}

const photoWorkflowSteps = (asset: any) => {
    const hasMedia = Number(asset?.image_count || 0) > 0
    const reviewed = asset?.photo_status === 'approved'
    const priced = asset?.price_status === 'completed'
    return [
        { key: 'capture', index: 1, title: '拍摄商品图', desc: hasMedia ? `已回传 ${ asset.image_count || 0 } 张` : '手机扫码拍照上传', done: hasMedia, active: !hasMedia },
        { key: 'review', index: 2, title: '确认拍照完成', desc: reviewed ? '照片已确认' : '删掉不清晰的，确认后进入定价', done: reviewed, active: hasMedia && !reviewed },
        { key: 'price', index: 3, title: '生成定价工单', desc: priced ? '定价已完成' : '确认后进入定价', done: priced, active: reviewed && !priced },
    ]
}

const sceneName = (scene = '') => {
    return ({
        front: '正面',
        back: '背面',
        side: '边框',
        flaw: '瑕疵',
        mobile: '手机拍摄',
        auto: '自动拍摄',
        common: '通用',
        video: '视频',
    } as Record<string, string>)[scene] || scene || '通用'
}

const sourceName = (source = '') => {
    return ({
        mobile: '手机',
        auto: '自动设备',
        manual: '人工补录',
        pc: 'PC',
    } as Record<string, string>)[source] || source || '未知'
}

const openPriceDialog = (row: any) => {
    priceRow.value = row
    priceDialogVisible.value = true
}

const handleExport = async () => {
    exportLoading.value = true
    try {
        const params = selectedAssetRows.value.length ? { asset_ids: selectedAssetRows.value.map(item => item.id) } : { ...assetSearch }
        const blob: any = await exportAssetExcel(params)
        const url = window.URL.createObjectURL(new Blob([blob]))
        const link = document.createElement('a')
        link.href = url
        link.download = `设备资产导出_${ new Date().toISOString().slice(0, 10) }.xlsx`
        link.click()
        window.URL.revokeObjectURL(url)
        ElMessage.success('导出成功')
        await loadAssets()
    } finally {
        exportLoading.value = false
    }
}

const refreshCurrentDetail = async () => {
    if (!currentAsset.value?.id) return
    const res: any = await getAssetInfo(currentAsset.value.id)
    currentAsset.value = res.data
    await loadAssets()
    ElMessage.success('详情已刷新')
}
</script>

<style lang="scss" scoped>
.device-asset-page {
    .page-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 18px;
    }

    .page-subtitle {
        margin-top: 6px;
        color: var(--el-text-color-secondary);
        font-size: 13px;
    }

    .head-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .scan-input {
        width: 320px;
    }

    .task-tabbar {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 16px;
    }

    .task-tab {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 16px;
        border: 1px solid var(--el-border-color-light);
        border-radius: 8px;
        color: var(--el-text-color-regular);
        background: var(--el-bg-color);
        cursor: pointer;
        transition: all 0.2s;

        strong {
            font-size: 20px;
        }
    }

    .task-tab.active {
        border-color: var(--el-color-primary);
        color: var(--el-color-primary);
        background: var(--el-color-primary-light-9);
        box-shadow: 0 0 0 1px var(--el-color-primary-light-7);
    }

    .asset-tabs :deep(.el-tabs__header) {
        display: none;
    }



    .pager {
        display: flex;
        justify-content: flex-end;
        margin-top: 16px;
    }

    .status-line {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }

    .asset-device-main {
        color: var(--el-text-color-primary);
        font-weight: 650;
        line-height: 1.5;
    }

    .table-summary {
        display: flex;
        flex-direction: column;
        gap: 3px;
        color: var(--el-text-color-secondary);
        font-size: 12px;
        line-height: 1.35;
    }

    .next-action {
        margin-top: 6px;
        color: var(--el-text-color-secondary);
        font-size: 12px;
        line-height: 1.35;
    }

    .muted {
        color: var(--el-text-color-secondary);
        font-size: 12px;
    }

    .detail-wrap {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    .section-title {
        font-weight: 600;
        color: var(--el-text-color-primary);
        margin-top: 6px;
    }

    .detail-actions {
        display: flex;
        justify-content: flex-end;
        margin-top: -8px;
        gap: 8px;
    }

    .check-panel,
    .price-context {
        border: 1px solid var(--el-border-color-light);
        border-radius: 8px;
        padding: 12px;
        background: var(--el-bg-color-page);
    }

    .check-summary {
        display: grid;
        grid-template-columns: 1fr;
        gap: 8px;
    }

    .check-summary__item {
        display: flex;
        flex-direction: column;
        gap: 4px;
        padding: 8px 12px;
        border-radius: 6px;
        background: var(--el-bg-color);
        color: var(--el-text-color-secondary);
        font-size: 12px;

        strong {
            color: var(--el-text-color-primary);
            font-weight: 600;
            text-align: left;
            line-height: 1.6;
            word-break: break-word;
            white-space: pre-wrap;

            &.is-clamped {
                display: -webkit-box;
                -webkit-line-clamp: 4;
                -webkit-box-orient: vertical;
                overflow: hidden;
                white-space: normal;
            }
        }

        .summary-toggle {
            align-self: flex-start;
            color: var(--el-color-primary);
            font-size: 12px;
            cursor: pointer;
        }
    }

    .mini-summary > div {
        display: flex;
        flex-direction: column;
        gap: 4px;
        padding: 7px 10px;
        border-radius: 6px;
        background: var(--el-bg-color);
        color: var(--el-text-color-secondary);
        font-size: 12px;

        strong {
            color: var(--el-text-color-primary);
            font-weight: 600;
            word-break: break-word;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-align: left;
        }
    }

    .check-images {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 8px;
        margin-top: 12px;

        .el-image {
            width: 100%;
            aspect-ratio: 1;
            border-radius: 6px;
            background: var(--el-bg-color);
        }
    }

    .media-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }

    .media-card {
        border: 1px solid var(--el-border-color-light);
        border-radius: 8px;
        padding: 8px;
        background: var(--el-bg-color);

        .el-image,
        .video-box {
            width: 100%;
            aspect-ratio: 1;
            border-radius: 6px;
            background: var(--el-bg-color-page);
        }
    }

    .video-box {
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--el-text-color-secondary);
    }

    .media-meta,
    .media-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-top: 8px;
        color: var(--el-text-color-secondary);
        font-size: 12px;
    }

    .dialog-body {
        min-height: 260px;
    }

    .photo-workflow {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 14px;
    }

    .photo-workflow__step {
        display: flex;
        gap: 10px;
        align-items: flex-start;
        min-height: 78px;
        padding: 12px;
        border: 1px solid var(--el-border-color-light);
        border-radius: 8px;
        background: var(--el-bg-color-page);

        > span {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            flex: 0 0 26px;
            border-radius: 50%;
            background: var(--el-fill-color);
            color: var(--el-text-color-secondary);
            font-size: 13px;
            font-weight: 700;
        }

        strong {
            display: block;
            color: var(--el-text-color-primary);
            font-size: 14px;
            line-height: 1.4;
        }

        small {
            display: block;
            margin-top: 4px;
            color: var(--el-text-color-secondary);
            line-height: 1.4;
        }
    }

    .photo-workflow__step.active {
        border-color: var(--el-color-primary-light-5);
        background: var(--el-color-primary-light-9);

        > span {
            background: var(--el-color-primary);
            color: #fff;
        }
    }

    .photo-workflow__step.done {
        border-color: var(--el-color-success-light-5);

        > span {
            background: var(--el-color-success);
            color: #fff;
        }
    }

    .mobile-capture-panel {
        display: grid;
        grid-template-columns: 132px 1fr;
        gap: 14px;
        margin-top: 14px;
        padding: 12px;
        border: 1px dashed var(--el-border-color);
        border-radius: 8px;
        background: var(--el-bg-color-page);
    }

    .mobile-capture-panel__qr {
        width: 132px;
        height: 132px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        background: #fff;

        img {
            width: 120px;
            height: 120px;
        }
    }

    .qr-empty {
        color: var(--el-text-color-secondary);
        font-size: 12px;
    }

    .mobile-capture-panel__title {
        font-weight: 650;
        color: var(--el-text-color-primary);
    }

    .mobile-capture-panel__desc {
        margin-top: 8px;
        color: var(--el-text-color-secondary);
        line-height: 1.6;
        font-size: 13px;
    }

    .mobile-capture-panel__actions {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 14px;
    }

    .mobile-capture-panel__status {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 12px;
        color: var(--el-text-color-secondary);
        font-size: 13px;
    }

    .capture-gallery {
        margin-top: 14px;
        padding: 12px;
        border: 1px solid var(--el-border-color-light);
        border-radius: 8px;
        background: var(--el-bg-color);
    }

    .capture-gallery__head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;

        strong {
            display: block;
            color: var(--el-text-color-primary);
            font-size: 14px;
        }

        small {
            display: block;
            margin-top: 4px;
            color: var(--el-text-color-secondary);
            line-height: 1.45;
        }
    }

    .capture-gallery__actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .capture-media-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 10px;
    }

    .capture-media-card {
        position: relative;
        padding: 8px;
        border: 1px solid var(--el-border-color-light);
        border-radius: 8px;
        background: var(--el-bg-color-page);

        .el-image,
        .video-box {
            width: 100%;
            aspect-ratio: 1;
            border-radius: 6px;
            background: var(--el-bg-color);
        }
    }

    .capture-media-card.approved {
        border-color: var(--el-color-success-light-5);
    }

    .capture-media-card.selected {
        border-color: var(--el-color-primary);
        box-shadow: 0 0 0 2px var(--el-color-primary-light-8);
    }

    .capture-media-card.rejected {
        border-color: var(--el-color-danger-light-5);
        opacity: 0.72;
    }

    .capture-media-card__check {
        position: absolute;
        z-index: 2;
        top: 10px;
        left: 10px;
        padding: 2px 5px;
        border-radius: 6px;
        background: rgba(255, 255, 255, 0.88);
    }

    .capture-media-card__meta,
    .capture-media-card__actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 6px;
        margin-top: 7px;
        color: var(--el-text-color-secondary);
        font-size: 12px;
    }

    .auto-camera-collapse {
        margin-top: 14px;
    }

    .auto-camera-panel {
        margin-top: 0;
        padding: 12px;
        border: 1px solid var(--el-border-color-light);
        border-radius: 8px;
        background: var(--el-bg-color);
    }

    .auto-camera-panel__head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
    }

    .auto-camera-panel__title {
        color: var(--el-text-color-primary);
        font-weight: 650;
    }

    .auto-camera-panel__desc {
        margin-top: 6px;
        color: var(--el-text-color-secondary);
        font-size: 13px;
        line-height: 1.5;
    }

    .auto-camera-panel__controls {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 12px;
    }

    .auto-camera-panel__url {
        width: 260px;
    }

    .auto-camera-panel__preset {
        width: 168px;
    }

    .auto-camera-panel__preset-option {
        display: flex;
        flex-direction: column;
        line-height: 1.35;

        small {
            color: var(--el-text-color-secondary);
            font-size: 12px;
        }
    }

    .auto-camera-panel__error {
        margin-top: 10px;
        color: var(--el-color-danger);
        font-size: 13px;
        line-height: 1.5;
    }

    .auto-camera-panel__status {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 14px;
        margin-top: 10px;
        color: var(--el-text-color-secondary);
        font-size: 12px;
    }

    .auto-camera-photos {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 8px;
        margin-top: 12px;
    }

    .auto-camera-photo {
        position: relative;

        .el-image {
            width: 100%;
            aspect-ratio: 1;
            border-radius: 6px;
            background: var(--el-bg-color-page);
        }

        span {
            position: absolute;
            left: 4px;
            bottom: 4px;
            max-width: calc(100% - 8px);
            padding: 1px 5px;
            border-radius: 4px;
            background: var(--el-mask-color-extra-light);
            color: var(--el-text-color-primary);
            font-size: 11px;
            line-height: 16px;
        }
    }

    .price-context {
        margin-bottom: 16px;
    }

    .price-profit-panel {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 14px;
        padding: 12px;
        border-radius: 8px;
        background: var(--el-bg-color-page);

        > div:not(.price-warnings) {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            color: var(--el-text-color-secondary);
            font-size: 13px;

            strong {
                color: var(--el-text-color-primary);
                font-size: 18px;
            }

            .danger {
                color: var(--el-color-danger);
            }
        }
    }

    .price-warnings {
        grid-column: 1 / -1;
        display: grid;
        gap: 8px;
    }

    .price-context__head {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
    }

    .price-context__title {
        color: var(--el-text-color-primary);
        font-size: 16px;
        font-weight: 700;
    }

    .price-context__cols {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .context-title {
        margin-bottom: 8px;
        color: var(--el-text-color-primary);
        font-size: 13px;
        font-weight: 650;
    }

    .mini-summary {
        display: grid;
        gap: 6px;
    }

    .mini-images {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 6px;

        .el-image {
            width: 100%;
            aspect-ratio: 1;
            border-radius: 6px;
            background: var(--el-bg-color);
        }
    }
}

@media (max-width: 1200px) {
    .device-asset-page {
        .page-head {
            flex-direction: column;
        }

        .head-actions {
            justify-content: flex-start;
        }

        .stat-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
}
</style>

<!-- 弹窗 teleport 到 body，用非 scoped 全局样式按弹窗类名定位，保证定高/滚动一定生效 -->
<style lang="scss">
/* 完成定价弹窗：主体定高可滚、底部按钮固定，统一交互观感 */
.da-form-dialog {
    .el-dialog__body {
        padding-top: 12px;
        padding-bottom: 8px;
    }

    .da-dialog-body {
        max-height: 56vh;
        overflow-y: auto;
        padding-right: 6px;
    }

    .da-dialog-body::-webkit-scrollbar {
        width: 6px;
    }

    .da-dialog-body::-webkit-scrollbar-thumb {
        border-radius: 6px;
        background: var(--el-border-color);
    }
}
</style>
