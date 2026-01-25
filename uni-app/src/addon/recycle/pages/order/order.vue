<template>
  <view class="min-h-screen bg-gradient-to-b from-gray-50 to-gray-100 p-3">
    <!-- 顶部导航栏 -->
    <DeliveryModeToggle
      v-model="currentTab"
      @to-order-list="toOrderList"
    />

    <u-form :model="form" :rules="rules" ref="formRef" label-position="left">
      <!-- 出货信息 -->
      <view class="bg-white rounded-lg p-3 mb-3 border-l-4" style="border-color: #D8C1C1;">
        <view class="flex items-center gap-1 mb-2">
          <up-icon name="info-circle" size="16" color="#8C7575"></up-icon>
          <text class="text-sm font-medium" style="color: #8C7575;">出货信息</text>
        </view>

        <!-- 设备列表管理 -->
        <DeviceListManager
          :devices="phoneList"
          :count="deviceCount"
          :show-add-button="true"
          :show-batch-button="true"
          :show-delete-button="true"
          @update:devices="phoneList = $event"
          @update:count="deviceCount = $event"
          @add-single-device="openSingleDeviceModal"
          @add-device="openBatchDeviceModal"
        />

        <!-- 备注 -->
        <up-row>
          <up-col span="3">
            <view class="label">备注</view>
          </up-col>
          <up-col span="9">
            <view class="input-wrapper">
              <up-textarea autoHeight v-model="form.comment" placeholder="请输入备注信息"></up-textarea>
            </view>
          </up-col>
        </up-row>
      </view>

      <!-- 寄件信息 -->
      <ExpressInfoSection
        v-if="currentTab === 0"
        :use-platform-delivery="enablePlatformDelivery"
        :express-no="form.express_no"
        :platform-delivery-form="platformDeliveryForm"
        :pickup-time-options="pickupTimeOptions"
        @update:use-platform-delivery="handlePlatformDeliveryChange"
        @update:express-no="form.express_no = $event"
        @update:platform-delivery-form="platformDeliveryForm = $event"
        @select-address="goToSelectAddress"
        @scan-express="scanCode"
      />

      <!-- 商家信息 -->
      <ShopInfoCard
        :shop-info="shopInfo"
        @copy="copyShopInfo"
        @open-location="openLocation"
      />
      <view class="mt-2 flex  bottom-14 bg-[#fff] rounded  shadow-md p-2 items-center justify-between">
       
      <!-- 回收协议 -->
        <AgreementCheckbox
          v-model="isAgreeRecycle"
          agreement-text="我已阅读并同意"
          agreement-key="recycle_service"
          agreement-title="回收服务协议"
        />

        <!-- 提交按钮 -->
        <view class="">
          <up-button type="primary" @click="handleSubmitOrder" text="确认发货"></up-button>
        </view>
      </view>
    </u-form>

    <!-- 设备输入弹窗 -->
    <DeviceInputModal
      :visible="showDeviceModal"
      :mode="deviceModalMode"
      :enable-pricing="true"
      @update:visible="showDeviceModal = $event"
      @confirm="handleDeviceConfirm"
    />

    <tabbar addon="recycle" />
  </view>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getAddressList } from '@/app/api/member'
import { getPaymentList } from '@/addon/recycle/api/payment'
import { useSubscribeMessage } from '@/hooks/useSubscribeMessage'

// 导入组件
import DeliveryModeToggle from './components/DeliveryModeToggle.vue'
import DeviceListManager from './components/DeviceListManager.vue'
import DeviceInputModal from './components/DeviceInputModal.vue'
import ExpressInfoSection from './components/ExpressInfoSection.vue'
import ShopInfoCard from './components/ShopInfoCard.vue'
import AgreementCheckbox from './components/AgreementCheckbox.vue'

// 导入 composables
import { useTabCache } from '../../hooks/useTabCache'
import { useOrderForm } from '../../hooks/useOrderForm'
import { useDeviceManagement } from '../../hooks/useDeviceManagement'
import { usePlatformDelivery } from '../../hooks/usePlatformDelivery'
import { useShopInfo } from '../../hooks/useShopInfo'
import { useOrderSubmit } from '../../hooks/useOrderSubmit'

// Tab 缓存管理
const TAB_CACHE_KEY = 'recycle_order_current_tab'
const { currentTab, switchTab } = useTabCache(TAB_CACHE_KEY, 0)

// 表单管理
const { form, rules, formRef, resetForm } = useOrderForm(currentTab)

// 计算属性确保 count 是数字类型
const deviceCount = computed({
  get: () => form.value.count,
  set: (val) => {  form.value.count = val.value  }
})

// 设备管理
const { phoneList, addDevices, scanIMEI } = useDeviceManagement()

// 平台快递管理
const {
  enablePlatformDelivery,
  platformDeliveryForm,
  pickupTimeOptions,
  goToSelectAddress,
  fillAddressFromSelected,
  handlePlatformDeliveryToggle,
  resetPlatformDeliveryForm
} = usePlatformDelivery()

// 商家信息管理
const { shopInfo, fetchShopInfo, copyShopInfo, openLocation } = useShopInfo()

// 订单提交
const { submitOrder } = useOrderSubmit()

// 协议勾选
const isAgreeRecycle = ref(false)

// 设备弹窗状态
const showDeviceModal = ref(false)
const deviceModalMode = ref<'single' | 'batch'>('batch')

// 监听 Tab 切换
watch(currentTab, (newVal) => {
  form.value.delivery_type = newVal === 0 ? 1 : 2
  switchTab(newVal)

  // 切换到自送时清空快递单号
  if (newVal === 1) {
    form.value.express_no = ''
  }
})

// 跳转到订单列表
const toOrderList = () => {
  uni.navigateTo({
    url: '/addon/recycle/pages/order/list'
  })
}

// 打开单台设备添加弹窗
const openSingleDeviceModal = () => {
  deviceModalMode.value = 'single'
  showDeviceModal.value = true
}

// 打开批量设备添加弹窗
const openBatchDeviceModal = () => {
  deviceModalMode.value = 'batch'
  showDeviceModal.value = true
}

// 处理设备确认添加
const handleDeviceConfirm = (devices: any[]) => {
  addDevices(devices)
  form.value.count = phoneList.value.length
}

// 扫描快递单号
const scanCode = () => {
  uni.scanCode({
    onlyFromCamera: true,
    success: res => {
      if (res.errMsg === 'scanCode:ok') {
        form.value.express_no = res.result
      } else {
        uni.showToast({ title: res.errMsg, icon: 'none' })
      }
    }
  })
}

// 处理平台快递切换
const handlePlatformDeliveryChange = async (value: boolean) => {
  enablePlatformDelivery.value = value
  if (value) {
    await handlePlatformDeliveryToggle()
  }
}

// 提交订单
const handleSubmitOrder = async () => {
  await submitOrder({
    form: form.value,
    phoneList: phoneList.value,
    currentTab: currentTab.value,
    usePlatformDelivery: enablePlatformDelivery.value,
    platformDeliveryForm: platformDeliveryForm.value,
    isAgreeRecycle: isAgreeRecycle.value,
    formRef: formRef.value,
    onSuccess: () => {
      // 清空表单
      resetForm()
      phoneList.value = []
      resetPlatformDeliveryForm()
      isAgreeRecycle.value = false
    }
  })
}

// 检查收款信息
const checkPaymentInfo = async () => {
  try {
    const res = await getPaymentList()

    if (res.data.length < 2) {
      uni.showModal({
        title: '提示',
        content: '您尚未输入个人信息，建议至少添加2种收款方式及完善个人信息，以便回收完成后能及时收到款项。',
        confirmText: '立即设置',
        cancelText: '稍后设置',
        success: function(res) {
          if (res.confirm) {
            uni.navigateTo({
              url: '/addon/recycle/pages/payment/index'
            })
          } else {
            uni.showToast({
              title: '请记得及时完善收款信息，避免影响回收款项到账',
              icon: 'none',
              duration: 3000
            })
          }
        }
      })
    }
  } catch (error) {
    console.error('获取收款信息失败：', error)
  }
}

// 页面显示时的处理
onShow(async () => {
  // 请求订阅相关消息通知
  await useSubscribeMessage().request('recycle_order_sign,recycle_order_agree,recycle_order_pay')

  // 检查收款信息
  await checkPaymentInfo()
  // 处理地址选择回调
  const selectAddressCallback = uni.getStorageSync('selectAddressCallback')
  if (selectAddressCallback && selectAddressCallback.address_id) {
    try {
      const res: any = await getAddressList({})
      const selectedAddress = res.data.find((addr: any) => addr.id === selectAddressCallback.address_id)

      if (selectedAddress) {
        fillAddressFromSelected(selectedAddress)
      }
    } catch (error) {
      console.error('获取地址详情失败：', error)
    }

    // 清除回调数据
    uni.removeStorageSync('selectAddressCallback')
  }
})

// 页面挂载时获取商家信息
fetchShopInfo()
</script>

<style scoped lang="scss">
.label {
  font-size: 14px;
  color: #374151;
}

.input-wrapper {
  width: 100%;
  overflow: hidden;
}

:deep(.up-button--primary) {
  background: linear-gradient(to right, #8C7575, #5F758A);
  border: none;
}
</style>
