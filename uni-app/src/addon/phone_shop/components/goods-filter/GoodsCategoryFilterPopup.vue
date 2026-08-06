<template>
    <u-popup :show="show" mode="bottom" :round="18" @close="close">
        <view class="category-popup" :style="themeColor()">
            <view class="category-popup__head">
                <view>
                    <view class="category-popup__title">选择分类</view>
                    <view class="category-popup__tip">可多选，同类商品一次筛选</view>
                </view>
                <view class="category-popup__close" @click="close">
                    <u-icon name="close" size="20" color="#64748b" />
                </view>
            </view>
            <view class="category-popup__content">
                <scroll-view :scroll-y="true" :show-scrollbar="false" class="category-roots">
                    <view
                        v-for="root in categories"
                        :key="root.category_id"
                        class="category-root"
                        :class="{ 'category-root--active': activeRootId === String(root.category_id) }"
                        @click="activeRootId = String(root.category_id)"
                    >
                        <text class="category-root__name">{{ root.category_name }}</text>
                        <u-icon
                            v-if="isSubscribed(root.category_id)"
                            name="bell-fill"
                            size="14"
                            color="var(--primary-color)"
                        />
                    </view>
                </scroll-view>
                <scroll-view :scroll-y="true" :show-scrollbar="false" class="category-children">
                    <view v-if="activeRoot" class="category-section">
                        <view class="category-all" :class="{ 'category-all--active': selected.includes(String(activeRoot.category_id)) }">
                            <view class="category-all__main" @click="toggle(activeRoot.category_id)">
                                <view>
                                    <view class="category-all__name">全部{{ activeRoot.category_name }}</view>
                                </view>
                                <view class="category-all__radio" :class="{ 'category-all__radio--active': selected.includes(String(activeRoot.category_id)) }">
                                    <view v-if="selected.includes(String(activeRoot.category_id))" class="category-all__radio-dot"></view>
                                </view>
                            </view>
                            <view
                                class="node-subscribe"
                                :class="{ 'node-subscribe--active': isSubscribed(activeRoot.category_id) }"
                                @click.stop="toggleSubscription(activeRoot)"
                            >
                                <u-icon
                                    :name="isSubscribed(activeRoot.category_id) ? 'bell-fill' : 'bell'"
                                    :color="isSubscribed(activeRoot.category_id) ? 'var(--primary-color)' : '#64748b'"
                                    size="16"
                                />
                                <text>{{ nodeLoading(activeRoot.category_id) ? '处理中' : (isSubscribed(activeRoot.category_id) ? '已订阅 · 取消' : '订阅该节点') }}</text>
                            </view>
                        </view>
                        <view v-for="group in activeGroups" :key="group.category_id" class="category-child-group">
                            <view class="category-child-group__head">
                                <view class="category-child-group__title">{{ group.category_name }}</view>
                                <view
                                    v-if="group.node"
                                    class="group-subscribe"
                                    :class="{ 'group-subscribe--active': isSubscribed(group.node.category_id) }"
                                    @click.stop="toggleSubscription(group.node)"
                                >
                                    <u-icon
                                        :name="isSubscribed(group.node.category_id) ? 'bell-fill' : 'bell'"
                                        :color="isSubscribed(group.node.category_id) ? 'var(--primary-color)' : '#64748b'"
                                        size="14"
                                    />
                                    <text>{{ nodeLoading(group.node.category_id) ? '处理中' : (isSubscribed(group.node.category_id) ? '取消订阅' : '订阅系列') }}</text>
                                </view>
                            </view>
                            <view class="category-chip-grid">
                                <view
                                    v-for="item in group.items"
                                    :key="item.category_id"
                                    class="category-chip"
                                    :class="{ 'category-chip--active': selected.includes(String(item.category_id)) }"
                                >
                                    <view class="category-chip__name" @click="toggle(item.category_id)">
                                        <text>{{ item.category_name }}</text>
                                        <view v-if="selected.includes(String(item.category_id))" class="category-chip__corner">
                                            <u-icon name="checkmark" size="11" color="#fff" />
                                        </view>
                                    </view>
                                    <view
                                        class="category-chip__bell"
                                        :class="{ 'category-chip__bell--active': isSubscribed(item.category_id) }"
                                        @click.stop="toggleSubscription(item)"
                                    >
                                        <u-icon
                                            :name="isSubscribed(item.category_id) ? 'bell-fill' : 'bell'"
                                            :color="isSubscribed(item.category_id) ? 'var(--primary-color)' : '#94a3b8'"
                                            size="15"
                                        />
                                    </view>
                                </view>
                            </view>
                        </view>
                    </view>
                    <u-empty v-else text="暂无商品分类" mode="list" />
                </scroll-view>
            </view>
            <view class="category-popup__footer">
                <view class="category-button category-button--plain" @click="selected = []">重置</view>
                <view class="category-button category-button--primary" @click="confirm">
                    确定<text v-if="selected.length">（{{ selected.length }}）</text>
                </view>
            </view>
        </view>
    </u-popup>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'

const props = defineProps<{
    show: boolean
    categories: any[]
    modelValue: Array<string | number>
    subscriptionMap?: Record<string, number>
    subscriptionLoadingId?: string
}>()
const emit = defineEmits(['update:show', 'confirm', 'subscribe-node', 'cancel-node'])
const selected = ref<string[]>([])
const activeRootId = ref('')

watch(() => props.show, (value) => {
    if (!value) return
    selected.value = (props.modelValue || []).map(String)
    activeRootId.value = activeRootId.value || String(props.categories?.[0]?.category_id || '')
}, { immediate: true })

watch(() => props.categories, (value) => {
    if (!activeRootId.value && value?.length) activeRootId.value = String(value[0].category_id)
}, { deep: true, immediate: true })

const activeRoot = computed(() => props.categories.find(item => String(item.category_id) === activeRootId.value))

const activeGroups = computed(() => {
    const root = activeRoot.value
    if (!root) return []
    const children = root.child_list || []
    if (!children.length) return [{ category_id: `root-${root.category_id}`, category_name: '具体分类', items: [root] }]
    return children.map((child: any) => {
        const descendants = flattenLeaves(child)
        return {
            category_id: child.category_id,
            category_name: child.category_name,
            node: child,
            items: descendants.length ? descendants : [child]
        }
    })
})

const flattenLeaves = (node: any): any[] => {
    const children = node?.child_list || []
    if (!children.length) return [node]
    return children.flatMap((child: any) => flattenLeaves(child))
}

const toggle = (value: string | number) => {
    const normalized = String(value)
    selected.value = selected.value.includes(normalized)
        ? selected.value.filter(item => item !== normalized)
        : [...selected.value, normalized]
}

const isSubscribed = (value: string | number) => Number(props.subscriptionMap?.[String(value)] || 0) > 0
const nodeLoading = (value: string | number) => props.subscriptionLoadingId === String(value)
const toggleSubscription = (node: any) => {
    if (!node || nodeLoading(node.category_id)) return
    emit(isSubscribed(node.category_id) ? 'cancel-node' : 'subscribe-node', node)
}

const close = () => emit('update:show', false)
const confirm = () => {
    emit('confirm', [...selected.value])
    close()
}
</script>

<style lang="scss" scoped>
.category-popup {
    height: 76vh;
    max-height: 1040rpx;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: #fff;
}

.category-popup__head {
    height: 96rpx;
    padding: 0 30rpx;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1rpx solid #eef2f7;
}

.category-popup__title {
    color: #0f172a;
    font-size: 32rpx;
    font-weight: 600;
}

.category-popup__tip {
    margin-top: 2rpx;
    color: #94a3b8;
    font-size: 22rpx;
}

.category-popup__close {
    width: 52rpx;
    height: 52rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #f5f7fa;
}

.category-popup__content {
    height: 0;
    flex: 1;
    min-height: 0;
    display: flex;
    overflow: hidden;
}

.category-roots {
    width: 188rpx;
    height: 100%;
    flex-shrink: 0;
    background: #f6f8fb;
}

.category-root {
    position: relative;
    min-height: 80rpx;
    padding: 0 18rpx;
    display: flex;
    align-items: center;
    color: #64748b;
    font-size: 25rpx;
}

.category-root__name {
    min-width: 0;
    flex: 1;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.category-root--active {
    color: #0f172a;
    background: #fff;
    font-weight: 600;
}

.category-root--active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 25rpx;
    width: 7rpx;
    height: 42rpx;
    border-radius: 0 6rpx 6rpx 0;
    background: var(--primary-color);
}

.category-children {
    min-width: 0;
    height: 100%;
    flex: 1;
    box-sizing: border-box;
    padding: 24rpx;
}

.category-all {
    min-height: 76rpx;
    padding: 10rpx 14rpx 10rpx 18rpx;
    display: flex;
    align-items: center;
    gap: 12rpx;
    box-sizing: border-box;
    border: 2rpx solid transparent;
    border-radius: 16rpx;
    background: #f7f9fc;
}

.category-all__main {
    min-width: 0;
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.category-all--active {
    border-color: var(--primary-color);
    background: var(--primary-color-light);
}

.category-all__name {
    color: #1e293b;
    font-size: 27rpx;
    font-weight: 600;
}

.category-all__desc {
    margin-top: 8rpx;
    color: #94a3b8;
    font-size: 21rpx;
}

.category-all__radio {
    width: 36rpx;
    height: 36rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-sizing: border-box;
    border: 2rpx solid #cbd5e1;
    border-radius: 50%;
}

.category-all__radio--active {
    border-color: var(--primary-color);
}

.category-all__radio-dot {
    width: 19rpx;
    height: 19rpx;
    border-radius: 50%;
    background: var(--primary-color);
}

.category-child-group {
    margin-top: 32rpx;
}

.category-child-group__title {
    color: #334155;
    font-size: 25rpx;
    font-weight: 600;
}

.category-child-group__head {
    min-height: 52rpx;
    margin-bottom: 14rpx;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16rpx;
}

.node-subscribe,
.group-subscribe {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7rpx;
    color: #64748b;
    font-size: 21rpx;
}

.node-subscribe {
    min-height: 48rpx;
    padding: 0 12rpx;
    flex-shrink: 0;
    border-radius: 24rpx;
    background: #fff;
}

.node-subscribe--active,
.group-subscribe--active {
    color: var(--primary-color);
}

.node-subscribe--active {
    background: var(--primary-color-light);
}

.group-subscribe {
    min-height: 48rpx;
    padding: 0 13rpx;
    flex-shrink: 0;
    border-radius: 24rpx;
    background: #f5f7fa;
}

.group-subscribe--active {
    background: var(--primary-color-light);
}

.category-chip-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16rpx;
}

.category-chip {
    position: relative;
    height: 70rpx;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: stretch;
    overflow: hidden;
    box-sizing: border-box;
    border: 2rpx solid transparent;
    border-radius: 13rpx;
    background: #f5f7fa;
    color: #475569;
    font-size: 23rpx;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.category-chip__name {
    min-width: 0;
    height: 100%;
    padding: 0 8rpx 0 13rpx;
    display: flex;
    flex: 1;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.category-chip__name text {
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.category-chip__bell {
    width: 52rpx;
    height: 100%;
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-left: 1rpx solid rgba(148, 163, 184, 0.18);
}

.category-chip__bell--active {
    background: var(--primary-color-light);
}

.category-chip--active {
    color: var(--primary-color);
    border-color: var(--primary-color);
    background: var(--primary-color-light);
}

.category-chip__corner {
    position: absolute;
    right: -1rpx;
    bottom: -1rpx;
    width: 32rpx;
    height: 28rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 13rpx 0 10rpx 0;
    background: var(--primary-color);
}

.category-popup__footer {
    padding: 20rpx 30rpx calc(20rpx + env(safe-area-inset-bottom));
    display: grid;
    grid-template-columns: 220rpx minmax(0, 1fr);
    gap: 20rpx;
    border-top: 1rpx solid #eef2f7;
}

.category-button {
    height: 82rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 42rpx;
    font-size: 28rpx;
    font-weight: 600;
}

.category-button--plain {
    border: 1rpx solid #dbe1ea;
    color: #475569;
}

.category-button--primary {
    color: #fff;
    background: var(--primary-color);
}
</style>
