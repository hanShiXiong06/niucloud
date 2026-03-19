<template>
    <div class="points-goods-container">
        <el-card class="search-card">
            <el-form :model="searchForm" inline>
                <el-form-item label="商品名称">
                    <el-input v-model="searchForm.name" placeholder="请输入商品名称" clearable />
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="searchForm.status" placeholder="全部" clearable>
                        <el-option label="上架" :value="1" />
                        <el-option label="下架" :value="0" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadList">搜索</el-button>
                    <el-button @click="handleReset">重置</el-button>
                    <el-button type="success" @click="showAdd">添加商品</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <el-card>
            <el-table :data="list" v-loading="loading" stripe>
                <el-table-column prop="id" label="ID" width="80" />
                <el-table-column prop="image" label="图片" width="80">
                    <template #default="{ row }">
                        <el-image v-if="row.image" :src="img(row.image)" style="width:50px;height:50px" fit="cover" />
                        <span v-else>-</span>
                    </template>
                </el-table-column>
                <el-table-column prop="name" label="商品名称" min-width="200" />
                <el-table-column prop="points_price" label="积分价格" width="120" />
                <el-table-column prop="stock" label="库存" width="100" />
                <el-table-column prop="exchange_count" label="已兑换" width="100" />
                <el-table-column prop="sort" label="排序" width="80" />
                <el-table-column prop="status" label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 1 ? 'success' : 'info'">{{ row.status === 1 ? '上架' : '下架' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="150" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link size="small" @click="showEdit(row)">编辑</el-button>
                        <el-button type="danger" link size="small" @click="handleDel(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <el-pagination
                v-model:current-page="pagination.page"
                v-model:page-size="pagination.limit"
                :total="pagination.total"
                :page-sizes="[10, 20, 50]"
                layout="total, sizes, prev, pager, next"
                @size-change="loadList"
                @current-change="loadList"
            />
        </el-card>

        <el-dialog v-model="formVisible" :title="formData.id ? '编辑商品' : '添加商品'" width="600px">
            <el-form :model="formData" label-width="100px">
                <el-form-item label="商品名称" required>
                    <el-input v-model="formData.name" placeholder="请输入商品名称" />
                </el-form-item>
                <el-form-item label="商品图片">
                    <upload-image v-model="formData.image" />
                    <div class="text-gray-400 text-xs mt-1">建议尺寸: 200x200px</div>
                </el-form-item>
                <el-form-item label="商品描述">
                    <el-input v-model="formData.description" type="textarea" :rows="3" placeholder="请输入商品描述" />
                </el-form-item>
                <el-form-item label="积分价格" required>
                    <el-input-number v-model="formData.points_price" :min="1" :max="999999" />
                </el-form-item>
                <el-form-item label="库存">
                    <el-input-number v-model="formData.stock" :min="0" :max="999999" />
                </el-form-item>
                <el-form-item label="排序">
                    <el-input-number v-model="formData.sort" :min="0" :max="9999" />
                </el-form-item>
                <el-form-item label="状态">
                    <el-switch v-model="formData.status" :active-value="1" :inactive-value="0" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="formVisible = false">取消</el-button>
                <el-button type="primary" @click="handleSubmit" :loading="submitLoading">确定</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getPointsGoodsList, addPointsGoods, editPointsGoods, delPointsGoods } from '@/addon/sd_xiaoyuan/api/pointsMall'
import UploadImage from '@/components/upload-image/index.vue'
import { img } from '@/utils/common'

const loading = ref(false)
const list = ref<any[]>([])
const pagination = reactive({ page: 1, limit: 10, total: 0 })
const searchForm = reactive({ name: '', status: '' })
const formVisible = ref(false)
const submitLoading = ref(false)

const formData = reactive({
    id: 0,
    name: '',
    image: '',
    description: '',
    points_price: 100,
    stock: 0,
    sort: 0,
    status: 1
})

onMounted(() => { loadList() })

const loadList = async () => {
    loading.value = true
    try {
        const res: any = await getPointsGoodsList({ ...searchForm, page: pagination.page, limit: pagination.limit })
        list.value = res.data?.data || []
        pagination.total = res.data?.total || 0
    } catch (e) { console.error(e) }
    finally { loading.value = false }
}

const handleReset = () => {
    searchForm.name = ''
    searchForm.status = ''
    pagination.page = 1
    loadList()
}

const showAdd = () => {
    Object.assign(formData, { id: 0, name: '', image: '', description: '', points_price: 100, stock: 0, sort: 0, status: 1 })
    formVisible.value = true
}

const showEdit = (row: any) => {
    Object.assign(formData, { ...row })
    formVisible.value = true
}

const handleSubmit = async () => {
    if (!formData.name) { ElMessage.warning('请输入商品名称'); return }
    if (!formData.points_price) { ElMessage.warning('请输入积分价格'); return }
    submitLoading.value = true
    try {
        if (formData.id) {
            await editPointsGoods(formData)
        } else {
            await addPointsGoods(formData)
        }
        ElMessage.success(formData.id ? '编辑成功' : '添加成功')
        formVisible.value = false
        loadList()
    } catch (e: any) { ElMessage.error(e.message || '操作失败') }
    finally { submitLoading.value = false }
}

const handleDel = (row: any) => {
    ElMessageBox.confirm('确定要删除该商品吗？', '提示', { type: 'warning' }).then(async () => {
        try {
            await delPointsGoods(row.id)
            ElMessage.success('删除成功')
            loadList()
        } catch (e: any) { ElMessage.error(e.message || '删除失败') }
    }).catch(() => {})
}
</script>

<style scoped>
.points-goods-container { padding: 20px; }
.search-card { margin-bottom: 20px; }
</style>
