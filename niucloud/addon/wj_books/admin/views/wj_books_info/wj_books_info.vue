<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center">
                <span class="text-lg">{{pageName}}</span>
                <el-button type="primary" @click="addEvent">
                    添加图书信息
                </el-button>
            </div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="wjBooksInfoTable.searchParam" ref="searchFormRef">
                    <el-form-item :label="t('isbn')" prop="isbn">
    <el-input v-model="wjBooksInfoTable.searchParam.isbn" :placeholder="t('isbnPlaceholder')" />
</el-form-item>
                    <el-form-item :label="'ISBN10'" prop="isbn10">
    <el-input v-model="wjBooksInfoTable.searchParam.isbn10" :placeholder="'请输入ISBN10'" />
</el-form-item>
                    <el-form-item :label="t('title')" prop="title">
    <el-input v-model="wjBooksInfoTable.searchParam.title" :placeholder="t('titlePlaceholder')" />
</el-form-item>
                    <el-form-item :label="t('canRecycle')" prop="can_recycle">
    <el-select v-model="wjBooksInfoTable.searchParam.can_recycle" :placeholder="t('canRecyclePlaceholder')" clearable>
        <el-option label="可回收" value="1" />
        <el-option label="不可回收" value="0" />
    </el-select>
</el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="loadWjBooksInfoList()">查询</el-button>
                        <el-button @click="resetForm(searchFormRef)">重置</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <div class="mt-[10px]">
                <el-table :data="wjBooksInfoTable.data" size="large" v-loading="wjBooksInfoTable.loading">
                    <template #empty>
                        <span>{{ !wjBooksInfoTable.loading ? '暂无数据' : '' }}</span>
                    </template>
                    <el-table-column prop="isbn" :label="t('isbn')" min-width="120" :show-overflow-tooltip="true"/>

                    <el-table-column prop="isbn10" :label="'ISBN10'" min-width="100" :show-overflow-tooltip="true"/>

                    <el-table-column prop="title" :label="t('title')" min-width="150" :show-overflow-tooltip="true"/>

                    <el-table-column prop="author" :label="t('author')" min-width="120" :show-overflow-tooltip="true"/>

                    <el-table-column prop="publisher" :label="t('publisher')" min-width="120" :show-overflow-tooltip="true"/>

                    <el-table-column prop="price" :label="t('price')" min-width="80" :show-overflow-tooltip="true"/>

                     <el-table-column :label="'封面'" width="80" align="center">
    <template #default="{ row }">
        <el-avatar v-if="row.img" :src="img(row.img)" />
        <el-avatar v-else icon="Picture" />
    </template>
</el-table-column>

                    <el-table-column prop="recycle_price" :label="t('recyclePrice')" min-width="80" :show-overflow-tooltip="true"/>

                    <el-table-column :label="t('canRecycle')" min-width="100" align="center">
    <template #default="{ row }">
        <el-tag :type="row.can_recycle === 1 ? 'success' : 'danger'">
            {{ row.can_recycle === 1 ? '可回收' : '不可回收' }}
        </el-tag>
    </template>
</el-table-column>

                    <el-table-column prop="recycle_count" :label="t('recycleCount')" min-width="80" :show-overflow-tooltip="true"/>

                    <el-table-column prop="create_time" :label="t('createTime')" min-width="150" :show-overflow-tooltip="true"/>

                    <el-table-column :label="t('operation')" fixed="right" min-width="180">
                       <template #default="{ row }">
                           <el-button type="primary" link @click="viewDetails(row)">查看详情</el-button>
                           <el-button type="primary" link @click="editEvent(row)">编辑</el-button>
                           <el-button type="primary" link @click="deleteEvent(row.id)">删除</el-button>
                       </template>
                    </el-table-column>

                </el-table>
                <div class="mt-[16px] flex justify-end">
                    <el-pagination v-model:current-page="wjBooksInfoTable.page" v-model:page-size="wjBooksInfoTable.limit"
                        layout="total, sizes, prev, pager, next, jumper" :total="wjBooksInfoTable.total"
                        @size-change="loadWjBooksInfoList()" @current-change="loadWjBooksInfoList" />
                </div>
            </div>

            <edit ref="editWjBooksInfoDialog" @complete="loadWjBooksInfoList" />
            <detail ref="detailWjBooksInfoDialog" />
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, watch } from 'vue'
import { t } from '@/lang'
import { useDictionary } from '@/app/api/dict'
import { getWjBooksInfoList, deleteWjBooksInfo } from '@/addon/wj_books/api/wj_books_info'
import { img } from '@/utils/common'
import { ElMessageBox,FormInstance } from 'element-plus'
import Edit from '@/addon/wj_books/views/wj_books_info/components/wj-books-info-edit.vue'
import Detail from '@/addon/wj_books/views/wj_books_info/components/wj-books-info-detail.vue'
import { useRoute } from 'vue-router'
const route = useRoute()
const pageName = route.meta.title;

let wjBooksInfoTable = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam:{
      "isbn":"",
      "isbn10":"",
      "title":"",
      "can_recycle":""
    }
})

const searchFormRef = ref<FormInstance>()

// 选中数据
const selectData = ref<any[]>([])

// 字典数据
    

/**
 * 获取图书信息列表
 */
const loadWjBooksInfoList = (page: number = 1) => {
    wjBooksInfoTable.loading = true
    wjBooksInfoTable.page = page

    getWjBooksInfoList({
        page: wjBooksInfoTable.page,
        limit: wjBooksInfoTable.limit,
         ...wjBooksInfoTable.searchParam
    }).then(res => {
        wjBooksInfoTable.loading = false
        wjBooksInfoTable.data = res.data.data
        wjBooksInfoTable.total = res.data.total
    }).catch(() => {
        wjBooksInfoTable.loading = false
    })
}
loadWjBooksInfoList()

const editWjBooksInfoDialog: Record<string, any> | null = ref(null)
const detailWjBooksInfoDialog: Record<string, any> | null = ref(null)

/**
 * 添加图书信息
 */
const addEvent = () => {
    editWjBooksInfoDialog.value.setFormData()
    editWjBooksInfoDialog.value.showDialog = true
}

/**
 * 编辑图书信息
 * @param data
 */
const editEvent = (data: any) => {
    editWjBooksInfoDialog.value.setFormData(data)
    editWjBooksInfoDialog.value.showDialog = true
}

/**
 * 查看详情
 * @param data
 */
const viewDetails = (data: any) => {
    detailWjBooksInfoDialog.value.setDetailData(data)
    detailWjBooksInfoDialog.value.showDialog = true
}

/**
 * 删除图书信息
 */
const deleteEvent = (id: number) => {
    ElMessageBox.confirm(t('wjBooksInfoDeleteTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning',
        }
    ).then(() => {
        deleteWjBooksInfo(id).then(() => {
            loadWjBooksInfoList()
        }).catch(() => {
        })
    })
}

    

const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    loadWjBooksInfoList()
}
</script>

<style lang="scss" scoped>
/* 多行超出隐藏 */
.multi-hidden {
		word-break: break-all;
		text-overflow: ellipsis;
		overflow: hidden;
		display: -webkit-box;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical;
	}
</style>
