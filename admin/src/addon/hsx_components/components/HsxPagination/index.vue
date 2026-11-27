<script lang="ts">
export default { name: 'HsxPagination', inheritAttrs: false }
</script>

<script setup lang="ts">
const props = withDefaults(
    defineProps<{
        currentPage?: number
        pageSize?: number
        total?: number
        pageSizes?: number[]
        layout?: string
        background?: boolean
        small?: boolean
        disabled?: boolean
        hideOnSinglePage?: boolean
    }>(),
    {
        currentPage: 1,
        pageSize: 20,
        total: 0,
        pageSizes: () => [10, 20, 50, 100],
        layout: 'total, sizes, prev, pager, next, jumper',
        background: true,
        small: false,
        disabled: false,
        hideOnSinglePage: false
    }
)

const emit = defineEmits<{
    (event: 'update:currentPage', value: number): void
    (event: 'update:pageSize', value: number): void
    (event: 'current-change', value: number): void
    (event: 'size-change', value: number): void
    (event: 'change', value: { page: number, limit: number }): void
}>()

function handleCurrentChange(page: number) {
    emit('update:currentPage', page)
    emit('current-change', page)
    emit('change', { page, limit: props.pageSize })
}

function handleSizeChange(limit: number) {
    emit('update:pageSize', limit)
    emit('update:currentPage', 1)
    emit('size-change', limit)
    emit('change', { page: 1, limit })
}
</script>

<template>
    <el-pagination
        v-bind="$attrs"
        :current-page="currentPage"
        :page-size="pageSize"
        :total="total"
        :page-sizes="pageSizes"
        :layout="layout"
        :background="background"
        :small="small"
        :disabled="disabled"
        :hide-on-single-page="hideOnSinglePage"
        @current-change="handleCurrentChange"
        @size-change="handleSizeChange"
    />
</template>
