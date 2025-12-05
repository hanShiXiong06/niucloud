import { defineStore } from "pinia";
import { ref } from "vue";

export const usePaginationStore = defineStore("pagination", () => {
    const page = ref(1);
    const limit = ref(10);
    const total = ref(0);
    const loading = ref(true);

    function setPage(newPage: number) {
        page.value = newPage;
    }

    function setLimit(newLimit: number) {
        limit.value = newLimit;
    }

    function setTotal(newTotal: number) {
        total.value = newTotal;
    }

    function setLoading(newLoading: boolean) {
        loading.value = newLoading;
    }

    return {
        page,
        limit,
        total,
        loading,
        setPage,
        setLimit,
        setTotal,
        setLoading,
    };
});
