<template>
    <div class="school-list-container">
        <!-- 搜索区域 -->
        <el-card class="search-card">
            <el-form :model="searchForm" inline>
                <el-form-item label="学校名称">
                    <el-input v-model="searchForm.name" placeholder="请输入学校名称" clearable />
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="searchForm.status" placeholder="全部状态" clearable>
                        <el-option label="启用" :value="1" />
                        <el-option label="禁用" :value="0" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadList">搜索</el-button>
                    <el-button @click="handleReset">重置</el-button>
                    <el-button type="success" @click="showAdd">添加学校</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 列表 -->
        <el-card>
            <el-table :data="list" v-loading="loading" stripe>
                <el-table-column prop="id" label="ID" width="80" />
                <el-table-column label="Logo" width="80" align="center">
                    <template #default="{ row }">
                        <el-image v-if="row.logo" :src="img(row.logo)" style="width: 48px; height: 48px; border-radius: 50%;" fit="cover" />
                        <span v-else class="text-gray-400">-</span>
                    </template>
                </el-table-column>
                <el-table-column prop="name" label="学校名称" min-width="200" />
                <el-table-column prop="short_name" label="简称" width="120" />
                <el-table-column prop="province" label="省份" width="100" />
                <el-table-column prop="city" label="城市" width="100" />
                <el-table-column prop="campus_list" label="校区" width="200">
                    <template #default="{ row }">
                        <template v-if="row.campus_list">
                            <el-tag v-for="(campus, index) in row.campus_list.split(',').filter((s: string) => s.trim())" :key="index" size="small" class="mr-1">
                                {{ campus.trim() }}
                            </el-tag>
                        </template>
                        <span v-else>-</span>
                    </template>
                </el-table-column>
                <el-table-column prop="status" label="状态" width="100">
                    <template #default="{ row }">
                        <el-switch v-model="row.status" :active-value="1" :inactive-value="0" @change="handleStatusChange(row)" />
                    </template>
                </el-table-column>
                <el-table-column label="坐标" width="80">
                    <template #default="{ row }">
                        <el-tag v-if="row.lng && row.lat" type="success" size="small">已设</el-tag>
                        <el-tag v-else type="info" size="small">未设</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="sort" label="排序" width="80" />
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
                :page-sizes="[10, 20, 50, 100]"
                layout="total, sizes, prev, pager, next, jumper"
                @size-change="loadList"
                @current-change="loadList"
            />
        </el-card>

        <!-- 添加/编辑弹窗 -->
        <el-dialog v-model="formVisible" :title="formData.id ? '编辑学校' : '添加学校'" width="600px" :destroy-on-close="true">
            <el-form :model="formData" label-width="100px">
                <el-form-item label="学校名称" required>
                    <el-input v-model="formData.name" placeholder="请输入学校名称" />
                </el-form-item>
                <el-form-item label="学校简称">
                    <el-input v-model="formData.short_name" placeholder="请输入学校简称" />
                </el-form-item>
                <el-form-item label="学校Logo">
                    <upload-image v-model="formData.logo" :limit="1" width="80px" height="80px" />
                </el-form-item>
                <el-form-item label="省份">
                    <el-input v-model="formData.province" placeholder="请输入省份" />
                </el-form-item>
                <el-form-item label="城市">
                    <el-input v-model="formData.city" placeholder="请输入城市" />
                </el-form-item>
                <el-form-item label="详细地址">
                    <el-input v-model="formData.address" placeholder="请输入详细地址，或在地图上点选" />
                </el-form-item>
                <el-form-item label="地图选点">
                    <div class="map-container">
                        <div id="schoolMapContainer" class="map-box" v-loading="mapLoading"></div>
                        <div class="map-lnglat" v-if="formData.lng && formData.lat">
                            经度: {{ formData.lng }} | 纬度: {{ formData.lat }}
                        </div>
                    </div>
                </el-form-item>
                <el-form-item label="校区列表">
                    <el-input v-model="formData.campus_list" placeholder="多个校区用英文逗号分隔，如：东校区,西校区,南校区" />
                    <div class="text-gray-400 text-xs mt-1">多个校区用英文逗号(,)分隔</div>
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
import { ref, reactive, onMounted, nextTick, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getSchoolList, addSchool, editSchool, delSchool, setSchoolStatus } from '@/addon/sd_xiaoyuan/api/school'
import { getMap } from '@/app/api/sys'
import { createMarker, latLngToAddress } from '@/utils/qqmap'
import { img } from '@/utils/common'

const loading = ref(false)
const list = ref<any[]>([])
const pagination = reactive({
    page: 1,
    limit: 10,
    total: 0
})

const searchForm = reactive({
    name: '',
    status: ''
})

const formVisible = ref(false)
const submitLoading = ref(false)
const formData = reactive({
    id: 0,
    name: '',
    short_name: '',
    logo: '',
    province: '',
    city: '',
    address: '',
    lng: '',
    lat: '',
    campus_list: '',
    sort: 0,
    status: 1
})

const mapLoading = ref(true)
let mapKey = ''
let schoolMap: any = null
let schoolMarker: any = null


onMounted(() => {
    loadList()
    loadMapKey()
})

const loadMapKey = async () => {
    try {
        // 腾讯地图 GL JS 要求使用“前端 JSAPI key”，且这里需要拿明文 key（否则会 KEY_FORMAT_ERROR）
        const res: any = await getMap({ need_encrypt: false })
        if (res.data && res.data.key) { mapKey = String(res.data.key).trim() }
    } catch (e) { console.error(e) }
}

const initSchoolMap = () => {
    if (!mapKey) { mapLoading.value = false; return }
    const existingScript = document.getElementById('tmap-script')
    if (!existingScript) {
        const script = document.createElement('script')
        script.id = 'tmap-script'
        script.type = 'text/javascript'
        script.src = 'https://map.qq.com/api/gljs?libraries=tools,service&v=1.exp&key=' + mapKey
        document.body.appendChild(script)
        script.onload = () => setTimeout(() => createSchoolMap(), 300)
    } else {
        createSchoolMap()
    }
}

const createSchoolMap = () => {
    const TMap = (window as any).TMap
    if (!TMap) { mapLoading.value = false; return }
    const lat = parseFloat(formData.lat) || 39.908626
    const lng = parseFloat(formData.lng) || 116.397190
    const center = new TMap.LatLng(lat, lng)
    const el = document.getElementById('schoolMapContainer')
    if (!el) { mapLoading.value = false; return }
    schoolMap = new TMap.Map('schoolMapContainer', { center, zoom: 14 })
    schoolMap.on('tilesloaded', () => { mapLoading.value = false })
    schoolMarker = createMarker(schoolMap)
    schoolMap.on('click', (evt: any) => {
        schoolMap.setCenter(evt.latLng)
        schoolMarker.updateGeometries({ id: 'center', position: evt.latLng })
        onSchoolLatLngChange(evt.latLng.lat, evt.latLng.lng)
    })
}

const onSchoolLatLngChange = (lat: number, lng: number) => {
    formData.lat = String(lat)
    formData.lng = String(lng)
    latLngToAddress({ mapKey, lat, lng }).then(({ message, result }: any) => {
        if (message === 'query ok' || message === 'Success') {
            formData.address = result.formatted_addresses.recommend
        }
    }).catch(() => {})
}

const loadList = async () => {
    loading.value = true
    try {
        const res: any = await getSchoolList({
            ...searchForm,
            page: pagination.page,
            limit: pagination.limit
        })
        list.value = res.data?.data || []
        pagination.total = res.data?.total || 0
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
    }
}

const handleReset = () => {
    searchForm.name = ''
    searchForm.status = ''
    pagination.page = 1
    loadList()
}

const showAdd = () => {
    formData.id = 0
    formData.name = ''
    formData.short_name = ''
    formData.logo = ''
    formData.province = ''
    formData.city = ''
    formData.address = ''
    formData.lng = ''
    formData.lat = ''
    formData.campus_list = ''
    formData.sort = 0
    formData.status = 1
    formVisible.value = true
    mapLoading.value = true
    nextTick(() => { setTimeout(() => initSchoolMap(), 300) })
}

const showEdit = (row: any) => {
    formData.id = row.id
    formData.name = row.name
    formData.short_name = row.short_name || ''
    formData.logo = row.logo || ''
    formData.province = row.province || ''
    formData.city = row.city || ''
    formData.address = row.address || ''
    formData.lng = row.lng || ''
    formData.lat = row.lat || ''
    formData.campus_list = row.campus_list || ''
    formData.sort = row.sort || 0
    formData.status = row.status
    formVisible.value = true
    mapLoading.value = true
    nextTick(() => { setTimeout(() => initSchoolMap(), 300) })
}


const handleSubmit = async () => {
    if (!formData.name) {
        ElMessage.warning('请输入学校名称')
        return
    }
    submitLoading.value = true
    try {
        if (formData.id) {
            await editSchool(formData)
        } else {
            await addSchool(formData)
        }
        ElMessage.success(formData.id ? '编辑成功' : '添加成功')
        formVisible.value = false
        loadList()
    } catch (e: any) {
        ElMessage.error(e.message || '操作失败')
    } finally {
        submitLoading.value = false
    }
}

const handleStatusChange = async (row: any) => {
    try {
        await setSchoolStatus(row.id, row.status)
        ElMessage.success('状态修改成功')
    } catch (e: any) {
        row.status = row.status === 1 ? 0 : 1
        ElMessage.error(e.message || '操作失败')
    }
}

const handleDel = (row: any) => {
    ElMessageBox.confirm('确定要删除该学校吗？', '提示', {
        type: 'warning'
    }).then(async () => {
        try {
            await delSchool(row.id)
            ElMessage.success('删除成功')
            loadList()
        } catch (e: any) {
            ElMessage.error(e.message || '删除失败')
        }
    }).catch(() => {})
}
</script>

<style lang="scss" scoped>
.school-list-container {
    padding: 20px;
}

.search-card {
    margin-bottom: 20px;
}

.mr-1 {
    margin-right: 5px;
}

.mb-1 {
    margin-bottom: 5px;
}

.campus-list-wrapper {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}

.campus-item {
    display: inline-block;
}

.cursor-pointer {
    cursor: pointer;
}

.map-container {
    width: 100%;
}

.map-box {
    width: 100%;
    height: 300px;
    border: 1px solid #dcdfe6;
    border-radius: 4px;
}

.map-lnglat {
    margin-top: 8px;
    font-size: 12px;
    color: #999;
}
</style>
