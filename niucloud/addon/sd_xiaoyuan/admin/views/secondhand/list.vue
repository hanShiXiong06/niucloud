<template>
    <div class="secondhand-list-container">
        <!-- 搜索区域 -->
        <el-card class="search-card">
            <el-form :model="searchForm" inline>
                <el-form-item label="学校">
                    <el-select v-model="searchForm.school_id" placeholder="全部学校" clearable filterable>
                        <el-option v-for="s in schoolList" :key="s.id" :label="s.name" :value="s.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="分类">
                    <el-select v-model="searchForm.category_id" placeholder="全部分类" clearable>
                        <el-option v-for="cat in categoryList" :key="cat.id" :label="cat.name" :value="cat.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="searchForm.status" placeholder="全部状态" clearable>
                        <el-option label="待审核" :value="0" />
                        <el-option label="在售" :value="1" />
                        <el-option label="已下架" :value="2" />
                        <el-option label="已售出" :value="3" />
                    </el-select>
                </el-form-item>
                <el-form-item label="发布类型">
                    <el-select v-model="searchForm.goods_type" placeholder="全部" clearable style="width: 140px">
                        <el-option label="正常出售" value="normal" />
                        <el-option label="急售捡漏" value="urgent" />
                        <el-option label="免费送" value="free" />
                    </el-select>
                </el-form-item>
                <el-form-item label="关键词">
                    <el-input v-model="searchForm.keyword" placeholder="请输入关键词" clearable />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadList">搜索</el-button>
                    <el-button @click="handleReset">重置</el-button>
                    <el-button type="success" @click="showCategoryManage">分类管理</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 列表 -->
        <el-card>
            <el-table :data="list" v-loading="loading" stripe>
                <el-table-column prop="id" label="ID" width="80" />
                <el-table-column label="图片" width="80">
                    <template #default="{ row }">
                        <el-image 
                            v-if="getFirstImage(row.images)"
                            :src="getImageUrl(getFirstImage(row.images))"
                            :preview-src-list="getImageUrls(getImageArray(row.images))"
                            fit="cover"
                            style="width: 50px; height: 50px; border-radius: 4px;"
                        />
                        <div v-else style="width: 50px; height: 50px; background: #f5f5f5; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #999; font-size: 12px;">
                            无图
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="school_name" label="学校" width="120" />
                <el-table-column prop="title" label="商品标题" min-width="200" show-overflow-tooltip />
                <el-table-column prop="category_id" label="分类" width="100">
                    <template #default="{ row }">
                        {{ getCategoryName(row.category_id) }}
                    </template>
                </el-table-column>
                <el-table-column label="发布类型" width="110">
                    <template #default="{ row }">
                        <el-tag v-for="(t, i) in getGoodsTypeTags(row)" :key="i" :type="t.type" size="small" class="mr-1">{{ t.text }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="original_price" label="原价" width="100">
                    <template #default="{ row }">
                        <span class="text-muted">¥{{ row.original_price }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="price" label="售价" width="100">
                    <template #default="{ row }">
                        <span class="text-price">¥{{ row.price }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="condition_level" label="成色" width="80">
                    <template #default="{ row }">
                        {{ row.condition_level }}成新
                    </template>
                </el-table-column>
                <el-table-column prop="view_count" label="浏览" width="80" />
                <el-table-column prop="want_count" label="想要" width="80" />
                <el-table-column prop="status" label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="getStatusType(row.status)" size="small">
                            {{ statusMap[row.status] || row.status }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="create_time" label="发布时间" width="180">
                    <template #default="{ row }">
                        {{ (row.create_time) }}
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="240" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link size="small" @click="handleEdit(row)">编辑</el-button>
                        <el-button link size="small" @click="handleAudit(row, 1)" v-if="row.status === 0">通过</el-button>
                        <el-button type="danger" link size="small" @click="handleAudit(row, 2)" v-if="row.status === 0">拒绝</el-button>
                        <el-button type="danger" link size="small" @click="handleDelete(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <el-pagination
                v-model:current-page="pagination.page"
                v-model:page-size="pagination.limit"
                :total="pagination.total"
                :page-sizes="[10, 20, 50, 100]"
                layout="total, sizes, prev, pager, next, jumper"
                @size-change="loadList"
                @current-change="loadList"
            />
        </el-card>

        <!-- 分类管理弹窗 -->
        <el-dialog v-model="categoryVisible" title="分类管理" width="600px">
            <el-button type="primary" size="small" @click="showAddCategory" class="mb-3">添加分类</el-button>
            <el-table :data="categoryList" stripe size="small">
                <el-table-column prop="id" label="ID" width="80" />
                <el-table-column prop="name" label="分类名称" />
                <el-table-column prop="sort" label="排序" width="80" />
                <el-table-column prop="status" label="状态" width="80">
                    <template #default="{ row }">
                        <el-tag :type="row.status ? 'success' : 'info'" size="small">
                            {{ row.status ? '启用' : '禁用' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="150">
                    <template #default="{ row }">
                        <el-button type="primary" link size="small" @click="showEditCategory(row)">编辑</el-button>
                        <el-button type="danger" link size="small" @click="handleDelCategory(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-dialog>

        <!-- 添加/编辑分类弹窗 -->
        <el-dialog v-model="categoryFormVisible" :title="categoryForm.id ? '编辑分类' : '添加分类'" width="400px">
            <el-form :model="categoryForm" label-width="80px">
                <el-form-item label="名称" required>
                    <el-input v-model="categoryForm.name" placeholder="请输入分类名称" />
                </el-form-item>
                <el-form-item label="排序">
                    <el-input-number v-model="categoryForm.sort" :min="0" />
                </el-form-item>
                <el-form-item label="状态">
                    <el-switch v-model="categoryForm.status" :active-value="1" :inactive-value="0" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="categoryFormVisible = false">取消</el-button>
                <el-button type="primary" @click="handleCategorySubmit" :loading="categoryLoading">确定</el-button>
            </template>
        </el-dialog>

        <!-- 详情弹窗 -->
        <el-dialog v-model="detailVisible" title="商品详情" width="700px">
            <el-descriptions :column="2" border v-if="currentGoods">
                <el-descriptions-item label="商品标题" :span="2">{{ currentGoods.title }}</el-descriptions-item>
                <el-descriptions-item label="分类">{{ getCategoryName(currentGoods.category_id) }}</el-descriptions-item>
                <el-descriptions-item label="发布类型">
                    <el-tag v-for="(t, i) in getGoodsTypeTags(currentGoods)" :key="i" :type="t.type" size="small" class="mr-1">{{ t.text }}</el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="成色">{{ currentGoods.condition_level }}成新</el-descriptions-item>
                <el-descriptions-item label="原价">¥{{ currentGoods.original_price }}</el-descriptions-item>
                <el-descriptions-item label="售价">¥{{ currentGoods.price }}</el-descriptions-item>
                <el-descriptions-item label="交易方式">{{ tradeMethodMap[currentGoods.trade_method] }}</el-descriptions-item>
                <el-descriptions-item label="交易地址">{{ currentGoods.trade_address || '-' }}</el-descriptions-item>
                <el-descriptions-item label="联系人">{{ currentGoods.contact_name }}</el-descriptions-item>
                <el-descriptions-item label="联系电话">{{ currentGoods.contact_mobile }}</el-descriptions-item>
                <el-descriptions-item label="浏览量">{{ currentGoods.view_count }}</el-descriptions-item>
                <el-descriptions-item label="想要数">{{ currentGoods.want_count }}</el-descriptions-item>
                <el-descriptions-item label="状态">
                    <el-tag :type="getStatusType(currentGoods.status)">{{ statusMap[currentGoods.status] }}</el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="发布时间">{{ currentGoods.create_time || '-' }}</el-descriptions-item>
                <el-descriptions-item label="商品描述" :span="2">{{ currentGoods.content || '-' }}</el-descriptions-item>
            </el-descriptions>
        </el-dialog>

        <!-- 编辑弹窗 -->
        <el-dialog v-model="editVisible" title="编辑商品" width="700px">
            <el-form :model="editForm" label-width="100px">
                <el-form-item label="商品标题" required>
                    <el-input v-model="editForm.title" placeholder="请输入商品标题" />
                </el-form-item>
                <el-form-item label="分类">
                    <el-select v-model="editForm.category_id" placeholder="请选择分类" style="width: 100%">
                        <el-option v-for="cat in categoryList" :key="cat.id" :label="cat.name" :value="cat.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="发布类型">
                    <el-radio-group v-model="editForm.publish_mode" @change="onPublishModeChange">
                        <el-radio label="normal">正常出售</el-radio>
                        <el-radio label="urgent">急售捡漏</el-radio>
                        <el-radio label="free">免费送</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item label="原价(元)">
                            <el-input-number v-model="editForm.original_price" :min="0" :precision="2" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="售价(元)" :required="editForm.publish_mode !== 'free'">
                            <el-input-number
                                v-model="editForm.price"
                                :min="0"
                                :precision="2"
                                style="width: 100%"
                                :disabled="editForm.publish_mode === 'free'"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="成色">
                    <el-slider v-model="editForm.condition_level" :min="1" :max="10" :marks="{ 1: '1成新', 5: '5成新', 10: '全新' }" />
                </el-form-item>
                <el-form-item label="交易方式">
                    <el-radio-group v-model="editForm.trade_method">
                        <el-radio label="FACE">面交</el-radio>
                        <el-radio label="EXPRESS">快递</el-radio>
                        <el-radio label="BOTH">都可以</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="交易地址">
                    <el-input v-model="editForm.trade_address" placeholder="请输入交易地址" />
                </el-form-item>
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item label="联系人">
                            <el-input v-model="editForm.contact_name" placeholder="联系人姓名" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="联系电话">
                            <el-input v-model="editForm.contact_mobile" placeholder="联系电话" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="商品图片">
                    <upload-image v-model="editForm.images" :limit="9" />
                    <div class="text-gray-400 text-xs mt-1">最多9张</div>
                </el-form-item>
                <el-form-item label="商品描述">
                    <el-input v-model="editForm.content" type="textarea" :rows="4" placeholder="请输入商品描述" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="editVisible = false">取消</el-button>
                <el-button type="primary" @click="handleEditSubmit" :loading="editLoading">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getSecondhandList, getSecondhandInfo, getSecondhandCategoryList, addSecondhandCategory, editSecondhandCategory, delSecondhandCategory, editSecondhand } from '@/addon/sd_xiaoyuan/api/secondhand'
import { getAllSchools, auditSecondhand, deleteSecondhand } from '@/addon/sd_xiaoyuan/api/admin'
import { img } from '@/utils/common'
import UploadImage from '@/components/upload-image/index.vue'

const loading = ref(false)
const list = ref<any[]>([])
const categoryList = ref<any[]>([])
const pagination = reactive({
    page: 1,
    limit: 10,
    total: 0
})

const searchForm = reactive({
    school_id: '',
    category_id: '',
    status: '',
    goods_type: '',
    keyword: ''
})

const schoolList = ref<any[]>([])

const loadSchools = async () => {
    const res: any = await getAllSchools()
    if (res.code === 1) {
        schoolList.value = res.data || []
    }
}

const statusMap: Record<number, string> = {
    0: '待审核',
    1: '在售',
    2: '已下架',
    3: '已售出'
}

const tradeMethodMap: Record<string, string> = {
    'FACE': '面交',
    'EXPRESS': '快递',
    'BOTH': '都可以'
}

const categoryVisible = ref(false)
const categoryFormVisible = ref(false)
const categoryLoading = ref(false)
const categoryForm = reactive({
    id: 0,
    name: '',
    sort: 0,
    status: 1
})

const detailVisible = ref(false)
const currentGoods = ref<any>(null)

const editVisible = ref(false)
const editLoading = ref(false)
const editForm = ref({
    id: 0,
    title: '',
    category_id: '',
    original_price: 0,
    price: 0,
    condition_level: 9,
    trade_method: 'FACE',
    trade_address: '',
    contact_name: '',
    contact_mobile: '',
    content: '',
    images: '',
    cover_image: '',
    publish_mode: 'normal' as 'normal' | 'urgent' | 'free'
})

onMounted(() => {
    loadSchools()
    loadCategoryList()
    loadList()
})

const loadCategoryList = async () => {
    try {
        const res: any = await getSecondhandCategoryList()
        categoryList.value = res.data || []
    } catch (e) {
        console.error(e)
    }
}

const loadList = async () => {
    loading.value = true
    try {
        const res: any = await getSecondhandList({
            ...searchForm,
            page: pagination.page,
            limit: pagination.limit
        })
        console.log('二手商品列表响应:', res)
        // 兼容不同的响应格式
        list.value = res.data?.list || res.data?.data || []
        pagination.total = res.data?.count || res.data?.total || 0
    } catch (e) {
        console.error('加载列表失败:', e)
        ElMessage.error('加载列表失败')
    } finally {
        loading.value = false
    }
}

const handleReset = () => {
    searchForm.school_id = ''
    searchForm.category_id = ''
    searchForm.status = ''
    searchForm.goods_type = ''
    searchForm.keyword = ''
    pagination.page = 1
    loadList()
}

const getGoodsTypeTags = (row: any) => {
    const p = Number(row.price)
    if (p === 0) {
        return [{ text: '免费送', type: 'success' as const }]
    }
    if (row.is_top == 1) {
        return [{ text: '急售捡漏', type: 'warning' as const }]
    }
    return [{ text: '正常出售', type: 'info' as const }]
}

const onPublishModeChange = (v: string | number | boolean | undefined) => {
    if (v === 'free') {
        editForm.value.price = 0
    }
}

const getCategoryName = (id: number) => {
    const cat = categoryList.value.find(c => c.id === id)
    return cat?.name || '-'
}

const getStatusType = (status: number) => {
    if (status === 0) return 'warning'  // 待审核
    if (status === 1) return 'success'  // 在售
    if (status === 2) return 'info'     // 已下架
    if (status === 3) return 'danger'   // 已售出
    return 'info'
}


const handleDelete = (row: any) => {
    ElMessageBox.confirm('确定要删除该商品吗？', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
    }).then(async () => {
        try {
            const res: any = await deleteSecondhand({ id: row.id })
            if (res.code === 1) {
                ElMessage.success('删除成功')
                loadList()
            } else {
                ElMessage.error(res.msg || '删除失败')
            }
        } catch (e: any) {
            ElMessage.error(e.message || '删除失败')
        }
    }).catch(() => {})
}

const handleAudit = async (row: any, status: number) => {
    const action = status === 1 ? '通过' : '拒绝'
    let refuseReason = ''
    
    if (status === 2) {
        // 拒绝时需要输入原因
        const result = await ElMessageBox.prompt('请输入拒绝原因', '拒绝审核', {
            confirmButtonText: '确定',
            cancelButtonText: '取消',
            inputPattern: /.+/,
            inputErrorMessage: '请输入拒绝原因'
        }).catch(() => null)
        
        if (!result) return
        refuseReason = result.value
    } else {
        // 通过审核确认
        await ElMessageBox.confirm(`确定${action}该商品吗？`, '提示', {
            confirmButtonText: '确定',
            cancelButtonText: '取消',
            type: 'warning'
        }).catch(() => null)
    }
    
    try {
        const res: any = await auditSecondhand({ 
            id: row.id, 
            status,
            refuse_reason: refuseReason 
        })
        if (res.code === 1) {
            ElMessage.success(`${action}成功`)
            loadList()
        } else {
            ElMessage.error(res.msg || `${action}失败`)
        }
    } catch (e: any) {
        ElMessage.error(e.message || `${action}失败`)
    }
}

const handleEdit = (row: any) => {
    editVisible.value = true
    let publish_mode: 'normal' | 'urgent' | 'free' = 'normal'
    if (Number(row.price) === 0) {
        publish_mode = 'free'
    } else if (row.is_top == 1) {
        publish_mode = 'urgent'
    }
    editForm.value = {
        id: row.id,
        title: row.title || '',
        category_id: row.category_id || '',
        original_price: row.original_price || 0,
        price: row.price || 0,
        condition_level: row.condition_level || 9,
        trade_method: row.trade_method || 'FACE',
        trade_address: row.trade_address || '',
        contact_name: row.contact_name || '',
        contact_mobile: row.contact_mobile || '',
        content: row.content || '',
        images: row.images || '',
        cover_image: row.cover_image || '',
        publish_mode
    }
}

const showCategoryManage = () => {
    categoryVisible.value = true
}

const showAddCategory = () => {
    categoryForm.id = 0
    categoryForm.name = ''
    categoryForm.sort = 0
    categoryForm.status = 1
    categoryFormVisible.value = true
}

const showEditCategory = (row: any) => {
    categoryForm.id = row.id
    categoryForm.name = row.name
    categoryForm.sort = row.sort
    categoryForm.status = row.status
    categoryFormVisible.value = true
}

const handleCategorySubmit = async () => {
    if (!categoryForm.name) {
        ElMessage.warning('请输入分类名称')
        return
    }
    
    categoryLoading.value = true
    try {
        if (categoryForm.id) {
            await editSecondhandCategory(categoryForm)
        } else {
            await addSecondhandCategory(categoryForm)
        }
        ElMessage.success(categoryForm.id ? '编辑成功' : '添加成功')
        categoryFormVisible.value = false
        loadCategoryList()
    } catch (e: any) {
        ElMessage.error(e.message || '操作失败')
    } finally {
        categoryLoading.value = false
    }
}

// 图片处理方法
const getImageUrl = (image: string) => {
    return img(image)
}

const getImageUrls = (images: string[]) => {
    return images.map(img)
}

// 获取第一张图片
const getFirstImage = (images: any) => {
    if (!images) return null
    if (typeof images === 'string') {
        return images
    }
    if (Array.isArray(images) && images.length > 0) {
        return images[0]
    }
    return null
}

// 获取图片数组
const getImageArray = (images: any) => {
    if (!images) return []
    if (typeof images === 'string') {
        return [images]
    }
    if (Array.isArray(images)) {
        return images
    }
    return []
}

const handleDelCategory = (row: any) => {
    ElMessageBox.confirm('确定要删除该分类吗？', '提示', {
        type: 'warning'
    }).then(async () => {
        try {
            await delSecondhandCategory(row.id)
            ElMessage.success('删除成功')
            loadCategoryList()
        } catch (e: any) {
            ElMessage.error(e.message || '删除失败')
        }
    }).catch(() => {})
}

const handleEditSubmit = async () => {
    if (!editForm.value.title) {
        ElMessage.warning('请输入商品标题')
        return
    }
    const mode = editForm.value.publish_mode
    if (mode === 'urgent' && Number(editForm.value.price) <= 0) {
        ElMessage.warning('急售捡漏售价须大于0')
        return
    }
    if (mode === 'normal' && Number(editForm.value.price) <= 0) {
        ElMessage.warning('正常出售售价须大于0')
        return
    }
    
    editLoading.value = true
    try {
        const mode = editForm.value.publish_mode
        const payload: any = { ...editForm.value }
        delete payload.publish_mode
        if (mode === 'free') {
            payload.price = 0
            payload.is_urgent = 0
        } else if (mode === 'urgent') {
            payload.is_urgent = 1
        } else {
            payload.is_urgent = 0
        }
        const res: any = await editSecondhand(payload)
        if (res.code === 1) {
            ElMessage.success('编辑成功')
            editVisible.value = false
            loadList()
        } else {
            ElMessage.error(res.msg || '编辑失败')
        }
    } catch (e: any) {
        ElMessage.error(e.message || '编辑失败')
    } finally {
        editLoading.value = false
    }
}
</script>

<style lang="scss" scoped>
.secondhand-list-container {
    padding: 20px;
}

.search-card {
    margin-bottom: 20px;
}

.text-price {
    color: #f56c6c;
    font-weight: bold;
}

.text-muted {
    color: #909399;
    text-decoration: line-through;
}

.mb-3 {
    margin-bottom: 15px;
}
</style>
