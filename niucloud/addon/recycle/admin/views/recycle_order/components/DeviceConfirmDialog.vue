<template>
    <el-dialog
        v-model="dialogVisible"
        title="设备信息确认"
        :width="isMobile ? '95vw' : '900px'"
        top="4vh"
        center
        class="device-confirm-dialog"
    >
        <template #header>
            <div class="flex justify-between items-center">
                <span class="text-lg font-bold">设备信息确认，共 {{ devices.length }} 台</span>
                <div class="flex gap-2">
                    <el-button type="success" size="small" @click="fetchLocalDevices" :loading="fetchingLocal">
                        <el-icon><Connection /></el-icon>
                        读取本地设备
                    </el-button>
                    <el-button type="primary" :icon="Plus" size="small" @click="addDevice">
                        添加设备
                    </el-button>
                </div>
            </div>
        </template>

        <el-table v-if="!isMobile" :data="devices" border v-loading="loading" class="device-table">
            <el-table-column label="序号" width="60" align="center">
                <template #default="{ $index }">
                    <span class="device-index">{{ $index + 1 }}</span>
                </template>
            </el-table-column>
            <el-table-column label="用户串号" width="130">
                <template #default="{ row }">
                    <span class="user-sn-display">{{ row.user_sn || '未提交' }}</span>
                </template>
            </el-table-column>
            <el-table-column label="IMEI串号" width="200">
                <template #header>
                    <div class="header-with-tip">
                        <span>IMEI串号</span>
                        <el-icon class="scanner-tip-icon" title="支持扫码枪输入">
                            <svg viewBox="0 0 1024 1024" width="14" height="14">
                                <path d="M864 64H160C107 64 64 107 64 160v128c0 17.7 14.3 32 32 32s32-14.3 32-32V160c0-17.7 14.3-32 32-32h704c17.7 0 32 14.3 32 32v128c0 17.7 14.3 32 32 32s32-14.3 32-32V160c0-53-43-96-96-96z" fill="currentColor"></path>
                                <path d="M864 896H160c-17.7 0-32-14.3-32-32V736c0-17.7-14.3-32-32-32s-32 14.3-32 32v128c0 53 43 96 96 96h704c53 0 96-43 96-96V736c0-17.7-14.3-32-32-32s-32 14.3-32 32v128c0 17.7-14.3 32-32 32z" fill="currentColor"></path>
                            </svg>
                        </el-icon>
                    </div>
                </template>
                <template #default="{ row }">
                    <div v-if="row.editing" class="imei-input-container">
                        <el-input
                            v-model="row.imei"
                            placeholder="请输入或扫描"
                            ref="imeiInputRef"
                            autofocus
                            maxlength="15"
                            @keydown.enter="handleScanComplete(row)"
                            class="imei-input"
                        />
                    </div>
                    <div v-else class="imei-display">{{ formatImei(row.imei) }}</div>
                </template>
            </el-table-column>
            <el-table-column label="设备型号" min-width="160">
                <template #default="{ row }">
                    <div v-if="row.editing">
                        <el-input
                            v-model="row.model"
                            placeholder="请输入型号"
                            ref="modelInputRef"
                            @keydown.enter="handleModelEnter(row)"
                        />
                    </div>
                    <div v-else class="model-display">{{ row.model }}</div>
                </template>
            </el-table-column>
            <el-table-column label="分类" width="120">
                <template #default="{ row }">
                    <el-tree-select
                        v-model="row.category"
                        placeholder="请选择"
                        :data="categoryTree"
                        :props="treeSelectProps"
                        node-key="category_id"
                        :loading="categoryLoading"
                        filterable
                        check-strictly
                        :render-after-expand="false"
                        size="small"
                        class="w-full"
                        :class="{ 'category-required': isInvalidCategory(row.category) }"
                        @change="handleCategoryChange(row)"
                    />
                    <div v-if="isInvalidCategory(row.category)" class="category-tip">
                        请选择分类
                    </div>
                </template>
            </el-table-column>
            <el-table-column label="预估价" width="120">
                <template #default="{ row }">
                    <el-input-number
                        v-if="row.editing"
                        v-model="row.initial_price"
                        :min="0"
                        :controls="false"
                        placeholder="选填"
                        size="small"

                    />
                    <span v-else class="price-display">{{ row.initial_price ? `¥${row.initial_price}` : '—' }}</span>
                </template>
            </el-table-column>
            <el-table-column label="操作" width="160" fixed="right">
                <template #default="{ row, $index }">
                    <div v-if="row.editing" class="action-buttons">
                        <el-button type="success" size="small" @click="saveDevice(row, $index)">
                            保存
                        </el-button>
                        <el-button size="small" @click="cancelEdit(row, $index)">
                            取消
                        </el-button>
                    </div>
                    <div v-else class="action-buttons">
                        <el-button type="primary" link size="small" :icon="Edit" @click="handleEditDevice(row, $index)">
                            编辑
                        </el-button>
                        <el-button type="danger" link size="small" @click="deleteDevice($index)">
                            删除
                        </el-button>
                    </div>
                </template>
            </el-table-column>
        </el-table>

        <div v-else v-loading="loading" class="mobile-device-list">
            <div
                v-for="(row, index) in devices"
                :key="row.id || `device-${index}`"
                class="device-card"
                :class="{ 'editing': row.editing }"
            >
                <div class="card-header">
                    <div class="device-number">设备 {{ index + 1 }}</div>
                    <el-tag :type="row.editing ? 'warning' : 'success'" size="small">
                        {{ row.editing ? '编辑中' : '已保存' }}
                    </el-tag>
                </div>

                <div class="card-body">
                    <div class="info-row">
                        <div class="info-label">用户串号</div>
                        <div class="info-value imei-value">{{ row.user_sn || '未提交' }}</div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">IMEI串号</div>
                        <el-input
                            v-if="row.editing"
                            v-model="row.imei"
                            placeholder="请输入或扫描"
                            ref="imeiInputRef"
                            maxlength="15"
                            @keydown.enter="handleScanComplete(row)"
                        />
                        <div v-else class="info-value imei-value">{{ formatImei(row.imei) || '未填写' }}</div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">设备型号</div>
                        <el-input
                            v-if="row.editing"
                            v-model="row.model"
                            placeholder="请输入型号"
                            ref="modelInputRef"
                            @keydown.enter="handleModelEnter(row)"
                        />
                        <div v-else class="info-value">{{ row.model || '未填写' }}</div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">分类</div>
                        <el-tree-select
                            v-model="row.category"
                            placeholder="请选择"
                            :data="categoryTree"
                            :props="treeSelectProps"
                            node-key="category_id"
                            :loading="categoryLoading"
                            filterable
                            check-strictly
                            :render-after-expand="false"
                            size="small"
                            class="w-full"
                            :class="{ 'category-required': isInvalidCategory(row.category) }"
                            @change="handleCategoryChange(row)"
                        />
                        <div v-if="isInvalidCategory(row.category)" class="category-tip">
                            请选择分类
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">预估价</div>
                        <el-input-number
                            v-if="row.editing"
                            v-model="row.initial_price"
                            :min="0"
                            :precision="2"
                            :controls="false"
                            placeholder="选填"
                            size="small"
                            class="w-full"
                        />
                        <div v-else class="info-value price-value">{{ row.initial_price ? `¥${row.initial_price}` : '未填写' }}</div>
                    </div>
                </div>

                <div class="card-footer">
                    <div v-if="row.editing" class="action-group">
                        <el-button type="success" size="small" @click="saveDevice(row, index)" class="flex-1">保存</el-button>
                        <el-button size="small" @click="cancelEdit(row, index)" class="flex-1">取消</el-button>
                    </div>
                    <div v-else class="action-group">
                        <el-button type="primary" size="small" :icon="Edit" @click="handleEditDevice(row, index)" class="flex-1">
                            编辑
                        </el-button>
                        <el-button type="danger" size="small" @click="deleteDevice(index)" class="flex-1">
                            删除
                        </el-button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="devices.length === 0" class="empty-data ">
            <el-empty>
               <div>
                  <div class="text-sm text-red-400">Tips 请将设备清点完毕后，再进行确认。</div>
                </div>
                </el-empty>
        </div>

        <template #footer>
            
            <div :class="isMobile ? 'flex w-full flex-col gap-2' : 'dialog-footer'">
                <el-button type="primary" :class="isMobile ? '!ml-0 w-full' : ''" @click="handleConfirm" :loading="submitting">
                    确认并签收
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { ref, defineProps, defineEmits, watch, toRaw, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { Edit, Plus, Connection } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getImeiInfo, deleteOrderDevice } from '@/addon/recycle/api/recycle_order'
import axios from 'axios'

// 定义设备信息接口
interface Device {
    id?: string | number;
    imei: string;
    user_sn?: string;
    imei2?: string;
    model: string;
    initial_price: number;
    editing: boolean;
    category: string | number;
    category_path?: Array<string | number>;
    _originalData?: any; // 用于存储编辑前的原始数据
    // 扩展字段（不显示但提交时需要）
    serial_number?: string;
    color?: string;
    capacity?: string;
    system_version?: string;
    battery_health?: string;
    battery_cycle?: string;
    battery_cycle_count?: string;
    warranty_info?: string;
    info?: any; // 存储原始设备信息
    [key: string]: any;
}

interface Category {
    id: string | number;
    name: string;
    path: Array<string | number>;
}

interface GoodsCategoryTreeItem {
    category_id: string | number;
    category_name: string;
    child_list?: GoodsCategoryTreeItem[];
}

const fallbackCategory: Category[] = [
    {
        id: 1,
        name: '手机',
        path: [1]
    },
    {
        id: 2,
        name: '平板',
        path: [2]
    },
    {
        id: 3,
        name: '笔记本',
        path: [3]
    },
    {
        id: 4,
        name: '手表',
        path: [4]
    },
    {
        id: 5,
        name: '其他',
        path: [5]
    }
]

const category = ref<Category[]>([...fallbackCategory])
const fallbackCategoryTree: GoodsCategoryTreeItem[] = fallbackCategory.map(item => ({
    category_id: item.id,
    category_name: item.name,
    child_list: []
}))
const categoryTree = ref<GoodsCategoryTreeItem[]>([...fallbackCategoryTree])
const categoryLoading = ref(false)
const defaultCategoryId = ref<string | number>(fallbackCategory[0].id)
const treeSelectProps = {
    value: 'category_id',
    label: 'category_name',
    children: 'child_list'
}

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    deviceList: {
        type: Array as () => Device[],
        default: () => []
    },
    orderId: {
        type: [Number, String],
        required: true
    }
})

const emit = defineEmits([
    'update:visible',
    'confirm',
    'cancel'
])

// 内部状态
const dialogVisible = ref(props.visible)
const devices = ref<Device[]>([])
const loading = ref(false)
const submitting = ref(false)
const isMobile = ref(false)
const fetchingLocal = ref(false)
// 保存原始设备列表，用于取消操作
const originalDeviceList = ref<Device[]>([])
// 输入框引用
const imeiInputRef = ref<any>(null)
const modelInputRef = ref<any>(null)

const updateResponsiveState = () => {
    isMobile.value = window.innerWidth <= 768
}

const isEmptyCategory = (categoryId: string | number | undefined | null) => {
    return categoryId === undefined || categoryId === null || categoryId === '' || Number(categoryId) === 0
}

const findCategoryOptionById = (categoryId: string | number | undefined | null) => {
    if (isEmptyCategory(categoryId)) return null
    return category.value.find(item => String(item.id) === String(categoryId)) || null
}

const categoryExists = (categoryId: string | number | undefined | null) => {
    if (isEmptyCategory(categoryId)) return false
    return !!findCategoryOptionById(categoryId)
}

const isInvalidCategory = (categoryId: string | number | undefined | null) => {
    if (isEmptyCategory(categoryId)) return true
    if (categoryLoading.value) return false
    return !categoryExists(categoryId)
}

const normalizeCategoryId = (categoryId: string | number | undefined | null) => {
    if (!isEmptyCategory(categoryId)) return categoryId as string | number
    return defaultCategoryId.value
}

const normalizeCategoryPath = (
    categoryPath: unknown,
    categoryId: string | number | undefined | null
): Array<string | number> => {
    if (typeof categoryPath === 'string') {
        try {
            const parsed = JSON.parse(categoryPath)
            if (Array.isArray(parsed) && parsed.length > 0) {
                return parsed.map(item => String(item))
            }
        } catch (e) {
            const splitPath = categoryPath.split(',').map(item => item.trim()).filter(Boolean)
            if (splitPath.length > 0) {
                return splitPath
            }
        }
    }
    if (Array.isArray(categoryPath) && categoryPath.length > 0) {
        return categoryPath.map(item => String(item))
    }
    const matched = findCategoryOptionById(categoryId)
    if (matched && matched.path.length > 0) {
        return matched.path.map(item => String(item))
    }
    const normalizedId = normalizeCategoryId(categoryId)
    return [String(normalizedId)]
}

const useFallbackCategoryTree = () => {
    categoryLoading.value = false
    categoryTree.value = [...fallbackCategoryTree]
    category.value = [...fallbackCategory]
    defaultCategoryId.value = fallbackCategory[0].id
    devices.value.forEach((device) => {
        if (isEmptyCategory(device.category)) {
            device.category = defaultCategoryId.value
        }
        device.category_path = normalizeCategoryPath(device.category_path, device.category)
    })
}

// 监听visible属性变化
watch(() => props.visible, (newVal) => {
    dialogVisible.value = newVal
    // 当对话框打开时，复制一份设备列表数据以避免直接修改props
    if (newVal) {
        syncDeviceData()
    }
})

// 监听deviceList属性变化
watch(() => props.deviceList, () => {
    // 仅当对话框可见时且原始列表为空时更新内部设备列表
    // 这样可以确保仅在第一次加载时同步数据，之后由组件内部维护
    if (dialogVisible.value && devices.value.length === 0) {
        syncDeviceData()
    }
}, { deep: true })

// 同步设备数据的方法
const syncDeviceData = () => {
    try {
        const devicesCopy = JSON.parse(JSON.stringify(props.deviceList || []))
        devices.value = devicesCopy.map((device: Device) => ({
            ...device,
            editing: false, // 确保所有设备初始时不在编辑状态
            category: normalizeCategoryId((device as any).category_id ?? (device as any).category),
            category_path: normalizeCategoryPath(
                (device as any).category_path ?? (device as any).info?.goods_category,
                (device as any).category_id ?? (device as any).category
            ),
            _originalData: null // 清空原始数据
        }))
        // 保存一份原始数据，用于取消操作
        originalDeviceList.value = JSON.parse(JSON.stringify(devices.value))
    } catch (error) {
        console.error('设备数据同步失败:', error)
        devices.value = []
        originalDeviceList.value = []
    }
}

const getCategoryName = (categoryId: string | number) => {
    const current = findCategoryOptionById(categoryId)
    return current ? current.name : '未选择'
}

// 格式化IMEI显示，每4位添加空格
const formatImei = (imei: string) => {
    if (!imei) return ''
    return imei.replace(/(\d{4})(?=\d)/g, '$1 ')
}

// 从本地设备数据映射到 Device 对象
const mapLocalDeviceToDevice = (localDevice: any): Device => {
    // 提取型号信息
    const model = localDevice.display_name || localDevice.model_name || localDevice.model || '未知型号'

    // 提取存储容量
    const capacity = localDevice.storage || localDevice.total_storage || localDevice.capacity || ''

    // 提取颜色
    const color = localDevice.color || ''

    // 提取系统版本
    const system_version = localDevice.ios_version || localDevice.os_version || localDevice.system_version || localDevice.android_version || ''

    // 提取电池健康度（处理多种格式）
    let battery = undefined
    if (localDevice.battery_health) {
        const healthStr = String(localDevice.battery_health)

        // 如果是百分比格式（如 "73.9%" 或 "73.9"）
        if (healthStr.includes('%') || /^\d+(\.\d+)?$/.test(healthStr)) {
            const healthNum = parseFloat(healthStr.replace('%', '').trim())
            if (!isNaN(healthNum)) {
                battery = healthNum
            }
        }
        // 如果是中文描述（如 "良好"、"一般"、"差"），不转换为数字
        // 保持原样存储在 info 中
    }

    // 提取电池循环次数（转为数字，去掉"次"等单位）
    let battery_num = undefined
    if (localDevice.battery_cycle_count || localDevice.battery_cycle) {
        const cycleStr = String(localDevice.battery_cycle_count || localDevice.battery_cycle)
            .replace(/[次\s]/g, '').trim()
        if (cycleStr) {
            const cycleNum = parseInt(cycleStr)
            if (!isNaN(cycleNum)) {
                battery_num = cycleNum
            }
        }
    }

    // 提取序列号
    const serial_number = localDevice.serial_number || localDevice.sn || ''

    // 提取IMEI和IMEI2
    const imei = localDevice.imei || ''
    const imei2 = localDevice.imei2 || ''

    // 提取保修信息
    const warranty_info = localDevice.warranty_info || ''

    // 构建 info 对象，包含 check_meta
    const info = {
        ...localDevice,
        check_meta: {
            version: 2,
            battery,
            battery_num,
            activation_lock: false,
            mdm_lock: false,
            function_ids: [],
            fix_ids: []
        }
    }

    return {
        imei,
        imei2,
        model,
        initial_price: 0,
        editing: false,
        category: defaultCategoryId.value,
        category_path: normalizeCategoryPath([], defaultCategoryId.value),
        // 扩展字段
        serial_number,
        color,
        capacity,
        system_version,
        warranty_info,
        // 电池信息
        battery_health: battery !== undefined ? String(battery) : '',
        battery_cycle: battery_num !== undefined ? String(battery_num) : '',
        battery_cycle_count: battery_num !== undefined ? String(battery_num) : '',
        info, // 包含 check_meta 的完整信息
        _originalData: null
    }
}

// 读取本地设备
const fetchLocalDevices = async () => {
    fetchingLocal.value = true
    try {
        // 开发环境使用代理，生产环境直接请求（需要本地服务开启 CORS）
        const apiUrl = import.meta.env.DEV
            ? '/api/local-device/connected?raw=1'
            : 'http://localhost:8080/api/devices/connected?raw=1'

        const response = await axios.get(apiUrl, {
            timeout: 15000
        })

        if (response.data.code !== 0) {
            ElMessage.error('读取本地设备失败：' + (response.data.message || '未知错误'))
            return
        }

        const localDevices = response.data.data || []

        if (localDevices.length === 0) {
            ElMessage.warning('未检测到本地连接的设备')
            return
        }

        // 如果只有一个设备，直接处理
        if (localDevices.length === 1) {
            handleImportDevice(localDevices[0])
        } else {
            // 多个设备，弹出选择框
            showDeviceSelectionDialog(localDevices)
        }
    } catch (error: any) {
        console.error('读取本地设备失败:', error)
        if (error.code === 'ECONNABORTED' || error.message?.includes('timeout')) {
            ElMessage.error('连接本地服务超时（15秒），请检查本地服务是否正常运行')
        } else if (error.code === 'ERR_NETWORK' || error.message?.includes('Network Error')) {
            ElMessage.error('无法连接到本地服务，请确保服务运行在 http://localhost:8080')
        } else {
            ElMessage.error('读取本地设备失败：' + (error.message || '未知错误'))
        }
    } finally {
        fetchingLocal.value = false
    }
}

// 显示设备选择对话框
const showDeviceSelectionDialog = (localDevices: any[]) => {
    import('element-plus').then(({ ElCheckboxGroup, ElCheckbox }) => {
        const selectedDevices = ref<number[]>([])

        const deviceOptions = localDevices.map((device, index) => {
            const imei = device.imei || '无IMEI'
            const model = device.display_name || device.model_name || device.model || '未知型号'
            const storage = device.storage || device.total_storage || device.capacity || ''
            const color = device.color || ''

            let label = model
            if (storage) label += ` ${storage}`
            if (color) label += ` ${color}`
            label += ` (IMEI: ${formatImei(imei)})`

            return {
                label,
                value: index
            }
        })

        ElMessageBox({
            title: '选择要导入的设备',
            message: `检测到 ${localDevices.length} 个设备，请选择要导入的设备`,
            showCancelButton: true,
            confirmButtonText: '导入选中设备',
            cancelButtonText: '取消',
            customClass: 'device-selection-dialog',
            beforeClose: (action, instance, done) => {
                if (action === 'confirm') {
                    if (selectedDevices.value.length === 0) {
                        ElMessage.warning('请至少选择一个设备')
                        return
                    }

                    selectedDevices.value.forEach(index => {
                        handleImportDevice(localDevices[index])
                    })
                }
                done()
            }
        }).catch(() => {
            // 用户取消
        })

        // 动态插入复选框组
        setTimeout(() => {
            const messageBox = document.querySelector('.device-selection-dialog .el-message-box__message')
            if (messageBox) {
                const container = document.createElement('div')
                container.style.cssText = 'margin-top: 16px;'

                const checkboxContainer = document.createElement('div')
                checkboxContainer.style.cssText = 'max-height: 400px; overflow-y: auto; border: 1px solid #dcdfe6; border-radius: 4px; padding: 12px;'

                deviceOptions.forEach(option => {
                    const checkboxWrapper = document.createElement('label')
                    checkboxWrapper.style.cssText = 'display: flex; align-items: center; padding: 10px; border-radius: 4px; margin-bottom: 8px; cursor: pointer; transition: background-color 0.3s; user-select: none;'
                    checkboxWrapper.onmouseover = () => checkboxWrapper.style.backgroundColor = '#f5f7fa'
                    checkboxWrapper.onmouseout = () => checkboxWrapper.style.backgroundColor = 'transparent'

                    const checkbox = document.createElement('input')
                    checkbox.type = 'checkbox'
                    checkbox.value = String(option.value)
                    checkbox.style.cssText = 'margin-right: 10px; width: 16px; height: 16px; cursor: pointer;'
                    checkbox.onchange = (e) => {
                        const target = e.target as HTMLInputElement
                        if (target.checked) {
                            selectedDevices.value.push(option.value)
                        } else {
                            const idx = selectedDevices.value.indexOf(option.value)
                            if (idx > -1) selectedDevices.value.splice(idx, 1)
                        }
                    }

                    const text = document.createElement('span')
                    text.textContent = option.label
                    text.style.cssText = 'font-size: 14px; color: #606266; flex: 1; font-family: "Monaco", "Menlo", "Consolas", monospace;'

                    checkboxWrapper.appendChild(checkbox)
                    checkboxWrapper.appendChild(text)
                    checkboxContainer.appendChild(checkboxWrapper)
                })

                container.appendChild(checkboxContainer)
                messageBox.appendChild(container)
            }
        }, 100)
    })
}

// 处理单个设备导入
const handleImportDevice = (localDevice: any) => {
    const newDevice = mapLocalDeviceToDevice(localDevice)

    // 检查是否已存在相同IMEI的设备
    const existingIndex = devices.value.findIndex(d => d.imei === newDevice.imei)

    if (existingIndex !== -1) {
        // 已存在，询问是否覆盖
        ElMessageBox.confirm(
            `设备 ${newDevice.model} (IMEI: ${newDevice.imei}) 已存在，是否覆盖？`,
            '设备已存在',
            {
                type: 'warning',
                confirmButtonText: '覆盖',
                cancelButtonText: '取消'
            }
        ).then(() => {
            // 保留原有的 editing 状态和 id
            const existingDevice = devices.value[existingIndex]
            devices.value[existingIndex] = {
                ...newDevice,
                id: existingDevice.id,
                editing: existingDevice.editing
            }
            ElMessage.success('设备信息已更新')
        }).catch(() => {
            // 用户取消
        })
    } else {
        // 不存在，直接添加
        devices.value.push(newDevice)
        ElMessage.success(`已添加设备：${newDevice.model}`)
    }
}

// 添加新设备
const addDevice = () => {
    // 检查是否有其他正在编辑的设备
    const editingDevice = devices.value.find(d => d.editing)
    if (editingDevice) {
        ElMessage.warning('请先保存正在编辑的设备')
        return
    }

    const newDevice: Device = {
        imei: '',
        model: '',
        initial_price: 0,
        editing: true,
        isNew: true,
        category: defaultCategoryId.value,
        category_path: normalizeCategoryPath([], defaultCategoryId.value),
        _originalData: null
    }

    devices.value.push(newDevice)

    // 自动聚焦到新设备的IMEI输入框
    nextTick(() => {
        if (imeiInputRef.value && imeiInputRef.value.focus) {
            imeiInputRef.value.focus()
        }
    })
}

const handleCategoryChange = (row: Device) => {
    const matched = findCategoryOptionById(row.category)
    if (matched && matched.path.length > 0) {
        row.category_path = matched.path.map(item => String(item))
        return
    }
    row.category_path = normalizeCategoryPath([], row.category)
}

// 监听内部visible状态变化，同步到父组件
watch(dialogVisible, (newVal) => {
    emit('update:visible', newVal)
})

// 处理扫码枪输入完成事件
const handleScanComplete = (row: Device) => {
    // 扫码枪通常会触发回车键，所以我们检测IMEI是否已输入
    // 
    if (row.imei && row.imei.length > 0) {
 

            // 获取IMEI信息
            getImeiInfo(row.imei).then((res: any) => {
                if (res.code === 1 && res.data.name) {
                    row.model = res.data.name
                }else{
                    ElMessage.warning('未找到设备信息,请手动输入型号')
                }
            })
            // 自动聚焦到型号输入框
            nextTick(() => {
                if (modelInputRef.value && modelInputRef.value.focus) {
                    modelInputRef.value.focus()
                }
            })

    }
}

// 处理型号输入框回车事件
const handleModelEnter = (row: Device) => {
    // 如果型号也已输入，则尝试保存
    if (row.model && row.model.length > 0) {
        // 查找此设备的索引
        const index = devices.value.findIndex(d => d === row)
        if (index !== -1) {
            saveDevice(row, index)
        }
    }
}

// 编辑设备
const handleEditDevice = (_row: Device, index: number) => {
    // 检查是否有其他正在编辑的设备
    const editingDevice = devices.value.find(d => d.editing)
    if (editingDevice) {
        ElMessage.warning('请先保存正在编辑的设备')
        return
    }

    // 保存原始数据，用于取消编辑
    devices.value[index]._originalData = JSON.parse(JSON.stringify(_row))

    // 设置编辑状态
    devices.value[index].editing = true

    // 编辑时自动聚焦到IMEI输入框
    nextTick(() => {
        if (imeiInputRef.value && imeiInputRef.value.focus) {
            imeiInputRef.value.focus()
        }
    })
}

// 保存设备编辑
const saveDevice = (row: Device, index: number) => {
    // 验证设备信息
    if (!row.imei || !row.model) {
        ElMessage.warning('IMEI和型号不能为空')
        return
    }
    
    // 验证分类不能为0
    if (isInvalidCategory(row.category)) {
        ElMessage.warning('请选择设备分类，建议选择手机')
        return
    }

    devices.value[index].category_path = normalizeCategoryPath(row.category_path, row.category)

    // 关闭编辑状态
    devices.value[index].editing = false
    // 清除原始数据
    devices.value[index]._originalData = null

    ElMessage.success('设备信息已保存')
}

// 取消编辑
const cancelEdit = (row: Device, index: number) => {
    if (row.isNew) {
        // 如果是新增的设备，直接从列表中移除
        devices.value.splice(index, 1)
    } else if (row._originalData) {
        // 如果是编辑现有设备，恢复原始数据
        const { editing, _originalData, ...originalProps } = row._originalData
        Object.assign(devices.value[index], originalProps)
        devices.value[index].editing = false
        devices.value[index]._originalData = null
    } else {
        // 直接关闭编辑状态
        devices.value[index].editing = false
    }
}

// 删除设备
const deleteDevice = async (index: number) => {
    const device = devices.value[index]
    const label = device.imei ? `设备 ${device.imei}` : `设备 ${index + 1}`

    ElMessageBox.confirm(`确定要删除 ${label} 吗？`, '提示', {
        type: 'warning',
        confirmButtonText: '确定删除',
        cancelButtonText: '取消'
    }).then(async () => {
        // 如果设备有ID，说明已存在于数据库，需要调用API删除
        if (device.id) {
            try {
                await deleteOrderDevice(device.id)
                devices.value.splice(index, 1)
                ElMessage.success('已删除')
            } catch (error) {
                ElMessage.error('删除失败，请重试')
                console.error('删除设备失败:', error)
            }
        } else {
            // 新添加的设备，直接从本地数组删除
            devices.value.splice(index, 1)
            ElMessage.success('已删除')
        }
    }).catch(() => {
        // 用户取消，不做操作
    })
}

// 处理取消操作
const handleCancel = () => {
    // 检查是否有未保存的更改
    const hasUnsavedChanges = devices.value.some(d => d.editing) ||
        JSON.stringify(devices.value) !== JSON.stringify(originalDeviceList.value)

    if (hasUnsavedChanges) {
        ElMessageBox.confirm('有未保存的更改，确定要取消吗？', '提示', {
            type: 'warning'
        }).then(() => {
            dialogVisible.value = false
            emit('cancel')
        }).catch(() => {
            // 用户取消关闭对话框，不做任何操作
        })
    } else {
        dialogVisible.value = false
        emit('cancel')
    }
}

// 处理确认操作
const handleConfirm = async () => {

    // 设备列表不能为空
    if (devices.value.length === 0) {
        ElMessage.warning('请添加设备')
        return
    }

    // 检查是否有正在编辑的设备
    const editingDevice = devices.value.find(d => d.editing)
    if (editingDevice) {
        ElMessage.warning('请先保存或取消正在编辑的设备')
        return
    }

    // 验证设备数据的完整性
    const invalidDevice = devices.value.find(device => !device.imei || !device.model)
    if (invalidDevice) {
        ElMessage.warning('请填写完整的设备信息（IMEI和型号）')
        return
    }

    // 验证设备分类
    const invalidCategoryDevice = devices.value.find(device => isInvalidCategory(device.category))
    if (invalidCategoryDevice) {
        ElMessage.warning('请为所有设备选择分类，建议选择手机')
        return
    }

    submitting.value = true
    try {
        // 提交前去除编辑状态标志和内部临时属性，并过滤掉无效设备
        const finalDevices = devices.value
            .filter(device => !device.editing && device.imei && device.model) // 过滤掉编辑中的和空的设备
            .map(device => {
                const rawDevice = toRaw(device)
                const { editing, _originalData, isNew, category, category_path, ...rest } = rawDevice

                // 构建提交数据，包含所有扩展字段
                return {
                    ...rest,
                    category_id: normalizeCategoryId(category),
                    category_path: normalizeCategoryPath(category_path, category),
                    // 确保扩展字段被包含（即使为空也传递）
                    serial_number: rest.serial_number || '',
                    color: rest.color || '',
                    capacity: rest.capacity || '',
                    system_version: rest.system_version || '',
                    battery_health: rest.battery_health || '',
                    battery_cycle: rest.battery_cycle || '',
                    battery_cycle_count: rest.battery_cycle_count || '',
                    warranty_info: rest.warranty_info || '',
                    imei2: rest.imei2 || ''
                }
            })

        emit('confirm', {
            orderId: props.orderId,
            devices: finalDevices
        })
    } catch (error) {
        console.error('确认提交失败:', error)
        ElMessage.error('提交失败，请重试')
    } finally {
        submitting.value = false
    }
}

onMounted(() => {
    updateResponsiveState()
    window.addEventListener('resize', updateResponsiveState)
    useFallbackCategoryTree()
})

onBeforeUnmount(() => {
    window.removeEventListener('resize', updateResponsiveState)
})
</script>

<style lang="scss" scoped>
.device-confirm-dialog {
    :deep(.el-dialog__body) {
        padding: 20px;
        max-height: 70vh;
        overflow-y: auto;
    }
}

.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding-top: 20px;
}

.empty-data {
    display: flex;
    justify-content: center;
    flex-direction: column;
    padding: 30px 0;
}

.user-sn-display {
    color: #475569;
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 12px;
    word-break: break-all;
}

// 桌面端表格样式
.device-table {
    :deep(.el-table__header) {
        th {
            background-color: #f5f7fa;
            font-weight: 600;
            color: #606266;
        }
    }

    .device-index {
        font-weight: 600;
        color: #909399;
    }

    .header-with-tip {
        display: flex;
        align-items: center;
        gap: 6px;

        .scanner-tip-icon {
            color: #409EFF;
            cursor: help;
        }
    }

    .imei-display {
        font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
        font-size: 13px;
        color: #303133;
        letter-spacing: 0.5px;
        font-weight: 500;
    }

    .model-display {
        color: #303133;
        font-weight: 500;
    }

    .price-display {
        color: #67C23A;
        font-weight: 600;
    }

    .action-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
}

.imei-input-container {
    position: relative;

    .imei-input {
        width: 100%;

        :deep(.el-input__inner) {
            font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
            letter-spacing: 0.5px;
        }
    }
}

// 移动端卡片样式
.mobile-device-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.device-card {
    background: #fff;
    border: 1px solid #e4e7ed;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s ease;

    &.editing {
        border-color: #409EFF;
        box-shadow: 0 2px 12px rgba(64, 158, 255, 0.15);
    }

    &:not(.editing) {
        &:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        background: linear-gradient(135deg, #f5f7fa 0%, #e8edf3 100%);
        border-bottom: 1px solid #e4e7ed;

        .device-number {
            font-size: 14px;
            font-weight: 600;
            color: #303133;
        }
    }

    .card-body {
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 14px;

        .info-row {
            display: flex;
            flex-direction: column;
            gap: 6px;

            .info-label {
                font-size: 12px;
                color: #909399;
                font-weight: 500;
            }

            .info-value {
                font-size: 14px;
                color: #303133;
                min-height: 22px;
                display: flex;
                align-items: center;

                &.imei-value {
                    font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
                    font-size: 13px;
                    letter-spacing: 0.5px;
                    font-weight: 500;
                    color: #409EFF;
                }

                &.price-value {
                    color: #67C23A;
                    font-weight: 600;
                }
            }
        }
    }

    .card-footer {
        padding: 12px 16px;
        background: #fafafa;
        border-top: 1px solid #e4e7ed;

        .action-group {
            display: flex;
            gap: 8px;

            .flex-1 {
                flex: 1;
            }
        }
    }
}

.category-required {
    :deep(.el-select) {
        .el-input {
            .el-input__wrapper {
                border-color: #f56c6c;
                box-shadow: 0 0 0 1px #f56c6c inset;
            }
        }
    }
}

.category-tip {
    color: #f56c6c;
    font-size: 12px;
    margin-top: 4px;
    line-height: 1.4;
}

// 响应式优化
@media (max-width: 768px) {
    .device-confirm-dialog {
        :deep(.el-dialog__body) {
            padding: 12px;
        }
    }
}
</style>
