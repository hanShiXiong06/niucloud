<template>
  <el-card class="mb-4" shadow="never" v-if="orderStatus >= 3">
    <template #header>
      <div class="flex justify-between items-center">
        <div class="flex items-center">
        <span class="font-bold">{{ t('rejectedBooks') }}</span>
          <el-tag v-if="rejectedList.length > 0" type="danger" class="ml-2" size="small">
            {{ rejectedList.length }} {{ t('itemCount') }}
          </el-tag>
        </div>
      </div>
    </template>

    <div v-loading="loading">
      <el-table :data="rejectedList" border stripe>
        <el-table-column prop="title" :label="t('bookTitle')" min-width="200">
          <template #default="{ row }">
            <div class="flex items-center">
              <el-image 
                v-if="row.img" 
                :src="getThumbImage(row.img)" 
                fit="cover" 
                class="w-[60px] h-[80px] mr-2"
                :preview-src-list="[getThumbImage(row.img)]"
              />
              <div>
                <div class="font-bold">{{ row.title }}</div>
                <div class="text-gray-500 text-sm">{{ row.author }}</div>
                <div class="text-gray-400 text-xs">ISBN: {{ row.isbn }}</div>
              </div>
            </div>
          </template>
        </el-table-column>
        
        <el-table-column prop="rejected_quantity" :label="t('rejectedQuantity')" width="100" align="center">
          <template #default="{ row }">
            <span class="text-red-500 font-medium">{{ row.rejected_quantity }}</span>
          </template>
        </el-table-column>
        
        <el-table-column prop="reject_reason" :label="t('rejectReason')" min-width="120">
          <template #default="{ row }">
            <div v-if="orderStatus === 3">
              <el-select 
                v-model="row.reject_reason" 
                size="small"
                @change="updateRejectReason(row)"
              >
                <el-option :label="t('rejectReasonDamaged')" value="damaged" />
                <el-option :label="t('rejectReasonNotMatch')" value="not_match" />
                <el-option :label="t('rejectReasonOld')" value="too_old" />
                <el-option :label="t('rejectReasonOther')" value="other" />
              </el-select>
            </div>
            <div v-else>
              <el-tag :type="getRejectReasonTagType(row.reject_reason)" effect="dark">
                {{ formatRejectReason(row.reject_reason) }}
            </el-tag>
            </div>
          </template>
        </el-table-column>
        
        <el-table-column :label="t('operation')" width="200" align="center">
          <template #default="{ row }">
            <el-button type="primary" link @click="showImagesDialog(row)">
              <el-icon class="mr-1"><Picture /></el-icon>
              {{ t('viewImages') }}
            </el-button>
            <el-button type="success" link @click="showUploadDialog(row)" v-if="orderStatus === 3">
              <el-icon class="mr-1"><Upload /></el-icon>
              {{ t('uploadImage') }}
            </el-button>
          </template>
        </el-table-column>
      </el-table>
      
      <div v-if="rejectedList.length === 0" class="text-center py-8 text-gray-400">
        {{ t('noRejectedBooks') }}
      </div>
    </div>

    <!-- 查看图片对话框 -->
    <el-dialog v-model="imagesDialog.visible" :title="t('rejectedBookImages')" width="800px">
      <div v-if="imagesDialog.book" class="mb-4">
        <div class="flex items-center mb-4">
          <el-image 
            v-if="imagesDialog.book.img" 
            :src="getThumbImage(imagesDialog.book.img)" 
            fit="cover" 
            class="w-[60px] h-[80px] mr-2"
          />
          <div>
            <div class="font-bold text-lg">{{ imagesDialog.book.title }}</div>
            <div class="text-gray-500">{{ imagesDialog.book.author }}</div>
            <div class="text-red-500">拒收数量: {{ imagesDialog.book.rejected_quantity }} 本</div>
            <div class="text-orange-500">拒收原因: {{ formatRejectReason(imagesDialog.book.reject_reason) }}</div>
          </div>
        </div>
        
        <el-tabs v-model="imagesDialog.activeTab" type="card">
          <el-tab-pane :label="t('allImages')" name="all">
            <div v-loading="imagesDialog.loading" class="py-4">
              <div v-if="imagesDialog.images.length > 0" class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div v-for="(image, index) in imagesDialog.images" :key="index" class="image-card">
                  <el-image 
                    :src="getThumbImage(image.image_url)" 
                    fit="cover"
                    :preview-src-list="imagesDialog.images.map(img => getThumbImage(img.image_url))"
                    :initial-index="index"
                    class="w-full h-[200px] object-cover rounded"
                  />
                  <div class="mt-2 text-center text-sm">
                    <el-tag :type="getImageTypeTagType(image.image_type)" size="small">
                    {{ formatImageType(image.image_type) }}
                    </el-tag>
                  </div>
                </div>
              </div>
              <div v-else class="text-center py-8 text-gray-400">
                {{ t('noImages') }}
              </div>
            </div>
          </el-tab-pane>
          <el-tab-pane :label="t('imageTypeCover')" name="cover">
            <div v-loading="imagesDialog.loading" class="py-4">
              <div v-if="getImagesByType(1).length > 0" class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div v-for="(image, index) in getImagesByType(1)" :key="index" class="image-card">
                  <el-image 
                    :src="getThumbImage(image.image_url)" 
                    fit="cover"
                    :preview-src-list="getImagesByType(1).map(img => getThumbImage(img.image_url))"
                    :initial-index="index"
                    class="w-full h-[200px] object-cover rounded"
                  />
                </div>
              </div>
              <div v-else class="text-center py-8 text-gray-400">
                {{ t('noImages') }}
              </div>
            </div>
          </el-tab-pane>
          <el-tab-pane :label="t('imageTypeDamage')" name="damage">
            <div v-loading="imagesDialog.loading" class="py-4">
              <div v-if="getImagesByType(2).length > 0" class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div v-for="(image, index) in getImagesByType(2)" :key="index" class="image-card">
                  <el-image 
                    :src="getThumbImage(image.image_url)" 
                    fit="cover"
                    :preview-src-list="getImagesByType(2).map(img => getThumbImage(img.image_url))"
                    :initial-index="index"
                    class="w-full h-[200px] object-cover rounded"
                  />
                </div>
              </div>
              <div v-else class="text-center py-8 text-gray-400">
                {{ t('noImages') }}
              </div>
            </div>
          </el-tab-pane>
          <el-tab-pane :label="t('imageTypeStain')" name="stain">
            <div v-loading="imagesDialog.loading" class="py-4">
              <div v-if="getImagesByType(3).length > 0" class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div v-for="(image, index) in getImagesByType(3)" :key="index" class="image-card">
                  <el-image 
                    :src="getThumbImage(image.image_url)" 
                    fit="cover"
                    :preview-src-list="getImagesByType(3).map(img => getThumbImage(img.image_url))"
                    :initial-index="index"
                    class="w-full h-[200px] object-cover rounded"
                  />
                </div>
              </div>
              <div v-else class="text-center py-8 text-gray-400">
                {{ t('noImages') }}
              </div>
            </div>
          </el-tab-pane>
          <el-tab-pane :label="t('imageTypeOther')" name="other">
            <div v-loading="imagesDialog.loading" class="py-4">
              <div v-if="getImagesByType(4).length > 0" class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div v-for="(image, index) in getImagesByType(4)" :key="index" class="image-card">
                  <el-image 
                    :src="getThumbImage(image.image_url)" 
                    fit="cover"
                    :preview-src-list="getImagesByType(4).map(img => getThumbImage(img.image_url))"
                    :initial-index="index"
                    class="w-full h-[200px] object-cover rounded"
                  />
                </div>
              </div>
              <div v-else class="text-center py-8 text-gray-400">
                {{ t('noImages') }}
              </div>
            </div>
          </el-tab-pane>
        </el-tabs>
      </div>
    </el-dialog>

    <!-- 上传图片对话框 -->
    <el-dialog v-model="uploadDialog.visible" :title="t('uploadRejectedBookImage')" width="700px">
      <div v-if="uploadDialog.book" class="mb-4">
        <div class="flex items-center mb-4 bg-gray-50 p-4 rounded">
          <el-image 
            v-if="uploadDialog.book.img" 
            :src="getThumbImage(uploadDialog.book.img)" 
            fit="cover" 
            class="w-[80px] h-[100px] mr-4"
          />
          <div class="flex-1">
            <div class="font-bold text-lg">{{ uploadDialog.book.title }}</div>
            <div class="text-gray-500">{{ uploadDialog.book.author }}</div>
            <div class="flex justify-between items-center mt-2">
              <div class="text-red-500">拒收数量: {{ uploadDialog.book.rejected_quantity }} 本</div>
              <div class="text-orange-500">拒收原因: {{ formatRejectReason(uploadDialog.book.reject_reason) }}</div>
            </div>
          </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="bg-white p-4 rounded shadow">
            <h3 class="font-bold text-lg mb-4">上传图片</h3>
            <el-form :model="uploadDialog.form" label-width="80px">
          <el-form-item :label="t('imageType')" required>
                <el-select v-model="uploadDialog.form.image_type" class="w-full" @change="handleImageTypeChange">
                  <el-option :label="t('imageTypeCover')" :value="1" :disabled="hasImageOfType(1)" />
                  <el-option :label="t('imageTypeDamage')" :value="2" :disabled="hasImageOfType(2)" />
                  <el-option :label="t('imageTypeStain')" :value="3" :disabled="hasImageOfType(3)" />
                  <el-option :label="t('imageTypeOther')" :value="4" :disabled="hasImageOfType(4)" />
            </el-select>
          </el-form-item>
          
          <el-form-item :label="t('image')" required>
                <upload-image v-model="uploadDialog.imageUrl" :width="'120px'" :height="'120px'" :image-text="'上传图片'" />
          </el-form-item>
          
              <el-form-item class="mt-4">
                <el-button type="primary" @click="confirmUpload" :loading="uploadDialog.loading">
                  {{ t('upload') }}
                </el-button>
          </el-form-item>
        </el-form>
      </div>
      
          <div class="bg-gray-50 p-4 rounded shadow">
            <h3 class="font-bold text-lg mb-4">已上传图片</h3>
            <div v-loading="imagesDialog.loading" class="h-[300px] overflow-y-auto">
              <div v-if="imagesDialog.images.length > 0" class="grid grid-cols-2 gap-2">
                <div v-for="(image, index) in imagesDialog.images" :key="index" class="image-card">
                  <el-image 
                    :src="getThumbImage(image.image_url)" 
                    fit="cover"
                    :preview-src-list="imagesDialog.images.map(img => getThumbImage(img.image_url))"
                    :initial-index="index"
                    class="w-full h-[120px] object-cover rounded"
                  />
                  <div class="mt-1 flex justify-between items-center px-2">
                    <el-tag :type="getImageTypeTagType(image.image_type)" size="small">
                      {{ formatImageType(image.image_type) }}
                    </el-tag>
          <el-button 
                      v-if="orderStatus === 3" 
                      type="danger" 
                      size="small" 
                      icon="Delete" 
                      circle 
                      @click="deleteImage(image.id)"
                    ></el-button>
                  </div>
                </div>
              </div>
              <div v-else class="text-center py-8 text-gray-400">
                {{ t('noImages') }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </el-dialog>
  </el-card>
</template>

<script lang="ts" setup>
import { reactive, ref, defineProps, defineEmits, watch } from 'vue'
import { t } from '@/lang'
import { ElMessageBox, ElMessage } from 'element-plus'
import { 
  getWjBooksRejectedBookList, 
  addWjBooksRejectedBook,
  getWjBooksRejectedImageList,
  uploadWjBooksRejectedImage,
  getWjBooksOrderBookList,
  updateWjBooksRejectedBook,
  deleteWjBooksRejectedImage
} from '@/addon/wj_books/api/wj_books_order'
import { Plus, Picture, Upload } from '@element-plus/icons-vue'
import { img } from '@/utils/common'
import UploadImage from '@/components/upload-image/index.vue'

const props = defineProps({
  orderId: {
    type: Number,
    default: 0
  },
  orderStatus: {
    type: Number,
    default: 0
  },
  rejectedBooks: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['update', 'updateOrderBookQuantity'])

// 拒收书籍列表
const rejectedList = ref<any[]>([])
const loading = ref(false)

// 订单书籍列表
const orderBookList = ref<any[]>([])

// 查看图片对话框
const imagesDialog = reactive({
  visible: false,
  loading: false,
  book: null as any,
  images: [] as any[],
  activeTab: 'all'
})

// 上传图片对话框
const uploadDialog = reactive({
  visible: false,
  loading: false,
  book: null as any,
  imageUrl: '', // 存储图片URL
  form: {
    rejected_book_id: 0,
    image_type: 1
  }
})

// 上传组件引用
const uploadRef = ref(null)

// 图片预览对话框
const previewDialog = reactive({
  visible: false,
  url: ''
})

/**
 * 获取缩略图
 */
const getThumbImage = (url: string) => {
  if (!url) return '';
  // 如果是完整URL，直接返回
  if (url.startsWith('http://') || url.startsWith('https://')) {
    return url;
  }
  // 如果是相对路径，添加域名
  const imgDomain = import.meta.env.VITE_IMG_DOMAIN || window.location.origin;
  return `${imgDomain}/${url.replace(/^\//, '')}`;
}

/**
 * 加载拒收书籍列表
 */
const loadRejectedList = () => {
  if (!props.orderId) return
  
  // 如果已经有传入的rejectedBooks数据，则直接使用
  if (props.rejectedBooks && props.rejectedBooks.length > 0) {
    rejectedList.value = props.rejectedBooks
    return
  }
  
  loading.value = true
  getWjBooksRejectedBookList(props.orderId).then(res => {
    rejectedList.value = res.data || []
    loading.value = false
  }).catch(() => {
    loading.value = false
  })
}

/**
 * 加载订单书籍列表
 */
const loadOrderBookList = () => {
  if (!props.orderId) return
  
  getWjBooksOrderBookList(props.orderId).then(res => {
    orderBookList.value = res.data || []
  })
}

/**
 * 格式化拒收原因
 */
const formatRejectReason = (reason: string) => {
  const reasonMap: Record<string, string> = {
    'damaged': t('rejectReasonDamaged'),
    'not_match': t('rejectReasonNotMatch'),
    'too_old': t('rejectReasonOld'),
    'other': t('rejectReasonOther')
  }
  return reasonMap[reason] || reason
}

/**
 * 格式化图片类型
 */
const formatImageType = (type: number) => {
  const typeMap: Record<number, string> = {
    1: t('imageTypeCover'),
    2: t('imageTypeDamage'),
    3: t('imageTypeStain'),
    4: t('imageTypeOther')
  }
  return typeMap[type] || type
}

/**
 * 显示图片对话框
 */
const showImagesDialog = (book: any) => {
  imagesDialog.book = book
  imagesDialog.visible = true
  imagesDialog.activeTab = 'all'
  imagesDialog.images = []
  
  loadRejectedImages(book)
}

/**
 * 根据类型获取图片
 */
const getImagesByType = (type: number) => {
  return imagesDialog.images.filter(image => image.image_type === type)
}

/**
 * 显示上传对话框
 */
const showUploadDialog = (book: any) => {
  uploadDialog.book = book
  uploadDialog.form.rejected_book_id = book.id
  uploadDialog.form.image_type = 1
  uploadDialog.imageUrl = ''
  uploadDialog.visible = true
}

/**
 * 检查是否已有指定类型的图片
 * @param type 图片类型
 * @returns 是否已有该类型图片
 */
const hasImageOfType = (type: number): boolean => {
  return imagesDialog.images.some(image => image.image_type === type)
}

/**
 * 处理图片类型变化
 */
const handleImageTypeChange = () => {
  // 清空已选择的文件
  uploadDialog.imageUrl = ''
}

/**
 * 删除图片
 * @param id 图片ID
 */
const deleteImage = (id: number) => {
  ElMessageBox.confirm('确定要删除这张图片吗？', '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    // 调用删除图片API
    deleteWjBooksRejectedImage(id).then(() => {
      ElMessage.success('删除成功')
      // 重新加载图片列表
      loadRejectedImages(uploadDialog.book)
    }).catch(error => {
      console.error('删除图片失败', error)
      ElMessage.error('删除图片失败，请重试')
    })
  }).catch(() => {})
}

/**
 * 确认上传图片
 */
const confirmUpload = async () => {
  if (!uploadDialog.book) {
    ElMessage.warning('请先选择拒收书籍')
    return
  }
  
  if (!uploadDialog.imageUrl) {
    ElMessage.warning(t('pleaseSelectImage'))
    return
  }
  
  // 检查是否已有该类型的图片
  if (hasImageOfType(uploadDialog.form.image_type)) {
    ElMessage.warning(`已存在${formatImageType(uploadDialog.form.image_type)}类型的图片，请先删除后再上传`)
    return
  }
  
  uploadDialog.loading = true
  
  try {
    // 上传图片
      const formData = new FormData()
      formData.append('rejected_book_id', uploadDialog.book.id.toString())
      formData.append('image_type', uploadDialog.form.image_type.toString())
    formData.append('image_url', uploadDialog.imageUrl) // 直接使用选择的图片URL
      
      await uploadWjBooksRejectedImage(formData)
    
    ElMessage.success(t('uploadSuccess'))
    
    // 清空已上传的图片
    uploadDialog.imageUrl = ''
    
    // 刷新图片列表
    loadRejectedImages(uploadDialog.book)
  } catch (error) {
    console.error('上传失败', error)
    ElMessage.error('上传失败，请重试')
  } finally {
    uploadDialog.loading = false
  }
}

/**
 * 获取拒收原因标签类型
 */
const getRejectReasonTagType = (reason: string) => {
  const typeMap: Record<string, string> = {
    'damaged': 'danger',
    'not_match': 'warning',
    'too_old': 'info',
    'other': 'info'
  }
  return typeMap[reason] || 'info'
}

/**
 * 获取图片类型标签类型
 */
const getImageTypeTagType = (type: number) => {
  const typeMap: Record<number, string> = {
    1: 'primary',
    2: 'danger',
    3: 'warning',
    4: 'info'
  }
  return typeMap[type] || 'info'
}

/**
 * 更新拒收原因
 */
const updateRejectReason = (row: any) => {
  if (!row || !row.id) return;
  
  // 调用API更新拒收原因
  const updateData = {
    id: row.id,
    reject_reason: row.reject_reason
  };
  
  // 这里假设有一个更新拒收书籍的API
  updateWjBooksRejectedBook(updateData).then(() => {
    ElMessage.success(t('updateRejectReasonSuccess'));
    
    // 同步通知订单书籍组件，虽然只是更新了拒收原因，但保持同步
    updateOrderBookAcceptedQuantity(row.order_book_id, row.rejected_quantity);
  }).catch((error) => {
    console.error('更新拒收原因失败', error);
    ElMessage.error(t('updateRejectReasonFailed'));
  });
}

/**
 * 更新订单书籍接收数量
 * @param orderBookId 订单书籍ID
 * @param rejectedQuantity 拒收数量
 */
const updateOrderBookAcceptedQuantity = (orderBookId: number, rejectedQuantity: number) => {
  emit('updateOrderBookQuantity', {
    orderBookId,
    rejectedQuantity
  })
}

/**
 * 加载拒收图片
 */
const loadRejectedImages = (book: any) => {
  if (!book || !book.id) return
  
  imagesDialog.loading = true
  getWjBooksRejectedImageList(book.id).then(res => {
    imagesDialog.images = res.data || []
    imagesDialog.loading = false
  }).catch(() => {
    imagesDialog.loading = false
  })
}

/**
 * 监听订单ID变化，加载拒收书籍列表
 */
watch(() => props.orderId, (newVal) => {
  if (newVal) {
    loadRejectedList()
    loadOrderBookList()
  } else {
    rejectedList.value = []
    orderBookList.value = []
  }
}, { immediate: true })

/**
 * 监听传入的rejectedBooks数组变化
 */
watch(() => props.rejectedBooks, (newVal) => {
  if (newVal && newVal.length > 0) {
    rejectedList.value = newVal
  }
}, { immediate: true })

// 向父组件暴露方法
defineExpose({
  loadRejectedList,
  
  /**
   * 强制刷新拒收书籍列表
   * 当订单书籍组件发生审核操作时，通过此方法刷新拒收书籍列表
   */
  refreshRejectedBooks() {
    loadRejectedList()
  },
  
  /**
   * 更新订单书籍接收数量
   * 当拒收书籍被添加或修改时，更新对应的订单书籍的接收数量
   * @param orderBookId 订单书籍ID
   * @param rejectedQuantity 拒收数量
   */
  updateOrderBookAcceptedQuantity(orderBookId: number, rejectedQuantity: number) {
    emit('updateOrderBookQuantity', {
      orderBookId,
      rejectedQuantity
    })
  }
})
</script>

<style lang="scss" scoped>
.mb-4 {
  margin-bottom: 16px;
}

.image-card {
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 12px 0 rgba(0, 0, 0, 0.1);
  transition: all 0.3s;
  
  &:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px 0 rgba(0, 0, 0, 0.15);
  }
}

:deep(.el-dialog__body) {
  padding-top: 10px;
}

:deep(.el-form-item__content) {
  display: block !important;
}
</style>
