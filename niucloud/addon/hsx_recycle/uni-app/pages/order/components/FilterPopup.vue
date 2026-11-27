<template>
    <div v-if="visible" class="filter-popup">
        <div class="filter-header">
            <h3>筛选条件</h3>
            <button @click="close">关闭</button>
        </div>
        <div class="filter-body">
            <div class="filter-group" v-for="(options, filterKey) in filters" :key="filterKey">
                <label :for="filterKey">{{ filterLabels[filterKey] }}</label>
                <select :id="filterKey" v-model="selectedFilters[filterKey]">
                    <option value="">全部</option>
                    <option v-for="option in options" :key="option" :value="option">
                        {{ option }}
                    </option>
                </select>
            </div>
        </div>
        <div class="filter-footer">
            <button @click="applyFilters">应用</button>
            <button @click="resetFilters">重置</button>
        </div>
    </div>
</template>

<script>
export default {
    name: 'FilterPopup',
    props: {
        visible: {
            type: Boolean,
            required: true
        },
        filters: {
            type: Object,
            required: true
        },
        filterLabels: {
            type: Object,
            required: true
        }
    },
    data() {
        return {
            selectedFilters: {}
        };
    },
    watch: {
        filters: {
            immediate: true,
            handler(newFilters) {
                this.selectedFilters = Object.keys(newFilters).reduce((acc, key) => {
                    acc[key] = '';
                    return acc;
                }, {});
            }
        }
    },
    methods: {
        applyFilters() {
            this.$emit('apply', this.selectedFilters);
            this.close();
        },
        resetFilters() {
            this.selectedFilters = Object.keys(this.filters).reduce((acc, key) => {
                acc[key] = '';
                return acc;
            }, {});
            this.$emit('reset');
        },
        close() {
            this.$emit('close');
        }
    }
};
</script>

<style scoped>
.filter-popup {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    width: 300px;
    padding: 16px;
    z-index: 1000;
}

.filter-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #ddd;
    margin-bottom: 16px;
}

.filter-body {
    margin-bottom: 16px;
}

.filter-group {
    margin-bottom: 12px;
}

.filter-footer {
    display: flex;
    justify-content: space-between;
}
</style>