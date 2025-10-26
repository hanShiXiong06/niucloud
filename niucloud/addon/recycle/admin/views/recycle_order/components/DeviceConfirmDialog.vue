<template>
    <el-dialog v-model="dialogVisible" title="设备信息确认" width="800" center>
        <template #header>
            <div class="flex justify-between items-center">
                <span class="text-lg font-bold">设备信息确认 , 共 {{ devices.length }} 台 </span>
            </div>
        </template>

        <el-table :data="devices" border v-loading="loading">
            <el-table-column >
                <template #header>
                    <span>IMEI</span>
                    <span class="text-sm text-gray-500">支持扫码枪输入 </span>
                </template>
                <!-- |支持扫码枪直接输入，输入完成会自动跳转到型号输入 -->
                <template #default="{ row }">
                    <div v-if="row.editing" class="imei-input-container">
                        <el-input 
                            v-model="row.imei" 
                            placeholder="请输入或扫描IMEI" 
                            ref="imeiInputRef"
                            autofocus
                            @keydown.enter="handleScanComplete(row)"

                            class="imei-input"
                        >
                            <template #suffix>
                                <el-icon title="支持扫码枪输入" class="scanner-icon">
                                    <svg viewBox="0 0 1024 1024" width="16" height="16">
                                        <path d="M864 64H160C107 64 64 107 64 160v128c0 17.7 14.3 32 32 32s32-14.3 32-32V160c0-17.7 14.3-32 32-32h704c17.7 0 32 14.3 32 32v128c0 17.7 14.3 32 32 32s32-14.3 32-32V160c0-53-43-96-96-96z" fill="currentColor"></path>
                                        <path d="M864 896H160c-17.7 0-32-14.3-32-32V736c0-17.7-14.3-32-32-32s-32 14.3-32 32v128c0 53 43 96 96 96h704c53 0 96-43 96-96V736c0-17.7-14.3-32-32-32s-32 14.3-32 32v128c0 17.7-14.3 32-32 32zM640 288c0 17.7 14.3 32 32 32s32-14.3 32-32-14.3-32-32-32-32 14.3-32 32z" fill="currentColor"></path>
                                        <path d="M352 320h160c17.7 0 32-14.3 32-32s-14.3-32-32-32H352c-17.7 0-32 14.3-32 32s14.3 32 32 32zM544 704H160c-17.7 0-32-14.3-32-32V352c0-17.7 14.3-32 32-32h128c17.7 0 32-14.3 32-32s-14.3-32-32-32H160c-53 0-96 43-96 96v320c0 53 43 96 96 96h384c17.7 0 32-14.3 32-32s-14.3-32-32-32z" fill="currentColor"></path>
                                        <path d="M864.2 384c-17.7 0-32 14.3-32 32v256c0 17.7 14.3 32 32 32 17.7 0 32-14.3 32-32V416c0-17.7-14.3-32-32-32zM96 576c-17.7 0-32 14.3-32 32s14.3 32 32 32h64c17.7 0 32-14.3 32-32s-14.3-32-32-32H96zM736 576c-17.7 0-32 14.3-32 32s14.3 32 32 32h64c17.7 0 32-14.3 32-32s-14.3-32-32-32h-64z" fill="currentColor"></path>
                                    </svg>
                                </el-icon>
                            </template>
                        </el-input>
                      
                    </div>
                    <span v-else>{{ row.imei }}</span>
                </template>
            </el-table-column>
            <el-table-column label="型号">
                <template #default="{ row }">
                    <div v-if="row.editing">
                        <el-input 
                            v-model="row.model" 
                            placeholder="请输入型号" 
                            ref="modelInputRef"
                            @keydown.enter="handleModelEnter(row)"
                        />
                    </div>
                    <span v-else>{{ row.model }}</span>
                </template>
            </el-table-column>
            <el-table-column label="设备分类">
                <template #default="{ row }">
                    <el-select 
                        v-model="row.category" 
                        placeholder="请选择设备分类"
                        :class="{ 'category-required': !row.category || row.category === 0 }"
                    >
                        <el-option v-for="item in category" :key="item.id" :label="item.name" :value="item.id">
                            <span>{{ item.name }}</span>
                           
                        </el-option>
                    </el-select>
                    <div v-if="!row.category || row.category === 0" class="category-tip">
                        请选择分类，建议选择手机
                    </div>
                </template>
            </el-table-column>
            <el-table-column label="操作" width="160">
                <template #default="{ row, $index }">
                    <div v-if="row.editing">
                        <el-button-group>
                            <el-button type="success" size="small" @click="saveDevice(row, $index)">
                                保存
                            </el-button>
                            <el-button type="info" size="small" @click="cancelEdit(row, $index)">
                                取消
                            </el-button>
                        </el-button-group>
                    </div>
                    <el-button-group v-else>
                        <el-button type="primary" size="small" :icon="Edit" @click="handleEditDevice(row, $index)">
                            编辑
                        </el-button>
                        <el-button type="danger" size="small" :icon="Delete" @click="handleRemoveDevice(row, $index)">
                            删除
                        </el-button>
                    </el-button-group>
                </template>
            </el-table-column>
        </el-table>

        <div v-if="devices.length === 0" class="empty-data ">
            <el-empty>
               <div>
                  <div class="text-sm text-red-400">Tips 请将设备清点完毕后，再进行确认。</div>
                </div>
                </el-empty>
        </div>

        <template #footer>
            <div class="dialog-footer">
                <el-button type="success" @click="handleAddDevice">添加设备</el-button>
                <el-button type="primary" @click="handleConfirm" :loading="submitting">
                    确认并签收
                </el-button> 
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { ref, defineProps, defineEmits, watch, toRaw, nextTick } from 'vue'
import { Edit, Delete , Notification } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getImeiInfo } from '@/addon/recycle/api/recycle_order'

// 定义设备信息接口
interface Device {
    id?: string | number;
    imei: string;
    model: string;
    initial_price: number;
    editing: boolean;
    category: string | number;
    _originalData?: any; // 用于存储编辑前的原始数据
    [key: string]: any;
}

interface Category {
    id: string | number;
    name: string;
}

const category = ref<Category[]>([
    {
        id: 1,
        name: '手机'
    },
    {
        id: 2,
        name: '平板'
    },
    {
        id: 3,
        name: '笔记本'
    },
    {
        id: 4,
        name: '手表'
    },
    {
        id: 5,
        name: '其他'
    }
])

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
    'cancel',
    'add-device',
    'edit-device',
    'remove-device'
])

// 内部状态
const dialogVisible = ref(props.visible)
const devices = ref<Device[]>([])
const loading = ref(false)
const submitting = ref(false)
// 保存原始设备列表，用于取消操作
const originalDeviceList = ref<Device[]>([])
// 输入框引用
const imeiInputRef = ref<any>(null)
const modelInputRef = ref<any>(null)

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
        console.log(devicesCopy);
        devices.value = devicesCopy.map((device: Device) => ({
            ...device,
            editing: false, // 确保所有设备初始时不在编辑状态
            category: device.category_id || 1, // 默认选择手机分类(ID=1)，如果已有分类则保持
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

// 添加设备
const handleAddDevice = () => {


    // 检查是否有未保存的编辑中设备
    const editingDevice = devices.value.find(d => d.editing)
    if (editingDevice) {
        ElMessage.warning('请先保存正在编辑的设备')
        return
    }

    // 添加一个新的设备到内部列表，并设置为编辑状态
    const newDevice = {
        imei: '',
        model: '',
        initial_price: 0,
        editing: true,
        category: 1, // 默认选择手机分类
        isNew: true
    }

    devices.value.push(newDevice)

  
    
    // 添加后自动聚焦到IMEI输入框
    nextTick(() => {
        if (imeiInputRef.value && imeiInputRef.value.focus) {
            imeiInputRef.value.focus()
        }
    })
}

// 编辑设备
const handleEditDevice = (row: Device, index: number) => {
    // 检查是否有其他正在编辑的设备
    const editingDevice = devices.value.find(d => d.editing)
    if (editingDevice) {
        ElMessage.warning('请先保存正在编辑的设备')
        return
    }

    // 保存原始数据，用于取消编辑
    devices.value[index]._originalData = JSON.parse(JSON.stringify(row))

    // 设置编辑状态
    devices.value[index].editing = true

    // 通知父组件（可选）
    emit('edit-device', row)
    
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
    if (!row.category || row.category === 0) {
        ElMessage.warning('请选择设备分类，建议选择手机')
        return
    }

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
const handleRemoveDevice = async (row: Device, index: number) => {
    try {
        // 确认删除操作
        await ElMessageBox.confirm('确定要删除该设备吗？', '提示', {
            confirmButtonText: '确定',
            cancelButtonText: '取消',
            type: 'warning'
        })

        // 从内部设备列表中移除该设备（本地操作）
        devices.value.splice(index, 1)
        
        // 可选：如果设备已有ID，也通知父组件（用于已保存的设备）
        if (row.id) {
            emit('remove-device', row)
        }
        
        ElMessage.success('设备已删除')

    } catch (error: any) {
        // 用户取消删除
        if (error === 'cancel') return
        console.error('删除设备失败:', error)
        ElMessage.error('删除失败：' + (error.message || '未知错误'))
    }
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
    const invalidCategoryDevice = devices.value.find(device => !device.category || device.category === 0)
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
                const { editing, _originalData, isNew, category, ...rest } = rawDevice
                return {
                    ...rest,
                    category_id: category || 1 // 将category转换为category_id
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
</script>

<style lang="scss" scoped>
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

.imei-input-container {
    position: relative;
    
    .imei-input {
        width: 100%;
    }
    
    .scan-tip {
        font-size: 12px;
        color: #909399;
        margin-top: 4px;
    }
    
    .scanner-icon {
        cursor: pointer;
        color: #409EFF;
        
        &:hover {
            color: #66b1ff;
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
}
</style>