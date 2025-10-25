<template>
    <view class="order-detail bg-gray-100 min-h-screen pb-20">
        <!-- 订单状态卡片 -->
        <view class="status-card bg-white rounded-lg shadow-sm mx-2 mt-2 mb-3 overflow-hidden">
            <view class="p-3">
                <!-- 顶部进度条 -->
                <view class=" rounded px-2 py-3 mb-3">
                    <up-steps :current="currentStep">
                        <up-steps-item v-if="currentStep == 9" error title="已取消" desc="订单取消"></up-steps-item>
                        <up-steps-item v-else :title="item.name" v-for="item in [
                            { name: '已下单', desc: '订单已提交' },
                            { name: '待质检', desc: '待设备检测' },
                            { name: '待确认', desc: '待确认价格' },
                            { name: '待打款', desc: '等待转账' },
                            { name: '已完成', desc: '交易完成' }
                        ]">
                            <template #desc>
                                <text class="text-[12px] text-gray-500">{{ item.desc }}</text>
                            </template>
                        </up-steps-item>
                    </up-steps>
                </view>
                
                <!-- 状态标题 -->
                <view class="flex items-center justify-between mb-3">
                    <text class="text-xs text-gray-500">{{ orderInfo.create_at }}</text>
                    <view class="status-badge rounded-full py-1 px-2 inline-flex items-center" :class="`status-bg-${orderInfo.status}`">
                        <up-icon :name="orderInfo.status == '4' ? 'rmb-circle' : 'checkbox-mark'" size="12" color="#fff" class="mr-1"></up-icon>
                        <text class="text-xs font-medium text-white">{{ orderInfo.status_name }}</text>
                    </view>
                </view>
                
                <!-- 订单说明 -->
                <view class="bg-gray-50 rounded px-3 py-2 text-xs text-gray-700 leading-relaxed">
                    {{ getStatusDescription(orderInfo.status) }}
                </view>
            </view>
        </view>

        <!-- 设备列表 -->
        <view class="mx-2">
            <view class="flex justify-between items-center mb-2">
                <text class="text-base font-bold text-gray-800">设备列表</text>
                <text class="text-xs font-medium text-red-500 bg-white py-1 px-2 rounded-full shadow-sm">
                    总价值: ¥{{ final_price }}
                </text>
            </view>
            
            <!-- 设备批量操作工具栏 -->
            <view class="bg-white rounded px-3 py-2 mb-2 shadow-sm flex justify-between items-center" v-if="orderInfo.devices && orderInfo.devices.length > 0">
                <view class="flex items-center space-x-3">
                    <view class="flex items-center" @tap.stop="toggleSelectAll">
                        <up-checkbox :checked="isAllSelected" shape="square" @change="toggleSelectAll"></up-checkbox>
                        <text class="ml-1 text-xs">{{ isAllSelected ? '取消全选' : '全选' }}</text>
                    </view>
                    <view class="flex items-center" @tap.stop="copySelectedIMEIs">
                        <up-icon name="cut" size="14" color="#2979ff"></up-icon>
                        <text class="ml-1 text-xs text-blue-500">复制IMEI</text>
                    </view>
                </view>
                <view class="text-xs text-blue-500 font-medium" v-if="selectedDevices.length > 0">
                    已选择 {{ selectedDevices.length }} 台
                </view>
            </view>

            <view class="mb-2" v-for="(device, index) in orderInfo.devices" :key="device.id">
                <view class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <!-- 设备基本信息 -->
                    <view class="p-2 flex items-center border-b border-gray-100 relative">
                        <!-- 选择框 -->
                        <view class="mr-2" @tap.stop="toggleDeviceSelection(device.id)">
                            <up-checkbox :checked="isDeviceSelected(device.id)" shape="square" @change="(e: Event) => toggleDeviceSelection(device.id, e)"></up-checkbox>
                        </view>
                        
                        <!-- 设备信息 -->
                        <view class="flex-1 ">
                           
                            <text :class="['absolute top-0 right-0 text-xs py-0.5 px-1.5 rounded-bl-lg rounded-tr-lg', `status-text-${device.status}`]">
                               
                              
                             {{ device.status_name || getDeviceStatusText(device.status) }}
                            </text>
                            <view class="mt-2">
                                
                                <text class="text-sm font-medium text-gray-700 flex items-center">
                                  <text class="text-sm  text-[#579af8]  ">
                                    {{ index + 1 }}. 
                                  </text>
                                    {{ device.model || '待识别' }}
                                </text>
                            </view>
                            <view class="mt-0.5 flex items-center" @longpress="copyIMEI(device.imei)" >
                               
                                <text class="text-xs text-gray-600">IMEI: {{ device.imei }}</text>
                                <up-icon name="cut" size="12" color="#000" @tap="copyIMEI(device.imei)" class="ml-1 opacity-50"></up-icon>
                            </view>
                        </view>
                        
                        <!-- 价格和展开区域 -->
                        <view class="flex mt-2 flex-row items-center justify-center min-w-[60px]" @tap.stop="toggleDeviceDetail(device.id)">
                            <!-- 价格 -->
                            <view class="text-center mb-1 mr-1 ">
                                <text class="text-sm font-bold text-red-500 block" v-if="device.final_price && device.final_price !== '0.00'">¥{{ device.final_price }}</text>
                                <text class="text-xs text-gray-400 block" v-else>待定价</text>
                            </view>
                            
                            <!-- 展开/收起图标 -->
                            <view class="transition-transform duration-300" :class="{ 'transform rotate-180': isDeviceExpanded(device.id) }">
                                <up-icon name="arrow-down" size="16" color="#999"></up-icon>
                            </view>
                        </view>
                    </view>

                    <!-- 设备详细信息（展开时显示） -->
                    <view v-show="isDeviceExpanded(device.id)">
                        <!-- 设备图片展示 -->
                        <view class="ml-2 mr-2 p-2 bg-gray-50">
                            <view class="text-xs font-medium text-gray-700 mb-1" v-if="device.check_images">设备检测图片</view>
                            <view class="text-xs text-gray-500 mb-1" v-else>暂无设备图片</view>
                            <scroll-view scroll-x class="whitespace-nowrap" v-if="device.check_images">
                                <view class="inline-flex space-x-1.5 py-1">
                                    <view class="w-15 h-15 rounded overflow-hidden shadow-sm" v-for="(img_url, index) in device.check_images.split(',')" :key="index">
                                        <image :src="img(img_url)" mode="aspectFill" class="w-full h-full object-cover" @tap.stop="previewImage(device.check_images.split(','), index)" />
                                    </view>
                                </view>
                            </scroll-view>
                        </view>

                        <!-- 价格详情 -->
                        <view class="p-2">
                            <view class="bg-gray-50 rounded p-2 mb-1"  v-if="device.initial_price!== '0.00' || device.final_price !== '0.00'  ">
                                <view class="flex justify-between mb-1 text-xs" v-if="device.initial_price !== '0.00'">
                                    <text class="text-gray-500">预估价格</text>
                                    <text class="text-red-500 font-medium">¥{{ device.initial_price || '0.00' }}</text>
                                </view>
                                <view class="flex justify-between mb-1 text-xs" v-if="device.final_price !== '0.00'">
                                    <text class="text-gray-500 ">最终价格</text>
                                    <text class="text-red-500 font-medium">¥{{ device.final_price }}</text>
                                </view>
                                <view class="flex justify-between text-xs" v-if="device.remark">
                                    <text class="text-gray-500 w-[125rpx]">价格说明</text>
                                    <text class="text-gray-700">{{ device.remark }}</text>
                                </view>
                            </view>

                            <!-- 检测详情 -->
                            <view class="bg-gray-50 rounded p-2" v-if="device.check_result || device.check_at">
                                <view class="flex justify-between mb-1 text-xs" v-if="device.check_status !== undefined">
                                    <text class="text-gray-500">检测状态</text>
                                    <text class="text-gray-700">{{ device.check_status === 1 ? '已检测' : '未检测' }}</text>
                                </view>
                                <view class="flex justify-between mb-1 text-xs" v-if="device.check_result">
                                    <text class="text-gray-500 w-[125rpx]">检测结果</text>
                                    <text class="text-gray-700">{{ device.check_result }}</text>
                                </view>
                                <view class="flex justify-between text-xs" v-if="device.check_at">
                                    <text class="text-gray-500">检测时间</text>
                                    <text class="text-gray-700">{{ formatTime(device.check_at) }}</text>
                                </view>
                            </view>
                        </view>

                        <!-- 设备操作按钮 -->
                       
                        </view>
                        <view class="p-2 flex space-x-2 border-t border-gray-100" v-if="showDeviceActions(device.status)">
                            <button class="flex-1 h-8 rounded bg-gradient-to-r flex from-teal-500 to-green-600 items-center justify-center text-white text-xs" @tap.stop="handleNegotiate">
                                <up-icon name="chat-fill" size="14" color="#fff" class="mr-1"></up-icon>
                                议价
                            </button>
                            <button class="flex-1 h-8 rounded bg-gradient-to-r  from-pink-500 to-pink-600  flex items-center justify-center text-white text-xs" @tap.stop="handleDeviceConfirm(device)">
                                <up-icon name="checkmark" size="14" color="#fff" class="mr-1"></up-icon>
                                确认价格
                            </button>
                    </view>
                </view>
            </view>
        </view>

        <!-- 底部操作栏 -->
        <view class="fixed left-0 right-0 bottom-0 bg-white bg-opacity-95 p-2 shadow-up backdrop-blur-sm" v-if="selectedDevices.length > 0  && orderInfo.status == 5">
            <button class="w-full h-10 rounded-full bg-gradient-to-r from-green-500 to-green-600 flex items-center justify-center text-white text-sm font-medium" @tap="handleConfirmSelected">
                <up-icon name="checkmark-circle" size="16" color="#fff" class="mr-1"></up-icon>
                确认选中设备 ({{ selectedDevices.length }})
            </button>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { getOrderDetail, deviceConfirm, getDeviceStatus, deviceCancel, updateOrderStatus , deviceAllConfirm } from '@/addon/recycle/api/order'
import {  getRecycleUserAddressInfo } from '@/addon/recycle/api/return_order'
import { getPaymentList} from '@/addon/recycle/api/payment'
import { timeStampTurnTime as formatDate, redirect, img } from '@/utils/common'
import { useSubscribeMessage } from '@/hooks/useSubscribeMessage'

interface StatusResponse {
    device_status: Record<string, string>;
    order_status: Record<string, string>;
}

interface ApiResponse<T = any> {
    code: number;
    data: T;
    msg?: string;
}

// 状态类型定义
type OrderStatus = '1' | '2' | '3' | '4' | '5' | '6' | '7' | '8' | '9' | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9
type DeviceStatus = '1' | '2' | '3' | '4' | '5' | '6' | 1 | 2 | 3 | 4 | 5 | 6

interface Device {
    id: number;
    status: DeviceStatus | number;
    status_name?: string;
    model: string;
    imei: string;
    remark:string;
    initial_price: string | number;
    final_price?: string | number;
    check_result?: string;
    check_status: number;
    price_remark?: string;
    create_at: number;
    update_at: number | null;
    check_at: number | null;
    check_images?: string;
}

interface OrderInfo {
    id: number;
    order_no: string;
    status: OrderStatus | number;
    status_name?: string;
    create_at: number;
    devices: Device[];
    delivery_type: 'mail' | 'self';
    express_id?: string;
    send_username: string;
    telphone: string;
    member?: {
        nickname?: string;
        mobile?: string;
    }
}

// 添加地址信息和支付方式的类型定义
interface AddressInfo {
    id: number;
    id_card: string;
    name: string;
    mobile: string;
    address: string;
    [key: string]: any;
}

interface PaymentMethod {
    id: number;
    name: string;
    account: string;
    [key: string]: any;
}

const orderInfo = ref<OrderInfo>({
    id: 0,
    order_no: '',
    status: '1',
    create_at: 0,
    devices: [],
    delivery_type: 'self',
    send_username: '',
    telphone: '',
})

const loading = ref(false)
const expandedDevices = ref<number[]>([]) // 存储展开的设备ID
const selectedDevices = ref<number[]>([]) // 存储选中的设备ID
const selectedMap = ref<Record<number, boolean>>({}) // 用于绑定v-model的选中状态映射

// 状态存储
const statusData = ref<StatusResponse>({
    device_status: {},
    order_status: {}
})

// 是否全选
const isAllSelected = computed({
    get: () => {
        return orderInfo.value.devices.length > 0 && selectedDevices.value.length === orderInfo.value.devices.length
    },
    set: (val) => {
        if (val) {
            // 全选
            selectedDevices.value = orderInfo.value.devices.map(device => device.id)
            // 更新映射
            orderInfo.value.devices.forEach(device => {
                selectedMap.value[device.id] = true
            })
        } else {
            // 取消全选
            selectedDevices.value = []
            // 更新映射
            orderInfo.value.devices.forEach(device => {
                selectedMap.value[device.id] = false
            })
        }
    }
})

// 检查设备是否被选中
const isDeviceSelected = (deviceId: number): boolean => {
    return !!selectedMap.value[deviceId]
}

// 切换设备选中状态
const toggleDeviceSelection = (deviceId: number, e?: Event) => {
    // 切换当前状态
    selectedMap.value[deviceId] = !selectedMap.value[deviceId]
    
    // 更新selectedDevices数组
    if (selectedMap.value[deviceId]) {
        // 如果是选中状态且不在数组中，则添加
        if (!selectedDevices.value.includes(deviceId)) {
            selectedDevices.value.push(deviceId)
        }
    } else {
        // 如果是取消选中，从数组中移除
        const index = selectedDevices.value.indexOf(deviceId)
        if (index !== -1) {
            selectedDevices.value.splice(index, 1)
        }
    }
}

// 全选/取消全选
const toggleSelectAll = () => {
    isAllSelected.value = !isAllSelected.value
}

// 复制选中设备的IMEI
const copySelectedIMEIs = () => {
    if (selectedDevices.value.length === 0) {
        uni.showToast({
            title: '请先选择设备',
            icon: 'none'
        })
        return
    }
    
    const imeis = orderInfo.value.devices
        .filter(device => selectedDevices.value.includes(device.id))
        .map(device => device.imei)
        .join('\n')
    
    uni.setClipboardData({
        data: imeis,
        success: () => {
            uni.showToast({
                title: '已复制选中IMEI',
                icon: 'success'
            })
        }
    })
}

// 复制单个IMEI
const copyIMEI = (imei: string) => {
    uni.setClipboardData({
        data: imei,
        success: () => {
            uni.showToast({
                title: '已复制IMEI',
                icon: 'success'
            })
        }
    })
}

// 获取设备状态列表
const loadDeviceStatus = async () => {
    try {
        const res = await getDeviceStatus() as ApiResponse<StatusResponse>
        console.log('设备状态数据:', res)
        if (res.code === 1 && res.data) {
            statusData.value = {
                device_status: res.data.device_status || {},
                order_status: res.data.order_status || {}
            }
        } else {
            console.error('获取状态列表失败, 接口返回:', res)
        }
    } catch (error) {
        console.error('获取状态列表失败:', error)
    }
}

// 检查设备是否展开
const isDeviceExpanded = (deviceId: number): boolean => {
    return expandedDevices.value.includes(deviceId)
}

// 切换设备展开状态
const toggleDeviceDetail = (deviceId: number) => {
    const index = expandedDevices.value.indexOf(deviceId)
    if (index === -1) {
        // 展开设备
        expandedDevices.value.push(deviceId)
    } else {
        // 折叠设备
        expandedDevices.value.splice(index, 1)
    }
}

// 获取设备状态文本
const getDeviceStatusText = (status: DeviceStatus | number): string => {
    // 将数字转为字符串形式查找
    const statusStr = String(status)
    console.log( statusData.value.device_status[statusStr]);
    
    return statusData.value.device_status[statusStr] || '未知状态'
}

// 获取状态描述
const getStatusDescription = (status: OrderStatus | number): string => {
    // 将status转为数字进行比较
    const statusNum = Number(status)
    
    switch (statusNum) {
        case 1:
            return '您的订单已提交，等待商家确认'
        case 2:
            return '您的订单已签收，等待商家质检'
        case 3:
            return '商家正在质检您的设备，请耐心等待'
        case 4:
            return '设备已完成质检，等待您确认价格'
        case 5:
            return '您已确认部分设备价格，等待确认剩余设备'
        case 6:
            return '价格已确认，等待商家打款'
        case 7:
            return '交易已完成，感谢您的使用'
        case 8:
            return '订单已取消'
        case 9:
            return '订单已删除'
        default:
            return '订单状态未知'
    }
}

// 判断是否显示设备操作按钮
const showDeviceActions = (status: DeviceStatus | number): boolean => {
    return Number(status) === 4 // 只有待确认状态下才显示操作按钮
}

// 检查是否有未确认的设备
const hasUnconfirmedDevices = computed(() => {
    return orderInfo.value?.devices?.some(device => Number(device.status) === 4) || false
})

// 处理设备确认
const handleDeviceConfirm = async (device: Device) => {

    // 如果订单的状态 < 4 那么 提示用户等一等 等全部质检完后 在确认
    if (Number(orderInfo.value.status) < 4) {
        uni.showToast({
            title: '请等待全部设备质检完成后在确认',
            icon: 'none'
        })
        return
    }


    try {
        loading.value = true
        
        // 请求订阅相关消息通知
        await useSubscribeMessage().request('recycle_order_pay');
        
        // 检查支付方式
        const paymentRes = await getPaymentList() as ApiResponse<PaymentMethod[]>
        const addressRes = await getRecycleUserAddressInfo() as ApiResponse<AddressInfo>
        
        // 更安全的检查逻辑
        if (!paymentRes || paymentRes.code !== 1 || !paymentRes.data || paymentRes.data.length === 0
            || !addressRes || addressRes.code !== 1 || !addressRes.data || !addressRes.data.id_card
        ) {
            loading.value = false // 先解除loading状态
            uni.showModal({
                title: '提示',
                content: '请先完善信息',
                confirmText: '去添加',
                success: (res) => {
                    if (res.confirm) {
                        setTimeout(() => {
                            uni.navigateTo({
                                url: '/addon/recycle/pages/payment/index'
                            })
                        }, 100)
                    }
                }
            })
            return
        }


       
       

        // 执行确认操作
        const res = await deviceConfirm(device.id) as ApiResponse<any>
        if (res.code === 1) {
            uni.showToast({
                title: '确认成功',
                icon: 'success'
            })
            loadOrderDetail(orderInfo.value.id)
        } else {
            uni.showToast({
                title: res.msg || '操作失败',
                icon: 'none'
            })
        }
    } catch (error) {
        console.error('确认设备失败:', error)
        uni.showToast({
            title: '操作失败',
            icon: 'none'
        })
    } finally {
        loading.value = false
    }
}

// 处理批量确认选中设备
const handleConfirmSelected = async () => {
    if (selectedDevices.value.length === 0) {
        uni.showToast({
            title: '请先选择设备',
            icon: 'none'
        })
        return
    }
    
    try {
        loading.value = true
        
        // 请求订阅相关消息通知
        await useSubscribeMessage().request('recycle_order_pay');
        
        // 只确认状态为待确认(4)的设备
        const devicesToConfirm = orderInfo.value.devices
            .filter(device => selectedDevices.value.includes(device.id) && Number(device.status) === 4)
        
        if (devicesToConfirm.length === 0) {
            uni.showToast({
                title: '所选设备中没有需要确认的设备',
                icon: 'none'
            })
            return
        }
        
        // 确认所有选中的设备
        // await Promise.all(devicesToConfirm.map(device => deviceConfirm(device.id)))
        // 获取所有选中的设备 id 并且状态是4 
        const deviceIds = devicesToConfirm.map(device => device.id)

        // console.log(deviceIds);
        const res =  await deviceAllConfirm(deviceIds) as ApiResponse<any>

        if (res.code === 1) {
            uni.showToast({
                title: '批量确认成功',
                icon: 'success'
            })
        
        // 重新加载数据并重置选择状态
        await loadOrderDetail(orderInfo.value.id)
        selectedDevices.value = []
        selectedMap.value = {}
        } else {
            uni.showToast({
                title: res.msg || '操作失败',
                icon: 'none'
            })
        }
    } catch (error) {
        console.error('批量确认失败:', error)
        uni.showToast({
            title: '操作失败',
            icon: 'none'
        })
    } finally {
        loading.value = false
    }
}

// 处理议价咨询，跳转至客服
const handleNegotiate = () => {
    redirect({ url: '/app/pages/member/contact', mode: 'navigateTo' })
}

// 格式化时间
const formatTime = (timestamp: number) => {
    return formatDate(timestamp)
}

// 获取订单详情
const loadOrderDetail = async (id: string | number) => {
    try {
        loading.value = true
        // 将字符串类型的id转换为数字
        const numericId = typeof id === 'string' ? parseInt(id) : id
        const res = await getOrderDetail(numericId) as ApiResponse<OrderInfo>
        if (res.code === 1) {
            orderInfo.value = res.data
            
            // 初始化设备选择状态
            selectedDevices.value = [] // 重置选中状态
            selectedMap.value = {} // 重置映射
            
            // 设置每个设备的选择状态为false（未选中）
            orderInfo.value.devices.forEach(device => {
                selectedMap.value[device.id] = false
            })
            
            // 默认展开第一个设备
            if (orderInfo.value.devices && orderInfo.value.devices.length > 0) {
                expandedDevices.value = [orderInfo.value.devices[0].id]
            }
        }
    } catch (error) {
        console.error('获取订单详情失败:', error)
    } finally {
        loading.value = false
    }
}

// 预览图片
const previewImage = (images: string[], current: number) => {
    // 处理图片路径
    const formattedImages = images.map(item => img(item));
    
    uni.previewImage({
        urls: formattedImages,
        current: formattedImages[current],
        longPressActions: {
            itemList: ['保存图片'],
            success: (data) => {
                if (data.tapIndex === 0) {
                    uni.saveImageToPhotosAlbum({
                        filePath: formattedImages[current],
                        success: () => {
                            uni.showToast({
                                title: '保存成功',
                                icon: 'success'
                            })
                        },
                        fail: (err) => {
                            console.error('保存图片失败:', err)
                            uni.showToast({
                                title: '保存失败',
                                icon: 'none'
                            })
                        }
                    })
                }
            }
        }
    })
}

// 计算当前步骤
const currentStep = computed(() => {
    // 将status转为数字
    const statusNum = Number(orderInfo.value?.status)
    
    switch (statusNum) {
        case 1: // 已下单
            return 0
        case 2: // 待质检
        case 3: // 质检中
            return 1
        case 4: // 待确认
        case 5: // 部分确认
            return 2
        case 6: // 待打款
            return 3
        case 7: // 已完成
            return 4
        case 8: // 已取消
        case 9: // 已删除
            return 9
        default:
            return 0
    }
})

// 计算最终价格
const final_price = computed(() => {
    // 通过计算device列表中的final_price
    return orderInfo.value?.devices?.reduce((total, device) => {
        return total + parseFloat(String(device.final_price || 0))
    }, 0).toFixed(2)
})

// 页面加载
onLoad((options?: Record<string, any>) => {
    if (options?.id) {
        loadOrderDetail(options.id)
    }
    loadDeviceStatus()
})

// 添加onShow生命周期钩子，请求订阅相关消息
onShow(async () => {
  // 请求订阅相关消息通知
  await useSubscribeMessage().request('recycle_order_sign,recycle_order_agree,recycle_order_pay');
});

// 确保设备状态加载
onMounted(() => {
    loadDeviceStatus()
})
</script>

<style lang="scss">
/* 状态背景色 */
.status-bg-1 { background: linear-gradient(135deg, #ffa726, #fb8c00); }
.status-bg-2 { background: linear-gradient(135deg, #42a5f5, #1e88e5); }
.status-bg-3 { background: linear-gradient(135deg, #42a5f5, #1e88e5); }
.status-bg-4 { background: linear-gradient(135deg, #66bb6a, #43a047); }
.status-bg-5 { background: linear-gradient(135deg, #66bb6a, #43a047); }
.status-bg-6 { background: linear-gradient(135deg, #ec407a, #d81b60); }
.status-bg-7 { background: linear-gradient(135deg, #7e57c2, #5e35b1); }
.status-bg-8 { background: linear-gradient(135deg, #ef5350, #e53935); }
.status-bg-9 { background: linear-gradient(135deg, #78909c, #546e7a); }

/* 设备状态文本颜色 */
.status-text-1 { background: rgba(255, 152, 0, 0.1); color: #f57c00; }
.status-text-2 { background: rgba(33, 150, 243, 0.1); color: #1976d2; }
.status-text-3 { background: rgba(76, 175, 80, 0.1); color: #388e3c; }
.status-text-4 { background: rgba(255, 64, 0, 0.146); color: #f52d00; }
.status-text-5 { background: rgba(33, 243, 103, 0.1); color: #15c02c; }
.status-text-6 { background: rgba(244, 67, 54, 0.1); color: #d32f2f; }

/* 底部阴影 */
.shadow-up {
    box-shadow: 0 -2rpx 10rpx rgba(0, 0, 0, 0.05);
}
</style>
