<template>
    <el-dialog v-model="dialogMemberVisible" title="添加地址"  width="950px">
        <el-form :model="formData" label-width="90px" ref="formRef" :rules="formRules" class="page-form">

            <el-form-item label="收货人" prop="name">
                <el-input v-model.trim="formData.name" clearable placeholder="请输入收货人" class="input-width" maxlength="10" />
            </el-form-item>

            <el-form-item label="联系电话" prop="mobile">
                <el-input v-model.trim="formData.mobile" clearable placeholder="请输入联系电话" class="input-width" @keyup="filterNumber($event)" @blur="formData.mobile = $event.target.value"/>
            </el-form-item>

            <el-form-item label="地址" prop="address_area">
                <el-select v-model="formData.province_id" value-key="id" clearable class="w-[200px]" ref="provinceRef">
                    <el-option :label="t('provincePlaceholder')" :value="0"/>
                    <el-option v-for="(item, index) in areaList.province" :key="index" :label="item.name" :value="item.id"/>
                </el-select>
                <el-select v-model="formData.city_id" value-key="id" clearable class="w-[200px] ml-3" ref="cityRef">
                    <el-option :label="t('cityPlaceholder')" :value="0"/>
                    <el-option v-for="(item, index) in areaList.city " :key="index" :label="item.name" :value="item.id"/>
                </el-select>
                <el-select v-model="formData.district_id" value-key="id" clearable class="w-[200px] ml-3" ref="districtRef">
                    <el-option :label="t('districtPlaceholder')" :value="0"/>
                    <el-option v-for="(item, index) in areaList.district " :key="index" :label="item.name" :value="item.id"/>
                </el-select>
            </el-form-item>
            
            <el-form-item label="选择地图" prop="address">
                <div>
                    <div>
                        <el-input v-model.trim="formData.address" clearable placeholder="请输入详细地址" class="input-width" maxlength="120"/>
                        <el-button class="ml-3" @click="searchOn">搜索</el-button>
                    </div>
                    <div class="mt-4">
                        <div id="AddressMap" class="map-item w-[700px] h-[400px]"></div>
                    </div>
                </div>
            </el-form-item>
        </el-form>
        <template #footer>
            <div class="dialog-footer">
                <el-button @click="dialogMemberVisible = false">取消</el-button>
                <el-button type="primary" @click="confirmFn(formRef)">保存</el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, nextTick, onUnmounted } from 'vue'
import { t } from '@/lang'
import { getAreaListByPid, getAddressInfo, getContraryAddress, getMap } from '@/app/api/sys'
import { addMemberAddress } from '@/app/api/member'
import { filterNumber } from '@/utils/common'
import type { FormInstance } from 'element-plus'
import { cloneDeep } from 'lodash-es'
import { ElMessage } from 'element-plus'

const dialogMemberVisible = ref(false)
const areaList = reactive({
    province: [],
    city: [],
    district: []
})
const provinceRef = ref()
const cityRef = ref()
const districtRef = ref()
let confirmRepeat = false

/**
* 表单数据
*/
const initialFormData = {
    member_id: 0,
    name: '',
    mobile: '',
    province_id: 0,
    province_name: '',
    city_id: 0,
    city_name: '',
    district_id: 0,
    district_name: '',
    address: '',
    full_address: '',
    lat: '',
    lng: ''
}

const formData: Record<string, any> = reactive({ ...initialFormData })

const open = (id:any) => {
    Object.assign(formData, initialFormData)
    formData.member_id = id
    confirmRepeat = false
    dialogMemberVisible.value = true
    // 打开对话框后初始化地图
    nextTick(() => {
        initMap()
    })
}

/**
 * 获取省
 */
getAreaListByPid(0).then(res => {
    areaList.province = res.data
})

/**
 * 获取市
 */
watch(() => formData.province_id, (nval) => {
    if (nval) {
        getAreaListByPid(formData.province_id).then(res => {
            areaList.city = res.data

            const cityId = formData.city_id
            if (cityId) {
                let isExist = false
                for (let i = 0; i < res.data.length; i++) {
                    if (cityId == res.data[i].id) {
                        isExist = true
                        break
                    }
                }
                if (isExist) {
                    formData.city_id = cityId
                    return
                }
            }
            formData.city_id = 0
        })
    } else {
        formData.city_id = 0
    }
})

/**
* 获取区
 */
watch(() => formData.city_id, (nval) => {
    if (nval) {
        getAreaListByPid(formData.city_id).then(res => {
            areaList.district = res.data

            const districtId = formData.district_id
            if (districtId) {
                let isExist = false
                for (let i = 0; i < res.data.length; i++) {
                    if (districtId == res.data[i].id) {
                        isExist = true
                        break
                    }
                }
                if (isExist) {
                    formData.district_id = districtId
                    return
                }
            }
            formData.district_id = 0
        })
    } else {
        formData.district_id = 0
    }
})

const formRef = ref<FormInstance>()

// 表单验证规则
const formRules = computed(() => {
    return {
        name: [
            { required: true, message: '请输入收货人', trigger: 'blur' }
        ],
        mobile: [
            { required: true, message: '请输入联系电话', trigger: 'blur' },
            {
                trigger: 'blur',
                validator: (rule: any, value: any, callback: any) => {
                    if (value && !/^1[3-9]\d{9}$/.test(value)) {
                        callback(new Error('请输入正确的联系电话'))
                    }
                    callback()
                }
            }
        ],
        address_area: [
            {
                required: true,
                validator: (rule: any, value: any, callback: any) => {
                    if (!formData.province_id) {
                        callback(new Error(t('请选择省')))
                    }
                    if (!formData.city_id) {
                        callback(new Error(t('请选择市')))
                    }
                    if (areaList.district.length && !formData.district_id) {
                        callback(new Error(t('请选择区/县')))
                    }
                    callback()
                },
                trigger: 'blur'
            }
        ],
        address: [
            { required: true, message: t('addressPlaceholder'), trigger: 'blur' }
        ]
    }
})

const confirmFn = async (formEl: FormInstance | undefined) => {
    if (confirmRepeat || !formEl) return
    await formEl.validate(async (valid) => {
        if (valid) {
            confirmRepeat = true

            const data = cloneDeep(formData)
            const address = [
                data.province_id ? (provinceRef.value.selectedLabel || provinceRef.value.currentPlaceholder) : '',
                data.city_id ? (cityRef.value.selectedLabel || cityRef.value.currentPlaceholder) : '',
                data.district_id ? (districtRef.value.selectedLabel || districtRef.value.currentPlaceholder) : '',
                data.address
            ]
            data.full_address = address.join('')

            addMemberAddress(data).then(res => {
                dialogMemberVisible.value = false
                confirmRepeat = false
                emit('confirm')
            }).catch(() => {
                confirmRepeat = false
            })
        }
    })
}

const emit = defineEmits(['confirm'])
defineExpose({
    dialogMemberVisible,
    open
})
// 地图相关变量
let mapFn: any = null
let mapScriptLoaded = false

// 地址搜索逻辑
const searchOn = () => {
    if (formData.province_id && formData.city_id && formData.district_id && formData.address) {
        const province = areaList.province.find(item => item.id == formData.province_id)
        const city = areaList.city.find(item => item.id == formData.city_id)
        const district = areaList.district.find(item => item.id == formData.district_id)
        
        formData.full_address = `${province?.name || ''}${city?.name || ''}${district?.name || ''}${formData.address}`
        
        getAddressInfo({ address: formData.full_address }).then(res => {
            if (res.data.result) {
                formData.lat = res.data.result.location.lat
                formData.lng = res.data.result.location.lng
            } else {
                ElMessage.error(res.data.message)
            }
        })
    } else {
        ElMessage.warning('请先选择完整的省市区信息并输入详细地址')
    }
}

// 地图初始化
const initMap = () => {
    if (mapScriptLoaded) {
        createMapInstance()
        return
    }

    getMap().then(res => {
        const mapScript = document.createElement('script')
        mapScript.type = 'text/javascript'
        mapScript.src = 'https://map.qq.com/api/gljs?v=1.exp&key=' + res.data.key
        document.body.appendChild(mapScript)

        mapScript.onload = () => {
            mapScriptLoaded = true
            createMapInstance()
        }

        mapScript.onerror = () => {
            console.error('地图脚本加载失败')
            ElMessage.error('地图加载失败，请重试')
        }
    })
}

// 地图实例创建
let markerLayer: any = null
const createMapInstance = () => {
    if (mapFn) {
        mapFn.destroy()
    }

    let latitude = formData.lat || '39.90469'
    let longitude = formData.lng || '116.40717'
    const center = new window.TMap.LatLng(latitude, longitude)

    mapFn = new window.TMap.Map('AddressMap', {
        center: center,
        zoom: 17,
        viewMode: '2D',
        showControl: true
    })

    markerLayer = new window.TMap.MultiMarker({
        id: 'marker-layer',
        map: mapFn,
        minimumClusterSize: 1
    })

    markerLayer.updateGeometries({
        id: 'address',
        position: center
    })

    // 地图点击事件
    mapFn.on('click', (evt: any) => {
        const evtModel = {
            lat: evt.latLng.getLat().toFixed(6),
            lng: evt.latLng.getLng().toFixed(6)
        }

        markerLayer.updateGeometries({
            id: 'address',
            position: evt.latLng
        })

        checkAddressInfo(evtModel.lat, evtModel.lng, 1)
    })

    // 地图 idle 事件
    mapFn.on('idle', () => {
        watch(() => [formData.lat, formData.lng], (newVal) => {
            if (newVal[0] && newVal[1]) {
                const latLng = new window.TMap.LatLng(newVal[0], newVal[1])
                mapFn.panTo(latLng)
                markerLayer.updateGeometries({
                    id: 'address',
                    position: latLng
                })
            }
        })
    })
}

// 逆地址解析
const checkAddressInfo = (lat: any, lng: any, type: any) => {
    getContraryAddress({
        location: lat + ',' + lng
    }).then(res => {
        if (res.data.result) {
            const addressComponent = res.data.result.address_component
            const provinceName = addressComponent.province || ''
            const cityName = addressComponent.city || ''
            const districtName = addressComponent.district || ''

            // 根据名称匹配ID
            if (provinceName) {
                const matchedProvince = areaList.province.find(item => item.name == provinceName)
                if (matchedProvince) {
                    formData.province_id = matchedProvince.id
                    formData.province_name = matchedProvince.name
                    
                    getAreaListByPid(matchedProvince.id).then(res => {
                        areaList.city = res.data
                        const matchedCity = areaList.city.find(item => item.name == cityName)
                        if (matchedCity) {
                            formData.city_id = matchedCity.id
                            formData.city_name = matchedCity.name
                            
                            getAreaListByPid(matchedCity.id).then(res => {
                                areaList.district = res.data
                                const matchedDistrict = areaList.district.find(item => item.name == districtName)
                                if (matchedDistrict) {
                                    formData.district_id = matchedDistrict.id
                                    formData.district_name = matchedDistrict.name
                                }
                            })
                        }
                    })
                }
            }

            if (type == 1) {
                formData.address = res.data.result.formatted_addresses.recommend
                formData.full_address = provinceName + cityName + districtName + formData.address
                formData.lat = lat
                formData.lng = lng
            }
        } else {
            ElMessage({
                type: 'warning',
                message: res.data.message || '获取地址信息失败'
            })
        }
    }).catch(err => {
        console.error('地址解析失败:', err)
        ElMessage.error('获取地址信息失败')
    })
}

// 组件卸载时清理地图
onUnmounted(() => {
    if (mapFn) {
        mapFn.destroy()
        mapFn = null
    }
})

</script>

<style lang="scss" scoped>
#AddressMap {
    border: 1px solid #e4e4e4;
    border-radius: 4px;
}
</style>
