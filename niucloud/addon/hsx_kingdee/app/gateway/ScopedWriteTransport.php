<?php
declare(strict_types=1);

namespace addon\hsx_kingdee\app\gateway;

/** A command connection is restricted to one immutable form/number, never arbitrary IDs. */
final class ScopedWriteTransport implements Transport
{
    private const PREFIX = 'Kingdee.BOS.WebApi.ServicesStub.DynamicFormService.';

    public function __construct(private EndpointPolicy $endpoint, private string $form, private string $number,
        private string $numberField, private string $identityField, private string $createOrgId)
    {
        if (!preg_match('/^HSX[A-Z0-9_-]{12,60}$/D', $number) || !preg_match('/^[1-9][0-9]*$/D', $createOrgId)
            || !in_array([$form, $numberField, $identityField], [
                ['BD_MATERIAL', 'FNumber', 'FMaterialId'], ['STK_InStock', 'FBillNo', 'FID'],
                ['SAL_OUTSTOCK', 'FBillNo', 'FID'], ['PUR_MRB', 'FBillNo', 'FID'], ['SAL_RETURNSTOCK', 'FBillNo', 'FID'],
            ], true)) throw new ApiFailure('write_scope_invalid', '写入范围必须限定到一张明确的集成单据');
    }

    public function post(string $service, array $payload, array $headers): array
    {
        $parameters = $payload['parameters'] ?? null;
        if (array_keys($payload) !== ['parameters'] || !is_array($parameters) || count($parameters) !== 2
            || $parameters[0] !== $this->form || !is_array($parameters[1])) $this->reject();
        $data = $parameters[1];
        if ($service === self::PREFIX . 'Save') {
            $model = $data['Model'] ?? [];
            if (($model[$this->numberField] ?? '') !== $this->number || (int)($model[$this->identityField] ?? 0) !== 0
                || ($data['ValidateFlag'] ?? null) !== true || ($data['IsVerifyBaseDataField'] ?? null) !== true
                || ($data['IsAutoSubmitAndAudit'] ?? false) !== false
                || ($data['IgnoreInterationFlag'] ?? '') !== '' || ($data['InterationFlags'] ?? '') !== '') $this->reject();
        } elseif (in_array($service, [self::PREFIX . 'Submit', self::PREFIX . 'Audit'], true)) {
            if ($data !== ['Numbers' => [$this->number], 'CreateOrgId' => (int)$this->createOrgId]) $this->reject();
        } elseif ($service === self::PREFIX . 'View') {
            if ($data !== ['Number' => $this->number, 'CreateOrgId' => (int)$this->createOrgId]) $this->reject();
        } else $this->reject();
        return (new SecureCurlTransport($this->endpoint, [$service]))->post($service, $payload, $headers);
    }

    private function reject(): void
    {
        throw new ApiFailure('write_scope_rejected', '请求超出本张集成单据授权范围，未发送');
    }
}
