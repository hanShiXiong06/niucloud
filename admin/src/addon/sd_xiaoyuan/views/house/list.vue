<template>
    <div class="house-list">
        <el-card class="search-card">
            <el-form :inline="true" :model="searchForm">
                <el-form-item label="学校">
                    <el-select v-model="searchForm.school_id" placeholder="全部学校" clearable filterable>
                        <el-option v-for="s in schoolList" :key="s.id" :label="s.name" :value="s.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="关键词">
                    <el-input v-model="searchForm.keyword" placeholder="标题/地址" clearable />
                </el-form-item>
                <el-form-item label="类型">
                    <el-select v-model="searchForm.house_type" placeholder="全部" clearable>
                        <el-option label="整租" value="RENT" />
                        <el-option label="合租" value="SHARE" />
                        <el-option label="转租" value="SUBLEASE" />
                    </el-select>
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="searchForm.status" placeholder="全部" clearable>
                        <el-option label="待审核" :value="0" />
                        <el-option label="已发布" :value="1" />
                        <el-option label="已下架" :value="2" />
                        <el-option label="已出租" :value="3" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="handleSearch">搜索</el-button>
                    <el-button @click="handleReset">重置</el-button>
                    <el-button type="success" @click="openPublishDialog">发布房源</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <el-card class="table-card">
            <el-table :data="tableData" v-loading="loading">
                <el-table-column prop="id" label="ID" width="80" />
                <el-table-column label="封面图" width="100">
                    <template #default="{ row }">
                        <el-image v-if="row.cover_image" :src="img(row.cover_image)" style="width:60px;height:45px;border-radius:4px" fit="cover" :preview-src-list="[img(row.cover_image)]" />
                        <span v-else class="text-gray">-</span>
                    </template>
                </el-table-column>
                <el-table-column prop="nearby_schools_name" label="附近学校" min-width="150" show-overflow-tooltip />
                <el-table-column prop="title" label="标题" min-width="150" show-overflow-tooltip />
                <el-table-column label="类型" width="80">
                    <template #default="{ row }">{{ typeMap[row.house_type] || row.house_type }}</template>
                </el-table-column>
                <el-table-column prop="rooms" label="户型" width="100" />
                <el-table-column label="月租" width="100">
                    <template #default="{ row }">¥{{ row.rent_price }}</template>
                </el-table-column>
                <el-table-column prop="address" label="地址" min-width="150" show-overflow-tooltip />
                <el-table-column label="配套设施" min-width="200">
                    <template #default="{ row }">
                        <template v-if="row.facilities">
                            <el-tag v-for="f in (typeof row.facilities === 'string' ? row.facilities.split(',').filter(Boolean) : row.facilities)" :key="f" size="small" class="mr-1 mb-1">{{ f }}</el-tag>
                        </template>
                        <span v-else class="text-gray">-</span>
                    </template>
                </el-table-column>
                <el-table-column label="坐标" width="80">
                    <template #default="{ row }">
                        <el-tag v-if="row.lng && row.lat" type="success" size="small">已设</el-tag>
                        <el-tag v-else type="info" size="small">未设</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="view_count" label="浏览" width="80" />
                <el-table-column label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag v-if="row.status === 0" type="warning">待审核</el-tag>
                        <el-tag v-else-if="row.status === 1" type="success">已发布</el-tag>
                        <el-tag v-else-if="row.status === 2" type="info">已下架</el-tag>
                        <el-tag v-else type="danger">已出租</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="240" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" type="primary" @click="handleEdit(row)">编辑</el-button>
                        <el-button size="small" @click="handleAudit(row, 1)" v-if="row.status === 0">通过</el-button>
                        <el-button size="small" type="danger" @click="handleAudit(row, 2)" v-if="row.status === 0">拒绝</el-button>
                        <el-button size="small" type="danger" @click="handleDelete(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <el-pagination
                v-model:current-page="page"
                v-model:page-size="limit"
                :total="total"
                layout="total, prev, pager, next"
                @current-change="loadData"
            />
        </el-card>

        <!-- 发布/编辑房源弹窗 -->
        <el-dialog v-model="publishVisible" :title="isEdit ? '编辑房源' : '发布房源'" width="800px" destroy-on-close>
            <el-form :model="publishForm" label-width="100px">
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item label="标题" required>
                            <el-input v-model="publishForm.title" placeholder="请输入房源标题" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="类型">
                            <el-select v-model="publishForm.house_type" style="width:100%">
                                <el-option label="整租" value="RENT" />
                                <el-option label="合租" value="SHARE" />
                                <el-option label="转租" value="SUBLEASE" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="附近学校" required>
                    <el-select v-model="publishForm.nearby_schools" multiple placeholder="请选择附近学校" style="width:100%" filterable>
                        <el-option v-for="s in schoolList" :key="s.id" :label="s.name" :value="s.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="配套设施">
                    <el-checkbox-group v-model="publishForm.facilities">
                        <el-checkbox v-for="item in facilitiesOptions" :key="item" :label="item">{{ item }}</el-checkbox>
                    </el-checkbox-group>
                </el-form-item>
                <el-row :gutter="20">
                    <el-col :span="8">
                        <el-form-item label="月租(元)" required>
                            <el-input-number v-model="publishForm.rent_price" :min="0" :precision="2" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="押金(元)">
                            <el-input-number v-model="publishForm.deposit" :min="0" :precision="2" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="面积(㎡)">
                            <el-input-number v-model="publishForm.area" :min="0" :precision="2" style="width:100%" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item label="户型">
                            <el-input v-model="publishForm.rooms" placeholder="如: 2室1厅1卫" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="楼层">
                            <el-input v-model="publishForm.floor" placeholder="如: 3/18层" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="地址" required>
                    <el-input v-model="publishForm.address" placeholder="请输入详细地址，或在地图上点选" />
                </el-form-item>
                <el-form-item label="地图选点">
                    <div class="map-container">
                        <div id="houseMapContainer" class="map-box" v-loading="mapLoading"></div>
                        <div class="map-lnglat" v-if="publishForm.lng && publishForm.lat">
                            经度: {{ publishForm.lng }} | 纬度: {{ publishForm.lat }}
                        </div>
                    </div>
                </el-form-item>
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item label="联系人">
                            <el-input v-model="publishForm.contact_name" placeholder="联系人姓名" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="联系电话">
                            <el-input v-model="publishForm.contact_mobile" placeholder="联系电话" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="封面图">
                    <upload-image v-model="publishForm.cover_image" />
                    <div class="text-gray-400 text-xs mt-1">建议尺寸: 750x500px</div>
                </el-form-item>
                <el-form-item label="轮播图">
                    <upload-image v-model="publishForm.images" :limit="9" />
                    <div class="text-gray-400 text-xs mt-1">最多9张，建议尺寸: 750x500px</div>
                </el-form-item>
                <el-form-item label="描述">
                    <el-input v-model="publishForm.content" type="textarea" :rows="3" placeholder="房源描述" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="publishVisible = false">取消</el-button>
                <el-button type="primary" @click="submitPublish" :loading="publishLoading">{{ isEdit ? '保存' : '发布' }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, nextTick } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getHouseList, auditHouse, deleteHouse, addHouse, editHouse, getAllSchools } from '../../api/admin'
import { getMap } from '@/app/api/sys'
import { createMarker, latLngToAddress } from '@/utils/qqmap'
import UploadImage from '@/components/upload-image/index.vue'
import { img } from '@/utils/common'

const typeMap: Record<string, string> = { RENT: '整租', SHARE: '合租', SUBLEASE: '转租' }
const searchForm = ref({ school_id: '', keyword: '', house_type: '', status: '' })
const schoolList = ref<any[]>([])

const loadSchools = async () => {
    const res: any = await getAllSchools()
    if (res.code === 1) {
        schoolList.value = res.data || []
    }
}
const tableData = ref([])
const loading = ref(false)
const page = ref(1)
const limit = ref(10)
const total = ref(0)

const publishVisible = ref(false)
const publishLoading = ref(false)
const mapLoading = ref(true)
const isEdit = ref(false)
const editId = ref(0)
let mapKey = ''
let map: any = null
let marker: any = null

const facilitiesOptions = ['WiFi', '空调', '洗衣机', '冰箱', '热水器', '电视', '独立卫生间', '阳台', '厨房', '床', '衣柜', '桌椅', '电梯', '停车位']

const defaultPublishForm = {
    title: '',
    house_type: 'RENT',
    nearby_schools: [] as number[],
    facilities: [] as string[],
    rent_price: 0,
    deposit: 0,
    area: 0,
    rooms: '',
    floor: '',
    address: '',
    lng: '',
    lat: '',
    content: '',
    contact_name: '',
    contact_mobile: '',
    cover_image: '',
    images: ''
}
const publishForm = ref({ ...defaultPublishForm })

onMounted(() => {
    loadSchools()
    loadData()
    loadMapKey()
})

const loadMapKey = async () => {
    try {
        const res: any = await getMap()
        if (res.data && res.data.key) {
            mapKey = res.data.key
        }
    } catch (e) {
        console.error('获取地图key失败', e)
    }
}

const openPublishDialog = () => {
    isEdit.value = false
    editId.value = 0
    publishForm.value = { ...defaultPublishForm, nearby_schools: [] }
    publishVisible.value = true
    nextTick(() => {
        setTimeout(() => initMap(), 300)
    })
}

const handleEdit = (row: any) => {
    isEdit.value = true
    editId.value = row.id
    publishForm.value = {
        title: row.title || '',
        house_type: row.house_type || 'RENT',
        nearby_schools: row.nearby_schools ? (typeof row.nearby_schools === 'string' ? row.nearby_schools.split(',').filter(Boolean).map(Number) : row.nearby_schools) : [],
        facilities: row.facilities ? (typeof row.facilities === 'string' ? row.facilities.split(',').filter(Boolean) : row.facilities) : [],
        rent_price: row.rent_price || 0,
        deposit: row.deposit || 0,
        area: row.area || 0,
        rooms: row.rooms || '',
        floor: row.floor || '',
        address: row.address || '',
        lng: row.lng || '',
        lat: row.lat || '',
        content: row.content || '',
        contact_name: row.contact_name || '',
        contact_mobile: row.contact_mobile || '',
        cover_image: row.cover_image || '',
        images: row.images || ''
    }
    publishVisible.value = true
    nextTick(() => {
        setTimeout(() => initMap(), 300)
    })
}

const initMap = () => {
    if (!mapKey) {
        mapLoading.value = false
        return
    }
    const existingScript = document.getElementById('tmap-script')
    if (!existingScript) {
        const script = document.createElement('script')
        script.id = 'tmap-script'
        script.type = 'text/javascript'
        script.src = 'https://map.qq.com/api/gljs?libraries=tools,service&v=1.exp&key=' + mapKey
        document.body.appendChild(script)
        script.onload = () => setTimeout(() => createMap(), 300)
    } else {
        createMap()
    }
}

const createMap = () => {
    const TMap = (window as any).TMap
    if (!TMap) { mapLoading.value = false; return }
    const LatLng = TMap.LatLng
    const center = new LatLng(39.908626, 116.397190)

    const el = document.getElementById('houseMapContainer')
    if (!el) { mapLoading.value = false; return }

    map = new TMap.Map('houseMapContainer', { center, zoom: 14 })
    map.on('tilesloaded', () => { mapLoading.value = false })
    marker = createMarker(map)

    map.on('click', (evt: any) => {
        map.setCenter(evt.latLng)
        marker.updateGeometries({ id: 'center', position: evt.latLng })
        onLatLngChange(evt.latLng.lat, evt.latLng.lng)
    })
}

const onLatLngChange = (lat: number, lng: number) => {
    publishForm.value.lat = String(lat)
    publishForm.value.lng = String(lng)
    latLngToAddress({ mapKey, lat, lng }).then((res: any) => {
        const msg = res?.message || ''
        const result = res?.result
        if ((msg === 'query ok' || msg === 'Success' || res?.status === 0) && result) {
            publishForm.value.address = result.formatted_addresses?.recommend || result.address || ''
        }
    }).catch(() => {})
}

const loadData = async () => {
    loading.value = true
    try {
        const res: any = await getHouseList({ ...searchForm.value, page: page.value, limit: limit.value })
        if (res.code === 1) {
            tableData.value = res.data.list
            total.value = res.data.count
        }
    } finally {
        loading.value = false
    }
}

const handleSearch = () => { page.value = 1; loadData() }
const handleReset = () => { searchForm.value = { school_id: '', keyword: '', house_type: '', status: '' }; handleSearch() }

const submitPublish = async () => {
    if (!publishForm.value.title) { ElMessage.warning('请输入标题'); return }
    if (!publishForm.value.nearby_schools || publishForm.value.nearby_schools.length === 0) { ElMessage.warning('请选择附近学校'); return }
    if (!publishForm.value.address) { ElMessage.warning('请输入地址'); return }
    if (!publishForm.value.rent_price) { ElMessage.warning('请输入月租'); return }

    publishLoading.value = true
    try {
        let res: any
        if (isEdit.value) {
            res = await editHouse({ id: editId.value, ...publishForm.value })
        } else {
            res = await addHouse(publishForm.value)
        }
        if (res.code === 1) {
            ElMessage.success(isEdit.value ? '保存成功' : '发布成功')
            publishVisible.value = false
            loadData()
        } else {
            ElMessage.error(res.msg || (isEdit.value ? '保存失败' : '发布失败'))
        }
    } catch (e) {
        ElMessage.error(isEdit.value ? '保存失败' : '发布失败')
    } finally {
        publishLoading.value = false
    }
}

const handleAudit = async (row: any, status: number) => {
    await ElMessageBox.confirm(`确定${status === 1 ? '通过' : '拒绝'}该房源？`, '提示')
    const res: any = await auditHouse({ id: row.id, status })
    if (res.code === 1) {
        ElMessage.success('操作成功')
        loadData()
    }
}

const handleDelete = async (row: any) => {
    await ElMessageBox.confirm('确定删除该房源？', '提示')
    const res: any = await deleteHouse({ id: row.id })
    if (res.code === 1) {
        ElMessage.success('删除成功')
        loadData()
    }
}
</script>

<style scoped>
.search-card { margin-bottom: 16px; }
.map-container { width: 100%; }
.map-box { width: 100%; height: 300px; border: 1px solid #dcdfe6; border-radius: 4px; }
.map-lnglat { margin-top: -28px; font-size: 12px; color: #f00; }
</style>
