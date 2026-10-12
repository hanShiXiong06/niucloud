<?php
declare(strict_types=1);

namespace addon\hsx_kingdee\app\gateway;

final class WebApiClient
{
    public function __construct(private EndpointPolicy $endpoint, private array $config, private string $secret, private Transport $transport) {}

    public function queryExact(string $form, array $fields, array $equals, int $limit = 10): array
    {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,79}$/D', $form) || !$fields || count($fields) > 20 || !$equals || count($equals) > 10
            || $limit < 1 || $limit > 100) throw new ApiFailure('query_invalid', '查询条件或查询范围不合法');
        foreach (array_merge($fields, array_keys($equals)) as $field) {
            if (!is_string($field) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_.]{0,99}$/D', $field)) {
                throw new ApiFailure('query_invalid', '查询字段不合法');
            }
        }
        $filters = [];
        foreach ($equals as $field => $value) {
            if (!is_string($value) || strlen($value) > 255 || preg_match('/[\x00-\x1F\x7F\\\\]/', $value)) {
                throw new ApiFailure('query_invalid', '查询值不合法');
            }
            $filters[] = $field . " = '" . str_replace("'", "''", $value) . "'";
        }
        $data = [
            'FormId' => $form, 'FieldKeys' => implode(',', $fields), 'FilterString' => implode(' AND ', $filters),
            'OrderString' => '', 'TopRowCount' => 0, 'StartRow' => 0, 'Limit' => $limit,
        ];
        $result = OfficialSdk::query($data, $this->endpoint, $this->config, $this->secret, $this->transport);
        // ExecuteBillQuery returns a matrix; errors can instead be nested objects, even with HTTP 200.
        if ($result !== array_values($result) || count($result) > $limit) throw new ApiFailure('query_rejected', '金蝶查询未通过，请核查第三方授权、表单权限及字段配置');
        foreach ($result as $row) {
            if (!is_array($row) || $row !== array_values($row) || count($row) !== count($fields)) throw new ApiFailure('query_rejected', '金蝶查询未通过或字段结构不一致，请核查接口权限与字段配置');
            foreach ($row as $value) if (!is_scalar($value) && $value !== null) throw new ApiFailure('query_rejected', '金蝶查询返回业务错误，未将其识别为成功');
        }
        return $result;
    }
}
