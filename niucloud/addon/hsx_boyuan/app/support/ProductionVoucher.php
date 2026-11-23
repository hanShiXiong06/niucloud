<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\support;

/** Permission to copy a confirmed ERP accounting voucher. Never authorizes financial commands. */
final class ProductionVoucher
{
    public const ENDPOINT='https://open-api.139erp.com/api';
    public static function profile(array $p): bool
    {
        return ($p['protocol']??'')==='openapi_v153' && ($p['endpoint']??'')===self::ENDPOINT
            && ($p['gateway_path']??'')==='/gateway.do' && !empty($p['account_id']) && !empty($p['connection_key'])
            && !empty($p['app_id']) && !empty($p['app_secret_cipher']) && !empty($p['probe_at']) && !empty($p['contract_confirmed'])
            && trim((string)($p['acceptance_ref']??''))!=='';
    }
    public static function released(array $p): bool
    {
        $r=$p['voucher_release']??[];
        return self::profile($p) && ($r['target']??null)===$p['connection_key'] && ($r['account']??null)===$p['account_id']
            && (int)($r['uid']??0)>0 && (int)($r['at']??0)>0;
    }
    public static function ready(array $config,array $p): bool
    {
        return ($config['mode']??'')==='production' && ($config['purchase_mode']??'')==='manual' && self::profile($p)
            && !empty($config['enabled']) && !empty($config['purchase_enabled']) && empty($config['paused'])
            && empty($config['finance_enabled']) && empty($config['uat_payment_enabled']);
    }
    public static function frozen(array $r,array $p): bool
    {
        return self::released($p) && ($r['scope']??'')==='confirmed_erp_voucher' && ($r['stage']??'')==='frozen'
            && ($r['mode']??'')==='production' && ($r['connection_key']??null)===$p['connection_key']
            && ($r['target_account']??null)===$p['account_id'] && ($r['uat_registration_experiment']??null)===false
            && is_int($r['source_settlement_id']??null) && $r['source_settlement_id']>0 && is_int($r['source_task_id']??null) && $r['source_task_id']>0
            && (bool)preg_match('/^[a-f0-9]{64}$/D',(string)($r['source_snapshot_hash']??''));
    }
}
