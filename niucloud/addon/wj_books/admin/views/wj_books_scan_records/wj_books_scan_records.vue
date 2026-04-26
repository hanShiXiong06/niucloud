<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center">
                <span class="text-lg">{{pageName}}</span>
                <el-button type="primary" @click="addEvent">
                    {{ t('addWjBooksScanRecords') }}
                </el-button>
            </div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="wjBooksScanRecordsTable.searchParam" ref="searchFormRef">
                    
<el-form-item :label="t('memberId')" prop="member_id">
    <el-select class="w-[280px]" v-model="wjBooksScanRecordsTable.searchParam.member_id" clearable :placeholder="t('memberIdPlaceholder')">
       <el-option
                   v-for="(item, index) in memberIdList"
                   :key="index"
                   :label="item['member_no']"
                   :value="item['member_id']"
               />
    </el-select>
</el-form-item>

                    <el-form-item :label="t('isbn')" prop="isbn">
    <el-input v-model="wjBooksScanRecordsTable.searchParam.isbn" :placeholder="t('isbnPlaceholder')" />
</el-form-item>

                    <el-form-item :label="t('scanType')" prop="scan_type">
    <el-select v-model="wjBooksScanRecordsTable.searchParam.scan_type" clearable :placeholder="t('scanTypePlaceholder')">
        <el-option :label="'扫码'" :value="1" />
        <el-option :label="'手动输入'" :value="2" />
    </el-select>
</el-form-item>

                    <el-form-item :label="t('status')" prop="status">
    <el-select v-model="wjBooksScanRecordsTable.searchParam.status" clearable :placeholder="t('statusPlaceholder')">
        <el-option :label="'仅扫描'" :value="0" />
        <el-option :label="'已加入回收车'" :value="1" />
        <el-option :label="'已提交回收'" :value="2" />
    </el-select>
</el-form-item>

                    <el-form-item>
                        <el-button type="primary" @click="loadWjBooksScanRecordsList()">{{ t('search') }}</el-button>
                        <el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <div class="mt-[10px]">
                <el-table :data="wjBooksScanRecordsTable.data" size="large" v-loading="wjBooksScanRecordsTable.loading">
                    <template #empty>
                        <span>{{ !wjBooksScanRecordsTable.loading ? t('emptyData') : '' }}</span>
                    </template>
                    <el-table-column prop="member_id_name" :label="t('memberId')" min-width="120" :show-overflow-tooltip="true"/>

                    <el-table-column prop="isbn" :label="t('isbn')" min-width="120" :show-overflow-tooltip="true"/>

                    <el-table-column prop="scan_time" :label="t('scanTime')" min-width="120" :show-overflow-tooltip="true"/>

                    <el-table-column prop="scan_type" :label="t('scanType')" min-width="120">
                        <template #default="{ row }">
                            {{ formatScanType(row.scan_type) }}
                        </template>
                    </el-table-column>

                    <el-table-column prop="status" :label="t('status')" min-width="120">
                        <template #default="{ row }">
                            {{ formatStatus(row.status) }}
                        </template>
                    </el-table-column>

                    <el-table-column prop="create_time" :label="t('createTime')" min-width="120" :show-overflow-tooltip="true"/>

                    <el-table-column prop="update_time" :label="t('updateTime')" min-width="120" :show-overflow-tooltip="true"/>

                    <el-table-column :label="t('operation')" fixed="right" min-width="120">
                       <template #default="{ row }">
                           <el-button type="primary" link @click="editEvent(row)">{{ t('edit') }}</el-button>
                           <el-button type="primary" link @click="deleteEvent(row.id)">{{ t('delete') }}</el-button>
                       </template>
                    </el-table-column>

                </el-table>
                <div class="mt-[16px] flex justify-end">
                    <el-pagination v-model:current-page="wjBooksScanRecordsTable.page" v-model:page-size="wjBooksScanRecordsTable.limit"
                        layout="total, sizes, prev, pager, next, jumper" :total="wjBooksScanRecordsTable.total"
                        @size-change="loadWjBooksScanRecordsList()" @current-change="loadWjBooksScanRecordsList" />
                </div>
            </div>

            <edit ref="editWjBooksScanRecordsDialog" @complete="loadWjBooksScanRecordsList" />
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, watch } from 'vue'
import { t } from '@/lang'
import { useDictionary } from '@/app/api/dict'
import { getWjBooksScanRecordsList, deleteWjBooksScanRecords, getWithMemberList } from '@/addon/wj_books/api/wj_books_scan_records'
import { img } from '@/utils/common'
import { ElMessageBox,FormInstance } from 'element-plus'
import Edit from '@/addon/wj_books/views/wj_books_scan_records/components/wj-books-scan-records-edit.vue'
import { useRoute } from 'vue-router'
const route = useRoute()
const pageName = route.meta.title;

let wjBooksScanRecordsTable = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam:{
      "member_id":"",
      "isbn":"",
      "scan_type":"",
      "status":""
    }
})

const searchFormRef = ref<FormInstance>()

// 选中数据
const selectData = ref<any[]>([])

// 字典数据
    

/**
 * 获取图书扫描记录列表
 */
const loadWjBooksScanRecordsList = (page: number = 1) => {
    wjBooksScanRecordsTable.loading = true
    wjBooksScanRecordsTable.page = page

    getWjBooksScanRecordsList({
        page: wjBooksScanRecordsTable.page,
        limit: wjBooksScanRecordsTable.limit,
         ...wjBooksScanRecordsTable.searchParam
    }).then(res => {
        wjBooksScanRecordsTable.loading = false
        wjBooksScanRecordsTable.data = res.data.data
        wjBooksScanRecordsTable.total = res.data.total
    }).catch(() => {
        wjBooksScanRecordsTable.loading = false
    })
}
loadWjBooksScanRecordsList()

const editWjBooksScanRecordsDialog: Record<string, any> | null = ref(null)

/**
 * 添加图书扫描记录
 */
const addEvent = () => {
    editWjBooksScanRecordsDialog.value.setFormData()
    editWjBooksScanRecordsDialog.value.showDialog = true
}

/**
 * 编辑图书扫描记录
 * @param data
 */
const editEvent = (data: any) => {
    editWjBooksScanRecordsDialog.value.setFormData(data)
    editWjBooksScanRecordsDialog.value.showDialog = true
}

/**
 * 删除图书扫描记录
 */
const deleteEvent = (id: number) => {
    ElMessageBox.confirm(t('wjBooksScanRecordsDeleteTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning',
        }
    ).then(() => {
        deleteWjBooksScanRecords(id).then(() => {
            loadWjBooksScanRecordsList()
        }).catch(() => {
        })
    })
}

    
    const memberIdList = ref([])
    const setMemberIdList = async () => {
    memberIdList.value = await (await getWithMemberList({})).data
    }
    setMemberIdList()

const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    loadWjBooksScanRecordsList()
}

/**
 * 格式化扫描类型
 * @param scanType 扫描类型值
 * @returns 格式化后的文本
 */
const formatScanType = (scanType: number) => {
    const scanTypeMap: Record<number, string> = {
        1: '扫码',
        2: '手动输入'
    }
    return scanTypeMap[scanType] || scanType
}

/**
 * 格式化状态
 * @param status 状态值
 * @returns 格式化后的文本
 */
const formatStatus = (status: number) => {
    const statusMap: Record<number, string> = {
        0: '仅扫描',
        1: '已加入回收车',
        2: '已提交回收'
    }
    return statusMap[status] || status
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
