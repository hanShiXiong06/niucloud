<template>
    <div class="main-container device-asset-page">
        <el-card class="!border-none" shadow="never">
            <div class="page-head">
                <div>
                    <div class="text-page-title">设备资产中台</div>
                    <div class="page-subtitle">承接回收完成设备，完成入库、拍照复检、定价和资料导出</div>
                </div>
                <div class="head-actions">
                    <el-input
                        v-model.trim="scanKeyword"
                        class="scan-input"
                        clearable
                        placeholder="扫码导入 device_id / IMEI / SN"
                        @keyup.enter="handleScanImport"
                    >
                        <template #prefix>
                            <el-icon><Aim /></el-icon>
                        </template>
                    </el-input>
                    <el-button type="primary" :icon="Plus" :loading="scanLoading" @click="handleScanImport">扫码入库</el-button>
                    <el-button :icon="Download" :loading="exportLoading" @click="handleExport">导出 Excel</el-button>
                </div>
            </div>

            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-label">待入库</div>
                    <div class="stat-value">{{ poolTable.total }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">资产总数</div>
                    <div class="stat-value">{{ assetTable.total }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">待拍照/复检</div>
                    <div class="stat-value">{{ pendingPhotoCount }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">待定价/导出</div>
                    <div class="stat-value">{{ pendingPriceCount }}</div>
                </div>
            </div>

            <el-tabs v-model="activeTab" class="asset-tabs" @tab-change="handleTabChange">
                <el-tab-pane label="待入库设备" name="pool">
                    <el-card class="search-panel !border-none" shadow="never">
                        <el-form :inline="true" :model="poolSearch" ref="poolSearchRef">
                            <el-form-item label="关键词" prop="keyword">
                                <el-input v-model.trim="poolSearch.keyword" class="!w-[240px]" clearable placeholder="IMEI / SN / 型号 / 设备ID" @keyup.enter="loadPool" />
                            </el-form-item>
                            <el-form-item label="回收时间" prop="update_at">
                                <el-date-picker
                                    v-model="poolSearch.update_at"
                                    type="daterange"
                                    value-format="YYYY-MM-DD"
                                    range-separator="至"
                                    start-placeholder="开始日期"
                                    end-placeholder="结束日期"
                                    clearable
                                />
                            </el-form-item>
                            <el-form-item>
                                <el-button type="primary" :icon="Search" @click="loadPool">查询</el-button>
                                <el-button @click="resetPoolSearch">重置</el-button>
                                <el-button type="success" :disabled="selectedPoolRows.length === 0" :loading="importLoading" @click="handleBatchImport">
                                    导入选中 {{ selectedPoolRows.length ? `(${ selectedPoolRows.length })` : '' }}
                                </el-button>
                            </el-form-item>
                        </el-form>
                    </el-card>

                    <el-table
                        :data="poolTable.data"
                        v-loading="poolTable.loading"
                        size="large"
                        @selection-change="selectedPoolRows = $event"
                    >
                        <template #empty>
                            <span>{{ poolTable.loading ? '' : '暂无待入库设备' }}</span>
                        </template>
                        <el-table-column type="selection" width="48" />
                        <el-table-column prop="id" label="设备ID" width="90" />
                        <el-table-column prop="imei" label="IMEI" min-width="150" show-overflow-tooltip />
                        <el-table-column prop="sn" label="SN" min-width="130" show-overflow-tooltip />
                        <el-table-column prop="model" label="型号" min-width="180" show-overflow-tooltip />
                        <el-table-column prop="category_name" label="分类" width="120" />
                        <el-table-column label="回收价" width="110" align="right">
                            <template #default="{ row }">¥{{ money(row.final_price) }}</template>
                        </el-table-column>
                        <el-table-column prop="status_name" label="回收状态" width="110" align="center">
                            <template #default="{ row }">
                                <el-tag type="success">{{ row.status_name || '-' }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="update_at" label="回收时间" width="170" />
                        <el-table-column label="操作" fixed="right" width="110" align="center">
                            <template #default="{ row }">
                                <el-button type="primary" link :icon="Plus" :loading="row._importing" @click="handleSingleImport(row)">入库</el-button>
                            </template>
                        </el-table-column>
                    </el-table>

                    <div class="pager">
                        <el-pagination
                            v-model:current-page="poolTable.page"
                            v-model:page-size="poolTable.limit"
                            layout="total, sizes, prev, pager, next, jumper"
                            :total="poolTable.total"
                            @size-change="loadPool"
                            @current-change="loadPool"
                        />
                    </div>
                </el-tab-pane>

                <el-tab-pane label="资产流转" name="asset">
                    <el-card class="search-panel !border-none" shadow="never">
                        <el-form :inline="true" :model="assetSearch" ref="assetSearchRef">
                            <el-form-item label="关键词" prop="keyword">
                                <el-input v-model.trim="assetSearch.keyword" class="!w-[240px]" clearable placeholder="资产编号 / IMEI / 型号 / 设备ID" @keyup.enter="loadAssets" />
                            </el-form-item>
                            <el-form-item label="资产状态" prop="status">
                                <el-select v-model="assetSearch.status" clearable class="!w-[150px]" placeholder="全部">
                                    <el-option v-for="item in assetStatusOptions" :key="item.value" :label="item.label" :value="item.value" />
                                </el-select>
                            </el-form-item>
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
                            <el-form-item>
                                <el-button type="primary" :icon="Search" @click="loadAssets">查询</el-button>
                                <el-button @click="resetAssetSearch">重置</el-button>
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
                        <el-table-column prop="imei" label="IMEI" min-width="150" show-overflow-tooltip />
                        <el-table-column prop="model" label="型号" min-width="170" show-overflow-tooltip />
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
                        <el-table-column label="状态" width="210">
                            <template #default="{ row }">
                                <div class="status-line">
                                    <el-tag :type="statusType(row.status)">{{ row.status_name || row.status }}</el-tag>
                                    <el-tag :type="photoStatusType(row.photo_status)" effect="plain">{{ row.photo_status_name || row.photo_status }}</el-tag>
                                    <el-tag :type="priceStatusType(row.price_status)" effect="plain">{{ row.price_status_name || row.price_status }}</el-tag>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column prop="create_at" label="入库时间" width="170" />
                        <el-table-column label="操作" fixed="right" width="330" align="center">
                            <template #default="{ row }">
                                <el-button type="primary" link :icon="View" @click="openDetail(row)">详情</el-button>
                                <el-button v-if="canUploadMedia(row)" type="primary" link :icon="Camera" @click="openMediaDialog(row)">
                                    {{ row.photo_status === 'rejected' ? '重新补图' : '拍照/补图' }}
                                </el-button>
                                <el-button
                                    v-if="canConfirmPhotos(row)"
                                    type="warning"
                                    link
                                    :icon="CircleCheck"
                                    :loading="row._confirmingPhotos"
                                    @click="handleConfirmPhotos(row)"
                                >
                                    确认照片
                                </el-button>
                                <el-button v-if="canPrice(row)" type="success" link :icon="Money" @click="openPriceDialog(row)">定价</el-button>
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
                </el-tab-pane>
            </el-tabs>
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
                            <strong>{{ item.value }}</strong>
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
                        <div class="media-actions">
                            <el-button size="small" type="success" link @click="reviewMedia(item, 'approved')">通过</el-button>
                            <el-button size="small" type="danger" link @click="reviewMedia(item, 'rejected')">退回</el-button>
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

        <el-dialog v-model="mediaDialogVisible" title="拍照/补图" width="720px" destroy-on-close>
            <div v-if="mediaAsset" class="dialog-body">
                <el-alert type="info" :closable="false" show-icon>
                    <template #title>
                        当前资产：{{ mediaAsset.asset_no }}，自动拍照服务可用任务号回传图片；人工补图可直接粘贴 OSS 图片链接。
                    </template>
                </el-alert>
                <div class="mobile-capture-panel">
                    <div class="mobile-capture-panel__qr">
                        <img v-if="mobileQrCode" :src="mobileQrCode" alt="移动拍照二维码" />
                        <div v-else class="qr-empty">生成中</div>
                    </div>
                    <div class="mobile-capture-panel__content">
                        <div class="mobile-capture-panel__title">手机扫码拍照</div>
                        <div class="mobile-capture-panel__desc">扫码后会自动绑定当前资产，手机拍照上传后，PC 端点击刷新即可看到图片。</div>
                        <div class="mobile-capture-panel__actions">
                            <el-button :icon="Refresh" @click="refreshMediaAsset">刷新图片</el-button>
                            <el-button link type="primary" @click="copyMobileCaptureUrl">复制链接</el-button>
                        </div>
                    </div>
                </div>
                <div class="auto-camera-panel">
                    <div class="auto-camera-panel__head">
                        <div>
                            <div class="auto-camera-panel__title">自动拍照设备</div>
                            <div class="auto-camera-panel__desc">在拍照电脑打开本地服务后，可直接启动设备拍摄并同步到当前资产。</div>
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
                <el-form label-width="96px" class="mt-[16px]">
                    <el-form-item label="拍照工位">
                        <el-input v-model.trim="photoTaskForm.station_id" placeholder="如 camera-01，可为空" />
                    </el-form-item>
                    <el-form-item label="外部任务号">
                        <el-input v-model.trim="photoTaskForm.camera_job_id" placeholder="本地拍照服务任务ID，可为空" />
                    </el-form-item>
                    <el-form-item label="图片链接">
                        <el-input
                            v-model.trim="mediaForm.url"
                            type="textarea"
                            :rows="4"
                            placeholder="每行一个图片/视频 URL，自动拍照服务回传 OSS 后也走这里保存"
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
            </div>
            <template #footer>
                <el-button @click="mediaDialogVisible = false">取消</el-button>
                <el-button :loading="photoTaskLoading" @click="handleCreatePhotoTask">创建拍照任务</el-button>
                <el-button type="primary" :loading="mediaSaveLoading" @click="handleSaveMedia">保存媒体</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="priceDialogVisible" title="完成定价" width="560px" destroy-on-close>
            <div v-if="priceAsset" class="price-context">
                <div class="price-context__head">
                    <div>
                        <div class="price-context__title">{{ priceAsset.model || priceAsset.asset_no }}</div>
                        <div class="muted">成本 ¥{{ money(priceAsset.recycle_final_price) }} / IMEI {{ priceAsset.imei || '-' }}</div>
                    </div>
                    <el-tag :type="photoStatusType(priceAsset.photo_status)">{{ priceAsset.photo_status_name || priceAsset.photo_status }}</el-tag>
                </div>
                <div class="price-context__cols">
                    <div>
                        <div class="context-title">质检摘要</div>
                        <div v-if="checkSummaryEntries(priceAsset).length" class="mini-summary">
                            <div v-for="item in checkSummaryEntries(priceAsset).slice(0, 8)" :key="item.key">
                                <span>{{ item.key }}</span>
                                <strong>{{ item.value }}</strong>
                            </div>
                        </div>
                        <el-empty v-else description="暂无摘要" :image-size="48" />
                    </div>
                    <div>
                        <div class="context-title">图片对比</div>
                        <div class="mini-images">
                            <el-image
                                v-for="(url, index) in priceCompareImages(priceAsset)"
                                :key="`${ url }-${ index }`"
                                :src="imgUrl(url)"
                                fit="cover"
                                :preview-src-list="priceCompareImages(priceAsset).map(imgUrl)"
                            />
                        </div>
                    </div>
                </div>
            </div>
            <el-form :model="priceForm" label-width="90px">
                <el-form-item label="销售价" required>
                    <el-input-number v-model="priceForm.sale_price" :min="0" :precision="2" class="!w-full" />
                </el-form-item>
                <el-form-item label="同行价">
                    <el-input-number v-model="priceForm.peer_price" :min="0" :precision="2" class="!w-full" />
                </el-form-item>
                <el-form-item label="最低价">
                    <el-input-number v-model="priceForm.min_price" :min="0" :precision="2" class="!w-full" />
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model.trim="priceForm.remark" type="textarea" :rows="3" placeholder="成色、渠道、底价原因等" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="priceDialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="priceLoading" @click="handleCompletePrice">保存定价</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script lang="ts" setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox, FormInstance } from 'element-plus'
import { Aim, Camera, CircleCheck, Download, Money, Plus, Refresh, Search, View } from '@element-plus/icons-vue'
import QRCode from 'qrcode'
import {
    completeAssetPrice,
    confirmAssetPhotos,
    createPhotoTask,
    exportAssetExcel,
    getAssetInfo,
    getAssetList,
    getAssetPool,
    importAssets,
    reviewAssetMedia,
    saveAssetMedia,
    scanImportAsset
} from '@/addon/hsx_device_asset/api/device_asset'
import { getToken, img } from '@/utils/common'
import storage from '@/utils/storage'

const activeTab = ref('pool')
const scanKeyword = ref('')
const scanLoading = ref(false)
const importLoading = ref(false)
const exportLoading = ref(false)

const poolSearchRef = ref<FormInstance>()
const assetSearchRef = ref<FormInstance>()
const poolSearch = reactive({ keyword: '', update_at: [] as string[] })
const assetSearch = reactive({ keyword: '', status: '', photo_status: '', price_status: '' })

const poolTable = reactive({ data: [] as any[], total: 0, page: 1, limit: 10, loading: false })
const assetTable = reactive({ data: [] as any[], total: 0, page: 1, limit: 10, loading: false })
const selectedPoolRows = ref<any[]>([])
const selectedAssetRows = ref<any[]>([])

const detailVisible = ref(false)
const currentAsset = ref<any>(null)
const mediaDialogVisible = ref(false)
const mediaAsset = ref<any>(null)
const mobileCaptureUrl = ref('')
const mobileQrCode = ref('')
const photoTaskLoading = ref(false)
const mediaSaveLoading = ref(false)
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
const priceAsset = ref<any>(null)
const priceLoading = ref(false)
const priceForm = reactive({ sale_price: 0, peer_price: 0, min_price: 0, remark: '' })

const assetStatusOptions = [
    { label: '待拍照', value: 'wait_photo' },
    { label: '拍照中', value: 'photoing' },
    { label: '待复检', value: 'photo_review' },
    { label: '图片退回', value: 'photo_rejected' },
    { label: '待定价', value: 'wait_price' },
    { label: '待导出', value: 'ready_export' },
    { label: '已导出', value: 'exported' }
]
const photoStatusOptions = [
    { label: '待拍照', value: 'wait_photo' },
    { label: '拍照中', value: 'photoing' },
    { label: '待复检', value: 'review' },
    { label: '复检退回', value: 'rejected' },
    { label: '图片通过', value: 'approved' }
]
const priceStatusOptions = [
    { label: '待定价', value: 'wait_price' },
    { label: '待处理', value: 'pending' },
    { label: '已完成', value: 'completed' }
]

const pendingPhotoCount = computed(() => {
    return assetTable.data.filter(item => ['wait_photo', 'photoing', 'photo_review', 'photo_rejected'].includes(item.status)).length
})
const pendingPriceCount = computed(() => {
    return assetTable.data.filter(item => ['wait_price', 'ready_export'].includes(item.status)).length
})

onMounted(() => {
    loadPool()
    loadAssets()
})

const loadPool = async () => {
    poolTable.loading = true
    try {
        const res: any = await getAssetPool({ ...poolSearch, page: poolTable.page, limit: poolTable.limit })
        poolTable.data = res.data?.data || []
        poolTable.total = res.data?.total || 0
    } finally {
        poolTable.loading = false
    }
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
    activeTab.value === 'pool' ? loadPool() : loadAssets()
}

const resetPoolSearch = () => {
    poolSearchRef.value?.resetFields()
    poolTable.page = 1
    loadPool()
}

const resetAssetSearch = () => {
    assetSearchRef.value?.resetFields()
    assetTable.page = 1
    loadAssets()
}

const handleBatchImport = async () => {
    if (!selectedPoolRows.value.length) return
    importLoading.value = true
    try {
        await importAssets(selectedPoolRows.value.map(item => item.id))
        ElMessage.success('资产导入成功')
        await Promise.all([loadPool(), loadAssets()])
        activeTab.value = 'asset'
    } finally {
        importLoading.value = false
    }
}

const handleSingleImport = async (row: any) => {
    row._importing = true
    try {
        await importAssets([row.id])
        ElMessage.success('资产导入成功')
        await Promise.all([loadPool(), loadAssets()])
        activeTab.value = 'asset'
    } finally {
        row._importing = false
    }
}

const handleScanImport = async () => {
    if (!scanKeyword.value) {
        ElMessage.warning('请先扫码或输入设备码')
        return
    }
    scanLoading.value = true
    try {
        await scanImportAsset(scanKeyword.value)
        ElMessage.success('扫码入库成功')
        scanKeyword.value = ''
        await Promise.all([loadPool(), loadAssets()])
        activeTab.value = 'asset'
    } finally {
        scanLoading.value = false
    }
}

const openDetail = async (row: any) => {
    const res: any = await getAssetInfo(row.id)
    currentAsset.value = res.data
    detailVisible.value = true
}

const openMediaDialog = (row: any) => {
    mediaAsset.value = row
    activePhotoTask.value = null
    photoTaskForm.station_id = ''
    photoTaskForm.camera_job_id = ''
    mediaForm.url = ''
    mediaForm.media_type = 'image'
    mediaForm.scene = 'common'
    localCamera.error = ''
    localCamera.photos = []
    localCamera.state = 'idle'
    localCamera.inspectionId = Number(localStorage.getItem(localInspectionKey(row.id)) || 0)
    mediaDialogVisible.value = true
    generateMobileCaptureQr(row)
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
    const res: any = await getAssetInfo(mediaAsset.value.id)
    mediaAsset.value = res.data
    await loadAssets()
    ElMessage.success('已刷新')
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
        ElMessage.success('媒体已保存，等待复检')
        mediaDialogVisible.value = false
        await loadAssets()
    } finally {
        mediaSaveLoading.value = false
    }
}

const reviewMedia = async (item: any, status: 'approved' | 'rejected') => {
    let rejectReason = ''
    if (status === 'rejected') {
        try {
            const { value } = await ElMessageBox.prompt('请填写退回原因', '图片复检', {
                confirmButtonText: '退回',
                cancelButtonText: '取消',
                inputPlaceholder: '如：反光、模糊、角度不完整'
            })
            rejectReason = value || ''
        } catch {
            return
        }
    }
    await reviewAssetMedia(item.id, { status, reject_reason: rejectReason })
    ElMessage.success(status === 'approved' ? '已通过' : '已退回')
    if (currentAsset.value?.id) await openDetail(currentAsset.value)
    await loadAssets()
}

const canConfirmPhotos = (asset: any) => {
    if (!asset) return false
    if (asset.photo_status === 'approved') return false
    if (asset.status === 'exported' || asset.status === 'archived') return false
    return Number(asset.image_count || 0) > 0
}

const canUploadMedia = (asset: any) => {
    if (!asset) return false
    if (asset.status === 'exported' || asset.status === 'archived') return false
    return ['wait_photo', 'photoing', 'photo_review', 'photo_rejected'].includes(asset.status)
}

const canPrice = (asset: any) => {
    if (!asset) return false
    if (asset.status === 'exported' || asset.status === 'archived') return false
    return asset.photo_status === 'approved' || ['wait_price', 'ready_export'].includes(asset.status)
}

const handleConfirmPhotos = async (asset: any, refreshDetail = false) => {
    if (!asset?.id || !canConfirmPhotos(asset)) return
    asset._confirmingPhotos = true
    try {
        await confirmAssetPhotos(asset.id)
        ElMessage.success('照片已确认，资产进入定价环节')
        await loadAssets()
        if (refreshDetail) {
            const res: any = await getAssetInfo(asset.id)
            currentAsset.value = res.data
        }
    } finally {
        asset._confirmingPhotos = false
    }
}

const openPriceDialog = async (row: any) => {
    const res: any = await getAssetInfo(row.id)
    priceAsset.value = res.data
    priceForm.sale_price = Number(priceAsset.value.sale_price || 0)
    priceForm.peer_price = Number(priceAsset.value.peer_price || 0)
    priceForm.min_price = Number(priceAsset.value.min_price || 0)
    priceForm.remark = priceAsset.value.price_remark || ''
    priceDialogVisible.value = true
}

const handleCompletePrice = async () => {
    if (!priceAsset.value?.id) return
    if (priceForm.sale_price <= 0) {
        ElMessage.warning('请输入销售价')
        return
    }
    priceLoading.value = true
    try {
        if (priceAsset.value.photo_status !== 'approved') {
            await ElMessageBox.confirm('当前照片还没有确认完成，仍然保存定价吗？建议先由复检人员确认照片。', '定价提醒', {
                type: 'warning'
            })
        }
        await completeAssetPrice(priceAsset.value.id, { ...priceForm })
        ElMessage.success('定价已保存')
        priceDialogVisible.value = false
        await loadAssets()
    } finally {
        priceLoading.value = false
    }
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

const money = (value: any) => Number(value || 0).toFixed(2)
const imgUrl = (url: string) => img(url || '')

const refreshCurrentDetail = async () => {
    if (!currentAsset.value?.id) return
    const res: any = await getAssetInfo(currentAsset.value.id)
    currentAsset.value = res.data
    await loadAssets()
    ElMessage.success('详情已刷新')
}

const checkSummaryEntries = (asset: any) => {
    const summary = asset?.check_summary || asset?.recycle_device?.check_result_buyer || asset?.recycleDevice?.check_result_buyer || {}
    const data = normalizeObject(summary)
    return Object.entries(data)
        .filter(([, value]) => value !== '' && value !== null && value !== undefined)
        .map(([key, value]) => ({
            key,
            value: typeof value === 'object' ? JSON.stringify(value) : String(value)
        }))
}

const recycleCheckImages = (asset: any) => {
    const device = asset?.recycle_device || asset?.recycleDevice || {}
    return [
        ...splitImages(device.check_images_buyer),
        ...splitImages(device.check_images_seller),
        ...splitImages(device.check_images)
    ].filter((url, index, arr) => url && arr.indexOf(url) === index)
}

const assetImages = (asset: any) => {
    return (asset?.media || [])
        .filter((item: any) => item.media_type !== 'video' && item.status !== 'rejected')
        .map((item: any) => item.url)
        .filter(Boolean)
}

const priceCompareImages = (asset: any) => {
    return [...recycleCheckImages(asset).slice(0, 4), ...assetImages(asset).slice(0, 4)]
}

const splitImages = (value: any) => {
    if (Array.isArray(value)) return value.filter(Boolean)
    return String(value || '').split(',').map(item => item.trim()).filter(Boolean)
}

const normalizeObject = (value: any) => {
    if (!value) return {}
    if (typeof value === 'object') return value
    try {
        const parsed = JSON.parse(value)
        return typeof parsed === 'object' && parsed ? parsed : { 原始质检: value }
    } catch {
        return { 原始质检: value }
    }
}

const statusType = (status: string) => {
    if (status === 'exported') return 'success'
    if (status === 'ready_export' || status === 'wait_price') return 'warning'
    if (status === 'photo_rejected') return 'danger'
    return 'info'
}
const photoStatusType = (status: string) => {
    if (status === 'approved') return 'success'
    if (status === 'rejected') return 'danger'
    if (status === 'review') return 'warning'
    return 'info'
}
const priceStatusType = (status: string) => status === 'completed' ? 'success' : (status === 'pending' ? 'warning' : 'info')
const mediaStatusType = (status: string) => status === 'approved' ? 'success' : (status === 'rejected' ? 'danger' : 'warning')
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

    .stat-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }

    .stat-card {
        border: 1px solid var(--el-border-color-light);
        border-radius: 8px;
        padding: 14px 16px;
        background: var(--el-bg-color-page);
    }

    .stat-label {
        color: var(--el-text-color-secondary);
        font-size: 13px;
    }

    .stat-value {
        margin-top: 8px;
        color: var(--el-text-color-primary);
        font-size: 24px;
        font-weight: 700;
        line-height: 1;
    }

    .search-panel {
        margin-bottom: 12px;
        background: var(--el-bg-color-page);
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
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }

    .check-summary__item,
    .mini-summary > div {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        min-height: 32px;
        padding: 7px 10px;
        border-radius: 6px;
        background: var(--el-bg-color);
        color: var(--el-text-color-secondary);
        font-size: 12px;

        strong {
            color: var(--el-text-color-primary);
            font-weight: 600;
            text-align: right;
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

    .auto-camera-panel {
        margin-top: 14px;
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
