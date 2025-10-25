<template>
  <el-dialog 
    v-model="dialogVisible" 
    title="" 
    width="60%" 
    top="3vh"
    center
    :destroy-on-close="true"
    class="device-detail-dialog"
  >
    <template #header>
      <div class="flex justify-between items-center p-4 bg-gradient-to-r from-blue-600 to-purple-700 text-white rounded-t-lg">
        <div class="flex items-center space-x-3">
          <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
              <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
            </svg>
          </div>
          <div>
            <h3 class="text-lg text-left font-bold">设备详情</h3>
            <p class="text-blue-100 text-xs" v-if="deviceData">
              ID: {{ deviceData.id }} | {{ deviceData.model }}
            </p>
          </div>
        </div>
        <div v-if="deviceData" class="text-right">
          <div class="text-xs text-blue-100">IMEI</div>
          <div class="font-mono text-sm">{{ deviceData.imei }}</div>
        </div>
      </div>
    </template>

    <div v-if="deviceData" class="p-4 bg-gray-50 space-y-4 max-h-[80vh] overflow-y-auto">
      
      <!-- 设备概览卡片 - 更紧凑的布局 -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-2 bg-gradient-to-r from-gray-50 to-blue-50 border-b">
          <div class="flex items-center space-x-2">
            <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
              <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6z"/>
            </svg>
            <span class="font-medium text-gray-800 text-sm">设备概览</span>
          </div>
          <el-tag 
            :type="getStatusTagType(deviceData.status)" 
            effect="light"
            size="small"
            class="font-medium"
          >
            {{ deviceData.status_name }}
          </el-tag>
        </div>
        
        <div class="p-4">
          <!-- 基础信息区域 -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            <!-- 左侧：设备基本信息 -->
            <div class="space-y-3">
              <h4 class="font-medium text-gray-700 text-sm flex items-center space-x-1">
                <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4z"/>
                </svg>
                <span>基础信息</span>
              </h4>
              
              <div class="grid grid-cols-2 gap-3">
                <div class="bg-gray-50 rounded-md p-2">
                  <div class="text-xs text-gray-500">设备型号</div>
                  <div class="text-sm font-medium text-gray-900 truncate">{{ deviceData.model }}</div>
                </div>
                <div class="bg-gray-50 rounded-md p-2">
                  <div class="text-xs text-gray-500">IMEI码</div>
                  <div class="text-sm font-mono text-gray-900">{{ deviceData.imei }}</div>
                </div>
                <div class="bg-gray-50 rounded-md p-2">
                  <div class="text-xs text-gray-500">创建时间</div>
                  <div class="text-sm text-gray-900">{{ formatDate(deviceData.create_at) }}</div>
                </div>
                <div class="bg-gray-50 rounded-md p-2" v-if="deviceData.info?.sn">
                  <div class="text-xs text-gray-500">序列号</div>
                  <div class="text-sm font-mono text-gray-900">{{ deviceData.info.sn }}</div>
                </div>
              </div>
            </div>

            <!-- 右侧：保修和价格信息 -->
            <div class="space-y-3">
              <!-- 保修信息 -->
              <div v-if="deviceData.info">
                <h4 class="font-medium text-gray-700 text-sm flex items-center space-x-1 mb-2">
                  <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                  </svg>
                  <span>保修信息</span>
                </h4>
                
                <div class="grid grid-cols-2 gap-2">
                  <div class="bg-gray-50 rounded-md p-2">
                    <div class="text-xs text-gray-500">保修状态</div>
                    <span 
                      :class="[
                        'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                        deviceData.info.coverage?.status === 'In Warranty' 
                          ? 'bg-green-100 text-green-700' 
                          : 'bg-red-100 text-red-700'
                      ]"
                    >
                      {{ deviceData.info.coverage?.status || deviceData.info.support || '未激活' }}
                    </span>
                  </div>
                  <div class="bg-gray-50 rounded-md p-2" v-if="deviceData.info.purchase?.date">
                    <div class="text-xs text-gray-500">购买日期</div>
                    <div class="text-sm text-gray-900">{{ deviceData.info.purchase.date }}</div>
                  </div>
                </div>
              </div>

              <!-- 价格信息 -->
              <div>
                <h4 class="font-medium text-gray-700 text-sm flex items-center space-x-1 mb-2">
                  <svg class="w-4 h-4 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/>
                  </svg>
                  <span>价格信息</span>
                </h4>
                
                <div class="grid grid-cols-2 gap-2">
                  <div v-if="deviceData.before_price" class="bg-blue-50 rounded-md p-2 border border-blue-200">
                    <div class="text-xs text-blue-600">最终价格</div>
                    <div class="text-sm font-bold text-blue-700">¥{{ deviceData.before_price }}</div>
                  </div>
                  <div class="bg-orange-50 rounded-md p-2 border border-orange-200">
                    <div class="text-xs text-orange-600">最终价格</div>
                    <div class="text-sm font-bold text-orange-700">
                      {{ deviceData.final_price ? `¥${deviceData.final_price}` : '未定价' }}
                    </div>
                  </div>
                </div>
                
                <div v-if="deviceData.before_price && deviceData.final_price" 
                     class="mt-2 text-center">
                  <span 
                    :class="[
                      'inline-flex items-center text-xs font-medium px-2 py-1 rounded',
                      getPriceChangeClass() === 'positive' ? 'bg-green-100 text-green-700' :
                      getPriceChangeClass() === 'negative' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700'
                    ]"
                  >
                    变化: {{ getPriceChangeText() }}
                  </span>
                </div>
              </div>
            </div>
          </div>
          
          <!-- 价格备注 -->
          <div v-if="deviceData.price_remark" class="bg-blue-50 border-l-3 border-blue-400 p-3 rounded-r">
            <div class="flex items-start">
              <svg class="w-4 h-4 text-blue-400 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
              </svg>
              <div>
                <p class="text-xs font-medium text-blue-700">价格备注</p>
                <p class="text-xs text-blue-600 mt-0.5">{{ deviceData.price_remark }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 质检和日志 - 并排布局 -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- 质检信息卡片 -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
          <div class="flex items-center justify-between px-4 py-2 bg-gradient-to-r from-gray-50 to-green-50 border-b">
            <div class="flex items-center space-x-2">
              <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
              </svg>
              <span class="font-medium text-gray-800 text-sm">质检信息</span>
            </div>
            
          </div>
          
          <div class="p-4">
            <div v-if="deviceData.check_result" class="space-y-3">
              <!-- 质检时间和质检员 -->
              <div class="flex items-center justify-between text-xs text-gray-600">
                <div v-if="deviceData.check_at" class="flex items-center space-x-1">
                  <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                  </svg>
                  <span>{{ formatDate(deviceData.check_at) }}</span>
                </div>
                <div v-if="deviceData.checkUser" class="flex items-center space-x-1">
                  <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                  </svg>
                  <span>{{ deviceData.checkUser.real_name || deviceData.checkUser.username }}</span>
                </div>
              </div>
              
              <!-- 质检结果 -->
              <div class="bg-green-50 rounded-md p-3 border border-green-200">
                <h4 class="text-xs font-medium text-green-800 mb-1">质检结果</h4>
                <div class="text-xs text-green-700 leading-relaxed">
                  {{ deviceData.check_result }}
                </div>
              </div>
              <div  class="bg-red-50 rounded-md p-3 border border-red-200">
                <h4 class="text-xs font-medium text-red-800 mb-1">扣费说明</h4>
                <div class="text-xs text-red-700 leading-relaxed">
                  {{ deviceData.remark }}
                </div>
              </div>
              
              <!-- 质检图片 -->
              <div v-if="checkImagesArray.length > 0" class="space-y-2">
                <h4 class="text-xs font-medium text-gray-800">质检图片</h4>
                <div class="grid grid-cols-6 gap-2">
                  <div 
                    v-for="(imgUrl, index) in checkImagesArray" 
                    :key="index"
                    class="relative group cursor-pointer"
                    @click="previewImage(checkImagesArray, index)"
                  >
                    <el-image 
                      :src="img(imgUrl)"
                      fit="cover"
                      class="w-16 h-16 rounded border-2 border-gray-200 group-hover:border-blue-400 transition-colors duration-200"
                      lazy
                    >
                      <template #error>
                        <div class="w-full h-16 bg-gray-100 rounded flex flex-col items-center justify-center">
                          <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/>
                          </svg>
                          <span class="text-xs text-gray-400">错误</span>
                        </div>
                      </template>
                    </el-image>
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-200 rounded flex items-center justify-center">
                      <svg class="w-4 h-4 text-white opacity-0 group-hover:opacity-100 transition-opacity duration-200" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                        <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                      </svg>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- 未质检状态 -->
            <div v-else class="text-center py-6">
              <svg class="w-12 h-12 text-gray-300 mx-auto mb-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
              </svg>
              <h3 class="text-sm font-medium text-gray-400 mb-1">暂无质检结果</h3>
              <p class="text-xs text-gray-400">设备尚未进行质检</p>
            </div>
          </div>
        </div>

        <!-- 操作日志卡片 -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
          <div class="flex items-center justify-between px-4 py-2 bg-gradient-to-r from-gray-50 to-purple-50 border-b">
            <div class="flex items-center space-x-2">
              <svg class="w-4 h-4 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
              </svg>
              <span class="font-medium text-gray-800 text-sm">操作日志</span>
            </div>
            <el-tag v-if="deviceData.logs && deviceData.logs.length > 0" type="info" size="small" effect="plain">
              {{ deviceData.logs.length }} 条
            </el-tag>
          </div>
          
          <div class="p-4">
            <div v-if="deviceData.logs && deviceData.logs.length > 0" class="space-y-3">
              <div 
                v-for="(log, index) in deviceData.logs" 
                :key="log.id"
                class="relative"
              >
                <!-- 时间线 -->
                <div v-if="index < deviceData.logs.length - 1" 
                     class="absolute left-4 top-8 w-0.5 h-4 bg-gray-200"></div>
                
                <div class="flex items-start space-x-3">
                  <!-- 时间点 -->
                  <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center flex-shrink-0 relative z-10">
                    <div class="w-2 h-2 bg-purple-600 rounded-full"></div>
                  </div>
                  
                  <!-- 日志内容 -->
                  <div class="flex-1 min-w-0 bg-gray-50 rounded-md p-3">
                    <div class="flex items-start justify-between mb-1">
                      <div class="flex items-center space-x-2">
                        <span class="font-medium text-gray-900 text-sm">{{ log.operator_name }}</span>
                        <el-tag size="small" effect="light" type="primary">{{ log.status_name }}</el-tag>
                      </div>
                      <time class="text-xs text-gray-500">{{ formatDate(log.create_at) }}</time>
                    </div>
                    
                    <div v-if="log.remark" class="text-xs text-gray-700 leading-relaxed">
                      {{ log.remark }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- 无日志状态 -->
            <div v-else class="text-center py-6">
              <svg class="w-12 h-12 text-gray-300 mx-auto mb-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
              </svg>
              <h3 class="text-sm font-medium text-gray-400 mb-1">暂无操作日志</h3>
              <p class="text-xs text-gray-400">该设备暂无操作记录</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 空状态 -->
    <div v-else class="text-center py-12">
      <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="currentColor" viewBox="0 0 20 20">
        <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
      </svg>
      <h3 class="text-lg font-medium text-gray-400 mb-2">暂无设备详情</h3>
      <p class="text-gray-400">设备信息加载失败或数据不存在</p>
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
import { ref, defineProps, defineEmits, watch, computed, reactive } from 'vue'
import { img } from '@/utils/common'

// 定义设备信息接口
interface DeviceLog {
    id: number | string;
    operator_name: string;
    create_at: string;
    status_name: string;
    remark?: string;
}

interface DeviceOrder {
    id: number | string;
    status_name: string;
    delivery_type_name: string;
    express_no?: string;
    [key: string]: any;
}

interface DeviceInfo {
    sn?: string;
    model?: string;
    coverage?: {
        status?: string;
        description?: string;
    };
    support?: string;
    purchase?: {
        date?: string;
        validated?: boolean;
    };
    [key: string]: any;
}

interface DeviceDetail {
    id: number | string;
    imei: string;
    model: string;
    status: number | string;
    status_name: string;
    check_status?: number;
    check_result?: string;
    check_at?: string | number;
    check_images?: string;
    before_price?: number | string;
    final_price?: number | string;
    price_remark?: string;
    remark?: string;
    create_at: string;
    update_at: string;
    order?: DeviceOrder;
    logs?: DeviceLog[];
    info?: DeviceInfo;
    checkUser?: {
        uid: number;
        username: string;
        real_name?: string;
    };
    [key: string]: any;
}

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    device: {
        type: Object as () => DeviceDetail | null,
        default: null
    }
})

const emit = defineEmits(['update:visible', 'closed'])

// 内部状态
const dialogVisible = ref(props.visible)
const deviceData = ref<DeviceDetail | null>(props.device)

// 计算质检图片数组
const checkImagesArray = computed(() => {
    if (!deviceData.value?.check_images) return []
    return deviceData.value.check_images.split(',').map(url => url.trim()).filter(url => url)
})

// 价格变化相关函数
const getPriceChangeText = () => {
    if (!deviceData.value?.final_price || !deviceData.value?.before_price) return ''
    const initial = parseFloat(deviceData.value.before_price.toString())
    const final = parseFloat(deviceData.value.final_price.toString())
    const diff = final - initial
    if (diff > 0) return `+¥${diff.toFixed(2)}`
    if (diff < 0) return `-¥${Math.abs(diff).toFixed(2)}`
    return '无变化'
}

const getPriceChangeClass = () => {
    if (!deviceData.value?.final_price || !deviceData.value?.before_price) return ''
    const initial = parseFloat(deviceData.value.before_price.toString())
    const final = parseFloat(deviceData.value.final_price.toString())
    const diff = final - initial
    if (diff > 0) return 'positive'
    if (diff < 0) return 'negative'
    return 'neutral'
}

// 监听visible属性变化
watch(() => props.visible, (newVal) => {
    dialogVisible.value = newVal
})

// 监听device属性变化
watch(() => props.device, (newVal) => {
    deviceData.value = newVal
}, { deep: true })

// 监听内部visible状态变化，同步到父组件
watch(dialogVisible, (newVal) => {
    emit('update:visible', newVal)
    if (!newVal) {
        emit('closed')
    }
})

// 预览图片
const imageViewer = reactive({
    show: false,
    index: 0
})

const previewImageList = ref<string[]>([])

const previewImage = (images: string[], index: number) => {
    previewImageList.value = images.map(url => img(url))
    imageViewer.index = index
    imageViewer.show = true
}

// 工具函数
const getStatusTagType = (status: number | string) => {
    const statusNum = parseInt(status.toString())
    switch (statusNum) {
        case 1: return 'info'
        case 2: return 'success'
        case 3: return 'warning'
        case 4: return 'danger'
        case 5: return 'primary'
        default: return 'info'
    }
}

const formatDate = (dateStr: string | number) => {
    if (!dateStr) return '暂无'
    
    let date: Date;
    if (typeof dateStr === 'number') {
        // 如果是时间戳，需要判断是秒还是毫秒
        const timestamp = dateStr > 9999999999 ? dateStr : dateStr * 1000;
        date = new Date(timestamp);
    } else {
        date = new Date(dateStr);
    }
    
    if (isNaN(date.getTime())) return '暂无'
    
    return date.toLocaleString('zh-CN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    })
}
</script>

<style lang="scss" scoped>
.device-detail-dialog {
  :deep(.el-dialog) {
    border-radius: 12px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    overflow: hidden;
    max-width: 1400px;
  }

  :deep(.el-dialog__header) {
    padding: 0;
    border: none;
  }
  
  :deep(.el-dialog__body) {
    padding: 0;
    background-color: #f9fafb;
  }

  :deep(.el-dialog__headerbtn) {
    top: 1.5rem;
    right: 1.5rem;
    
    .el-dialog__close {
      color: white;
      font-size: 1.25rem;
      background: rgba(255, 255, 255, 0.2);
      border-radius: 0.375rem;
      padding: 0.25rem;
      
      &:hover {
        background: rgba(255, 255, 255, 0.3);
        color: white;
      }
    }
  }
}

// 自定义滚动条
:deep(.el-dialog__body) {
  &::-webkit-scrollbar {
    width: 6px;
  }
  
  &::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 3px;
  }
  
  &::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
    
    &:hover {
      background: #94a3b8;
    }
  }
}

// 动画效果
@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.bg-white {
  animation: slideUp 0.4s ease-out;
}

.bg-white:nth-child(2) {
  animation-delay: 0.1s;
}

.bg-white:nth-child(3) {
  animation-delay: 0.2s;
}

.bg-white:nth-child(4) {
  animation-delay: 0.3s;
}
</style>