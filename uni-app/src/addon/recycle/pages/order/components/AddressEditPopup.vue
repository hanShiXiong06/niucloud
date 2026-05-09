<template>
  <u-popup
    :show="show"
    mode="bottom"
    :round="20"
    @close="handleClose"
    :safeAreaInsetBottom="true"
  >
    <view class="address-edit-popup">
      <!-- 标题栏 -->
      <view class="popup-header">
        <text class="header-title">添加地址</text>
        <view class="close-btn" @click="handleClose">
          <up-icon name="close" size="20" color="#64748b"></up-icon>
        </view>
      </view>

      <!-- 表单内容 -->
      <scroll-view scroll-y class="form-content">
        <u-form :model="formData" :rules="rules" ref="formRef" labelPosition="left">
          <!-- 姓名 -->
          <view class="form-item">
            <u-form-item label="姓名" prop="name" labelWidth="80">
              <u-input
                v-model="formData.name"
                placeholder="请输入收货人姓名"
                border="none"
                clearable
                maxlength="25"
              />
            </u-form-item>
          </view>

          <!-- 手机号 -->
          <view class="form-item">
            <u-form-item label="手机号" prop="mobile" labelWidth="80">
              <u-input
                v-model="formData.mobile"
                placeholder="请输入手机号"
                border="none"
                clearable
                maxlength="11"
                type="number"
              />
            </u-form-item>
          </view>

          <!-- 省市区 -->
          <view class="form-item">
            <u-form-item label="所在地区" prop="area" labelWidth="80">
              <view class="area-selector" @click="openAreaSelect">
                <text v-if="formData.area" class="area-text">{{ formData.area }}</text>
                <text v-else class="placeholder-text">请选择省市区</text>
                <up-icon name="arrow-right" size="14" color="#94a3b8"></up-icon>
              </view>
            </u-form-item>
          </view>

          <!-- 详细地址 -->
          <view class="form-item">
            <u-form-item label="详细地址" prop="address" labelWidth="80">
              <u-input
                v-model="formData.address"
                placeholder="请输入详细地址"
                border="none"
                clearable
                maxlength="120"
                type="textarea"
                :autoHeight="true"
              />
            </u-form-item>
          </view>

          <!-- 设为默认 -->
          <view class="form-item default-item">
            <u-form-item label="设为默认" :borderBottom="false" labelWidth="80">
              <u-switch
                v-model="formData.is_default"
                size="20"
                :activeValue="1"
                :inactiveValue="0"
                activeColor="#3b82f6"
              />
            </u-form-item>
          </view>
        </u-form>
      </scroll-view>

      <!-- 保存按钮 -->
      <view class="save-btn-wrapper">
        <up-button
          type="primary"
          :loading="saving"
          :disabled="saving"
          @click="handleSave"
          text="保存"
          customStyle="background: linear-gradient(135deg, #3b82f6, #2563eb); border: none; height: 44px; border-radius: 22px;"
        ></up-button>
      </view>
    </view>
  </u-popup>

  <!-- 地区选择器 -->
  <area-select ref="areaRef" @complete="handleAreaComplete" />
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { addAddress } from '@/app/api/member'
import AreaSelect from '@/components/area-select/area-select.vue'

interface Props {
  show: boolean
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:show': [value: boolean]
  'success': []
}>()

const formRef: any = ref(null)
const areaRef: any = ref(null)
const saving = ref(false)

const formData = reactive({
  name: '',
  mobile: '',
  province_id: 0,
  city_id: 0,
  district_id: 0,
  area: '',
  address: '',
  full_address: '',
  is_default: 0
})

// 表单验证规则
const rules = computed(() => {
  return {
    name: {
      type: 'string',
      required: true,
      message: '请输入收货人姓名',
      trigger: ['blur', 'change']
    },
    mobile: [
      {
        type: 'string',
        required: true,
        message: '请输入手机号',
        trigger: ['blur', 'change']
      },
      {
        validator(_rule: any, value: any, callback: any) {
          const mobile = /^1[3-9]\d{9}$/
          if (!mobile.test(value)) {
            callback(new Error('请输入正确的手机号'))
          } else {
            callback()
          }
        }
      }
    ],
    area: {
      validator() {
        return formData.area !== '' && formData.area !== null && formData.area !== undefined
      },
      message: '请选择省市区'
    },
    address: {
      type: 'string',
      required: true,
      message: '请输入详细地址',
      trigger: ['blur', 'change']
    }
  }
})

// 打开地区选择器
const openAreaSelect = () => {
  areaRef.value.open()
}

// 地区选择完成
const handleAreaComplete = (event: any) => {
  formData.province_id = event.province?.id || 0
  formData.city_id = event.city?.id || 0
  formData.district_id = event.district?.id || 0
  formData.area = `${event.province?.name || ''}${event.city?.name || ''}${event.district?.name || ''}`
}

// 保存地址
const handleSave = () => {
  formRef.value.validate().then(async () => {
    if (saving.value) return

    try {
      saving.value = true
      formData.full_address = formData.area + formData.address

      await addAddress(formData)

      uni.showToast({
        title: '添加成功',
        icon: 'success'
      })

      // 重置表单
      resetForm()

      // 通知父组件刷新列表
      emit('success')

      // 关闭弹窗
      handleClose()
    } catch (error) {
      console.error('保存地址失败：', error)
    } finally {
      saving.value = false
    }
  })
}

// 重置表单
const resetForm = () => {
  Object.assign(formData, {
    name: '',
    mobile: '',
    province_id: 0,
    city_id: 0,
    district_id: 0,
    area: '',
    address: '',
    full_address: '',
    is_default: 0
  })
}

// 关闭弹窗
const handleClose = () => {
  emit('update:show', false)
}
</script>

<style scoped lang="scss">
.address-edit-popup {
  background: #fff;
  max-height: 80vh;
  display: flex;
  flex-direction: column;

  .popup-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;

    .header-title {
      font-size: 16px;
      font-weight: 600;
      color: #1e293b;
    }

    .close-btn {
      padding: 4px;
      cursor: pointer;

      &:active {
        opacity: 0.6;
      }
    }
  }

  .form-content {
    flex: 1;
    overflow-y: auto;
    padding: 12px 20px;
    box-sizing: border-box;

    .form-item {
      margin-bottom: 16px;
      background: #f8fafc;
      border-radius: 8px;
      padding: 12px;

      .area-selector {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        min-height: 40px;

        .area-text {
          font-size: 14px;
          color: #1e293b;
          flex: 1;
        }

        .placeholder-text {
          font-size: 14px;
          color: #94a3b8;
          flex: 1;
        }
      }

      &.default-item {
        background: transparent;
        padding: 0;
      }
    }
  }

  .save-btn-wrapper {
    padding: 16px 20px;
    border-top: 1px solid #f1f5f9;
  }
}
</style>