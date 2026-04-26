<template>
  <el-card class="mb-4" shadow="never">
    <template #header>
      <div class="flex justify-between items-center">
        <span class="font-bold">{{ t('orderBooks') }}</span>
        <div>
          <el-button v-if="orderId && orderStatus === 3" type="primary" size="small" @click="showAuditAllDialog">
            {{ t('batchAudit') }}
          </el-button>
        </div>
      </div>
    </template>

    <div v-loading="loading">
      <el-table :data="bookList" border stripe>
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
        
        <el-table-column prop="quantity" :label="t('quantity')" width="100" align="center" />
        
        <el-table-column :label="t('estimatedPrice')" width="120" align="center">
          <template #default="{ row }">
            ¥{{ row.price }}
          </template>
        </el-table-column>
        
        <el-table-column :label="t('finalPrice')" width="180" align="center">
          <template #default="{ row }">
            <div v-if="orderStatus >= 3">
              <div v-if="row.final_price !== null">
                <div class="text-green-600 font-medium">
                ¥{{ row.final_price }} × {{ row.accepted_quantity || row.quantity }}
                = ¥{{ (row.final_price * (row.accepted_quantity || row.quantity)).toFixed(2) }}
                </div>
                <div v-if="row.accepted_quantity < row.quantity" class="text-red-500 text-xs mt-1">
                  (已拒收: {{ row.quantity - row.accepted_quantity }} 本)
                </div>
              </div>
              <div v-else class="text-gray-400">{{ t('notAudited') }}</div>
            </div>
            <div v-else class="text-gray-400">{{ t('waitingForAudit') }}</div>
          </template>
        </el-table-column>
        
        <el-table-column :label="t('operation')" width="240" align="center" v-if="orderStatus === 3">
          <template #default="{ row }">
            <div class="flex justify-center space-x-2">
              <el-button type="success" size="small" @click="quickAccept(row)" v-if="row.final_price === null">
                {{ t('accept') }}
              </el-button>
              <el-button type="danger" size="small" @click="quickReject(row)" v-if="row.final_price === null">
                {{ t('reject') }}
              </el-button>
              <el-button type="warning" size="small" @click="showPartialRejectDialog(row)" v-if="row.quantity > 1 && row.final_price === null">
                {{ t('partialReject') }}
              </el-button>
              <el-button type="primary" link @click="showAuditDialog(row)" v-if="row.final_price !== null">
                {{ t('editAudit') }}
            </el-button>
            </div>
          </template>
        </el-table-column>
      </el-table>
      
      <div v-if="bookList.length === 0" class="text-center py-8 text-gray-400">
        {{ t('noBooks') }}
      </div>
    </div>

    <!-- 审核对话框 -->
    <el-dialog v-model="auditDialog.visible" :title="t('auditBook')" width="650px">
      <div v-if="auditDialog.book" class="mb-4">
        <div class="flex items-center mb-4">
          <el-image 
            v-if="auditDialog.book.img" 
            :src="getThumbImage(auditDialog.book.img)" 
            fit="cover" 
            class="w-[80px] h-[100px] mr-4"
          />
          <div>
            <div class="font-bold text-lg">{{ auditDialog.book.title }}</div>
            <div class="text-gray-500">{{ auditDialog.book.author }}</div>
            <div class="text-gray-400 text-sm">ISBN: {{ auditDialog.book.isbn }}</div>
          </div>
        </div>
        
        <el-alert
          v-if="auditDialog.book.quantity > 1"
          type="info"
          :closable="false"
          class="mb-4"
        >
          该书籍有 {{ auditDialog.book.quantity }} 本，您可以选择全部通过、部分通过或全部拒收
        </el-alert>
        
        <div class="mb-4">
        <div class="flex justify-center mb-4">
          <el-radio-group v-model="auditDialog.auditResult" @change="handleAuditResultChange">
            <el-radio :label="'pass'">审核通过</el-radio>
            <el-radio :label="'reject'">审核不通过</el-radio>
              <el-radio :label="'partial'" v-if="auditDialog.book.quantity > 1">部分通过</el-radio>
          </el-radio-group>
        </div>
        
        <el-form :model="auditDialog.form" label-width="100px">
            <!-- 审核通过：显示数量和原始价格 -->
            <template v-if="auditDialog.auditResult === 'pass'">
              <el-form-item :label="t('quantity')">
                <div class="flex items-center">
                  <span class="font-medium">{{ auditDialog.book.quantity }} 本</span>
                </div>
              </el-form-item>
              
              <el-form-item :label="t('finalPrice')">
                <div class="flex items-center">
                  <span class="font-medium">¥{{ auditDialog.form.final_price }}</span>
                  <span class="text-gray-500 text-sm ml-2">
                    (总价: ¥{{ (auditDialog.form.final_price * auditDialog.book.quantity).toFixed(2) }})
                  </span>
                </div>
              </el-form-item>
            </template>
            
            <!-- 审核不通过：显示拒收原因 -->
            <template v-if="auditDialog.auditResult === 'reject'">
              <el-form-item :label="t('rejectReason')" required>
                <el-select v-model="auditDialog.form.reject_reason" class="w-full">
                  <el-option :label="t('rejectReasonDamaged')" value="damaged" />
                  <el-option :label="t('rejectReasonNotMatch')" value="not_match" />
                  <el-option :label="t('rejectReasonOld')" value="too_old" />
                  <el-option :label="t('rejectReasonOther')" value="other" />
                </el-select>
              </el-form-item>
            </template>
            
            <!-- 部分通过：显示接受数量、拒收原因和最终价格 -->
            <template v-if="auditDialog.auditResult === 'partial'">
              <el-form-item :label="t('quantity')" required>
            <div class="flex items-center">
              <span class="mr-2">{{ t('original') }}: {{ auditDialog.book.quantity }}</span>
              <el-input-number 
                v-model="auditDialog.form.accepted_quantity" 
                    :min="1" 
                    :max="auditDialog.book.quantity - 1"
                controls-position="right"
                class="w-[120px]"
                @change="updateFinalPrice"
              ></el-input-number>
            </div>
                <div class="text-orange-500 text-sm mt-1">
                  将拒收: {{ auditDialog.book.quantity - auditDialog.form.accepted_quantity }} 本
            </div>
          </el-form-item>
          
              <el-form-item :label="t('rejectReason')" required>
                <el-select v-model="auditDialog.form.reject_reason" class="w-full">
                  <el-option :label="t('rejectReasonDamaged')" value="damaged" />
                  <el-option :label="t('rejectReasonNotMatch')" value="not_match" />
                  <el-option :label="t('rejectReasonOld')" value="too_old" />
                  <el-option :label="t('rejectReasonOther')" value="other" />
                </el-select>
              </el-form-item>
              
              <el-form-item :label="t('finalPrice')">
                <div class="flex items-center">
                  <span class="font-medium">¥{{ auditDialog.form.final_price }}</span>
                  <span class="text-gray-500 text-sm ml-2">
                    (总价: ¥{{ (auditDialog.form.final_price * auditDialog.form.accepted_quantity).toFixed(2) }})
                  </span>
                </div>
              </el-form-item>
            </template>
          </el-form>
            </div>
      </div>
      
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="auditDialog.visible = false">{{ t('cancel') }}</el-button>
          <el-button type="primary" @click="confirmAudit" :loading="auditDialog.loading">
            {{ t('confirm') }}
          </el-button>
        </span>
      </template>
    </el-dialog>

    <!-- 批量审核对话框 -->
    <el-dialog v-model="batchAuditDialog.visible" :title="t('batchAudit')" width="500px">
      <el-form :model="batchAuditDialog.form" label-width="120px">
        <el-form-item label="审核结果">
          <el-radio-group v-model="batchAuditDialog.form.audit_result">
            <el-radio :label="'pass'">全部通过</el-radio>
            <el-radio :label="'reject'">全部不通过</el-radio>
          </el-radio-group>
        </el-form-item>
        
        <el-form-item v-if="batchAuditDialog.form.audit_result === 'reject'">
          <el-divider content-position="left">拒收书籍信息</el-divider>
          
          <el-form-item :label="t('rejectReason')" required>
            <el-select v-model="batchAuditDialog.form.reject_reason" class="w-full">
              <el-option :label="t('rejectReasonDamaged')" value="damaged" />
              <el-option :label="t('rejectReasonNotMatch')" value="not_match" />
              <el-option :label="t('rejectReasonOld')" value="too_old" />
              <el-option :label="t('rejectReasonOther')" value="other" />
            </el-select>
          </el-form-item>
        </el-form-item>
      </el-form>
      
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="batchAuditDialog.visible = false">{{ t('cancel') }}</el-button>
          <el-button type="primary" @click="confirmBatchAudit" :loading="batchAuditDialog.loading">
            {{ t('confirm') }}
          </el-button>
        </span>
      </template>
    </el-dialog>

    <!-- 部分拒收对话框 -->
    <el-dialog v-model="partialRejectDialog.visible" :title="t('partialReject')" width="500px">
      <div v-if="partialRejectDialog.book" class="mb-4">
        <div class="flex items-center mb-4">
          <el-image 
            v-if="partialRejectDialog.book.img" 
            :src="getThumbImage(partialRejectDialog.book.img)" 
            fit="cover" 
            class="w-[80px] h-[100px] mr-4"
          />
          <div>
            <div class="font-bold text-lg">{{ partialRejectDialog.book.title }}</div>
            <div class="text-gray-500">{{ partialRejectDialog.book.author }}</div>
            <div class="text-gray-400 text-sm">ISBN: {{ partialRejectDialog.book.isbn }}</div>
          </div>
        </div>
        
        <el-form :model="partialRejectDialog.form" label-width="100px">
          <el-form-item :label="t('rejectQuantity')" required>
            <el-input-number 
              v-model="partialRejectDialog.form.reject_quantity" 
              :min="1" 
              :max="partialRejectDialog.book.quantity - 1"
              controls-position="right"
              class="w-[120px]"
            ></el-input-number>
            <div class="text-gray-400 text-xs mt-1">
              {{ t('totalQuantity') }}: {{ partialRejectDialog.book.quantity }}
            </div>
          </el-form-item>
          
          <el-form-item :label="t('rejectReason')" required>
            <el-select v-model="partialRejectDialog.form.reject_reason" class="w-full">
              <el-option :label="t('rejectReasonDamaged')" value="damaged" />
              <el-option :label="t('rejectReasonNotMatch')" value="not_match" />
              <el-option :label="t('rejectReasonOld')" value="too_old" />
              <el-option :label="t('rejectReasonOther')" value="other" />
            </el-select>
          </el-form-item>
        </el-form>
      </div>
      
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="partialRejectDialog.visible = false">{{ t('cancel') }}</el-button>
          <el-button type="primary" @click="confirmPartialReject" :loading="partialRejectDialog.loading">
            {{ t('confirm') }}
          </el-button>
        </span>
      </template>
    </el-dialog>
  </el-card>
</template>

<script lang="ts" setup>
import { reactive, ref, defineProps, defineEmits, watch, computed } from 'vue'
import { t } from '@/lang'
import { ElMessageBox, ElMessage } from 'element-plus'
import { 
  getWjBooksOrderBookList, 
  updateWjBooksOrderBookPrice, 
  addWjBooksRejectedBook, 
  updateWjBooksOrderAuditProgress,
  getWjBooksRejectedBookList,
  updateWjBooksRejectedBookQuantity,
  deleteWjBooksRejectedBook
} from '@/addon/wj_books/api/wj_books_order'
import { Plus } from '@element-plus/icons-vue'
import { uploadWjBooksRejectedImage } from '@/addon/wj_books/api/wj_books_order'

const props = defineProps({
  orderId: {
    type: Number,
    default: 0
  },
  orderStatus: {
    type: Number,
    default: 0
  },
  books: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['update', 'viewRejectedBooks', 'syncRejectedBooks'])

// 书籍列表
const bookList = ref<any[]>([])
const loading = ref(false)

// 审核对话框
const auditDialog = reactive({
  visible: false,
  loading: false,
  book: null as any,
  auditResult: 'pass',
  form: {
    accepted_quantity: 0,
    final_price: 0,
    reject_reason: 'damaged'
  }
})

// 批量审核对话框
const batchAuditDialog = reactive({
  visible: false,
  loading: false,
  form: {
    audit_result: 'pass',
    reject_reason: 'damaged'
  }
})

// 部分拒收对话框
const partialRejectDialog = reactive({
  visible: false,
  loading: false,
  book: null as any,
  form: {
    reject_quantity: 0,
    reject_reason: 'damaged'
  }
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
 * 加载订单书籍列表
 */
const loadBookList = () => {
  if (!props.orderId) return
  
  // 如果已经有传入的books数据，则直接使用
  if (props.books && props.books.length > 0) {
    bookList.value = props.books
    return
  }
  
  loading.value = true
  getWjBooksOrderBookList(props.orderId).then(res => {
    bookList.value = res.data || []
    loading.value = false
  }).catch(() => {
    loading.value = false
  })
}

/**
 * 显示审核对话框
 */
const showAuditDialog = (book: any) => {
  auditDialog.book = book
    auditDialog.auditResult = 'pass'
  auditDialog.form.accepted_quantity = book.quantity
  auditDialog.form.final_price = book.final_price !== null ? book.final_price : book.price
  auditDialog.form.reject_reason = 'damaged'
  auditDialog.visible = true
}

/**
 * 同步更新拒收书籍组件
 * 在审核操作后调用，通知父组件更新拒收书籍列表
 */
const syncRejectedBooks = () => {
  emit('syncRejectedBooks')
}

/**
 * 确认审核
 */
const confirmAudit = async () => {
  if (!auditDialog.book) return
  
  if (auditDialog.form.final_price < 0) {
    ElMessage.warning(t('invalidAuditValues'))
    return
  }
  
  // 根据审核结果设置接受数量
  if (auditDialog.auditResult === 'pass') {
    auditDialog.form.accepted_quantity = auditDialog.book.quantity
  } else if (auditDialog.auditResult === 'reject') {
    auditDialog.form.accepted_quantity = 0
  }
  
  auditDialog.loading = true
  
  try {
    // 更新书籍价格和接受数量
    await updateWjBooksOrderBookPrice(
      auditDialog.book.id, 
      auditDialog.form.final_price,
      auditDialog.form.accepted_quantity
    )
    
    // 先检查是否已存在该书籍的拒收记录
    let existingRejectedBook = null
    try {
      const rejectedBooksRes = await getWjBooksRejectedBookList(props.orderId)
      existingRejectedBook = rejectedBooksRes.data?.find(item => 
        item.order_book_id === auditDialog.book.id && 
        item.book_id === auditDialog.book.book_id
      )
    } catch (error) {
      console.error('获取拒收书籍列表失败', error)
    }
    
    // 如果有拒收书籍，添加或更新拒收记录
    const rejectedQuantity = auditDialog.book.quantity - auditDialog.form.accepted_quantity
    
    if (rejectedQuantity > 0) {
      // 有拒收数量，添加或更新拒收记录
      if (existingRejectedBook) {
        // 如果已存在拒收记录，更新拒收数量
        const updateData = {
          id: existingRejectedBook.id,
          rejected_quantity: rejectedQuantity,
          reject_reason: auditDialog.form.reject_reason
        }
        
        try {
          await updateWjBooksRejectedBookQuantity(updateData)
        } catch (error) {
          console.error('更新拒收书籍数量失败', error)
          ElMessage.error('更新拒收书籍数量失败')
        }
      } else {
        // 如果不存在拒收记录，添加新的拒收记录
      const rejectedBookData = {
        order_id: props.orderId,
        order_book_id: auditDialog.book.id,
          book_id: auditDialog.book.book_id,
          title: auditDialog.book.title,
          author: auditDialog.book.author,
          img: auditDialog.book.img,
          isbn: auditDialog.book.isbn,
        rejected_quantity: rejectedQuantity,
          reject_reason: auditDialog.form.reject_reason
        }
        
        try {
          await addWjBooksRejectedBook(rejectedBookData)
        } catch (error) {
          console.error('添加拒收书籍失败', error)
          ElMessage.error('添加拒收书籍失败')
        }
      }
    } else if (existingRejectedBook) {
      // 没有拒收数量，但存在拒收记录，需要删除拒收记录
      try {
        // 调用删除拒收书籍API
        await deleteWjBooksRejectedBook(existingRejectedBook.id)
        console.log('删除拒收书籍成功', existingRejectedBook.id)
      } catch (error) {
        console.error('删除拒收书籍失败', error)
      }
    }
    
    // 更新本地数据
    const index = bookList.value.findIndex(item => item.id === auditDialog.book.id)
    if (index !== -1) {
      bookList.value[index].final_price = auditDialog.form.final_price
      bookList.value[index].accepted_quantity = auditDialog.form.accepted_quantity
    }
    
    // 计算并更新审核进度
    updateAuditProgress()
    
    ElMessage.success('审核完成')
    auditDialog.visible = false
    emit('update')
    syncRejectedBooks() // 同步更新拒收书籍
  } catch (error) {
    console.error('审核失败', error)
    ElMessage.error('审核失败，请重试')
  } finally {
    auditDialog.loading = false
  }
}

/**
 * 显示批量审核对话框
 */
const showAuditAllDialog = () => {
  batchAuditDialog.form.audit_result = 'pass'
  batchAuditDialog.visible = true
}

/**
 * 确认批量审核
 */
const confirmBatchAudit = async () => {
  if (bookList.value.length === 0) return
  
  batchAuditDialog.loading = true
  
  try {
    // 获取当前所有拒收书籍
    let existingRejectedBooks: any[] = []
    try {
      const rejectedBooksRes = await getWjBooksRejectedBookList(props.orderId)
      existingRejectedBooks = rejectedBooksRes.data || []
    } catch (error) {
      console.error('获取拒收书籍列表失败', error)
    }
    
    // 逐个更新书籍
    for (const book of bookList.value) {
      // 根据审核结果确定接受数量
      let acceptedQuantity = 0;
      
      if (batchAuditDialog.form.audit_result === 'pass') {
        // 全部通过
        acceptedQuantity = book.quantity
      } else if (batchAuditDialog.form.audit_result === 'reject') {
        // 全部不通过
        acceptedQuantity = 0
      }
      
      // 无论是否拒收，都使用预估价格
      const finalPrice = book.price
      
      await updateWjBooksOrderBookPrice(book.id, finalPrice, acceptedQuantity)
      
      // 更新本地数据
      book.final_price = finalPrice
      book.accepted_quantity = acceptedQuantity
      
      // 查找该书籍是否存在拒收记录
      const existingRejectedBook = existingRejectedBooks.find(item => 
        item.order_book_id === book.id && 
        item.book_id === book.book_id
      )
      
      // 如果有拒收书籍，添加或更新拒收记录
      const rejectedQuantity = book.quantity - acceptedQuantity
      if (rejectedQuantity > 0) {
        // 有拒收数量，添加或更新拒收记录
        if (existingRejectedBook) {
          // 如果已存在拒收记录，更新拒收数量
          const updateData = {
            id: existingRejectedBook.id,
            rejected_quantity: rejectedQuantity,
            reject_reason: batchAuditDialog.form.reject_reason
          }
          
          try {
            await updateWjBooksRejectedBookQuantity(updateData)
          } catch (error) {
            console.error('更新拒收书籍数量失败', error)
          }
        } else {
          // 如果不存在拒收记录，添加新的拒收记录
        const rejectedBookData = {
          order_id: props.orderId,
          order_book_id: book.id,
            book_id: book.book_id,
            title: book.title,
            author: book.author,
            img: book.img,
            isbn: book.isbn,
          rejected_quantity: rejectedQuantity,
            reject_reason: batchAuditDialog.form.reject_reason
        }
        
          try {
        await addWjBooksRejectedBook(rejectedBookData)
          } catch (error) {
            console.error('添加拒收书籍失败', error)
          }
        }
      } else if (existingRejectedBook) {
        // 没有拒收数量，但存在拒收记录，需要删除拒收记录
        try {
          // 调用删除拒收书籍API
          await deleteWjBooksRejectedBook(existingRejectedBook.id)
          console.log('删除拒收书籍成功', existingRejectedBook.id)
        } catch (error) {
          console.error('删除拒收书籍失败', error)
        }
      }
    }
    
    // 计算并更新审核进度
    updateAuditProgress()
    
    batchAuditDialog.loading = false
    batchAuditDialog.visible = false
    emit('update')
    syncRejectedBooks() // 同步更新拒收书籍
    
    ElMessage.success(t('batchAuditSuccess'))
  } catch (error) {
    batchAuditDialog.loading = false
    ElMessage.error(t('batchAuditFailed'))
  }
}

/**
 * 监听订单ID变化，加载书籍列表
 */
watch(() => props.orderId, (newVal) => {
  if (newVal) {
    loadBookList()
  } else {
    bookList.value = []
  }
}, { immediate: true })

/**
 * 监听传入的books数组变化
 */
watch(() => props.books, (newVal) => {
  if (newVal && newVal.length > 0) {
    bookList.value = newVal
  }
}, { immediate: true })

/**
 * 处理审核结果变化
 */
const handleAuditResultChange = (value: string) => {
  if (!auditDialog.book) return
  
  if (value === 'pass') {
    // 全部通过
    auditDialog.form.accepted_quantity = auditDialog.book.quantity
      auditDialog.form.final_price = auditDialog.book.price
  } else if (value === 'reject') {
    // 全部拒绝
    auditDialog.form.accepted_quantity = 0
    auditDialog.form.final_price = auditDialog.book.price
  } else {
    // 部分通过，设置为一半数量
      auditDialog.form.accepted_quantity = Math.ceil(auditDialog.book.quantity / 2)
    auditDialog.form.final_price = auditDialog.book.price
  }
}

/**
 * 更新最终价格（基于接受数量）
 */
const updateFinalPrice = () => {
  if (!auditDialog.book) return
  
  // 无论接受数量如何，最终价格都使用预估价
    auditDialog.form.final_price = parseFloat((auditDialog.book.price).toFixed(2))
  }

/**
 * 计算并更新审核进度
 */
const updateAuditProgress = () => {
  if (!props.orderId) return
  
  // 计算已审核的书籍数量
  const auditedBooks = bookList.value.filter(book => book.final_price !== null).length
  const totalBooks = bookList.value.length
  
  if (totalBooks === 0) return
  
  // 计算审核进度百分比
  const progress = Math.round((auditedBooks / totalBooks) * 100)
  
  // 更新到服务器
  updateWjBooksOrderAuditProgress(props.orderId, progress)
}

/**
 * 显示部分拒收对话框
 */
const showPartialRejectDialog = (book: any) => {
  partialRejectDialog.book = book
  partialRejectDialog.form.reject_quantity = 1
  partialRejectDialog.form.reject_reason = 'damaged'
  partialRejectDialog.visible = true
}

/**
 * 确认部分拒收
 */
const confirmPartialReject = async () => {
  if (!partialRejectDialog.book) return
  
  if (partialRejectDialog.form.reject_quantity < 1 || partialRejectDialog.form.reject_quantity >= partialRejectDialog.book.quantity) {
    ElMessage.warning(t('invalidRejectQuantity'))
    return
  }
  
  partialRejectDialog.loading = true
  
  try {
    const acceptedQuantity = partialRejectDialog.book.quantity - partialRejectDialog.form.reject_quantity
    // 更新书籍价格和接受数量，使用预估价格
    await updateWjBooksOrderBookPrice(
      partialRejectDialog.book.id, 
      partialRejectDialog.book.price,
      acceptedQuantity
    )
    
    // 添加拒收记录
    const rejectedBookData = {
      order_id: props.orderId,
      order_book_id: partialRejectDialog.book.id,
      book_id: partialRejectDialog.book.book_id,
      title: partialRejectDialog.book.title,
      author: partialRejectDialog.book.author,
      img: partialRejectDialog.book.img,
      isbn: partialRejectDialog.book.isbn,
      rejected_quantity: partialRejectDialog.form.reject_quantity,
      reject_reason: partialRejectDialog.form.reject_reason
    }
    
    try {
      await addWjBooksRejectedBook(rejectedBookData)
    } catch (error) {
      console.error('添加拒收书籍失败', error)
      ElMessage.error('添加拒收书籍失败')
    }
    
    // 更新本地数据
    const index = bookList.value.findIndex(item => item.id === partialRejectDialog.book.id)
    if (index !== -1) {
      bookList.value[index].final_price = partialRejectDialog.book.price
      bookList.value[index].accepted_quantity = acceptedQuantity
    }
    
    // 计算并更新审核进度
    updateAuditProgress()
    
    ElMessage.success(t('partialRejectSuccess'))
    partialRejectDialog.visible = false
    emit('update')
    syncRejectedBooks() // 同步更新拒收书籍
  } catch (error) {
    console.error('部分拒收失败', error)
    ElMessage.error(t('partialRejectFailed'))
  } finally {
    partialRejectDialog.loading = false
  }
}

/**
 * 快速同意（审核通过）
 */
const quickAccept = async (book: any) => {
  if (!book) return
  
  try {
    // 更新书籍价格和接受数量，使用预估价格
    await updateWjBooksOrderBookPrice(
      book.id, 
      book.price,
      book.quantity
    )
    
    // 检查是否存在拒收记录，如果存在则删除
    try {
      const rejectedBooksRes = await getWjBooksRejectedBookList(props.orderId)
      const existingRejectedBook = rejectedBooksRes.data?.find(item => 
        item.order_book_id === book.id && 
        item.book_id === book.book_id
      )
      
      if (existingRejectedBook) {
        // 删除拒收记录
        await deleteWjBooksRejectedBook(existingRejectedBook.id)
        console.log('删除拒收书籍成功', existingRejectedBook.id)
      }
    } catch (error) {
      console.error('处理拒收书籍失败', error)
    }
    
    // 更新本地数据
    const index = bookList.value.findIndex(item => item.id === book.id)
    if (index !== -1) {
      bookList.value[index].final_price = book.price
      bookList.value[index].accepted_quantity = book.quantity
    }
    
    // 计算并更新审核进度
    updateAuditProgress()
    
    ElMessage.success(t('acceptSuccess'))
    emit('update')
    syncRejectedBooks() // 同步更新拒收书籍
  } catch (error) {
    console.error('审核通过失败', error)
    ElMessage.error(t('acceptFailed'))
  }
}

/**
 * 快速拒收（审核不通过）
 */
const quickReject = async (book: any) => {
  if (!book) return
  
  ElMessageBox.confirm(t('confirmReject'), t('warning'), {
    confirmButtonText: t('confirm'),
    cancelButtonText: t('cancel'),
    type: 'warning',
  }).then(async () => {
    try {
      // 更新书籍价格和接受数量，价格为预估价，接受数量为0
      await updateWjBooksOrderBookPrice(
        book.id, 
        book.price,
        0
      )
      
      // 添加拒收记录
      const rejectedBookData = {
        order_id: props.orderId,
        order_book_id: book.id,
        book_id: book.book_id,
        title: book.title,
        author: book.author,
        img: book.img,
        isbn: book.isbn,
        rejected_quantity: book.quantity,
        reject_reason: 'damaged', // 默认拒收原因
        can_retrieve: 1 // 默认可取回
      }
      
      await addWjBooksRejectedBook(rejectedBookData)
      
      // 更新本地数据
      const index = bookList.value.findIndex(item => item.id === book.id)
      if (index !== -1) {
        bookList.value[index].final_price = book.price
        bookList.value[index].accepted_quantity = 0
      }
      
      // 计算并更新审核进度
      updateAuditProgress()
      
      ElMessage.success(t('rejectSuccess'))
      emit('update')
      syncRejectedBooks() // 同步更新拒收书籍
    } catch (error) {
      console.error('拒收失败', error)
      ElMessage.error(t('rejectFailed'))
    }
  }).catch(() => {})
}

/**
 * 接收拒收书籍组件发送的更新请求
 * 更新订单书籍的接收数量
 * @param data 包含orderBookId和rejectedQuantity的对象
 */
const updateFromRejectedBooks = (data: { orderBookId: number, rejectedQuantity: number }) => {
  const { orderBookId, rejectedQuantity } = data
  
  // 查找对应的订单书籍
  const bookIndex = bookList.value.findIndex(item => item.id === orderBookId)
  if (bookIndex === -1) return
  
  const book = bookList.value[bookIndex]
  
  // 计算新的接收数量
  const newAcceptedQuantity = book.quantity - rejectedQuantity
  
  // 如果接收数量没有变化，则不需要更新
  if (book.accepted_quantity === newAcceptedQuantity) return
  
  // 更新书籍价格和接受数量
  updateWjBooksOrderBookPrice(
    orderBookId,
    book.final_price || book.price,
    newAcceptedQuantity
  ).then(() => {
    // 更新本地数据
    book.accepted_quantity = newAcceptedQuantity
    
    // 计算并更新审核进度
    updateAuditProgress()
    
    ElMessage.success('订单书籍数量已同步更新')
  }).catch(error => {
    console.error('更新订单书籍数量失败', error)
    ElMessage.error('更新订单书籍数量失败')
  })
}

// 向父组件暴露方法
defineExpose({
  loadBookList,
  updateFromRejectedBooks
})
</script>

<style lang="scss" scoped>
.mb-4 {
  margin-bottom: 16px;
}

.audit-tabs {
  :deep(.el-tabs__nav) {
    display: flex;
    width: 100%;
  }
  
  :deep(.el-tabs__item) {
    flex: 1;
    text-align: center;
  }
}
</style>
