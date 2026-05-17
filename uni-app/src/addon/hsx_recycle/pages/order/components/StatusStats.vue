<template>
    <div class="status-stats">
        <div class="status-item" v-for="(text, key) in statusMap" :key="key">
            <span class="status-key">{{ key }}</span>
            <span class="status-text">{{ text }}</span>
        </div>
    </div>
</template>

<script>
export default {
    name: 'StatusStats',
    props: {
        statusMap: {
            type: Object,
            required: true
        }
    }
};
</script>

<style scoped>
.status-stats {
    display: flex;
    flex-direction: column;
}

.status-item {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem;
    border-bottom: 1px solid #ccc;
}
</style>