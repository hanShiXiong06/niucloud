<template>
  <u-popup
    :show="show"
    mode="bottom"
    :round="20"
    @close="handleClose"
    :safeAreaInsetBottom="true"
  >
    <view class="address-popup">
      <!-- 标题栏 -->
      <view class="popup-header">
        <text class="header-title">选择寄件地址</text>
        <view class="close-btn" @click="handleClose">
          <up-icon name="close" size="20" color="#64748b"></up-icon>
        </view>
      </view>

      <!-- 地址列表 -->
      <scroll-view scroll-y class="address-list" v-if="!loading && addressList.length">
        <view
          v-for="(item, index) in addressList"
          :key="item.id"
          class="address-item"
          @click="selectAddress(item)"
        >
          <view class="address-content">
            <view class="user-info">
              <view class="user-avatar">
                <up-icon name="account-fill" size="16" color="#fff"></up-icon>
              </view>
              <view class="user-details">
                <view class="name-phone">
                  <text class="name">{{ item.name }}</text>
                  <text class="phone">{{ item.mobile }}</text>
                </view>
                <view class="address-text">
                  <up-icon name="map-fill" size="12" color="#94a3b8"></up-icon>
                  <text>{{ item.full_address }}</text>
                </view>
              </view>
            </view>
            <view v-if="item.is_default" class="default-badge">
              <text>默认</text>
            </view>
          </view>
          <view class="divider" v-if="index < addressList.length - 1"></view>
        </view>
      </scroll-view>

      <!-- 空状态 -->
      <view v-if="!loading && !addressList.length" class="empty-state">
        <up-icon name="map" size="60" color="#cbd5e1"></up-icon>
        <text class="empty-text">暂无地址</text>
      </view>

      <!-- 加载状态 -->
      <view v-if="loading" class="loading-state">
        <view class="loading-spinner"></view>
        <text class="loading-text">加载中...</text>
      </view>

      <!-- 添加地址按钮 -->
      <view class="add-address-btn" @click="showEditPopup = true">
        <up-icon name="plus" size="18" color="#3b82f6"></up-icon>
        <text>添加新地址</text>
      </view>
    </view>
  </u-popup>

  <!-- 地址编辑弹窗 -->
  <AddressEditPopup
    :show="showEditPopup"
    @update:show="showEditPopup = $event"
    @success="handleAddressAdded"
  />
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { getAddressList } from '@/app/api/member'
import AddressEditPopup from './AddressEditPopup.vue'

interface Props {
  show: boolean
}

interface AddressItem {
  id: number
  name: string
  mobile: string
  full_address: string
  is_default: number
  province: string
  city: string
  district: string
  address: string
  [key: string]: any
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:show': [value: boolean]
  'select': [address: AddressItem]
}>()

const loading = ref(false)
const addressList = ref<AddressItem[]>([])
const showEditPopup = ref(false)

// 加载地址列表
const loadAddressList = async () => {
  try {
    loading.value = true
    const res: any = await getAddressList({})
    if (res.code === 1 && res.data) {
      addressList.value = res.data
    }
  } catch (error) {
    console.error('加载地址列表失败：', error)
    uni.showToast({
      title: '加载地址失败',
      icon: 'none'
    })
  } finally {
    loading.value = false
  }
}

// 监听弹窗显示状态，显示时加载地址列表
watch(() => props.show, (newVal) => {
  if (newVal) {
    loadAddressList()
  }
})

// 选择地址
const selectAddress = (address: AddressItem) => {
  emit('select', address)
  handleClose()
}

// 关闭弹窗
const handleClose = () => {
  emit('update:show', false)
}

// 地址添加成功后刷新列表
const handleAddressAdded = () => {
  loadAddressList()
}
</script>

<style scoped lang="scss">
.address-popup {
  background: #fff;
  max-height: 70vh;
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

  .address-list {
    flex: 1;
    overflow-y: auto;
    padding: 12px 0;

    .address-item {
      cursor: pointer;
      transition: background 0.2s;

      &:active {
        background: #f8fafc;
      }

      .address-content {
        padding: 16px 20px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;

        .user-info {
          flex: 1;
          display: flex;
          gap: 12px;

          .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
          }

          .user-details {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;

            .name-phone {
              display: flex;
              align-items: center;
              gap: 12px;

              .name {
                font-size: 15px;
                font-weight: 500;
                color: #1e293b;
              }

              .phone {
                font-size: 14px;
                color: #64748b;
              }
            }

            .address-text {
              display: flex;
              align-items: flex-start;
              gap: 6px;

              text {
                font-size: 13px;
                color: #64748b;
                line-height: 1.5;
              }
            }
          }
        }

        .default-badge {
          padding: 4px 10px;
          background: linear-gradient(135deg, #3b82f6, #2563eb);
          border-radius: 12px;
          flex-shrink: 0;

          text {
            font-size: 11px;
            color: #fff;
            font-weight: 500;
          }
        }
      }

      .divider {
        height: 1px;
        background: #f1f5f9;
        margin: 0 20px;
      }
    }
  }

  .empty-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    gap: 16px;

    .empty-text {
      font-size: 14px;
      color: #94a3b8;
    }
  }

  .loading-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    gap: 16px;

    .loading-spinner {
      width: 40px;
      height: 40px;
      border: 3px solid #f3f3f3;
      border-top-color: #3b82f6;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }

    .loading-text {
      font-size: 14px;
      color: #64748b;
    }
  }

  @keyframes spin {
    0% {
      transform: rotate(0deg);
    }
    100% {
      transform: rotate(360deg);
    }
  }

  .add-address-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 16px 20px;
    border-top: 1px solid #f1f5f9;
    cursor: pointer;
    transition: background 0.2s;

    &:active {
      background: #f8fafc;
    }

    text {
      font-size: 15px;
      color: #3b82f6;
      font-weight: 500;
    }
  }
}
</style>
