<template>
    <div class="search-bar flex items-center p-4 bg-white shadow-md">
        <input v-model="searchQuery" type="text" class="flex-1 border border-gray-300 rounded-lg px-4 py-2 text-sm"
            placeholder="请输入订单号或关键字搜索" />
        <button @click="handleSearch" class="ml-4 bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
            搜索
        </button>
    </div>
</template>

<script>
export default {
    name: 'SearchBar',
    props: {
        /**
         * 初始搜索关键字
         */
        initialQuery: {
            type: String,
            default: ''
        }
    },
    data() {
        return {
            searchQuery: this.initialQuery
        };
    },
    methods: {
        /**
         * 触发搜索事件
         */
        handleSearch() {
            this.$emit('search', this.searchQuery);
        }
    }
};
</script>

<style scoped>
.search-bar {
    display: flex;
    align-items: center;
    background-color: #fff;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
</style>