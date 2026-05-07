<template>
  <el-dialog
    v-model="dialogVisible"
    title=""
    :width="isMobile ? '95vw' : 'min(1060px, calc(100vw - 48px))'"
    :top="isMobile ? '0' : '3vh'"
    :fullscreen="isMobile"
    center
    :destroy-on-close="true"
    class="device-detail-dialog"
  >

    <div v-if="deviceData" class="ddd-wrap">

      <!-- ===== 设备信息卡片 ===== -->
      <DeviceInfoCard :device="deviceData" mode="full" class="ddd-section" />

      <!-- ===== 价格信息 ===== -->
      <div class="ddd-section ddd-price-section">
        <div class="ddd-section-header">
          <svg class="ddd-section-icon text-orange-500" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/>
          </svg>
          <span class="ddd-section-title">价格信息</span>
        </div>
        <div class="ddd-price-grid">
          <div class="ddd-price-card ddd-price-card--final">
            <div class="ddd-price-label">最终价格</div>
            <div class="ddd-price-value">
              {{ deviceData.final_price ? `¥${deviceData.final_price}` : '未定价' }}
            </div>
          </div>
          <div class="ddd-price-card ddd-price-card--sell">
            <div class="ddd-price-label">卖货价格</div>
            <div class="ddd-price-value">
              {{ deviceData.sell_price ? `¥${deviceData.sell_price}` : '未填写' }}
            </div>
          </div>
        </div>
        <!-- 价格备注 -->
        <div v-if="deviceData.price_remark" class="ddd-remark">
          <svg class="ddd-remark-icon" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
          </svg>
          <div>
            <p class="ddd-remark-title">价格备注</p>
            <p class="ddd-remark-content">{{ deviceData.price_remark }}</p>
          </div>
        </div>
      </div>

      <!-- ===== 质检 & 日志 并排 ===== -->
      <div class="ddd-two-col">

        <!-- 质检信息 -->
        <div class="ddd-section ddd-check-section">
          <div class="ddd-section-header">
            <svg class="ddd-section-icon text-green-500" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <span class="ddd-section-title">质检信息</span>
          </div>

          <div v-if="hasCheckResult" class="ddd-check-body">
            <!-- 质检时间 & 质检员 -->
            <div class="ddd-check-meta">
              <span v-if="deviceData.check_at" class="ddd-check-meta-item">
                🕐 {{ formatDate(deviceData.check_at) }}
              </span>
              <span v-if="deviceData.checkUser" class="ddd-check-meta-item">
                👤 {{ deviceData.checkUser.real_name || deviceData.checkUser.username }}
              </span>
            </div>

            <!-- 卖家质检结果 -->
            <div v-if="sellerCheckResult" class="ddd-result-block ddd-result-block--seller">
              <div class="ddd-result-title">卖家质检结果</div>
              <div class="ddd-result-content">{{ sellerCheckResult }}</div>
            </div>

            <!-- 买家质检结果 -->
            <div v-if="buyerCheckResult" class="ddd-result-block ddd-result-block--buyer">
              <div class="ddd-result-title">买家质检结果</div>
              <div class="ddd-result-content">{{ buyerCheckResult }}</div>
            </div>

            <!-- 扣费说明 -->
            <div v-if="deviceData.remark" class="ddd-result-block ddd-result-block--deduct">
              <div class="ddd-result-title">扣费说明</div>
              <div class="ddd-result-content">{{ deviceData.remark }}</div>
            </div>

            <!-- 卖家质检图片 -->
            <div v-if="checkImagesSellerArray.length > 0" class="ddd-image-group">
              <div class="ddd-image-group-title">卖家质检图片</div>
              <div class="ddd-image-grid">
                <div
                  v-for="(imgUrl, index) in checkImagesSellerArray"
                  :key="'seller-' + index"
                  class="ddd-image-thumb"
                  @click="previewImage(checkImagesSellerArray, index)"
                >
                  <el-image
                    :src="img(checkImagesSellerThumbArray[index] || imgUrl)"
                    fit="cover"
                    lazy
                  >
                    <template #error>
                      <div class="ddd-image-error">🖼</div>
                    </template>
                  </el-image>
                </div>
              </div>
            </div>

            <!-- 买家质检图片 -->
            <div v-if="checkImagesBuyerArray.length > 0" class="ddd-image-group">
              <div class="ddd-image-group-title">买家质检图片</div>
              <div class="ddd-image-grid">
                <div
                  v-for="(imgUrl, index) in checkImagesBuyerArray"
                  :key="'buyer-' + index"
                  class="ddd-image-thumb ddd-image-thumb--buyer"
                  @click="previewImage(checkImagesBuyerArray, index)"
                >
                  <el-image
                    :src="img(checkImagesBuyerThumbArray[index] || imgUrl)"
                    fit="cover"
                    lazy
                  >
                    <template #error>
                      <div class="ddd-image-error">🖼</div>
                    </template>
                  </el-image>
                </div>
              </div>
            </div>
          </div>

          <!-- 未质检 -->
          <div v-else class="ddd-empty">
            <div class="ddd-empty-icon">🔍</div>
            <div class="ddd-empty-text">暂无质检结果</div>
          </div>
        </div>

        <!-- 操作日志 -->
        <div class="ddd-section ddd-log-section">
          <div class="ddd-section-header">
            <svg class="ddd-section-icon text-purple-500" fill="currentColor" viewBox="0 0 20 20">
              <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
              <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
            </svg>
            <span class="ddd-section-title">操作日志</span>
            <el-tag v-if="deviceData.logs?.length" type="info" size="small" effect="plain" class="ml-auto">
              {{ deviceData.logs.length }} 条
            </el-tag>
          </div>

          <div v-if="deviceData.logs?.length" class="ddd-log-list">
            <div
              v-for="(log, index) in deviceData.logs"
              :key="log.id"
              class="ddd-log-item"
            >
              <div class="ddd-log-dot"></div>
              <div v-if="index < deviceData.logs.length - 1" class="ddd-log-line"></div>
              <div class="ddd-log-content">
                <div class="ddd-log-top">
                  <span class="ddd-log-operator">{{ log.operator_name }}</span>
                  <el-tag size="small" effect="light" type="primary">{{ log.status_name }}</el-tag>
                  <time class="ddd-log-time">{{ formatDate(log.create_at) }}</time>
                </div>
                <div v-if="log.remark" class="ddd-log-remark">{{ log.remark }}</div>
              </div>
            </div>
          </div>

          <div v-else class="ddd-empty">
            <div class="ddd-empty-icon">📋</div>
            <div class="ddd-empty-text">暂无操作日志</div>
          </div>
        </div>

      </div>
    </div>

    <!-- 空状态 -->
    <div v-else class="ddd-empty ddd-empty--page">
      <div class="ddd-empty-icon">📱</div>
      <div class="ddd-empty-text">设备信息加载失败或数据不存在</div>
    </div>

    <!-- 图片预览 -->
    <el-image-viewer
      v-if="imageViewer.show"
      :url-list="previewImageList"
      :initial-index="imageViewer.index"
      :zoom-rate="1.2"
      @close="imageViewer.show = false"
    />
  </el-dialog>

</template>


<script setup lang="ts">
import { ref, defineProps, defineEmits, watch, computed, reactive, onMounted, onBeforeUnmount } from 'vue'
import { img } from '@/utils/common'
import DeviceInfoCard from './DeviceInfoCard.vue'

// 定义设备信息接口
interface DeviceLog {
    id: number | string;
    operator_name: string;
    create_at: string;
    status_name: string;
    remark?: string;
}

interface DeviceDetail {
    id: number | string;
    imei: string;
    model: string;
    status: number | string;
    status_name: string;
    capacity?: string;
    color?: string;
    system_version?: string;
    warranty_info?: string;
    check_status?: number;
    check_result?: string;
    check_result_seller?: string;
    check_result_buyer?: string;
    check_at?: string | number;
    check_images?: string;
    check_images_seller?: string;
    check_images_buyer?: string;
    check_images_thumb_small?: string[];
    check_images_seller_thumb_small?: string[];
    check_images_buyer_thumb_small?: string[];
    before_price?: number | string;
    final_price?: number | string;
    sell_price?: number | string;
    price_remark?: string;
    remark?: string;
    create_at: string;
    update_at: string;
    logs?: DeviceLog[];
    info?: { sn?: string; [key: string]: any };
    checkUser?: { uid: number; username: string; real_name?: string };
    [key: string]: any;
}

const props = defineProps({
    visible: { type: Boolean, default: false },
    device: { type: Object as () => DeviceDetail | null, default: null }
})

const emit = defineEmits(['update:visible', 'closed'])

const dialogVisible = ref(props.visible)
const deviceData = ref<DeviceDetail | null>(props.device)
const isMobile = ref(false)

const updateResponsiveState = () => { isMobile.value = window.innerWidth <= 768 }

// 质检图片
const checkImagesSellerArray = computed(() => {
    const raw = deviceData.value?.check_images_seller || deviceData.value?.check_images
    if (!raw) return []
    return raw.split(',').map((u: string) => u.trim()).filter((u: string) => u)
})
const checkImagesSellerThumbArray = computed(() => {
    const thumbs = deviceData.value?.check_images_seller_thumb_small || deviceData.value?.check_images_thumb_small
    return (thumbs && Array.isArray(thumbs) && thumbs.length > 0) ? thumbs : checkImagesSellerArray.value
})
const checkImagesBuyerArray = computed(() => {
    if (!deviceData.value?.check_images_buyer) return []
    return deviceData.value.check_images_buyer.split(',').map((u: string) => u.trim()).filter((u: string) => u)
})
const checkImagesBuyerThumbArray = computed(() => {
    const thumbs = deviceData.value?.check_images_buyer_thumb_small
    return (thumbs && Array.isArray(thumbs) && thumbs.length > 0) ? thumbs : checkImagesBuyerArray.value
})

const sellerCheckResult = computed(() =>
    deviceData.value?.check_result_seller || deviceData.value?.check_result || ''
)
const buyerCheckResult = computed(() => deviceData.value?.check_result_buyer || '')
const hasCheckResult = computed(() => !!(sellerCheckResult.value || buyerCheckResult.value))

// 监听
watch(() => props.visible, (v) => { dialogVisible.value = v })
watch(() => props.device, (v) => { deviceData.value = v }, { deep: true })
watch(dialogVisible, (v) => {
    emit('update:visible', v)
    if (!v) emit('closed')
})

// 图片预览
const imageViewer = reactive({ show: false, index: 0 })
const previewImageList = ref<string[]>([])
const previewImage = (images: string[], index: number) => {
    previewImageList.value = images.map(url => img(url))
    imageViewer.index = index
    imageViewer.show = true
}

const formatDate = (dateStr: string | number) => {
    if (!dateStr) return '—'
    let date: Date
    if (typeof dateStr === 'number') {
        date = new Date(dateStr > 9999999999 ? dateStr : dateStr * 1000)
    } else {
        date = new Date(dateStr)
    }
    if (isNaN(date.getTime())) return '—'
    return date.toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit'
    })
}

onMounted(() => { updateResponsiveState(); window.addEventListener('resize', updateResponsiveState) })
onBeforeUnmount(() => { window.removeEventListener('resize', updateResponsiveState) })
</script>


<style lang="scss" scoped>
/* =====================
   Dialog 容器
   ===================== */
.device-detail-dialog {
  :deep(.el-dialog) {
    border-radius: 12px;
    box-shadow: 0 20px 48px rgba(0, 0, 0, 0.18);
    overflow: hidden;
  }
  :deep(.el-dialog__header) { padding: 0; border: none; }
  :deep(.el-dialog__body) {
    padding: 0;
    background: #f1f5f9;
    overflow: hidden;
  }
  :deep(.el-dialog__headerbtn) {
    top: 12px; right: 12px; z-index: 10;
  }
}

/* =====================
   整体包裹
   ===================== */
.ddd-wrap {
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-height: calc(100vh - 110px);
  overflow-y: auto;

  &::-webkit-scrollbar { width: 5px; }
  &::-webkit-scrollbar-track { background: #f1f5f9; }
  &::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
}

/* =====================
   通用 section 卡片
   ===================== */
.ddd-section {
  background: #fff;
  border-radius: 10px;
  border: 1px solid #e5e7eb;

}

.ddd-section-header {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 14px;
  background: linear-gradient(to right, #f9fafb, #f3f4f6);
  border-bottom: 1px solid #e5e7eb;
}

.ddd-section-icon {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
}

.ddd-section-title {
  font-size: 13px;
  font-weight: 600;
  color: #374151;
}

/* =====================
   价格区
   ===================== */
.ddd-price-section {
  .ddd-price-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    padding: 12px 14px;
  }
}

.ddd-price-card {
  border-radius: 8px;
  padding: 10px 12px;

  .ddd-price-label {
    font-size: 11px;
    margin-bottom: 4px;
  }
  .ddd-price-value {
    font-size: 18px;
    font-weight: 700;
    line-height: 1;
  }

  &--final {
    background: #fff7ed;
    border: 1px solid #fed7aa;
    .ddd-price-label { color: #ea580c; }
    .ddd-price-value { color: #c2410c; }
  }
  &--sell {
    background: #faf5ff;
    border: 1px solid #e9d5ff;
    .ddd-price-label { color: #7c3aed; }
    .ddd-price-value { color: #6d28d9; }
  }
}

.ddd-remark {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin: 0 14px 12px;
  padding: 10px 12px;
  background: #eff6ff;
  border-left: 3px solid #60a5fa;
  border-radius: 0 6px 6px 0;

  .ddd-remark-icon {
    width: 14px;
    height: 14px;
    color: #3b82f6;
    flex-shrink: 0;
    margin-top: 2px;
  }
  .ddd-remark-title {
    font-size: 11px;
    font-weight: 600;
    color: #1d4ed8;
    margin-bottom: 2px;
  }
  .ddd-remark-content {
    font-size: 12px;
    color: #1e40af;
    line-height: 1.5;
  }
}

/* =====================
   两列并排（质检 + 日志）
   ===================== */
.ddd-two-col {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

/* =====================
   质检区
   ===================== */
.ddd-check-body {
  padding: 10px 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.ddd-check-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.ddd-check-meta-item {
  font-size: 11px;
  color: #6b7280;
}

.ddd-result-block {
  border-radius: 6px;
  padding: 8px 10px;
  border-width: 1px;
  border-style: solid;

  .ddd-result-title {
    font-size: 11px;
    font-weight: 600;
    margin-bottom: 4px;
  }
  .ddd-result-content {
    font-size: 11px;
    line-height: 1.6;
    white-space: pre-line;
  }

  &--seller {
    background: #eff6ff;
    border-color: #bfdbfe;
    .ddd-result-title { color: #1d4ed8; }
    .ddd-result-content { color: #1e40af; }
  }
  &--buyer {
    background: #f0fdf4;
    border-color: #bbf7d0;
    .ddd-result-title { color: #15803d; }
    .ddd-result-content { color: #166534; }
  }
  &--deduct {
    background: #fef2f2;
    border-color: #fecaca;
    .ddd-result-title { color: #b91c1c; }
    .ddd-result-content { color: #991b1b; }
  }
}

/* =====================
   质检图片
   ===================== */
.ddd-image-group {
  .ddd-image-group-title {
    font-size: 11px;
    font-weight: 600;
    color: #6b7280;
    margin-bottom: 6px;
  }
}
.ddd-image-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.ddd-image-thumb {
  width: 56px;
  height: 56px;
  border-radius: 6px;
  overflow: hidden;
  cursor: pointer;
  border: 2px solid #e5e7eb;
  transition: border-color 0.2s;

  &:hover { border-color: #60a5fa; }
  &--buyer:hover { border-color: #4ade80; }

  :deep(.el-image) { width: 100%; height: 100%; display: block; }
}
.ddd-image-error {
  width: 100%;
  height: 100%;
  background: #f3f4f6;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
}

/* =====================
   操作日志
   ===================== */
.ddd-log-list {
  padding: 10px 12px;
  display: flex;
  flex-direction: column;
  gap: 0;
}

.ddd-log-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  position: relative;
  padding-bottom: 12px;

  &:last-child { padding-bottom: 0; }
}

.ddd-log-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #8b5cf6;
  flex-shrink: 0;
  margin-top: 5px;
  position: relative;
  z-index: 1;
}

.ddd-log-line {
  position: absolute;
  left: 3px;
  top: 14px;
  bottom: 0;
  width: 2px;
  background: #e9d5ff;
}

.ddd-log-content {
  flex: 1;
  min-width: 0;
}

.ddd-log-top {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 2px;
}

.ddd-log-operator {
  font-size: 12px;
  font-weight: 600;
  color: #1f2937;
}

.ddd-log-time {
  font-size: 10px;
  color: #9ca3af;
  margin-left: auto;
}

.ddd-log-remark {
  font-size: 11px;
  color: #6b7280;
  line-height: 1.5;
}

/* =====================
   空状态
   ===================== */
.ddd-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 24px 16px;
  gap: 6px;

  &--page { padding: 60px 16px; }
}
.ddd-empty-icon { font-size: 32px; opacity: 0.4; }
.ddd-empty-text { font-size: 12px; color: #9ca3af; }

/* =====================
   响应式
   ===================== */
@media (max-width: 640px) {
  .ddd-two-col {
    grid-template-columns: 1fr;
  }
}
</style>
