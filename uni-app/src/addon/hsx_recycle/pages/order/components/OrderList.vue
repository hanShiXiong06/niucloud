<template>
    <div class="order-list">
        <div v-if="orders.length" class="order-item" v-for="order in orders" :key="order.id">
            <div class="order-info">
                <p class="order-id">订单号: {{ order.id }}</p>
                <p class="order-status">状态: {{ getStatusText(order.status) }}</p>
            </div>
            <div class="order-actions">
                <button @click="viewOrder(order.id)" class="btn btn-view">查看</button>
                <button @click="deleteOrder(order.id)" class="btn btn-delete">删除</button>
            </div>
        </div>
        <p v-else class="no-orders">暂无订单</p>
    </div>
</template>

<script>
import { getStatusText } from '@/utils/statusUtils';

export default {
    name: 'OrderList',
    props: {
        orders: {
            type: Array,
            required: true
        }
    },
    methods: {
        getStatusText,
        viewOrder(orderId) {
            this.$emit('view', orderId);
        },
        deleteOrder(orderId) {
            this.$emit('delete', orderId);
        }
    }
};
</script>

<style scoped>
.order-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.order-item {
    border: 1px solid #ddd;
    padding: 16px;
    border-radius: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.order-info {
    flex-grow: 1;
}

.order-id,
.order-status {
    margin: 0;
}

.order-actions {
    display: flex;
    gap: 8px;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.btn-view {
    background-color: #007bff;
    color: #fff;
}

.btn-delete {
    background-color: #dc3545;
    color: #fff;
}

.no-orders {
    text-align: center;
    color: #888;
}
</style>