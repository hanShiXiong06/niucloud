<template>
    <view class="page-container" :style="themeColor()" v-if="config">
        <!-- 配置内容区域 -->
        <view class="content-area">
            <!-- 分佣和QPS合并显示 -->
            <view class="config-item">
                <view class="label">服务信息</view>
                <view class="info-row">
                    <view class="info-item">
                        <view class="info-label">分佣比例:</view>
                        <view class="info-value">{{ config.rate }}%</view>
                    </view>
                    <view class="info-item">
                        <view class="info-label">QPS限制:</view>
                        <view class="info-value">{{ config.qps==0? '不限': config.qps}}</view>
                    </view>
                </view>
                <view class="desc">按照实付金额的{{ config.rate }}%分佣</view>
            </view>
            
            <view class="config-item">
                <view class="label">pub_id:</view>
                <view class="value-container">
                    <view class="value">{{ config.id }}</view>
                    <view class="copy-btn" @click="copyText(config.id, 'ID')">复制</view>
                </view>
            </view>
            <view class="config-item">
                <view class="label">api_key:</view>
                <view class="value-container">
                    <view class="value">{{ config.api_key }}</view>
                    <view class="copy-btn" @click="copyText(config.api_key, 'API_KEY')">复制</view>
                </view>
            </view>
            <view class="config-item">
                <view class="label">api_secret:</view>
                <view class="value-container">
                    <view class="value">{{ config.api_secret }}</view>
                    <view class="copy-btn" @click="copyText(config.api_secret, 'API_SECRET')">复制</view>
                </view>
            </view>
            <view class="one-click-copy">
                <view class="copy-all-btn" @click="copyAllConfig">一键复制全部配置</view>
            </view>
            <!-- 链接信息区域 -->
            <view v-if="link" class="link-section">
                <view class="section-title">链接信息</view>
                
                <view class="config-item">
                    <view class="label">原始ID:</view>
                    <view class="value-container">
                        <view class="value">{{ link.app_id }}</view>
                        <view class="copy-btn" @click="copyText(link.app_id, '原始ID')">复制</view>
                    </view>
                </view>
                
                <view class="config-item">
                    <view class="label">App ID:</view>
                    <view class="value-container">
                        <view class="value">{{ link.app_id }}</view>
                        <view class="copy-btn" @click="copyText(link.app_id, 'App ID')">复制</view>
                    </view>
                </view>
                
                <view class="config-item">
                    <view class="label">页面路径:</view>
                    <view class="value-container">
                        <view class="value">{{ link.page }}</view>
                        <view class="copy-btn" @click="copyText(link.page, '页面路径')">复制</view>
                    </view>
                </view>
                
                <view class="config-item">
                    <view class="label">完整URL:</view>
                    <view class="value-container">
                        <view class="value url-text">{{ link.url }}</view>
                        <view class="copy-btn" @click="copyText(link.url, '完整URL')">复制</view>
                    </view>
                </view>
                
                <!-- 一键复制链接信息 -->
                <view class="one-click-copy">
                    <view class="copy-all-btn secondary" @click="copyAllLinkInfo">一键复制链接信息</view>
                </view>
            </view>
        </view>
        
        <!-- 底部操作按钮 -->
        <view class="bottom-actions">
            <view class="action-buttons">
                <view class="action-btn" @click="restKeyFn">重置密钥</view>
                <view class="action-btn" @click="redirect({url:'/addon/kd_api/pages/order'})">订单列表</view>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { getConfig,restKey,getLink } from '@/addon/kd_api/api/common'
import { onShow } from '@dcloudio/uni-app'
import {redirect} from '@/utils/common'
const link=ref()
getLink().then(res => {
    link.value = res.data
})
const restKeyFn = () => {
    restKey().then(res => {
        uni.$u.toast('重置成功')
        getConfigFn()
    })
}

const copyText = (text: string, type: string) => {
    uni.setClipboardData({
        data: text,
        success: () => {
            uni.$u.toast(`${type}已复制`)
        },
        fail: () => {
            uni.$u.toast('复制失败')
        }
    })
}

const copyAllConfig = () => {
    const configText = `快递API配置信息：
pub_id: ${config.value.id}
api_key: ${config.value.api_key}
api_secret: ${config.value.api_secret}
分佣比例: ${config.value.rate}%
QPS限制: ${config.value.qps == 0 ? '不限' : config.value.qps}

备注：按照实付金额的${config.value.rate}%分佣`

    uni.setClipboardData({
        data: configText,
        success: () => {
            uni.$u.toast('全部配置已复制')
        },
        fail: () => {
            uni.$u.toast('复制失败')
        }
    })
}

const copyAllLinkInfo = () => {
    if (!link.value) return
    
    const linkText = `链接信息：
原始ID: ${link.value.app_id}
App ID: ${link.value.app_id}
页面路径: ${link.value.page}
完整URL: ${link.value.url}`

    uni.setClipboardData({
        data: linkText,
        success: () => {
            uni.$u.toast('链接信息已复制')
        },
        fail: () => {
            uni.$u.toast('复制失败')
        }
    })
}
const config = ref()
const getConfigFn = () => {
    getConfig().then(res => {
        if (res.data.is_open == 0) {
            uni.$u.toast('请联系管理员获取权限')
            uni.navigateBack()
        }
        config.value = res.data.data
    })
}
onShow(() => {
    getConfigFn()
})
</script>
<style lang="scss" scoped>
.page-container {
    min-height: 100vh;
    background: #f5f5f5;
    display: flex;
    flex-direction: column;
}

.content-area {
    flex: 1;
    padding: 12px 12px 0 12px;
}

.bottom-actions {
    position: sticky;
    bottom: 0;
    background: #ffffff;
    padding: 12px 12px calc(12px + env(safe-area-inset-bottom)) 12px;
    border-top: 1px solid #e9ecef;
    box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.1);
    
    .action-buttons {
        display: flex;
        gap: 10px;

        .action-btn {
            flex: 1;
            background: linear-gradient(135deg, #007aff, #0056cc);
            color: white;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0, 122, 255, 0.3);

            &:active {
                transform: translateY(1px);
                box-shadow: 0 1px 3px rgba(0, 122, 255, 0.3);
            }
        }
    }
}

.config-item {
    background: white;
    border-radius: 8px;
    padding: 14px;
    margin-bottom: 10px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
    border: 1px solid #f0f0f0;

    .label {
        font-size: 14px;
        color: #666;
        margin-bottom: 8px;
        font-weight: 600;
    }

    .info-row {
        display: flex;
        gap: 12px;
        margin-bottom: 6px;

        .info-item {
            flex: 1;
            background: #f8f9fa;
            padding: 8px 10px;
            border-radius: 6px;
            border: 1px solid #e9ecef;

            .info-label {
                font-size: 11px;
                color: #666;
                margin-bottom: 2px;
            }

            .info-value {
                font-size: 15px;
                color: #333;
                font-weight: 600;
            }
        }
    }

    .value-container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #f8f9fa;
        padding: 8px 10px;
        border-radius: 6px;
        border: 1px solid #e9ecef;
        
        .value {
            flex: 1;
            font-size: 13px;
            color: #333;
            word-break: break-all;
            margin-right: 8px;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
        }

        .copy-btn {
            background: linear-gradient(135deg, #007aff, #0056cc);
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 11px;
            cursor: pointer;
            white-space: nowrap;
            box-shadow: 0 1px 3px rgba(0, 122, 255, 0.3);
            
            &:active {
                transform: translateY(1px);
                box-shadow: 0 1px 2px rgba(0, 122, 255, 0.3);
            }
        }
    }

    .value {
        font-size: 15px;
        color: #333;
        font-weight: 500;
    }

    .desc {
        font-size: 12px;
        color: #999;
        margin-top: 6px;
        padding: 6px 10px;
        background: #f8f9fa;
        border-radius: 4px;
    }
}

.one-click-copy {
    margin: 12px 0;
    
    .copy-all-btn {
        background: linear-gradient(135deg, #ff6b6b, #ee5a52);
        color: white;
        padding: 12px;
        border-radius: 8px;
        text-align: center;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(255, 107, 107, 0.3);
        position: relative;
        overflow: hidden;
        
        &.secondary {
            background: linear-gradient(135deg, #28a745, #20c997);
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
            
            &:active {
                box-shadow: 0 1px 4px rgba(40, 167, 69, 0.3);
            }
        }
        
        &::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        &:active {
            transform: translateY(1px);
            box-shadow: 0 1px 4px rgba(255, 107, 107, 0.3);
            
            &::before {
                left: 100%;
            }
        }
    }
}

.link-section {
    margin-top: 20px;
    
    .section-title {
        font-size: 16px;
        font-weight: 600;
        color: #333;
        margin-bottom: 12px;
        padding: 0 4px;
        position: relative;
        
        &::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 16px;
            background: linear-gradient(135deg, #28a745, #20c997);
            border-radius: 2px;
            margin-right: 8px;
        }
        
        padding-left: 12px;
    }
    
    .url-text {
        font-size: 12px !important;
        line-height: 1.4;
        word-break: break-all;
        color: #007aff !important;
    }
}


</style>
