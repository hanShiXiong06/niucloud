-- ============================================================
-- 存量修数: 修复 current_cost=0 但 purchase_cost>0 的设备
-- ------------------------------------------------------------
-- 背景: 历史上"快速过账 / 跳过确认入库"的设备, 建资产时 current_cost 被写死为 0,
--       未从 purchase_cost(进货成本)复制, 导致成本=0、毛利虚高(售价-0)。
--       代码侧已修(建资产即写 current_cost=purchase_cost; 快速过账翻状态时显式落成本),
--       此脚本用于订正已存在的历史数据。
--
-- 注意:
--   1. 表前缀按本站为 saas_ ; 若你的库前缀不同, 全局替换 saas_ 为你的前缀。
--   2. 代卖(consignment)设备 purchase_cost 本就为 0, 条件 purchase_cost>0 已自动排除, 不受影响。
--   3. 先跑【第一步预览】确认范围, 再跑【第二步更新】。建议先备份 erp_asset 表。
-- ============================================================

-- 第一步: 预览将被修正的设备(不改数据)
SELECT
    id, asset_no, imei, model, inventory_status,
    purchase_cost AS 进货成本, current_cost AS 当前成本_将改为进货成本,
    FROM_UNIXTIME(stock_in_at) AS 入库时间
FROM saas_erp_asset
WHERE current_cost <= 0
  AND purchase_cost > 0
ORDER BY id DESC;

-- 第二步: 执行修正(确认预览无误后再运行)
-- UPDATE saas_erp_asset
-- SET current_cost = purchase_cost,
--     update_at    = UNIX_TIMESTAMP()
-- WHERE current_cost <= 0
--   AND purchase_cost > 0;

-- 第三步(可选): 复核——修正后应无残留
-- SELECT COUNT(*) AS 残留台数 FROM saas_erp_asset WHERE current_cost <= 0 AND purchase_cost > 0;
