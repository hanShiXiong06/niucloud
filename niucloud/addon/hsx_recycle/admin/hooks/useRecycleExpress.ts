import { onUnmounted, ref } from "vue";
import { ElMessage } from "element-plus";
import { getExpress } from "@/addon/hsx_recycle/api/device_query_api";

interface RecycleOrderMobileInfo {
  mobile?: string;
}

export interface RecycleOrderExpressRow {
  id: number | string;
  express_no?: string;
  member?: RecycleOrderMobileInfo;
  recycleUserAddress?: RecycleOrderMobileInfo;
}

interface ExpressTraceItem {
  timeDesc: string;
  areaName: string;
  desc: string;
}

export interface ExpressDetail {
  logisticsCompanyName?: string;
  mailNo?: string;
  logisticsStatus?: string;
  logisticsStatusDesc?: string;
  theLastMessage?: string;
  theLastTime?: string;
  logisticsTraceDetailList?: ExpressTraceItem[];
  [key: string]: any;
}

interface ExpressQueryPayload {
  data?: ExpressDetail;
}

interface UseRecycleExpressOptions {
  hoverDelay?: number;
}

export function useRecycleExpress(options: UseRecycleExpressOptions = {}) {
  const { hoverDelay = 500 } = options;

  const expressLoading = ref<Record<string, boolean>>({});
  const expressPopoverVisible = ref(false);
  const expressInfo = ref<ExpressDetail | null>(null);
  const hoverTimer = ref<ReturnType<typeof setTimeout> | null>(null);

  const clearHoverTimer = () => {
    if (hoverTimer.value) {
      clearTimeout(hoverTimer.value);
      hoverTimer.value = null;
    }
  };

  const queryExpress = async (
    expressCode: string,
    mobileLast4: string
  ): Promise<ExpressQueryPayload> => {
    try {
      const res = await getExpress(expressCode, mobileLast4);
      return (res.data || {}) as ExpressQueryPayload;
    } catch (error) {
      console.error("查询快递信息失败:", error);
      throw error;
    }
  };

  const handleExpressHover = (row: RecycleOrderExpressRow) => {
    if (!row.express_no || row.express_no === "暂无") return;

    clearHoverTimer();

    hoverTimer.value = setTimeout(async () => {
      const rowKey = String(row.id);
      try {
        expressLoading.value[rowKey] = true;

        const mobile = row.member?.mobile || row.recycleUserAddress?.mobile || "";
        const mobileLast4 = mobile.slice(-4);

        if (!mobileLast4) {
          ElMessage.warning("无法获取用户手机号，无法查询快递信息");
          return;
        }

        const expressData = await queryExpress(row.express_no as string, mobileLast4);
        const expressPayload = expressData?.data;

        if (
          !expressPayload ||
          !Array.isArray(expressPayload.logisticsTraceDetailList) ||
          expressPayload.logisticsTraceDetailList.length === 0
        ) {
          ElMessage.info("暂无物流信息");
          return;
        }

        expressInfo.value = expressPayload;
        expressPopoverVisible.value = true;
      } catch (error) {
        console.error("查询快递信息失败:", error);
        ElMessage.error("查询快递信息失败");
      } finally {
        expressLoading.value[rowKey] = false;
      }
    }, hoverDelay);
  };

  const handleExpressLeave = () => {
    clearHoverTimer();
  };

  onUnmounted(() => {
    clearHoverTimer();
  });

  return {
    expressLoading,
    expressPopoverVisible,
    expressInfo,
    handleExpressHover,
    handleExpressLeave,
  };
}
