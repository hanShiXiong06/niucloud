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
                <scroll-view scroll-y class="category-roots">
                    <view
                        v-for="root in categories"
                        :key="root.category_id"
                        class="category-root"
                        :class="{ 'category-root--active': activeRootId === String(root.category_id) }"
                        @click="activeRootId = String(root.category_id)"
                    >
                        {{ root.category_name }}
                    </view>
                </scroll-view>
                <scroll-view scroll-y class="category-children">
                    <view v-if="activeRoot" class="category-section">
                        <view
                            class="category-all"
                            :class="{ 'category-all--active': selected.includes(String(activeRoot.category_id)) }"
                            @click="toggle(activeRoot.category_id)"
                        >
                            <view>
                                <view class="category-all__name">全部{{ activeRoot.category_name }}</view>
                                <view class="category-all__desc">包含该分类下所有商品</view>
                            </view>
                            <view class="category-all__radio" :class="{ 'category-all__radio--active': selected.includes(String(activeRoot.category_id)) }">
                                <view v-if="selected.includes(String(activeRoot.category_id))" class="category-all__radio-dot"></view>
                            </view>
                        </view>
                        <view v-for="group in activeGroups" :key="group.category_id" class="category-child-group">
                            <view class="category-child-group__title">{{ group.category_name }}</view>
                            <view class="category-chip-grid">
                                <view
                                    v-for="item in group.items"
                                    :key="item.category_id"
                                    class="category-chip"
                                    :class="{ 'category-chip--active': selected.includes(String(item.category_id)) }"
                                    @click="toggle(item.category_id)"
                                >
                                    {{ item.category_name }}
                                    <view v-if="selected.includes(String(item.category_id))" class="category-chip__corner">
                                        <u-icon name="checkmark" size="11" color="#fff" />
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
}>()
const emit = defineEmits(['update:show', 'confirm'])
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

const close = () => emit('update:show', false)
const confirm = () => {
    emit('confirm', [...selected.value])
    close()
}
</script>

<style lang="scss" scoped>
.category-popup {
    height: min(76vh, 1040rpx);
    display: flex;
    flex-direction: column;
    background: #fff;
}

.category-popup__head {
    height: 124rpx;
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
    margin-top: 7rpx;
    color: #94a3b8;
    font-size: 22rpx;
}

.category-popup__close {
    width: 60rpx;
    height: 60rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #f5f7fa;
}

.category-popup__content {
    flex: 1;
    min-height: 0;
    display: grid;
    grid-template-columns: 188rpx minmax(0, 1fr);
}

.category-roots {
    height: 100%;
    background: #f6f8fb;
}

.category-root {
    position: relative;
    min-height: 92rpx;
    padding: 0 18rpx;
    display: flex;
    align-items: center;
    color: #64748b;
    font-size: 25rpx;
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
    height: 100%;
    box-sizing: border-box;
    padding: 24rpx;
}

.category-all {
    min-height: 104rpx;
    padding: 20rpx 22rpx;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-sizing: border-box;
    border: 2rpx solid transparent;
    border-radius: 16rpx;
    background: #f7f9fc;
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
    margin-bottom: 18rpx;
    color: #334155;
    font-size: 25rpx;
    font-weight: 600;
}

.category-chip-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16rpx;
}

.category-chip {
    position: relative;
    height: 70rpx;
    padding: 0 14rpx;
    display: flex;
    align-items: center;
    justify-content: center;
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
