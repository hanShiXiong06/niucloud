<template>
    <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
    <view class="game-publish" v-if="isFeatureEnabled">
        <view class="form-section">
            <view class="section-title">游戏信息</view>
            <view class="form-item">
                <text class="label required">游戏类型</text>
                <view class="select-grid">
                    <view class="select-item" :class="{ active: form.game_type === item.value }" v-for="item in gameTypes" :key="item.value" @click="form.game_type = item.value">
                        {{ item.label }}
                    </view>
                </view>
            </view>
            <view class="form-item">
                <text class="label required">服务类型</text>
                <view class="select-grid">
                    <view class="select-item" :class="{ active: form.service_type === item.value }" v-for="item in serviceTypes" :key="item.value" @click="form.service_type = item.value">
                        {{ item.label }}
                    </view>
                </view>
            </view>
            <view class="form-item">
                <text class="label">游戏昵称/ID</text>
                <input v-model="form.game_name" placeholder="输入游戏内昵称或ID" />
            </view>
            <view class="form-item">
                <text class="label">段位/等级</text>
                <input v-model="form.rank_level" placeholder="如：王者、全球精英等" />
            </view>
        </view>

        <view class="form-section">
            <view class="section-title">陪玩信息</view>
            <view class="form-item">
                <text class="label required">标题</text>
                <input v-model="form.title" placeholder="一句话描述你的服务" maxlength="50" />
            </view>
            <view class="form-item">
                <text class="label">详细描述</text>
                <textarea v-model="form.content" placeholder="描述你的游戏特长、服务内容等" maxlength="500" :auto-height="true" />
            </view>
            <view class="form-item">
                <text class="label required">价格</text>
                <view class="price-input">
                    <text class="yen">¥</text>
                    <input v-model="form.price" type="digit" placeholder="0.00" />
                    <text class="unit-text">/{{ form.unit }}</text>
                </view>
            </view>
            <view class="form-item">
                <text class="label">计价单位</text>
                <view class="select-grid small">
                    <view class="select-item" :class="{ active: form.unit === u }" v-for="u in ['小时', '局', '把', '天']" :key="u" @click="form.unit = u">{{ u }}</view>
                </view>
            </view>
        </view>

        <view class="form-section">
            <view class="section-title">个人设置</view>
            <view class="form-item row">
                <text class="label">语音陪玩</text>
                <switch :checked="form.voice_chat === 1" @change="form.voice_chat = $event.detail.value ? 1 : 0" color="#c0fe95" />
            </view>
            <view class="form-item">
                <text class="label">在线时间</text>
                <input v-model="form.online_time" placeholder="如：每天20:00-23:00" />
            </view>
            <view class="form-item">
                <text class="label">展示图片</text>
                <xy-upload v-model="imageStr" :maxCount="6" />
            </view>
        </view>

        <view class="submit-section">
            <button class="submit-btn" :disabled="!canSubmit" @click="submitPublish">{{ editId ? '保存修改' : '发布陪练' }}</button>
        </view>
        
        <!-- 支付组件 -->
        <pay ref="payRef" @success="onPaySuccess" @fail="onPayFail" />
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, computed, onMounted } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getGameDetail, getGameTypes as fetchGameTypes, getServiceTypes as fetchServiceTypes } from '../../api/game'
import { createOrder } from '../../api/xiaoyuan'
import { tryBindFenxiao } from '../../utils/bindFenxiao'
import pay from '@/components/pay/pay.vue'
import xyUpload from '../../components/xy-upload.vue'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_game')

const editId = ref(0)
const imageStr = ref('')
const payRef = ref<any>(null)
const currentOrderId = ref(0)

const gameTypes = ref<any[]>([])
const serviceTypes = ref<any[]>([])

const form = ref({
    game_type: '',
    game_name: '',
    rank_level: '',
    service_type: '',
    title: '',
    content: '',
    price: '',
    unit: '小时',
    voice_chat: 1,
    online_time: '',
    nickname: '',
    avatar: '',
    school_id: 0,
})

const gameMap: Record<string, string> = {
    WZRY: '王者荣耀', LOL: '英雄联盟', PUBG: '和平精英',
    CSGO: 'CS2', YS: '原神', EGG: '蛋仔派对', OTHER: '其他'
}
const serviceMap: Record<string, string> = {
    PLAY_WITH: '陪玩', BOOST: '代练', TEACH: '教学', TEAM: '组队'
}

const canSubmit = computed(() => {
    return form.value.game_type && form.value.service_type && form.value.title && parseFloat(form.value.price) > 0
})

onLoad((options: any) => {
    if (options?.id) {
        editId.value = parseInt(options.id)
        loadDetail()
    }
})

onMounted(() => {
    loadConfig()
    tryBindFenxiao()
    loadTypes()
    // 获取用户信息
    const memberInfo = uni.getStorageSync('wap_member_info')
    if (memberInfo) {
        form.value.nickname = memberInfo.nickname || ''
        form.value.avatar = memberInfo.headimg || memberInfo.avatar || ''
    }
})

const loadTypes = async () => {
    try {
        const [gRes, sRes]: any[] = await Promise.all([fetchGameTypes(), fetchServiceTypes()])
        if (gRes.code === 1) gameTypes.value = gRes.data || []
        if (sRes.code === 1) serviceTypes.value = sRes.data || []
    } catch (e) {
        gameTypes.value = Object.keys(gameMap).map(k => ({ value: k, label: gameMap[k] }))
        serviceTypes.value = Object.keys(serviceMap).map(k => ({ value: k, label: serviceMap[k] }))
    }
}

const loadDetail = async () => {
    try {
        const res: any = await getGameDetail({ id: editId.value })
        if (res.code === 1 && res.data) {
            const d = res.data
            form.value = {
                game_type: d.game_type, game_name: d.game_name, rank_level: d.rank_level,
                service_type: d.service_type, title: d.title, content: d.content || '',
                price: String(d.price), unit: d.unit || '小时', voice_chat: d.voice_chat,
                online_time: d.online_time || '',
                nickname: d.nickname || '', avatar: d.avatar || '', school_id: d.school_id || 0,
            }
            if (d.images) {
                if (typeof d.images === 'string') {
                    try {
                        const parsed = JSON.parse(d.images)
                        imageStr.value = Array.isArray(parsed) ? parsed.join(',') : d.images
                    } catch {
                        imageStr.value = d.images
                    }
                } else if (Array.isArray(d.images)) {
                    imageStr.value = d.images.join(',')
                }
            }
        }
    } catch (e) { console.error(e) }
}

const submitPublish = async () => {
    if (!canSubmit.value) return

    try {
        uni.showLoading({ title: '提交中...' })
        const ext = {
            game_type: form.value.game_type,
            game_name: form.value.game_name,
            rank_level: form.value.rank_level,
            service_type: form.value.service_type,
            voice_chat: form.value.voice_chat,
            online_time: form.value.online_time,
            unit: form.value.unit
        }
        const orderData: any = {
            task_type: 'GAME',
            goods_name: form.value.title,
            task_desc: form.value.content,
            goods_image: imageStr.value,
            total_fee: parseFloat(form.value.price),
            remark: form.value.content,
            ext: JSON.stringify(ext)
        }
        
        const res: any = await createOrder(orderData)
        uni.hideLoading()

        if (res.code === 1) {
            currentOrderId.value = res.data.id
            // 使用框架支付组件
            payRef.value?.open('sd_xiaoyuan_order', res.data.id, '/addon/sd_xiaoyuan/pages/order/detail?id=' + res.data.id)
        } else {
            uni.showToast({ title: res.msg || '操作失败', icon: 'none' })
        }
    } catch (e: any) {
        uni.hideLoading()
        uni.showToast({ title: e.msg || '网络错误', icon: 'none' })
    }
}

// 支付成功回调
const onPaySuccess = () => {
    uni.showToast({ title: '支付成功', icon: 'success' })
    setTimeout(() => {
        uni.redirectTo({
            url: `/addon/sd_xiaoyuan/pages/order/detail?id=${currentOrderId.value}`
        })
    }, 1500)
}

// 支付失败回调
const onPayFail = () => {
    uni.showToast({ title: '支付失败', icon: 'none' })
}
</script>

<style lang="scss" scoped>
.game-publish { min-height: 100vh; background: #f5f5f5; padding: 20rpx; padding-bottom: 200rpx; }

.form-section { background: #fff; border-radius: 20rpx; padding: 28rpx; margin-bottom: 20rpx; }
.section-title { font-size: 30rpx; font-weight: bold; color: #333; margin-bottom: 24rpx; padding-left: 16rpx; border-left: 8rpx solid #c0fe95; line-height: 1; }

.form-item {
    margin-bottom: 24rpx;
    &.row { display: flex; justify-content: space-between; align-items: center; }
    .label { display: block; font-size: 28rpx; color: #333; margin-bottom: 12rpx; font-weight: 500; &.required::before { content: '*'; color: #ff4d4f; margin-right: 4rpx; } }
    input { display: block; box-sizing: border-box; width: 100%; height: 80rpx; padding: 0 20rpx; background: #f8f8f8; border-radius: 12rpx; font-size: 28rpx; }
    textarea { display: block; box-sizing: border-box; width: 100%; min-height: 200rpx; padding: 20rpx; background: #f8f8f8; border-radius: 12rpx; font-size: 28rpx; }
}

.select-grid {
    display: flex; flex-wrap: wrap; gap: 16rpx;
    &.small .select-item { padding: 12rpx 28rpx; font-size: 24rpx; }
}
.select-item {
    padding: 16rpx 32rpx; border-radius: 12rpx; font-size: 26rpx; color: #666; background: #f5f5f5; border: 2rpx solid transparent;
    &.active { background: #f0fff0; border-color: #52c41a; color: #333; font-weight: bold; }
}

.price-input {
    display: flex; align-items: center; background: #f8f8f8; border-radius: 12rpx; padding: 0 20rpx;
    .yen { font-size: 32rpx; color: #ff6b00; font-weight: bold; margin-right: 8rpx; }
    input { flex: 1; width: auto; height: 80rpx; padding: 0; background: transparent; font-size: 32rpx; font-weight: bold; color: #ff6b00; }
    .unit-text { font-size: 26rpx; color: #999; }
}

.image-list { display: flex; flex-wrap: wrap; gap: 16rpx; }
.img-wrap {
    position: relative; width: 180rpx; height: 180rpx;
    image { width: 100%; height: 100%; border-radius: 12rpx; }
    .del-btn { position: absolute; top: -10rpx; right: -10rpx; width: 40rpx; height: 40rpx; background: #ff4d4f; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28rpx; }
}
.add-img {
    width: 180rpx; height: 180rpx; border: 2rpx dashed #ddd; border-radius: 12rpx;
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8rpx;
    text { font-size: 22rpx; color: #999; }
}

.submit-section {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 20rpx 30rpx;
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    background: #fff;
    box-shadow: 0 -2rpx 20rpx rgba(0, 0, 0, 0.05);
}

.submit-btn {
    width: 100%;
    height: 80rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(to top, #aaf69b, #d1ff7c);
    color: #000;
    border: none;
    border-radius: 40rpx;
    font-size: 30rpx;
    font-weight: bold;
    box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);
    &::after { border: none; }
    &[disabled] { background: #ccc; color: #999; box-shadow: none; }
}
</style>
