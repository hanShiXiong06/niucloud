import {
  Bell,
  CircleClose,
  Close,
  DocumentChecked,
  InfoFilled,
  PriceTag,
  Search,
  ShoppingBag,
  SuccessFilled,
  View,
} from "@element-plus/icons-vue";

type StatusTagType = "success" | "warning" | "info" | "primary" | "danger";
type StatusTagEffect = "dark" | "light" | "plain";

interface OrderStatusUiConfig {
  type: StatusTagType;
  effect: StatusTagEffect;
  badgeType: StatusTagType;
  icon: string;
}

const ORDER_STATUS_UI_MAP: Record<number, OrderStatusUiConfig> = {
  1: { type: "warning", effect: "light", badgeType: "warning", icon: "Clock" },
  2: { type: "warning", effect: "light", badgeType: "info", icon: "Finished" },
  3: { type: "primary", effect: "light", badgeType: "primary", icon: "Loading" },
  4: {
    type: "success",
    effect: "light",
    badgeType: "success",
    icon: "DocumentChecked",
  },
  5: {
    type: "warning",
    effect: "light",
    badgeType: "warning",
    icon: "CircleCheck",
  },
  6: { type: "primary", effect: "dark", badgeType: "primary", icon: "Money" },
  7: {
    type: "success",
    effect: "dark",
    badgeType: "success",
    icon: "CircleCheckFilled",
  },
  8: { type: "info", effect: "plain", badgeType: "info", icon: "CircleClose" },
  9: {
    type: "danger",
    effect: "dark",
    badgeType: "danger",
    icon: "QuestionFilled",
  },
  10: {
    type: "danger",
    effect: "dark",
    badgeType: "danger",
    icon: "QuestionFilled",
  },
};

const DEVICE_STATUS_TYPE_MAP: Record<number, StatusTagType> = {
  1: "info",
  2: "warning",
  3: "primary",
  4: "success",
  5: "success",
  6: "danger",
};

const ACTION_BUTTON_TYPE_MAP: Record<string, string> = {
  order_sign: "primary",
  order_check: "success",
  order_price: "warning",
  order_payment: "primary",
  order_complete: "success",
  order_cancel: "danger",
  order_delete: "danger",
  order_detail: "info",
};

const ACTION_ICON_MAP: Record<string, any> = {
  order_sign: DocumentChecked,
  order_check: Search,
  order_price: PriceTag,
  order_payment: ShoppingBag,
  order_complete: SuccessFilled,
  order_cancel: CircleClose,
  order_delete: Close,
  order_detail: View,
  order_push_notify: Bell,
};

const EXPRESS_STATUS_TYPE_MAP: Record<string, string> = {
  ACCEPT: "info",
  TRANSPORT: "warning",
  DELIVER: "primary",
  SIGN: "success",
  REJECT: "danger",
  EXCEPTION: "danger",
};

export function useRecycleOrderUi() {
  const getStatusType = (status: number): string =>
    ORDER_STATUS_UI_MAP[status]?.type || "info";

  const getStatusEffect = (status: number): string =>
    ORDER_STATUS_UI_MAP[status]?.effect || "plain";

  const getStatusBadgeType = (status: number): string =>
    ORDER_STATUS_UI_MAP[status]?.badgeType || "info";

  const getStatusIcon = (status: number): string =>
    ORDER_STATUS_UI_MAP[status]?.icon || "QuestionFilled";

  const getDeviceStatusType = (status: number): string =>
    DEVICE_STATUS_TYPE_MAP[status] || "info";

  const getActionButtonType = (actionKey: string): string =>
    ACTION_BUTTON_TYPE_MAP[actionKey] || "default";

  const getActionIcon = (actionKey: string) =>
    ACTION_ICON_MAP[actionKey] || InfoFilled;

  const formatDateTime = (
    dateTime: string | number | null | undefined
  ): string => {
    if (!dateTime) return "-";

    if (typeof dateTime === "number") {
      const date = new Date(dateTime * 1000);
      return date
        .toLocaleString("zh-CN", {
          year: "numeric",
          month: "2-digit",
          day: "2-digit",
          hour: "2-digit",
          minute: "2-digit",
          second: "2-digit",
          hour12: false,
        })
        .replace(/\//g, "-");
    }

    if (typeof dateTime === "string" && dateTime.match(/^\d{4}-\d{2}-\d{2}/)) {
      return dateTime;
    }

    return "-";
  };

  const formatPrice = (price: number | string): string => {
    if (!price) return "¥0.00";
    const num = typeof price === "string" ? parseFloat(price) : price;
    return `¥${num.toFixed(2)}`;
  };

  const getDeviceCount = (devices: any[]) => {
    if (!Array.isArray(devices)) return 0;
    return devices.length;
  };

  const getExpressStatusType = (status: string): string =>
    EXPRESS_STATUS_TYPE_MAP[status] || "info";

  return {
    getStatusType,
    getStatusEffect,
    getStatusBadgeType,
    getStatusIcon,
    getDeviceStatusType,
    getActionButtonType,
    getActionIcon,
    formatDateTime,
    formatPrice,
    getDeviceCount,
    getExpressStatusType,
  };
}
