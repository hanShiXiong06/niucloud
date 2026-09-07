<template>
  <HsxDrawer
    v-model="dialogVisible"
    title="设备档案"
    subtitle="核对报价、质检与流转记录"
    size="lg"
    :destroy-on-close="true"
    class="device-detail-dialog"
  >

    <div v-if="deviceData" class="ddd-wrap">

      <!-- ===== 头部:设备信息(左) + 价格(右) ===== -->
      <div class="ddd-header-row ddd-section">
        <DeviceInfoCard :device="deviceData" mode="full" class="ddd-header-row__info" />
        <div class="ddd-header-price">
          <div class="ddd-header-price__label">最终价格</div>
          <div class="ddd-header-price__value">{{ deviceData.final_price ? `¥${deviceData.final_price}` : '未定价' }}</div>
          <div v-if="deviceData.initial_price" class="ddd-header-price__sub">初始参考 ¥{{ deviceData.initial_price }}</div>
          <div v-if="Number(deviceData.sell_price) > 0" class="ddd-header-price__sub">卖货价 ¥{{ deviceData.sell_price }}</div>
        </div>
      </div>

      <!-- ===== 下游流转进度 ===== -->
      <DownstreamProgress
        v-if="Number(deviceData.pay_status) === 1 || Number(deviceData.dispose_status) === 1 || Number(deviceData.downstream_stage) > 0"
        class="ddd-section"
        :stage="deviceData.downstream_stage"
        :sale-price="deviceData.downstream_sale_price"
        :staged-at="deviceData.downstream_stage_at"
        :erp-asset-id="deviceData.downstream_erp_asset_id"
        :pay-status="deviceData.pay_status"
        :dispose-status="deviceData.dispose_status"
      />

      <!-- ===== 价格补充:成本调整 / 价格备注(主价格已上移到头部) ===== -->
      <div v-if="hasCostAdjustment || canAdjustCost || deviceData.price_remark" class="ddd-section ddd-price-section">
        <div v-if="hasCostAdjustment || canAdjustCost" class="ddd-cost-adjust">
          <div class="ddd-cost-adjust__summary">
            <div>
              <div class="ddd-cost-adjust__title">成本调整</div>
              <div class="ddd-cost-adjust__desc">
                已打款后如需改设备成本，在这里留痕处理；确认后请同步修改进销存软件成本。
              </div>
            </div>
            <el-button v-if="canAdjustCost" type="warning" size="small" @click="openCostAdjustDialog">
              成本调整
            </el-button>
          </div>
          <div v-if="hasCostAdjustment" class="ddd-cost-adjust__meta">
            <span>累计调整：{{ formatSignedMoney(deviceData.cost_adjust_amount) }}</span>
            <span>调整次数：{{ deviceData.cost_adjust_count || 0 }} 次</span>
            <span v-if="deviceData.last_cost_adjust_time">最后调整：{{ formatDate(deviceData.last_cost_adjust_time) }}</span>
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

            <!-- 质检结果:公共组件(突出项 + 级别计数 + 异常突出 + 折叠) -->
            <div v-if="checkItems.length || sellerCheckResult" class="ddd-result-block">
              <CheckResultPanel
                :summary-fields="viewCheck.summary_fields"
                :severity-summary="viewCheck.severity_summary"
                :abnormal-items="viewCheck.abnormal_items"
                :items="viewCheck.items"
                :text="sellerCheckResult"
              />
            </div>

            <!-- 卖家质检结果 -->
            <div v-if="sellerCheckResult" class="ddd-result-block ddd-result-block--seller">
              <div class="ddd-result-title">卖家质检结果</div>
              <div
                :ref="setClampRef('seller')"
                class="ddd-result-content"
                :class="{ 'is-clamped': !expanded.seller }"
              >{{ sellerCheckResult }}</div>
              <button
                v-if="overflowing.seller"
                type="button"
                class="ddd-result-toggle"
                @click="expanded.seller = !expanded.seller"
              >{{ expanded.seller ? '收起' : '展开全部' }}</button>
            </div>

            <!-- 买家质检结果 -->
            <div v-if="buyerCheckResult" class="ddd-result-block ddd-result-block--buyer">
              <div class="ddd-result-title">买家质检结果</div>
              <div
                :ref="setClampRef('buyer')"
                class="ddd-result-content"
                :class="{ 'is-clamped': !expanded.buyer }"
              >{{ buyerCheckResult }}</div>
              <button
                v-if="overflowing.buyer"
                type="button"
                class="ddd-result-toggle"
                @click="expanded.buyer = !expanded.buyer"
              >{{ expanded.buyer ? '收起' : '展开全部' }}</button>
            </div>

            <!-- 扣费说明 -->
            <div v-if="deviceData.remark" class="ddd-result-block ddd-result-block--deduct">
              <div class="ddd-result-title">扣费说明</div>
              <div
                :ref="setClampRef('deduct')"
                class="ddd-result-content"
                :class="{ 'is-clamped': !expanded.deduct }"
              >{{ deviceData.remark }}</div>
              <button
                v-if="overflowing.deduct"
                type="button"
                class="ddd-result-toggle"
                @click="expanded.deduct = !expanded.deduct"
              >{{ expanded.deduct ? '收起' : '展开全部' }}</button>
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

    <HsxDialog :confirm-loading="costAdjustDialog.submitting"
      v-model="costAdjustDialog.visible"
      title="设备成本调整"
      width="620px"
      append-to-body
      destroy-on-close
    >
      <HsxNotice
        type="warning"
        :closable="false"
        title="该操作会修改设备当前成本，不会修改历史打款记录。提交后请同步修改进销存软件里的库存成本，并保留与客户沟通记录。"
      />
      <el-form class="ddd-cost-form" label-width="110px">
        <el-form-item label="当前成本">
          <strong class="ddd-current-cost">¥{{ formatMoney(deviceData?.final_price) }}</strong>
        </el-form-item>
        <el-form-item label="调整类型" required>
          <el-radio-group v-model="costAdjustForm.adjust_type">
            <el-radio-button label="refund_from_customer">客户退回差额</el-radio-button>
            <el-radio-button label="pay_to_customer">补款给客户</el-radio-button>
            <el-radio-button label="cost_correction">内部修正</el-radio-button>
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="costAdjustForm.adjust_type === 'cost_correction'" label="修正方向">
          <el-radio-group v-model="costAdjustForm.direction">
            <el-radio label="decrease">成本减少</el-radio>
            <el-radio label="increase">成本增加</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="调整金额" required>
          <el-input-number v-model="costAdjustForm.adjust_amount" :min="0" :precision="2" :step="10" controls-position="right" />
          <span class="ddd-form-help">调整后的成本：{{ previewAfterCost }}</span>
        </el-form-item>
        <el-form-item label="调整原因" required>
          <el-input v-model="costAdjustForm.reason" type="textarea" :rows="3" maxlength="200" show-word-limit placeholder="例如：已打款后发现主板维修，客户同意退回200元" />
        </el-form-item>
        <el-form-item label="处理确认">
          <el-checkbox v-model="costAdjustForm.customer_handled" :true-label="1" :false-label="0">
            与客户差额已沟通/已处理
          </el-checkbox>
        </el-form-item>
        <el-form-item label="同步进销存">
          <div>
            <el-switch v-model="costAdjustForm.auto_sync_erp" :active-value="1" :inactive-value="0" active-text="自动同步" inactive-text="手动处理" inline-prompt />
            <div class="text-xs text-gray-400 mt-1">
              <template v-if="Number(costAdjustForm.auto_sync_erp) === 1">开启后，调整将自动同步到 ERP（进销存）的该设备库存成本；若未使用 ERP 则自动忽略，无影响。</template>
              <template v-else>关闭后仅在回收侧调整，请记得手动同步进销存软件中的库存成本。</template>
            </div>
          </div>
        </el-form-item>
      </el-form>

      <div class="ddd-cost-log">
        <div class="ddd-cost-log__title">历史调整记录</div>
        <el-table v-if="costAdjustLogs.length" :data="costAdjustLogs" size="small" max-height="180">
          <el-table-column prop="adjust_type_name" label="类型" min-width="110" />
          <el-table-column label="调整" width="110">
            <template #default="{ row }">{{ formatSignedMoney(row.adjust_delta) }}</template>
          </el-table-column>
          <el-table-column label="调整后" width="110">
            <template #default="{ row }">¥{{ formatMoney(row.after_cost) }}</template>
          </el-table-column>
          <el-table-column prop="operator_name" label="操作人" width="110" />
          <el-table-column label="时间" width="150">
            <template #default="{ row }">{{ formatDate(row.create_at) }}</template>
          </el-table-column>
        </el-table>
        <el-empty v-else description="暂无成本调整记录" :image-size="64" />
      </div>

      <template #footer>
        <el-button :disabled="costAdjustDialog.submitting" @click="costAdjustDialog.visible = false">取消</el-button>
        <el-button :disabled="costAdjustDialog.submitting" type="warning" :loading="costAdjustDialog.submitting" @click="submitCostAdjust">
          确认调整成本
        </el-button>
      </template>
    </HsxDialog>
  </HsxDrawer>

</template>


<script setup lang="ts">
import { ref, watch, computed, reactive, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { HsxDrawer, HsxDialog, HsxNotice, useFeedback } from '@/addon/hsx_components/core'
const feedback = useFeedback()
import { img } from '@/utils/common'
import { adjustDeviceCost, getDeviceCostAdjustLogs } from '@/addon/hsx_recycle/api/recycle_order'
import useUserStore from '@/stores/modules/user'
import DeviceInfoCard from './DeviceInfoCard.vue'
import DownstreamProgress from './DownstreamProgress.vue'
import CheckResultPanel from './CheckResultPanel.vue'

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
    downstream_stage?: number;
    downstream_stage_at?: number;
    downstream_sale_price?: number | string;
    downstream_erp_asset_id?: number;
    pay_status?: number;
    dispose_status?: number;
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

const emit = defineEmits(['update:visible', 'closed', 'updated'])

const dialogVisible = ref(props.visible)
const deviceData = ref<DeviceDetail | null>(props.device)
const isMobile = ref(false)
const userStore = useUserStore()
const costAdjustDialog = reactive({ visible: false, submitting: false })
const costAdjustLogs = ref<any[]>([])
const costAdjustForm = reactive({
    adjust_type: 'refund_from_customer',
    direction: 'decrease',
    adjust_amount: 0,
    reason: '',
    customer_handled: 0,
    auto_sync_erp: 1
})

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

// 质检结果文本折叠（内容过长时只展示前几行，超出可展开）
type ClampKey = 'seller' | 'buyer' | 'deduct'
const clampKeys: ClampKey[] = ['seller', 'buyer', 'deduct']
const clampRefs: Record<ClampKey, HTMLElement | null> = { seller: null, buyer: null, deduct: null }
const expanded = reactive<Record<ClampKey, boolean>>({ seller: false, buyer: false, deduct: false })
const overflowing = reactive<Record<ClampKey, boolean>>({ seller: false, buyer: false, deduct: false })
const setClampRef = (key: ClampKey) => (el: any) => { clampRefs[key] = (el as HTMLElement) || null }
const measureClamp = async () => {
    await nextTick()
    for (const key of clampKeys) {
        const el = clampRefs[key]
        // 在折叠态下测量：真实内容高度超过可视高度即认为溢出
        overflowing[key] = !!el && !expanded[key] && (el.scrollHeight - el.clientHeight > 1)
    }
}

const sellerCheckResult = computed(() =>
    deviceData.value?.check_result_seller || deviceData.value?.check_result || ''
)
const buyerCheckResult = computed(() => deviceData.value?.check_result_buyer || '')
const hasCheckResult = computed(() => !!(sellerCheckResult.value || buyerCheckResult.value))

// 质检结果:统一用公共组件渲染,数据取后端 view.check(突出项/级别/异常/全部项)
const viewCheck = computed<any>(() => deviceData.value?.view?.check || {})
const checkItems = computed<any[]>(() => deviceData.value?.view?.check?.items || [])
const abnormalCount = computed(() => checkItems.value.filter((i: any) => i.severity === 'abnormal').length)
const generalCount = computed(() => checkItems.value.filter((i: any) => i.severity === 'general').length)
const onlyAbnormal = ref(false)
const visibleCheckItems = computed(() =>
    onlyAbnormal.value ? checkItems.value.filter((i: any) => i.severity !== 'normal') : checkItems.value
)
const hasCostAdjustment = computed(() => Number(deviceData.value?.cost_adjust_count || 0) > 0)
const hasCostAdjustPermission = computed(() => (userStore.rules || []).includes('recycle_device_cost_adjust'))
const canAdjustCost = computed(() => hasCostAdjustPermission.value && Number(deviceData.value?.pay_status || 0) === 1 && Number(deviceData.value?.status || 0) !== 6)
const previewAfterCost = computed(() => {
    const current = Number(deviceData.value?.final_price || 0)
    const amount = Number(costAdjustForm.adjust_amount || 0)
    let delta = 0
    if (costAdjustForm.adjust_type === 'refund_from_customer') delta = -amount
    else if (costAdjustForm.adjust_type === 'pay_to_customer') delta = amount
    else delta = costAdjustForm.direction === 'increase' ? amount : -amount
    return `¥${formatMoney(Math.max(current + delta, 0))}`
})

// 监听
watch(() => props.visible, (v) => {
    dialogVisible.value = v
    if (v) {
        loadCostAdjustLogs()
        clampKeys.forEach(k => { expanded[k] = false })
        measureClamp()
    }
})
watch(() => props.device, (v) => {
    deviceData.value = v
    clampKeys.forEach(k => { expanded[k] = false })
    measureClamp()
}, { deep: true })
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

const openCostAdjustDialog = () => {
    costAdjustForm.adjust_type = 'refund_from_customer'
    costAdjustForm.direction = 'decrease'
    costAdjustForm.adjust_amount = 0
    costAdjustForm.reason = ''
    costAdjustForm.customer_handled = 0
    costAdjustForm.auto_sync_erp = 1
    costAdjustDialog.visible = true
    loadCostAdjustLogs()
}

const loadCostAdjustLogs = async () => {
    const deviceId = deviceData.value?.id
    if (!deviceId || !hasCostAdjustPermission.value) return
    try {
        const res: any = await getDeviceCostAdjustLogs(deviceId)
        costAdjustLogs.value = Array.isArray(res?.data) ? res.data : []
    } catch (error) {
        costAdjustLogs.value = []
    }
}

const submitCostAdjust = async () => {
    if (!deviceData.value?.id) return
    if (!costAdjustForm.adjust_amount || Number(costAdjustForm.adjust_amount) <= 0) {
        feedback.warning('请输入大于 0 的调整金额')
        return
    }
    if (!String(costAdjustForm.reason || '').trim()) {
        feedback.warning('请填写成本调整原因')
        return
    }
    costAdjustDialog.submitting = true
    try {
        const res: any = await adjustDeviceCost(deviceData.value.id, { ...costAdjustForm })
        const data = res?.data || {}
        deviceData.value.final_price = data.after_cost
        deviceData.value.cost_adjust_amount = Number(deviceData.value.cost_adjust_amount || 0) + Number(data.adjust_delta || 0)
        deviceData.value.cost_adjust_count = data.cost_adjust_count
        deviceData.value.last_cost_adjust_time = data.create_at
        deviceData.value.last_cost_adjust_no = data.adjust_no
        await loadCostAdjustLogs()
        costAdjustDialog.visible = false
        feedback.success(Number(costAdjustForm.auto_sync_erp) === 1 ? '成本已调整，并已自动同步至 ERP（进销存）' : '成本已调整，请记得手动同步进销存软件成本')
        emit('updated', deviceData.value)
    } catch (error: any) {
        feedback.error(error?.msg || error?.message || '成本调整失败')
    } finally {
        costAdjustDialog.submitting = false
    }
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

const formatMoney = (value: any) => {
    const num = Number(value || 0)
    return Number.isFinite(num) ? num.toFixed(2) : '0.00'
}

const formatSignedMoney = (value: any) => {
    const num = Number(value || 0)
    if (!Number.isFinite(num) || num === 0) return '¥0.00'
    return `${num > 0 ? '+' : '-'}¥${Math.abs(num).toFixed(2)}`
}

onMounted(() => { updateResponsiveState(); window.addEventListener('resize', updateResponsiveState) })
onBeforeUnmount(() => { window.removeEventListener('resize', updateResponsiveState) })
</script>


<style lang="scss" scoped>
/* =====================
   Dialog 容器
   ===================== */

/* =====================
   整体包裹
   ===================== */
.ddd-wrap {
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;

  &::-webkit-scrollbar { width: 5px; }
  &::-webkit-scrollbar-track { background: var(--hsx-bg-muted); }
  &::-webkit-scrollbar-thumb { background: var(--hsx-border-strong); border-radius: 3px; }
}

/* =====================
   通用 section 卡片
   ===================== */
.ddd-section {
  background: var(--hsx-bg-surface);
  border-radius: 10px;
  border: 1px solid var(--hsx-border-color);

}

/* 头部:设备信息(左) + 价格(右) */
.ddd-header-row {
  display: flex;
  align-items: stretch;
  gap: 12px;
  flex-wrap: wrap;
  background: transparent;
  border: none;
}
.ddd-header-row__info { flex: 1; min-width: 240px; }
.ddd-header-price {
  flex-shrink: 0;
  min-width: 150px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: flex-end;
  text-align: right;
  padding: 12px 18px;
  border-radius: 10px;
  background: var(--el-color-danger-light-9);
  border: 1px solid var(--el-color-danger-light-7);

  &__label { font-size: 12px; color: var(--el-text-color-secondary); }
  &__value { font-size: 22px; font-weight: 700; color: var(--el-color-danger); line-height: 1.35; }
  &__sub { font-size: 11px; color: var(--el-text-color-secondary); margin-top: 2px; }
}

.ddd-section-header {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 14px;
  background: linear-gradient(to right, #f9fafb, #f3f4f6);
  border-bottom: 1px solid var(--hsx-border-color);
}

.ddd-section-icon {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
}

.ddd-section-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--hsx-text-regular);
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

  background: var(--el-fill-color-light);
  border: 1px solid var(--el-border-color-lighter);
  .ddd-price-label { color: var(--el-text-color-secondary); }

  &--final {
    .ddd-price-value { color: var(--el-color-danger); }
  }
  &--sell {
    .ddd-price-value { color: var(--el-color-primary); }
  }
}

.ddd-cost-adjust {
  margin: 0 14px 12px;
  padding: 12px;
  border-radius: 8px;
  border: 1px solid #fde68a;
  background: #fffbeb;
}

.ddd-cost-adjust__summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.ddd-cost-adjust__title {
  font-size: 13px;
  font-weight: 700;
  color: #92400e;
}

.ddd-cost-adjust__desc {
  margin-top: 4px;
  font-size: 12px;
  line-height: 1.5;
  color: #a16207;
}

.ddd-cost-adjust__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 16px;
  margin-top: 10px;
  font-size: 12px;
  color: #78350f;
}

.ddd-cost-form {
  margin-top: 16px;
}

.ddd-current-cost {
  color: #ea580c;
  font-size: 16px;
}

.ddd-form-help {
  margin-left: 12px;
  font-size: 12px;
  color: var(--hsx-text-secondary);
}

.ddd-cost-log {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid var(--hsx-border-color);
}

.ddd-cost-log__title {
  margin-bottom: 8px;
  font-size: 13px;
  font-weight: 600;
  color: var(--hsx-text-regular);
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
  color: var(--hsx-text-secondary);
}

.ddd-check-items-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.ddd-check-items {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.ddd-check-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  font-size: 12px;
  padding: 5px 8px;
  border-radius: 5px;
  border-left: 3px solid transparent;
  background: var(--el-fill-color-lighter);

  .ddd-check-item__name { color: var(--el-text-color-secondary); flex-shrink: 0; }
  .ddd-check-item__val { color: var(--el-text-color-primary); text-align: right; }

  &.is-abnormal {
    background: var(--el-color-danger-light-9);
    border-left-color: var(--el-color-danger);
    .ddd-check-item__val { color: var(--el-color-danger); font-weight: 600; }
  }
  &.is-general {
    background: var(--el-color-warning-light-9);
    border-left-color: var(--el-color-warning);
    .ddd-check-item__val { color: var(--el-color-warning); }
  }
  &.is-normal { border-left-color: var(--el-border-color-lighter); }
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
    word-break: break-word;

    &.is-clamped {
      display: -webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
  }

  .ddd-result-toggle {
    margin-top: 4px;
    padding: 0;
    background: none;
    border: none;
    font-size: 11px;
    font-weight: 600;
    line-height: 1.4;
    cursor: pointer;
    color: inherit;
    opacity: 0.85;
    transition: opacity 0.2s;

    &:hover { opacity: 1; text-decoration: underline; }
  }

  &--seller {
    background: #eff6ff;
    border-color: #bfdbfe;
    .ddd-result-title { color: #1d4ed8; }
    .ddd-result-content { color: #1e40af; }
    .ddd-result-toggle { color: #1d4ed8; }
  }
  &--buyer {
    background: #f0fdf4;
    border-color: #bbf7d0;
    .ddd-result-title { color: #15803d; }
    .ddd-result-content { color: #166534; }
    .ddd-result-toggle { color: #15803d; }
  }
  &--deduct {
    background: #fef2f2;
    border-color: #fecaca;
    .ddd-result-title { color: #b91c1c; }
    .ddd-result-content { color: #991b1b; }
    .ddd-result-toggle { color: #b91c1c; }
  }
}

/* =====================
   质检图片
   ===================== */
.ddd-image-group {
  .ddd-image-group-title {
    font-size: 11px;
    font-weight: 600;
    color: var(--hsx-text-secondary);
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
  border: 2px solid var(--hsx-border-color);
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
  max-height: 360px;
  overflow-y: auto;
  overscroll-behavior: contain;

  &::-webkit-scrollbar { width: 5px; }
  &::-webkit-scrollbar-thumb { background: var(--hsx-border-strong); border-radius: 3px; }
  &::-webkit-scrollbar-track { background: transparent; }
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
  color: var(--hsx-text-primary);
}

.ddd-log-time {
  font-size: 10px;
  color: #9ca3af;
  margin-left: auto;
}

.ddd-log-remark {
  font-size: 11px;
  color: var(--hsx-text-secondary);
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

  .ddd-log-list {
    max-height: 300px;
  }
}
</style>
