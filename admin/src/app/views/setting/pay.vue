<template>
    <!--支付设置-->
    <div class="main-container" v-loading="payLoading">

        <el-card class="box-card !border-none" shadow="never">
            <div class="flex justify-between items-center" v-if="!payLoading">
                <span class="text-page-title">{{ pageName }}</span>
                <el-button type="primary" @click="isEdit = true" ref="setConfigBtn">{{ t('setConfig') }}</el-button>
            </div>
        </el-card>

        <el-card class="box-card mt-[15px] !border-none" shadow="never" v-for="(payItems, payKey) in payConfigData" :key="payKey">
            <h3 class="panel-title !text-sm">{{ payItems.name }}</h3>

            <div>
                <div class="flex items-center justify-between px-[10px] table-item-border bg h-[50px]">
                    <span class="text-base w-[230px]">{{ t('payType') }}</span>
                    <span class="text-base w-[110px] text-center">{{ t('onState') }}</span>
                    <span class="text-base w-[80px] text-center" v-if="isEdit">{{ t('templateName') }}</span>
                </div>

                <div ref="fieldBoxRefs" :data-key="payKey">
                    <div class="flex items-center justify-between p-[10px] table-item-border" v-for="(childrenItem, childrenIndex) in payItems.pay_type" :key="childrenItem.redio_key" :id="payKey + '_' + childrenIndex">
                        <div class="flex w-[230px] flex-shrink-0">
                            <span v-if="isEdit" class="iconfont icontuodong mr-2 handle cursor-pointer"></span>
                            <div class="flex items-center select-none">
                                <div class="mr-[15px] w-[30px] h-[30px] flex-shrink-0">
                                    <img class="w-[30px]" :src="img(childrenItem.icon)" />
                                </div>
                                <span class="text-base text-[#666]">{{ childrenItem.name }}</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-center w-[110px] select-none">
                            <el-switch v-if="isEdit" v-model="childrenItem.status" :active-value="1" :inactive-value="0" :active-text="t('isEnable')" :loading="!!paymentEnablePending[childrenItem.key]" @change="enablePaymentMode(childrenItem)" />
                            <div v-else>
                                <el-tag v-if="childrenItem.status" class="ml-2" type="success">{{ t('open') }}</el-tag>
                                <el-tag v-else class="ml-2" type="info">{{ t('notOpen') }}</el-tag>
                            </div>
                        </div>
                        <div class="flex items-center justify-center w-[80px] select-none" v-if="isEdit">
                            <button class="text-base" :disabled="!!paymentEnablePending[childrenItem.key]" @click="configPayFn(childrenItem)" v-if="childrenItem.setting_component">{{ t('clickConfigure') }}</button>
                            <button v-else>--</button>
                        </div>
                    </div>
                </div>
            </div>
        </el-card>

        <div class="fixed-footer-wrap" v-if="isEdit">
            <div class="fixed-footer">
                <el-button type="primary" :loading="loading" @click="cancelFn">{{ t('cancel') }}</el-button>
                <el-button type="primary" :loading="loading" :disabled="paymentEnableBusy" @click="saveFn(formRef)">{{ t('save') }}</el-button>
            </div>
        </div>
        <template v-for="(item, index) in payTypeList">
            <component :is="item.setting_component" :ref="(el) => setPayTypeRefs(el, item.key)" v-if="item.setting_component" @complete="setConfigInfo"/>
        </template>

    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, computed, watch, nextTick, defineAsyncComponent, onBeforeUnmount } from 'vue'
import { t } from '@/lang'
import { getPayConfigList, setPatConfig } from '@/app/api/sys'
import { getAllPayType } from '@/app/api/pay'
import { img } from '@/utils/common'
import { ElMessage } from 'element-plus'
import Sortable, { SortableEvent } from 'sortablejs'
import { useRoute } from 'vue-router'
import { cloneDeep } from 'lodash-es'
import useUserStore from '@/stores/modules/user'

const route = useRoute()
const pageName = route.meta.title
const user = useUserStore()
const paymentActor = () => `${user.token || ''}:${user.siteInfo?.site_id || ''}`
const paymentEnablePending = reactive<Record<string, boolean>>({})
const paymentEnableBusy = computed(() => Object.values(paymentEnablePending).some(Boolean))
const paymentEnableJobs = new Map<string, symbol>()
let paymentPageAlive = true
onBeforeUnmount(() => { paymentPageAlive = false; paymentEnableJobs.clear() })

const payTypeList = ref([])
const payTypeRefs = ref({})
import { dynamicComponentModules as modules } from '@/utils/dynamic-components'

getAllPayType().then(({ data }) => {
    Object.keys(data).forEach((key: string) => {
        data[key].setting_component && (data[key].setting_component = defineAsyncComponent(modules[data[key].setting_component]))
    })
    payTypeList.value = data
})

const setPayTypeRefs = (el: any, index: string) => {
    payTypeRefs.value[index] = (el)
}

const payLoading = ref(true)

const isEdit = ref<boolean>(false)
const setConfigBtn = ref()

// 获取原始数据
const payConfigData = ref([])
const checkPayConfigList = () => {
    getPayConfigList().then(res => {
        const payData = res.data
        for (const i in payData) {
            const payType = payData[i].pay_type
            const pay_type = []
            let default_key = ''

            payType.forEach((item, index) => {
                item.redio_key = payData[i].key + '_' + item.key
                item.defauit_key = ''
                if (item.is_default == 1) {
                    default_key = item.redio_key
                }
                pay_type.push(item)
            })

            payData[payData[i].key].default_pay_type = default_key
            payData[payData[i].key].pay_type = pay_type
        }
        payConfigData.value = payData
        payLoading.value = false
        nextTick(() => {
            fieldBoxRefs.value.forEach((item, index) => {
                sortableFn(item, index)
            })
        })
    })
}
checkPayConfigList()

// 配置支付宝、微信信息
const setConfigInfo = (data:any) => {
    payConfigData.value[data.channel].pay_type.forEach(element => {
        if (element.key == data.type) {
            element.config = data.config
        }
    })
}

// 初始化配置信息
const configPayFn = (data:any) => {
    if (paymentEnablePending[data.key]) return
    payTypeRefs.value[data.key].setFormData(data)
    payTypeRefs.value[data.key].showDialog = true
}

// 开启支付方式
const enablePaymentMode = async (data: any) => {
    // Disabling must not depend on a credential or remote catalogue check.
    if (Number(data.status) !== 1) return true
    const key = String(data.key), component = payTypeRefs.value[key]
    if (paymentEnablePending[key]) { data.status = 0; return false }
    if (!component) {
        if (!data.setting_component) return true
        data.status = 0
        ElMessage.warning('支付配置组件正在加载，请稍后重试')
        return false
    }
    if (typeof component.enableVerify !== 'function') return true
    const job = Symbol(key), actor = paymentActor()
    paymentEnableJobs.set(key, job); paymentEnablePending[key] = true
    const current = () => paymentPageAlive && isEdit.value && actor === paymentActor()
        && paymentEnableJobs.get(key) === job
        && Object.values(payConfigData.value).some((group: any) => group.pay_type?.some((row: any) => row === data))
    try {
        // Setting components may read remote options before they can validate.
        // Await also accepts all existing synchronous component contracts.
        if (typeof component.setFormData === 'function') await component.setFormData(data)
        if (!current() || Number(data.status) !== 1) { data.status = 0; return false }
        const verify = await component.enableVerify()
        if (!current() || Number(data.status) !== 1) { data.status = 0; return false }
        if (verify !== true) {
            data.status = 0
            const detail = typeof component.getEnableError === 'function' ? component.getEnableError() : ''
            ElMessage.warning(typeof detail === 'string' && detail ? detail : t('configurePaymentMethod'))
            return false
        }
        return true
    } catch (_) {
        if (current()) ElMessage.warning('支付配置未能核对，请重新打开配置后重试；尚未启用')
        data.status = 0
        return false
    } finally {
        if (paymentEnableJobs.get(key) === job) { paymentEnableJobs.delete(key); paymentEnablePending[key] = false }
    }
}

// 拖动
const fieldBoxRefs = ref<any>([])
watch(isEdit, (newValue, oldValue) => {
    if (newValue) {
        nextTick(() => {
            fieldBoxRefs.value.forEach((item:any, index:any) => {
                sortableFn(item, index)
            })
        })
    }
})

// item=>拖动的每项，index 索引
const sortableFn = (item, index) => {
    const sortable = Sortable.create(item, {
        group: {
            put: false // 不允许拖拽进这个列表
        },
        handle: '.handle',
        animation: 200,
        disabled: false,
        onEnd: (evt) => {
            const key = evt.target.getAttribute('data-key')
            const data = payConfigData.value[key].pay_type
            data.splice(evt.newIndex, 0, ...data.splice(evt.oldIndex, 1))
        }
    })
}

// 保存
const saveFn = () => {
    if (paymentEnableBusy.value) { ElMessage.warning('支付配置正在核对，请稍候再保存'); return }
    payLoading.value = true
    const data = cloneDeep(payConfigData.value)
    Object.values(data).forEach((item, index) => {
        item.pay_type.forEach((subItem:any, subIndex:any) => {
            subItem.sort = subIndex
        })
    })

    setPatConfig({ config: data }).then(res => {
        checkPayConfigList()
        isEdit.value = false
        payLoading.value = false
    })
}

// 取消按钮
const cancelFn = () => {
    location.reload()
}
</script>

<style lang="scss" scoped>
    .table-item-border {
        @apply border-b border-[var(--el-border-color-lighter)];
    }
</style>
