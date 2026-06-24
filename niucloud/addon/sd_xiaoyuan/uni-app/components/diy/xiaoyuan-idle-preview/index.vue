<template>
    <view :style="warpCss" class="xiaoyuan-idle-preview-wrap">
        <view class="idle-container">
            <view class="header-bar">
                <view class="title-box">
                    <text class="title">{{ diyComponent.title || '闲置好物' }}</text>
                </view>
                <view v-if="diyComponent.showMore" class="more-btn" @click="goMore">
                    <text class="more-text">{{ diyComponent.moreText || '查看更多' }}</text>
                    <u-icon name="arrow-right" size="24" color="#999"></u-icon>
                </view>
            </view>

            <view class="idle-list" v-if="list.length">
                <view v-for="item in list" :key="item.id" class="idle-item" @click="goDetail(item.id)">
                    <view class="item-header">
                        <view class="user-info">
                            <image
                                :src="item.member_avatar ? img(item.member_avatar) : defaultAvatar"
                                class="avatar"
                                mode="aspectFill"
                            />
                            <view class="user-detail">
                                <text class="nickname">{{ item.member_nickname || '同学' }}</text>
                                <text class="time">{{ formatTime(item.create_time) }}</text>
                            </view>
                        </view>
                        <view v-if="Number(item.price) === 0" class="type-tag free">免费送</view>
                        <view v-else-if="Number(item.is_top) === 1" class="type-tag urgent">急售捡漏</view>
                        <view v-else class="type-tag normal">正常出售</view>
                    </view>

                    <view class="item-content">
                        <text class="t-title">{{ item.title }}</text>
                        <text class="content" v-if="item.content">{{ item.content }}</text>
                    </view>

                    <view v-if="item._imgs && item._imgs.length" class="image-grid">
                        <image
                            v-for="(im, idx) in item._imgs.slice(0, 3)"
                            :key="idx"
                            :src="im"
                            class="grid-image"
                            mode="aspectFill"
                        />
                    </view>

                    <view class="item-footer">
                        <view class="stat-item">
                            <u-icon name="eye" size="28" color="#999"></u-icon>
                            <text class="stat-text">{{ item.view_count || 0 }}</text>
                        </view>
                        <view class="stat-item">
                            <u-icon name="heart" size="28" color="#999"></u-icon>
                            <text class="stat-text">{{ item.want_count || 0 }}</text>
                        </view>
                        <view class="price-tag" v-if="Number(item.price) > 0">
                            <text class="price-txt">¥{{ item.price }}</text>
                        </view>
                    </view>
                </view>
            </view>

            <view v-else class="empty-state">
                <u-icon name="shopping-cart" size="80" color="#ddd"></u-icon>
                <text class="empty-text">暂无闲置商品</text>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import useDiyStore from '@/app/stores/diy'
import { getSecondhandList } from '@/addon/sd_xiaoyuan/api/xiaoyuan'
import { ensureXiaoyuanDefaultSchool } from '@/addon/sd_xiaoyuan/composables/useXiaoyuanDefaultSchool'
import { redirect, img } from '@/utils/common'
import { normalizeMediaImages } from '@/addon/sd_xiaoyuan/utils/mediaImages'

const props = defineProps(['component', 'index'])
const diyStore = useDiyStore()

const diyComponent = computed(() => {
    if (diyStore.mode == 'decorate') {
        return diyStore.value[props.index]
    }
    return props.component
})

const warpCss = computed(() => {
    let style = ''
    const margin = diyComponent.value.margin || { top: 0, bottom: 0, both: 10 }
    style += `margin: ${margin.top}rpx ${margin.both}rpx ${margin.bottom}rpx;`
    style += `border-radius: ${diyComponent.value.topRounded || 12}rpx ${diyComponent.value.topRounded || 12}rpx ${diyComponent.value.bottomRounded || 12}rpx ${diyComponent.value.bottomRounded || 12}rpx;`
    if (diyComponent.value.componentStartBgColor) {
        if (diyComponent.value.componentStartBgColor && diyComponent.value.componentEndBgColor) {
            style += `background:linear-gradient(${diyComponent.value.componentGradientAngle},${diyComponent.value.componentStartBgColor},${diyComponent.value.componentEndBgColor});`
        } else {
            style += 'background-color:' + diyComponent.value.componentStartBgColor + ';'
        }
    }
    return style
})

const list = ref<any[]>([])
const defaultAvatar = '/static/resource/images/default_headimg.png'

const getSchoolId = (s: any) => {
    const v = s?.id ?? s?.school_id
    const n = parseInt(String(v ?? 0), 10)
    return Number.isFinite(n) && n > 0 ? n : 0
}

const onSchoolChanged = () => {
    if (diyStore.mode !== 'decorate') loadData()
}

onMounted(() => {
    if (diyStore.mode !== 'decorate') {
        ;(async () => {
            await ensureXiaoyuanDefaultSchool()
            loadData()
        })()
        uni.$on('xiaoyuan_school_changed', onSchoolChanged)
    }
})

onUnmounted(() => {
    uni.$off('xiaoyuan_school_changed', onSchoolChanged)
})

onShow(() => {
    if (diyStore.mode !== 'decorate') {
        ensureXiaoyuanDefaultSchool().then(() => loadData())
    }
})

const loadData = async () => {
    try {
        const params: any = {
            page: 1,
            limit: diyComponent.value.num || 10,
            sort: diyComponent.value.sort || 'new'
        }
        const cid = diyComponent.value.category_id
        if (cid !== undefined && cid !== null && String(cid) !== '' && Number(cid) > 0) {
            params.category_id = String(cid)
        }
        const gt = diyComponent.value.goods_type
        if (gt === 'normal' || gt === 'urgent' || gt === 'free') {
            params.goods_type = gt
        }
        const school = uni.getStorageSync('current_school') || {}
        const sid = getSchoolId(school)
        if (sid > 0) {
            params.school_id = sid
        }
        const res: any = await getSecondhandList(params)
        if (res.code === 1 && res.data && res.data.list) {
            list.value = res.data.list.map((row: any) => {
                const paths = normalizeMediaImages(row.images)
                row._imgs = paths.map((p: string) => img(p))
                return row
            })
        } else {
            list.value = []
        }
    } catch (e) {
        console.error(e)
        list.value = []
    }
}

const goMore = () => {
    if (diyComponent.value.moreUrl) {
        const url = diyComponent.value.moreUrl.url || diyComponent.value.moreUrl
        redirect({ url })
    }
}

const goDetail = (id: number) => {
    redirect({ url: '/addon/sd_xiaoyuan/pages/secondhand/detail', param: { id } })
}

const formatTime = (time: any) => {
    if (!time) return ''
    if (typeof time === 'string') return time
    const now = Date.now()
    const ts = typeof time === 'number' ? (time > 1e12 ? time : time * 1000) : new Date(time).getTime()
    const diff = now - ts
    const minute = 60 * 1000
    const hour = 60 * minute
    const day = 24 * hour
    if (diff < minute) return '刚刚'
    if (diff < hour) return Math.floor(diff / minute) + '分钟前'
    if (diff < day) return Math.floor(diff / hour) + '小时前'
    if (diff < 7 * day) return Math.floor(diff / day) + '天前'
    const date = new Date(ts)
    return `${date.getMonth() + 1}-${date.getDate()}`
}
</script>

<style lang="scss" scoped>
.xiaoyuan-idle-preview-wrap {
    overflow: hidden;
}

.idle-container {
    padding: 24rpx;
}

.header-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24rpx;

    .title-box .title {
        font-size: 32rpx;
        font-weight: 700;
        color: #1a1a1a;
    }

    .more-btn {
        display: flex;
        align-items: center;
        gap: 4rpx;
        .more-text {
            font-size: 24rpx;
            color: #999;
        }
    }
}

.idle-list {
    display: flex;
    flex-direction: column;
    gap: 20rpx;
}

.idle-item {
    background: #f8f9fb;
    border-radius: 16rpx;
    padding: 24rpx;

    .item-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16rpx;

        .user-info {
            display: flex;
            align-items: center;
            gap: 16rpx;
            flex: 1;
            min-width: 0;

            .avatar {
                width: 64rpx;
                height: 64rpx;
                border-radius: 50%;
                background: #eee;
            }

            .user-detail {
                display: flex;
                flex-direction: column;
                gap: 4rpx;

                .nickname {
                    font-size: 26rpx;
                    font-weight: 600;
                    color: #333;
                }

                .time {
                    font-size: 22rpx;
                    color: #999;
                }
            }
        }

        .type-tag {
            padding: 4rpx 16rpx;
            font-size: 20rpx;
            border-radius: 8rpx;
            flex-shrink: 0;
            &.normal {
                background: #f0f0f0;
                color: #666;
            }
            &.urgent {
                background: #fff3e0;
                color: #ff6b00;
            }
            &.free {
                background: #e8f5e9;
                color: #00a870;
            }
        }
    }

    .item-content {
        margin-bottom: 16rpx;

        .t-title {
            display: block;
            font-size: 28rpx;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 8rpx;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .content {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            overflow: hidden;
            font-size: 26rpx;
            color: #666;
            line-height: 1.6;
        }
    }

    .image-grid {
        display: flex;
        gap: 12rpx;
        margin-bottom: 16rpx;

        .grid-image {
            width: 200rpx;
            height: 200rpx;
            border-radius: 12rpx;
            background: #f0f0f0;
        }
    }

    .item-footer {
        display: flex;
        align-items: center;
        gap: 32rpx;
        padding-top: 16rpx;
        border-top: 1rpx solid #eee;

        .stat-item {
            display: flex;
            align-items: center;
            gap: 8rpx;
            .stat-text {
                font-size: 24rpx;
                color: #999;
            }
        }

        .price-tag {
            margin-left: auto;
            .price-txt {
                font-size: 28rpx;
                font-weight: bold;
                color: #ff5722;
            }
        }
    }
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 80rpx 0;
    .empty-text {
        margin-top: 24rpx;
        font-size: 26rpx;
        color: #999;
    }
}
</style>
