<template>
  <FormDialog
    :visible="dialogVisible"
    title="回收定价"
    subtitle="基于质检结果确定回收报价和整备安排"
    width="lg"
    :loading="submitting"
    :confirm-disabled="!isFormValid"
    confirm-text="确认回收定价"
    @update:visible="dialogVisible = $event"
    @confirm="handleConfirm"
    @cancel="handleCancel"
  >
    <div class="pfd-body">

      <!-- ===== 设备信息卡片 ===== -->
      <DeviceInfoCard :device="deviceData" mode="full" :show-status="false" />

      <div class="pfd-layout">
        <aside class="pfd-sidebar">
          <div class="pfd-summary-card">
            <div class="pfd-summary-card__title">质检摘要</div>
            <div class="pfd-summary-card__content">
              {{ deviceData.check_result_seller || deviceData.check_result || '暂无质检摘要' }}
            </div>
          </div>

          <div class="pfd-summary-card">
            <div class="pfd-summary-card__title">价格参考</div>
            <div class="pfd-metric">
              <span>预估价</span>
              <strong>¥{{ moneyText(deviceData.initial_price) }}</strong>
            </div>
            <div class="pfd-metric">
              <span>原报价</span>
              <strong>¥{{ moneyText(deviceData.before_price || deviceData.final_price) }}</strong>
            </div>
            <div class="pfd-metric" v-if="deviceForm.final_price !== undefined">
              <span>本次报价</span>
              <strong class="pfd-metric__primary">¥{{ moneyText(deviceForm.final_price) }}</strong>
            </div>
          </div>

          <div class="pfd-process-note">
            <strong>流程边界</strong>
            <span>质检只描述设备事实；回收定价负责报价、整备决策和负责人安排。</span>
          </div>
        </aside>

        <div class="pfd-main">
          <el-form :model="deviceForm" label-position="top" class="pfd-form">
            <section class="pfd-section">
              <div class="pfd-section__head">
                <div>
                  <h4>回收报价</h4>
                  <p class="text-[12px]">给客户的最终回收价格，不是销售定价。</p>
                </div>
                <el-tag type="info" effect="plain">回收定价</el-tag>
              </div>

              <div class="pfd-price-grid">
                <el-form-item label="最终回收价 *">
                  <el-input-number
                    v-model="deviceForm.final_price"
                    placeholder="请输入最终回收价"
                    :step="10"
                    :min="0"
                    :precision="0"
                    style="width: 100%;"
                    @change="updatePriceClass"
                  />
                  <div v-if="deviceData.initial_price" class="pfd-hint">
                    参考预估价：¥{{ moneyText(deviceData.initial_price) }}
                  </div>
                </el-form-item>

                <el-form-item label="内部卖货参考价">
                  <el-input-number
                    v-model="deviceForm.sell_price"
                    placeholder="可选"
                    :step="10"
                    :min="0"
                    :precision="0"
                    style="width: 100%;"
                  />
                  <div class="pfd-hint">仅用于内部参考，不等同于 ERP 销售定价。</div>
                </el-form-item>
              </div>

              <div v-if="deviceData.before_price && deviceForm.final_price !== undefined" class="pfd-price-diff">
                <span :class="['pfd-price-diff__badge', priceChangeClass]">
                  {{ getPriceChangeText() }}
                </span>
              </div>

              <el-form-item label="定价说明">
                <el-input
                  v-model="deviceForm.remark"
                  type="textarea"
                  :rows="3"
                  placeholder="填写报价依据，例如：电池效率低、外壳磕碰、屏幕维修等"
                  maxlength="200"
                  show-word-limit
                />
              </el-form-item>
            </section>

            <section class="pfd-section">
              <div class="pfd-section__head">
                <div>
                  <h4>销售去向</h4>
                  <p class="text-[12px]">定价员在回收定价阶段确定后续销售链路。</p>
                </div>
                <el-tag v-if="saleDestinationText" type="info" effect="plain">{{ saleDestinationText }}</el-tag>
              </div>
              <!-- 仓库模式：ERP 已连接且有仓库 → 选仓库即决定流向 -->
              <template v-if="warehouseMode">
                <el-select
                  v-model="deviceForm.target_warehouse_id"
                  placeholder="选择入库仓库（决定销售流向）"
                  class="w-full"
                  @change="onWarehouseChange"
                >
                  <el-option
                    v-for="w in erpWarehouses"
                    :key="w.id"
                    :label="w.warehouse_name"
                    :value="w.id"
                  >
                    <span>{{ w.warehouse_name }}</span>
                    <span class="text-gray-400 text-xs ml-2">{{ warehouseTypeLabel(w.business_type) }}</span>
                  </el-option>
                </el-select>
                <div class="pfd-hint">
                  入此仓将按其业务类型自动确定流向：<b>{{ saleDestinationText || '—' }}</b><template v-if="saleDestinationDescription">（{{ saleDestinationDescription }}）</template>
                </div>

                <div v-if="currentWarehouseLocations.length" class="pfd-warehouse">
                  <div class="pfd-warehouse__label">库位（可选）</div>
                  <el-select
                    v-model="deviceForm.target_location_id"
                    placeholder="选择库位，不选则由 ERP 入库时再定"
                    clearable
                    class="w-full"
                    @change="onLocationChange"
                  >
                    <el-option
                      v-for="loc in currentWarehouseLocations"
                      :key="loc.id"
                      :label="loc.location_name"
                      :value="loc.id"
                    />
                  </el-select>
                </div>
              </template>

              <!-- 渠道模式：ERP 未连接 → 固定渠道单选 -->
              <template v-else>
                <el-radio-group v-model="deviceForm.sale_destination" class="flex flex-wrap gap-2">
                  <el-radio-button
                    v-for="item in saleDestinationOptions"
                    :key="item.value"
                    :label="item.value"
                  >
                    {{ item.label }}
                  </el-radio-button>
                </el-radio-group>
                <div v-if="saleDestinationDescription" class="pfd-hint">{{ saleDestinationDescription }}</div>
                <div v-if="erpConnected && !erpWarehouses.length" class="pfd-hint pfd-hint--warn">
                  已连接 ERP，但尚未创建启用的仓库。可先按固定渠道定价，或到「ERP · 仓库管理」创建仓库后改用仓库选择。
                </div>
              </template>
            </section>

            <section :class="['pfd-section', 'pfd-refurbish-card', deviceForm.refurbishment_required === 1 ? 'is-required' : '']">
              <div class="pfd-section__head">
                <div>
                  <h4>整备安排</h4>
                  <p >需要整备时会生成整备计划，并可触发整备标签打印。</p>
                </div>
                <el-switch
                  v-model="deviceForm.refurbishment_required"
                  :active-value="1"
                  :inactive-value="0"
                  active-text="需要"
                  inactive-text="无需"
                />
              </div>

              <div class="pfd-refurbish-status">
                <span>{{ deviceForm.refurbishment_required === 1 ? '这台机器需要整备后再销售' : '默认无需整备，入库后进入后续销售定价' }}</span>
              </div>

              <template v-if="deviceForm.refurbishment_required === 1">
                <div class="pfd-price-grid">
                  <el-form-item label="整备负责人 *">
                    <el-select
                      v-model="deviceForm.refurbishment_assignee_uid"
                      filterable
                      clearable
                      placeholder="选择谁负责整备"
                      style="width: 100%;"
                    >
                      <el-option
                        v-for="user in userOptions"
                        :key="user.uid"
                        :label="userName(user)"
                        :value="Number(user.uid)"
                      >
                        <span>{{ userName(user) }}</span>
                        <span v-if="Number(user.pick_count) > 0" class="text-gray-400 text-xs ml-2">{{ user.pick_count }} 次</span>
                      </el-option>
                    </el-select>
                  </el-form-item>

                  <el-form-item label="预估整备成本">
                    <el-input-number
                      v-model="deviceForm.refurbishment_estimated_cost"
                      :min="0"
                      :precision="0"
                      :step="10"
                      style="width: 100%;"
                    />
                  </el-form-item>
                </div>

                <el-form-item label="建议整备项目">
                  <el-checkbox-group v-model="deviceForm.refurbishment_item_keys" class="pfd-refurbishment-items">
                    <el-checkbox-button v-for="item in refurbishmentPresets" :key="item.key" :label="item.key">
                      {{ item.name }}
                    </el-checkbox-button>
                  </el-checkbox-group>
                  <el-input
                    v-model="deviceForm.refurbishment_custom_item"
                    class="mt-2"
                    placeholder="其他项目，例如：更换尾插、补胶"
                  />
                </el-form-item>

                <el-form-item label="整备说明">
                  <el-input
                    v-model="deviceForm.refurbishment_reason"
                    type="textarea"
                    :rows="2"
                    placeholder="例如：电池效率低于 80%，建议更换电池后销售"
                    maxlength="300"
                    show-word-limit
                  />
                </el-form-item>

                <div class="pfd-print-tip">
                  <strong>标签打印事件</strong>
                  <span>定价阶段只保存整备计划；设备确认回收后才触发 device.recycled.refurbishment_required，可在打印场景中配置是否自动打印。</span>
                </div>
              </template>
            </section>

            <div
              v-if="!isFormValid && deviceForm.final_price !== undefined"
              class="pfd-alert pfd-alert--error"
            >
              请输入有效回收价；需要整备时必须选择负责人。
            </div>
            <div v-else-if="isFormValid" class="pfd-alert pfd-alert--success">
              表单填写完整，可以提交回收定价。
            </div>
          </el-form>
        </div>
      </div>

    </div>
  </FormDialog>
</template>

<script setup lang="ts">
import { ref, watch, computed, reactive, onMounted, onBeforeUnmount } from 'vue'
import { ElMessage } from 'element-plus'
import DeviceInfoCard from './DeviceInfoCard.vue'
import FormDialog from '@/addon/hsx_recycle/components/FormDialog.vue'
import { getDevice, getRefurbishmentOptions, getSaleDestinationOptions, getRefurbishmentAssigneeOptions } from '@/addon/hsx_recycle/api/recycle_order'

interface DeviceInfo {
    id?: string | number;
    model?: string;
    imei?: string;
    capacity?: string;
    color?: string;
    system_version?: string;
    warranty_info?: string;
    initial_price?: string | number;
    before_price?: string | number;
    check_result?: string;
    check_result_seller?: string;
    check_result_buyer?: string;
    final_price?: string | number;
    sell_price?: string | number;
    sale_destination?: string;
    remark?: string;
    refurbishment_required?: number;
    refurbishment_assignee_uid?: number;
    refurbishment_reason?: string;
    refurbishment_items?: any;
    refurbishment_estimated_cost?: string | number;
    status?: number;
    status_name?: string;
    info?: { sn?: string; [key: string]: any };
    [key: string]: any;
}

const props = defineProps({
    visible: { type: Boolean, default: false },
    device: { type: Object as () => DeviceInfo, default: () => ({}) },
    submitting: { type: Boolean, default: false }
})

const emit = defineEmits(['update:visible', 'confirm', 'cancel'])

const dialogVisible = ref(props.visible)
const deviceData = ref<DeviceInfo>({ ...props.device })
const isMobile = ref(false)
const userOptions = ref<any[]>([])
const detailLoading = ref(false)

const refurbishmentPresets = ref<Array<{ key: string; name: string; type: string }>>([])
const saleDestinationOptions = ref<Array<{ value: string; label: string; description?: string }>>([])
const erpWarehouses = ref<Array<{ id: number; warehouse_name: string; business_type?: string; is_default?: number; locations?: Array<{ id: number; location_name: string }> }>>([])
const erpConnected = ref(false)
// 当前所选仓库的库位列表（手动选，不自动匹配）
const currentWarehouseLocations = computed(() => {
    const w = erpWarehouses.value.find(item => item.id === deviceForm.target_warehouse_id)
    return (w?.locations || []) as Array<{ id: number; location_name: string }>
})

// 仓库业务类型 → 销售流向（与后端 RecycleOrderDict::saleDestinationFromWarehouseType 保持一致）
const WH_TYPE_DEST: Record<string, string> = { mall: 'mall', peer: 'peer', scrap: 'scrap', hold: 'hold' }
const WH_TYPE_LABEL: Record<string, string> = { mall: '商城', peer: '同行', scrap: '报废', hold: '暂存' }
const warehouseTypeLabel = (t?: string) => WH_TYPE_LABEL[t || 'mall'] || '商城'
// 仓库模式：ERP 已连接且有可用仓库时，以仓库为主选项（选仓即定流向）
const warehouseMode = computed(() => erpConnected.value && erpWarehouses.value.length > 0)

const deviceForm = reactive<{
    final_price: number | undefined;
    sell_price: number | undefined;
    sale_destination: string;
    target_warehouse_id: number;
    target_warehouse_name: string;
    target_location_id: number;
    target_location_name: string;
    remark: string;
    refurbishment_required: number;
    refurbishment_assignee_uid: number;
    refurbishment_reason: string;
    refurbishment_item_keys: string[];
    refurbishment_custom_item: string;
    refurbishment_estimated_cost: number;
}>({
    final_price: typeof props.device.final_price === 'number'
        ? props.device.final_price
        : typeof props.device.final_price === 'string'
            ? parseFloat(props.device.final_price) || undefined
            : undefined,
    sell_price: typeof props.device.sell_price === 'number'
        ? props.device.sell_price
        : typeof props.device.sell_price === 'string'
            ? parseFloat(props.device.sell_price) || undefined
            : undefined,
    sale_destination: props.device.sale_destination || '',
    target_warehouse_id: Number(props.device.target_warehouse_id || 0),
    target_warehouse_name: props.device.target_warehouse_name || '',
    target_location_id: Number(props.device.target_location_id || 0),
    target_location_name: props.device.target_location_name || '',
    remark: props.device.remark || '',
    refurbishment_required: Number(props.device.refurbishment_required || 0),
    refurbishment_assignee_uid: Number(props.device.refurbishment_assignee_uid || 0),
    refurbishment_reason: props.device.refurbishment_reason || '',
    refurbishment_item_keys: [],
    refurbishment_custom_item: '',
    refurbishment_estimated_cost: Number(props.device.refurbishment_estimated_cost || 0)
})

const isFormValid = computed(() =>
    typeof deviceForm.final_price === 'number' && deviceForm.final_price >= 0
    && (deviceForm.refurbishment_required !== 1 || Number(deviceForm.refurbishment_assignee_uid || 0) > 0)
)

const priceChangeClass = ref('')
const saleDestinationText = computed(() =>
    saleDestinationOptions.value.find(item => item.value === deviceForm.sale_destination)?.label || ''
)
const saleDestinationDescription = computed(() =>
    saleDestinationOptions.value.find(item => item.value === deviceForm.sale_destination)?.description || ''
)

const updateResponsiveState = () => { isMobile.value = window.innerWidth <= 768 }
const userName = (user: any) => user.real_name || user.username || `员工#${user.uid}`
const moneyText = (value: any) => {
    const amount = typeof value === 'number' ? value : parseFloat(value || '0')
    if (!Number.isFinite(amount)) return '0'
    return Number.isInteger(amount) ? String(amount) : amount.toFixed(2)
}

const parseRefurbishmentItems = (value: any) => {
    if (!value) return { keys: [], custom: '' }
    let items = value
    if (typeof value === 'string') {
        try { items = JSON.parse(value) } catch { items = [] }
    }
    if (!Array.isArray(items)) return { keys: [], custom: '' }
    const presetNameMap = new Map(refurbishmentPresets.value.map(item => [item.name, item.key]))
    const presetKeySet = new Set(refurbishmentPresets.value.map(item => item.key))
    const keys: string[] = []
    const custom: string[] = []
    items.forEach((item: any) => {
        const rawKey = typeof item === 'object' && item ? String(item.item_key || item.key || '') : ''
        const name = typeof item === 'string' ? item : (item.item_name || item.name || '')
        const key = rawKey && presetKeySet.has(rawKey) ? rawKey : presetNameMap.get(name)
        if (key) keys.push(key)
        else if (name) custom.push(name)
    })
    return { keys: Array.from(new Set(keys)), custom: custom.join('、') }
}

const buildRefurbishmentItems = () => {
    const presetItems = deviceForm.refurbishment_item_keys
        .map(key => refurbishmentPresets.value.find(item => item.key === key))
        .filter(Boolean)
        .map((item: any) => ({ item_key: item.key, item_name: item.name, item_type: item.type }))
    const custom = deviceForm.refurbishment_custom_item.trim()
    if (custom) {
        presetItems.push({ item_name: custom, item_type: 'other' })
    }
    return presetItems
}

const applyRefurbishmentFromDevice = (device: DeviceInfo) => {
    const parsed = parseRefurbishmentItems(device.refurbishment_items)
    deviceForm.refurbishment_required = Number(device.refurbishment_required || 0)
    deviceForm.refurbishment_assignee_uid = Number(device.refurbishment_assignee_uid || 0)
    deviceForm.refurbishment_reason = device.refurbishment_reason || ''
    deviceForm.refurbishment_item_keys = parsed.keys
    deviceForm.refurbishment_custom_item = parsed.custom
    deviceForm.refurbishment_estimated_cost = Number(device.refurbishment_estimated_cost || 0)
    ensureAssigneeOption(device)
}

const applyDeviceToForm = (device: DeviceInfo) => {
    deviceData.value = { ...device }
    deviceForm.final_price = typeof device.final_price === 'number' ? device.final_price
        : typeof device.final_price === 'string' ? parseFloat(device.final_price) || undefined : undefined
    deviceForm.sell_price = typeof device.sell_price === 'number' ? device.sell_price
        : typeof device.sell_price === 'string' ? parseFloat(device.sell_price) || undefined : undefined
    deviceForm.sale_destination = device.sale_destination || saleDestinationOptions.value[0]?.value || ''
    deviceForm.remark = device.remark || ''
    applyRefurbishmentFromDevice(device)
    deviceData.value.status = 4
    updatePriceClass()
}

const loadDeviceDetail = async () => {
    const id = Number(deviceData.value.id || props.device?.id || 0)
    if (!id) return
    // 防止开窗时 visible / device 两个 watch 同帧触发导致重复请求同一设备
    if (detailLoading.value) return
    detailLoading.value = true
    try {
        const res: any = await getDevice(id)
        if (res?.code === 1 && res.data) {
            applyDeviceToForm({ ...deviceData.value, ...res.data })
        }
    } catch (e) {
        // 详情刷新失败不阻断弹窗，保留父组件传入的数据。
    } finally {
        detailLoading.value = false
    }
}

const ensureAssigneeOption = (device: DeviceInfo) => {
    const uid = Number(device.refurbishment_assignee_uid || 0)
    const name = String(device.refurbishment_assignee_name || '')
    if (uid <= 0 || userOptions.value.some((user: any) => Number(user.uid) === uid)) return
    userOptions.value.unshift({ uid, real_name: name || `员工#${uid}`, username: name || `员工#${uid}` })
}

const loadUsers = async () => {
    try {
        const res: any = await getRefurbishmentAssigneeOptions()
        userOptions.value = res.data?.users || res.data || []
        ensureAssigneeOption(deviceData.value)
    } catch (e) {
        userOptions.value = []
        ensureAssigneeOption(deviceData.value)
    }
}

const loadRefurbishmentOptions = async () => {
    try {
        const res: any = await getRefurbishmentOptions()
        refurbishmentPresets.value = res.data?.items || []
        applyRefurbishmentFromDevice(deviceData.value)
    } catch (e) {
        refurbishmentPresets.value = []
    }
}

const onWarehouseChange = (val: number) => {
    const w = erpWarehouses.value.find(item => item.id === val)
    deviceForm.target_warehouse_name = w?.warehouse_name || ''
    // 选仓即决定流向：按仓库业务类型自动设置 sale_destination
    if (w) deviceForm.sale_destination = WH_TYPE_DEST[w.business_type || 'mall'] || 'hold'
    // 换仓后库位需重选：若原库位不在新仓库位内则清空
    if (!(w?.locations || []).some(loc => loc.id === deviceForm.target_location_id)) {
        deviceForm.target_location_id = 0
        deviceForm.target_location_name = ''
    }
}

const onLocationChange = (val: number) => {
    const loc = currentWarehouseLocations.value.find(item => item.id === val)
    deviceForm.target_location_name = loc?.location_name || ''
}

// 仓库模式下未选仓库时，自动默认选中"默认入库仓"，没有则第一个；
// 用 watch 兼容仓库列表与设备详情两路异步先后到达，避免被回填覆盖成 0。
watch([erpWarehouses, () => deviceForm.target_warehouse_id], () => {
    if (!warehouseMode.value) return
    const valid = erpWarehouses.value.some(w => w.id === deviceForm.target_warehouse_id)
    if (valid) return
    const def = erpWarehouses.value.find(w => Number(w.is_default) === 1) || erpWarehouses.value[0]
    if (def) {
        deviceForm.target_warehouse_id = def.id
        onWarehouseChange(def.id)
    }
}, { deep: true })

const loadSaleDestinationOptions = async () => {
    try {
        const res: any = await getSaleDestinationOptions()
        saleDestinationOptions.value = res.data?.items || []
        erpWarehouses.value = res.data?.warehouses || []
        erpConnected.value = !!res.data?.erp_connected
        if (!saleDestinationOptions.value.some(item => item.value === deviceForm.sale_destination)) {
            deviceForm.sale_destination = saleDestinationOptions.value[0]?.value || ''
        }
    } catch (e) {
        saleDestinationOptions.value = []
        erpWarehouses.value = []
        erpConnected.value = false
    }
}

const updatePriceClass = () => {
    const init = typeof deviceData.value.before_price === 'string'
        ? parseFloat(deviceData.value.before_price)
        : (deviceData.value.before_price || 0)
    if (!deviceForm.final_price || !init) { priceChangeClass.value = ''; return }
    if (deviceForm.final_price > init) priceChangeClass.value = 'increase'
    else if (deviceForm.final_price < init) priceChangeClass.value = 'decrease'
    else priceChangeClass.value = ''
}

const getPriceChangeText = () => {
    const init = typeof deviceData.value.before_price === 'string'
        ? parseFloat(deviceData.value.before_price)
        : (deviceData.value.before_price || 0)
    if (!deviceForm.final_price || !init) return ''
    const diff = deviceForm.final_price - init
    if (diff > 0) return `↑ 上涨 ¥${diff.toFixed(2)}`
    if (diff < 0) return `↓ 下降 ¥${Math.abs(diff).toFixed(2)}`
    return '价格不变'
}

watch(() => props.visible, (v) => {
    dialogVisible.value = v
    if (v) {
        applyDeviceToForm(props.device as DeviceInfo)
        loadDeviceDetail()
        ensureOptionsLoaded()
    }
})
watch(() => props.device, (newVal) => {
    // 只同步表单，不在此再次请求详情：开窗拉取统一交给 visible 监听，避免开窗时两个监听重复请求
    applyDeviceToForm(newVal)
}, { deep: true })
watch(dialogVisible, (v) => { emit('update:visible', v) })

const handleCancel = () => { dialogVisible.value = false; emit('cancel') }
const handleConfirm = () => {
    if (!isFormValid.value) { ElMessage.warning('请输入有效的价格'); return }
    if (deviceForm.refurbishment_required === 1 && !deviceForm.refurbishment_assignee_uid) {
        ElMessage.warning('请选择整备负责人')
        return
    }
    emit('confirm', {
        id: deviceData.value.id,
        final_price: deviceForm.final_price,
        sell_price: deviceForm.sell_price,
        sale_destination: deviceForm.sale_destination,
        target_warehouse_id: deviceForm.target_warehouse_id || 0,
        target_warehouse_name: deviceForm.target_warehouse_name || '',
        target_location_id: deviceForm.target_location_id || 0,
        target_location_name: deviceForm.target_location_name || '',
        status: deviceData.value.status,
        remark: deviceForm.remark,
        refurbishment_required: deviceForm.refurbishment_required,
        refurbishment_assignee_uid: deviceForm.refurbishment_required === 1 ? deviceForm.refurbishment_assignee_uid : 0,
        refurbishment_reason: deviceForm.refurbishment_required === 1 ? deviceForm.refurbishment_reason : '',
        refurbishment_items: deviceForm.refurbishment_required === 1 ? buildRefurbishmentItems() : [],
        refurbishment_estimated_cost: deviceForm.refurbishment_required === 1 ? deviceForm.refurbishment_estimated_cost : 0
    })
}

// 选项数据改为"首次打开时"加载，避免组件常驻挂载时在进入列表页就发起无效请求
let optionsLoaded = false
const ensureOptionsLoaded = () => {
    if (optionsLoaded) return
    optionsLoaded = true
    loadUsers()
    loadRefurbishmentOptions()
    loadSaleDestinationOptions()
}

onMounted(() => {
    updateResponsiveState()
    applyRefurbishmentFromDevice(deviceData.value)
    if (props.visible) ensureOptionsLoaded()
    window.addEventListener('resize', updateResponsiveState)
})
onBeforeUnmount(() => { window.removeEventListener('resize', updateResponsiveState) })
</script>

<style lang="scss" scoped>
/* Body */
.pfd-body {
  &::-webkit-scrollbar { width: 5px; }
  &::-webkit-scrollbar-thumb { background: var(--el-border-color); border-radius: 3px; }
}

.pfd-layout {
  margin-top: 10px;
  display: grid;
  grid-template-columns: 260px minmax(0, 1fr);
  gap: 12px;
}

.pfd-sidebar,
.pfd-main {
  min-width: 0;
}

.pfd-sidebar {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.pfd-summary-card,
.pfd-process-note,
.pfd-section {
  background: var(--el-bg-color);
  border: 1px solid var(--el-border-color-lighter);
  border-radius: 12px;
}

.pfd-summary-card {
  overflow: hidden;

  &__title {
    padding: 10px 12px;
    font-size: 12px;
    font-weight: 500;
    color: var(--el-text-color-regular);
    background: var(--el-fill-color-light);
    border-bottom: 1px solid var(--el-border-color-lighter);
  }

  &__content {
    padding: 12px;
    min-height: 88px;
    color: var(--el-text-color-regular);
    font-size: 12px;
    line-height: 1.7;
    background: var(--el-fill-color-light);
    white-space: pre-line;
  }
}

.pfd-metric {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 12px;
  border-bottom: 1px solid var(--el-border-color-lighter);

  &:last-child { border-bottom: 0; }
  span { font-size: 12px; color: var(--el-text-color-secondary); }
  strong { font-size: 13px; color: var(--el-text-color-primary); }

  &__primary { color: var(--el-color-danger) !important; }
}

.pfd-process-note {
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  background: var(--el-color-warning-light-9);
  border-color: var(--el-color-warning-light-7);

  strong { font-size: 12px; color: var(--el-color-warning); }
  span { font-size: 12px; line-height: 1.6; color: var(--el-text-color-regular); }
}

.pfd-section {
  padding: 14px;
  margin-bottom: 12px;

  &__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;

    h4 {
      margin: 0;
      font-size: 15px;
      color: var(--el-text-color-primary);
      font-weight: 500;
    }

    p {
      margin: 4px 0 0;
      font-size: 12px;
      color: var(--el-text-color-secondary);
      line-height: 1.5;
    }
  }
}

.pfd-price-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.pfd-refurbish-card {
  border-color: var(--el-border-color-lighter);

  &.is-required {
    border-color: var(--el-color-warning-light-5);
    background: var(--el-color-warning-light-9);
  }
}

.pfd-refurbish-status {
  padding: 9px 10px;
  margin-bottom: 12px;
  border-radius: 8px;
  background: var(--el-fill-color-light);
  color: var(--el-text-color-secondary);
  font-size: 12px;
}

.pfd-refurbishment-items {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;

  :deep(.el-checkbox-button__inner) {
    border-radius: 999px !important;
    border-left: 1px solid var(--el-border-color);
    padding: 7px 12px;
  }
}

.pfd-print-tip {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 10px 12px;
  border-radius: 8px;
  background: var(--el-fill-color-light);
  border: 1px solid var(--el-border-color-lighter);

  strong { font-size: 12px; color: var(--el-text-color-regular); }
  span { font-size: 12px; line-height: 1.6; color: var(--el-text-color-secondary); }
}

/* 区块 header */
.pfd-block-header {
  display: flex;
  align-items: center;
  padding: 9px 12px;
  background: var(--el-fill-color-light);
  border-bottom: 1px solid var(--el-border-color-lighter);
  font-size: 12px;
  font-weight: 500;
  color: var(--el-text-color-regular);
}

/* 质检结果展示 */
.pfd-check-result {
  background: var(--el-bg-color);
  border: 1px solid var(--el-border-color-lighter);
  border-radius: 10px;


  &__content {
    padding: 10px 12px;
    font-size: 12px;
    color: var(--el-text-color-regular);
    background: var(--el-fill-color-light);
    line-height: 1.6;
    white-space: pre-line;
  }
}

/* 定价表单 */
.pfd-form-card {
  background: var(--el-bg-color);
  border: 1px solid var(--el-border-color-lighter);
  border-radius: 10px;

}

.pfd-form {
  :deep(.el-form-item__label) {
    font-size: 12px;
    font-weight: 500;
    color: var(--el-text-color-regular);
    padding-bottom: 4px;
  }
}

.cdd-pricing-main__ref {
  display: inline-block;
  margin-top: 6px;
  font-size: 12px;
  color: var(--el-color-danger);
  font-weight: 500;
}

/* 价格变化指示 */
.pfd-price-diff {
  margin-top: 6px;

  .pfd-price-diff__badge {
    display: inline-flex;
    align-items: center;
    font-size: 11px;
    font-weight: 500;
    padding: 2px 8px;
    border-radius: 4px;

    &.increase { background: var(--el-color-success-light-9); color: var(--el-color-success); }
    &.decrease { background: var(--el-color-danger-light-9); color: var(--el-color-danger); }
  }
}

.pfd-hint {
  font-size: 11px;
  color: var(--el-text-color-placeholder);
  margin-top: 4px;
}
.pfd-hint--warn {
  color: var(--el-color-warning);
}
.pfd-warehouse {
  margin-top: 12px;
}
.pfd-warehouse__label {
  font-size: 13px;
  color: var(--el-text-color-regular);
  margin-bottom: 6px;
}

/* 校验提示 */
.pfd-alert {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 10px;
  border-radius: 6px;
  font-size: 12px;
  margin-top: 4px;

  &--error { background: var(--el-color-danger-light-9); border: 1px solid var(--el-color-danger-light-7); color: var(--el-color-danger); }
  &--success { background: var(--el-color-success-light-9); border: 1px solid var(--el-color-success-light-7); color: var(--el-color-success); }
}

@media (max-width: 768px) {
  .pfd-layout {
    grid-template-columns: 1fr;
  }

  .pfd-price-grid {
    grid-template-columns: 1fr;
  }

  .pfd-section__head {
    flex-direction: column;
  }
}
</style>
