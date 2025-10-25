<template>
  <div class="warranty-info-compact">
    <!-- 设备基本信息 - 水平紧凑布局 -->
    <div class="device-summary">
      <div class="device-info">
        <img v-if="warrantyData.image" :src="warrantyData.image" :alt="warrantyData.model" class="device-thumb" />
        <div class="device-text">
          <h4 class="device-title">{{ warrantyData.model }}  <span v-if="warrantyData.capacity" class="detail-item">
           {{ warrantyData.capacity }}
            </span>

            <span v-if="warrantyData.color" class="detail-item">
            {{ warrantyData.color }}
            </span>
        </h4>
          <div class="device-tags">
            <span class="model-tag">{{ warrantyData.modelnumber }}</span>
            <span class="id-tag">{{ warrantyData.identifier }}</span>
          </div>
          <div class="device-sn">序列号: <strong>{{ warrantyData.sn }}</strong></div>
        </div>
      </div>
      <div class="activation-badge">
        <el-icon class="status-icon" :class="warrantyData.activated ? 'activated' : 'not-activated'">
          <CircleCheck v-if="warrantyData.activated" />
          <CircleClose v-else />
        </el-icon>
        <span>{{ warrantyData.activated ? '已激活' : '未激活' }}</span>
      </div>
      <div class="additional-info">
      <div class="info-header">
        <el-icon><InfoFilled /></el-icon>
        <span>其他信息</span>
      </div>
      <div class="info-details">
        <span class="detail-item">
          <strong>查询来源:</strong> {{ warrantyData.source }}
        </span>

      </div>
    </div>
    </div>

    <!-- 核心信息网格 - 2x2 紧凑布局 -->
    <div class="info-grid">
      <!-- 保修状态 -->
      <div class="info-item coverage-item">
        <div class="item-header">
          <el-icon><Headset /></el-icon>
          <span>保修状态</span>
        </div>
        <div class="item-content">
          <div :class="warrantyData.coverage.status=='Out Of Warranty' ? 'status-main bg-red-300 text-red-900' :'status-main bg-green-300 text-green-900'">
            {{ warrantyData.coverage.status=='Out Of Warranty' ? '过保' : '在保' }}
          </div>
          <div v-if="warrantyData.coverage.status!=='Out Of Warranty'" class="status-sub">{{ warrantyData.coverage.description }}</div>
          <div v-if="warrantyData.coverage.status!=='Out Of Warranty'" class="status-date">到期: {{ formatDate(warrantyData.coverage.date) }}</div>
        </div>
      </div>

      <!-- AppleCare -->
      <div class="info-item applecare-item">
        <div class="item-header">
          <el-icon><Star /></el-icon>
          <span>AppleCare</span>
        </div>
        <div class="item-content">
          <el-tag :type="warrantyData.applecare ? 'success' : 'info'" size="large">
            {{ warrantyData.applecare ? '已购买' : '未购买' }}
          </el-tag>
          <div class="sub-info">
            <span>购买资格: </span>
            <el-tag :type="warrantyData['applecare-eligible'] === 'Y' ? 'success' : 'warning'" size="small">
              {{ warrantyData['applecare-eligible'] === 'Y' ? '符合条件' : '不符合条件' }}
            </el-tag>
          </div>
        </div>
      </div>

      <!-- 购买信息 -->
      <div class="info-item purchase-item">
        <div class="item-header">
          <el-icon><ShoppingCart /></el-icon>
          <span>购买信息</span>
        </div>
        <div class="item-content">
          <div class="info-row">
            <span>购买时间:</span>
            <strong>{{ warrantyData.purchase.date || '未知' }}</strong>
          </div>
          <div class="info-row">
            <span>验证状态:</span>
            <el-tag :type="warrantyData.purchase.validated ? 'success' : 'danger'" size="small">
              {{ warrantyData.purchase.validated ? '已验证' : '未验证' }}
            </el-tag>
          </div>
          <div class="info-row">
            <span>注册状态:</span>
            <el-tag :type="warrantyData.registered === 'Y' ? 'success' : 'info'" size="small">
              {{ warrantyData.registered === 'Y' ? '已注册' : '未注册' }}
            </el-tag>
          </div>
        </div>
      </div>

      <!-- 设备状态 -->
      <div class="info-item device-item">
        <div class="item-header">
          <el-icon><Monitor /></el-icon>
          <span>设备状态</span>
        </div>
        <div class="item-content">
          <div class="status-tags">
            <div class="tag-row">
              <span>技术支持:</span>
              <el-tag :type="warrantyData.support === 'Expired' ? 'danger' : 'success'" size="small">
                {{ warrantyData.support === 'Expired' ? '已过期' : '有效' }}
              </el-tag>
            </div>
            <div class="tag-row">
              <span>是否官换:</span>
              <el-tag :type="warrantyData.replaced ? 'warning' : 'success'" size="small">
                {{ warrantyData.replaced ? '是' : '否' }}
              </el-tag>
            </div>
            <div class="tag-row">
              <span>是否官方维修中:</span>
              <el-tag :type="warrantyData.maintenance ? 'warning' : 'success'" size="small">
                {{ warrantyData.maintenance ? '是' : '否' }}
              </el-tag>
            </div>
          </div>
        </div>
      </div>
    </div>
    
  </div>
</template>

<script setup lang="ts">
import { defineProps } from 'vue'
import { 
  Star, ShoppingCart, Monitor, InfoFilled,
  CircleCheck, CircleClose, Headset
} from '@element-plus/icons-vue'

interface WarrantyData {
  sn: string
  model: string
  capacity: string
  color: string
  modelnumber: string
  identifier: string
  activated: boolean
  image: string
  fmi: string
  locked: string
  purchase: {
    date: string
    validated: boolean
  }
  activation: {
    date: string
  }
  coverage: {
    status: string
    description: string
    date: string
    'days-remaining': number
  }
  support: string
  applecare: boolean
  'applecare-eligible': string
  brightstar: string
  'pre-activated': boolean
  registered: string
  loaner: string
  replaced: boolean
  manufacture: {
    date: string
  }
  manufacturer: string
  source: string
  balance: number
  maintenance: boolean
}

const props = defineProps<{
  warrantyData: WarrantyData
}>()

// 获取保修状态样式类
const getCoverageStatusClass = () => {
  const status = props.warrantyData.coverage.status.toLowerCase()
  if (status.includes('warranty') && !status.includes('out')) {
    return 'in-warranty'
  } else if (status.includes('out') || status.includes('expired')) {
    return 'out-warranty'
  } else {
    return 'unknown-warranty'
  }
}

// 格式化日期
const formatDate = (date: string) => {
  if (!date || date === 'Expired') {
    return date || '未知'
  }
  return date
}
</script>

<style lang="scss" scoped>
.warranty-info-compact {
  padding: 16px;
  background: #f8f9fa;
  border-radius: 8px;
  
  // 设备概览
  .device-summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: white;
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 16px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    
    .device-info {
      display: flex;
      align-items: center;
      gap: 12px;
      
      .device-thumb {
        width: 60px;
        height: 60px;
        object-fit: contain;
        border-radius: 6px;
        background: #f5f5f5;
        padding: 6px;
      }
      
      .device-text {
        .device-title {
          font-size: 18px;
          font-weight: 700;
          color: #1f2937;
          margin: 0 0 6px 0;
          max-width: 300px;
        }
        
        .device-tags {
          display: flex;
          gap: 6px;
          margin-bottom: 4px;
          
          .model-tag, .id-tag {
            background: #e5e7eb;
            color: #374151;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
          }
          
          .id-tag {
            background: #dbeafe;
            color: #1d4ed8;
          }
        }
        
        .device-sn {
          font-size: 14px;
          color: #6b7280;
          font-family: monospace;
        }
      }
    }
    
    .activation-badge {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 4px;
      
      .status-icon {
        font-size: 24px;
        
        &.activated {
          color: #10b981;
        }
        
        &.not-activated {
          color: #ef4444;
        }
      }
      
      span {
        font-size: 12px;
        font-weight: 600;
      }
    }
  }
  
  // 信息网格 - 2x2布局
  .info-grid {
    display: grid;
    grid-template-columns:  1fr 1fr 1fr 1fr;
    gap: 12px;
    margin-bottom: 16px;
    
    .info-item {
      background: white;
      border-radius: 8px;
      padding: 12px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      
      .item-header {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 10px;
        font-size: 14px;
        font-weight: 600;
        color: #374151;
        
        .el-icon {
          font-size: 16px;
        }
      }
      
      .item-content {
        .status-main {
          font-size: 16px;
          font-weight: 700;
          margin-bottom: 4px;
          padding: 6px 10px;
          border-radius: 6px;
          text-align: center;
          
          &.in-warranty {
            background: #dcfce7;
            color: #166534;
          }
          
          &.out-warranty {
            background: #fef2f2;
            color: #dc2626;
          }
          
          &.unknown-warranty {
            background: #f3f4f6;
            color: #6b7280;
          }
        }
        
        .status-sub {
          font-size: 12px;
          color: #6b7280;
          text-align: center;
          margin-bottom: 6px;
        }
        
        .status-date {
          font-size: 12px;
          color: #374151;
          text-align: center;
        }
        
        .sub-info {
          margin-top: 8px;
          font-size: 12px;
          color: #6b7280;
          
          span {
            margin-right: 4px;
          }
        }
        
        .info-row {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 6px;
          font-size: 13px;
          
          &:last-child {
            margin-bottom: 0;
          }
          
          span:first-child {
            color: #6b7280;
          }
        }
        
        .status-tags {
          .tag-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
            font-size: 12px;
            
            &:last-child {
              margin-bottom: 0;
            }
            
            span:first-child {
              color: #6b7280;
            }
          }
        }
      }
    }
  }
  
  // 其他信息
  .additional-info {
    background: white;
    border-radius: 8px;
    padding: 12px 16px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    
    .info-header {
      display: flex;
      align-items: center;
      gap: 6px;
      margin-bottom: 10px;
      font-size: 14px;
      font-weight: 600;
      color: #374151;
      
      .el-icon {
        font-size: 16px;
        color: #6366f1;
      }
    }
    
    .info-details {
      display: flex;
      flex-wrap: wrap;
      gap: 16px;
      
      .detail-item {
        font-size: 13px;
        color: #374151;
        white-space: nowrap;
        
        strong {
          color: #6b7280;
          font-weight: 500;
          margin-right: 4px;
        }
        
        .highlight {
          color: #f59e0b;
          font-weight: 600;
        }
      }
    }
  }
}

// 响应式 - 小屏幕下改为单列
@media (max-width: 768px) {
  .warranty-info-compact {
    .device-summary {
      flex-direction: column;
      gap: 12px;
      text-align: center;
    }
    
    .info-grid {
      grid-template-columns: 1fr;
    }
    
    .additional-info .info-details {
      flex-direction: column;
      gap: 8px;
    }
  }
}
</style> 