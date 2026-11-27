<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3).'/vendor/autoload.php';
use addon\hsx_lead\app\service\cooperation\{PromotionRules,Rules};
use core\exception\CommonException;
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok)throw new RuntimeException($label);
    $checks++;
};
$deny = static function (callable $run, string $label) use ($check): void {
    try {
        $run();
    } catch (CommonException|TypeError $error) {
        $check(true, $label);
        return;
    }throw new RuntimeException('Not rejected: '.$label);
};
$config = PromotionRules::defaults();
$check(!$config['enabled'] && array_filter($config['sources'], static fn (array $row): bool => $row['enabled']) === [], 'promotion defaults closed');
$check(PromotionRules::source('commission') === 'maintenance', 'cooperation commission maps to platform fee source');
$check(array_keys($config['sources']) === ['membership', 'maintenance'] && PromotionRules::source('membership') === 'membership', 'only current membership and maintenance income sources exist');
$check(PromotionRules::source('publishing') === '' && PromotionRules::source('certification') === '', 'removed fee purposes cannot generate promotion rewards');
$check(PromotionRules::source('unknown') === '', 'unknown sources do not earn');
$check(PromotionRules::reward(990, '20.00') === 198, 'maintenance share 9.90 x 20% = 1.98');
$check(PromotionRules::reward(1, '50.00') === 0, 'fractional cents round down');
$check(PromotionRules::reward(9999999999, '100.00') === 9999999999, 'framework upper bound exact integer cents');
foreach (['100.01', '99999.00', '-1', '1e2', 1.2] as $bad)$deny(static fn () => PromotionRules::reward(100, $bad), 'invalid rates rejected');
foreach ([-1, 10000000000] as $bad)$deny(static fn () => PromotionRules::reward($bad, '10.00'), 'invalid bases rejected');
foreach (['yes', [], null] as $bad)$deny(static fn () => PromotionRules::bool($bad), 'nonexplicit switches rejected');
$deny(static fn () => PromotionRules::config(['settlement_days' => '1.5']), 'fractional settlement day rejected');
$deny(static fn () => PromotionRules::config(['eligibility_months' => '121']), 'eligibility out of bounds rejected');
$deny(static fn () => PromotionRules::config(['sources' => 'bad']), 'invalid source map rejected');
$config['enabled'] = true;
$config['sources']['membership'] = ['enabled' => true, 'normal_rate' => '10.00', 'provider_rate' => '20.00', 'renewal' => false];
$at = strtotime('2026-01-31 12:00:00');
$config['eligibility_months'] = 1;
$check(PromotionRules::eligible($config, 'membership', false, $at, $at + 60), 'first purchase within window earns');
$check(!PromotionRules::eligible($config, 'membership', true, $at, $at + 60), 'renewal does not earn when disabled');
$check(!PromotionRules::eligible($config, 'maintenance', false, $at, $at + 60), 'disabled source does not earn');
$check(!PromotionRules::eligible($config, 'membership', false, $at, Rules::addMonths($at, 1)), 'expiry boundary excludes new payment');
$config['eligibility_months'] = 0;
$check(PromotionRules::eligible($config, 'membership', false, $at, $at + 10 * 365 * 86400), 'zero eligibility month means permanent');
$normalized = PromotionRules::config($config);
$check($normalized['sources']['maintenance']['renewal'] === true, 'maintenance always per paid business');
$deny(static fn () => PromotionRules::config(['sources' => ['publishing' => ['enabled' => true, 'rate' => '20.00']]]), 'removed income source configuration is rejected');
foreach (['publishing', 'certification', 'unknown_future_source'] as $source) {
    try {
        PromotionRules::config(['sources' => [$source => ['enabled' => true, 'normal_rate' => '10.00', 'provider_rate' => '20.00']]]);
        throw new RuntimeException('Retired or unknown source was accepted');
    } catch (CommonException $error) {
        $check(str_contains($error->getMessage(), '刷新页面后重新核对') && str_contains($error->getMessage(), '不含押金、经验费'), 'source mismatch gives an actionable explanation without converting old rates');
    }
}
$deny(static fn () => PromotionRules::config(['sources' => ['publishing' => ['normal_rate' => '10.00'], 'certification' => ['normal_rate' => '20.00'], 'membership' => ['normal_rate' => '1.00']]]), 'conflicting old sources never merge into or replace an explicit membership service rate');
$legacy = ['enabled' => true, 'sources' => ['publishing' => ['enabled' => true, 'normal_rate' => '11.00', 'provider_rate' => '22.00'], 'certification' => ['enabled' => true, 'normal_rate' => '33.00', 'provider_rate' => '44.00'], 'maintenance' => ['enabled' => true, 'normal_rate' => '7.00', 'provider_rate' => '13.00']]];
$unchanged = $legacy;
$read = PromotionRules::readConfiguration($legacy);
$check($legacy === $unchanged, 'reading a known old configuration does not mutate its source data');
$check(array_keys($read['sources']) === ['membership', 'maintenance'] && $read['requires_review'] && $read['ignored_source_keys'] === ['publishing', 'certification'], 'known old sources are excluded only from the effective read projection and explicitly reported');
$check(!$read['sources']['membership']['enabled'] && $read['sources']['membership']['normal_rate'] === '0.00' && $read['sources']['membership']['provider_rate'] === '0.00', 'unconfigured membership service sharing stays off instead of reusing or adding retired rates');
$check($read['sources']['maintenance']['enabled'] && $read['sources']['maintenance']['normal_rate'] === '7.00' && $read['sources']['maintenance']['provider_rate'] === '13.00', 'existing explicit maintenance rates remain unchanged');
$check(str_contains($read['review_message'], '未自动换算或合并') && str_contains($read['review_message'], '确认后再保存'), 'read response explains the pending manual configuration review');
$legacy['sources']['membership'] = ['enabled' => true, 'normal_rate' => '5.00', 'provider_rate' => '8.00', 'renewal' => true];
$explicit = PromotionRules::readConfiguration($legacy);
$check($explicit['sources']['membership']['normal_rate'] === '5.00' && $explicit['sources']['membership']['provider_rate'] === '8.00' && $explicit['sources']['membership']['renewal'], 'explicit current membership rates are preserved even alongside retired sources');
$current = PromotionRules::readConfiguration(PromotionRules::config($read));
$check(!$current['requires_review'] && $current['ignored_source_keys'] === [] && $current['review_message'] === '', 'a strict current-source save clears the review metadata');
$deny(static fn () => PromotionRules::readConfiguration(['sources' => ['unknown_future_source' => []]]), 'unrecognized persisted source is not silently dropped');
$deny(static fn () => PromotionRules::readConfiguration(['sources' => ['publishing' => [], 'membership' => ['normal_rate' => '101.00']]]), 'invalid current rates still block reads instead of silently disabling or changing a real current rule');
$tiers = PromotionRules::config(['sources' => ['maintenance' => ['enabled' => true, 'normal_rate' => '10.00', 'provider_rate' => '30.00']]]);
$check(PromotionRules::rateFor($tiers, 'maintenance', 'normal') === '10.00' && PromotionRules::rateFor($tiers, 'maintenance', 'provider') === '30.00', 'two roles choose exactly one independently configured rate');
$check(!isset($tiers['sources']['maintenance']['rate']), 'ambiguous single-rate alias is not returned');
$deny(static fn () => PromotionRules::config(['sources' => ['membership' => ['normal_rate' => '100.01']]]), 'normal rate above actual base is rejected');
$deny(static fn () => PromotionRules::config(['sources' => ['membership' => ['provider_rate' => '100.01']]]), 'provider rate above actual base is rejected');
$deny(static fn () => PromotionRules::rateFor($tiers, 'maintenance', 'unknown'), 'unknown promoter role cannot choose a rate');
$tiers['sources']['membership']['level_rates'] = ['ML701' => '13.50', 'ML702' => '0.00'];
$tiers = PromotionRules::config($tiers);
$check(PromotionRules::rateFor($tiers, 'membership', 'normal', 'ML701') === '13.50', 'dynamic paid tier may override normal rate');
$check(PromotionRules::rateFor($tiers, 'membership', 'normal', 'ML702') === '0.00', 'explicit tier zero never falls back');
$check(PromotionRules::rateFor($tiers, 'membership', 'normal', 'ML999') === $tiers['sources']['membership']['normal_rate'], 'unconfigured tier explicitly inherits ordinary rate');
$check(PromotionRules::rateFor($tiers, 'membership', 'provider', 'ML701') === $tiers['sources']['membership']['provider_rate'], 'provider wins independently without stacking tier');
foreach ([['V1' => '10.00'], [0 => '10.00'], ['ML0' => '10.00'], ['ML701' => '100.01'], ['ML701' => 1.2]] as $bad)$deny(static fn () => PromotionRules::config(['sources' => ['membership' => ['level_rates' => $bad]]]), 'invalid dynamic tier map is rejected');
$retained = PromotionRules::config(['sources' => ['membership' => ['level_rates' => ['ML4294967295' => '12.00']]]]);
$check($retained['sources']['membership']['level_rates']['ML4294967295'] === '12.00', 'valid stable IDs are retained without deleting disabled historical tier configuration');
$two = PromotionRules::defaults();
$check($two['max_level'] === 1 && $two['sources']['membership']['second_level']['normal_rate'] === '0.00', 'two-level stays off and zero until explicitly enabled');
$two['max_level'] = 2;
$two['sources']['membership']['normal_rate'] = '10.00';
$two['sources']['membership']['provider_rate'] = '30.00';
$two['sources']['membership']['second_level'] = ['normal_rate' => '5.00', 'provider_rate' => '20.00', 'level_rates' => ['ML701' => '15.00']];
$two = PromotionRules::config($two);
$check(PromotionRules::rateFor($two, 'membership', 'normal', '', 1) === '10.00' && PromotionRules::rateFor($two, 'membership', 'provider', '', 2) === '20.00', 'each recipient independently selects its own role rate on the same base');
$check(PromotionRules::rateFor($two, 'membership', 'normal', 'ML701', 2) === '15.00', 'level2 supports independent paid-tier override');
$two['max_level'] = 1;
$check(PromotionRules::rateFor($two, 'membership', 'provider', '', 2) === '0.00', 'disabling level2 suppresses its stored rate without deleting configuration');
$deny(static fn () => PromotionRules::config(['max_level' => 3]), 'unbounded commission depth is rejected');
$deny(static fn () => PromotionRules::config(['max_level' => 2, 'sources' => ['membership' => ['normal_rate' => '60.00', 'provider_rate' => '10.00', 'second_level' => ['normal_rate' => '41.00']]]]), 'any cross-identity max sum above real base is rejected');
$deny(static fn () => PromotionRules::config(['max_level' => 2, 'sources' => ['membership' => ['normal_rate' => '10.00', 'second_level' => ['level_rates' => ['ML701' => '101.00']]]]]), 'bad second-tier percentage is rejected');
$deny(static fn () => PromotionRules::rateFor($two, 'membership', 'normal', '', 3), 'unknown reward level cannot choose a rate');
echo 'PASS '.$checks." pure promotion rule checks; no network, database, payment or file mutation.\n";
