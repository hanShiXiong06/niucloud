<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service;

use app\service\core\weapp\CoreWeappService;
use core\exception\CommonException;

/** Reads this site's official public library through the framework client; never adds/deletes/sends a template. */
final class WeappNoticeLibrary
{
    private ?\Closure $reader;
    public function __construct(?callable $reader = null)
    {
        $this->reader = $reader === null ? null : \Closure::fromCallable($reader);
    }

    public function read(int $site, array $input = []): array
    {
        if ($site <= 0) throw new CommonException('请先选择有效站点');
        $operation = $input['operation'] ?? 'categories';
        if (!is_string($operation) || !in_array($operation, ['categories', 'titles', 'keywords'], true)) throw new CommonException('模板库查询方式无效');
        if ($operation === 'keywords') {
            $tid = $input['tid'] ?? '';
            if ((!is_string($tid) && !is_int($tid)) || !preg_match('/^[1-9][0-9]{0,15}$/D', (string) $tid)) throw new CommonException('请选择微信模板库中的真实模板');
            $response = $this->get($site, 'wxaapi/newtmpl/getpubtemplatekeywords', ['tid' => (string) $tid]);
            $rows = [];
            foreach ($response['data'] ?? [] as $row) {
                if (!is_array($row)) continue;
                $id = $this->positive($row['kid'] ?? null);
                $name = $this->text($row['name'] ?? null, 40);
                $rule = $this->text($row['rule'] ?? null, 30);
                if (!$id || !$name) continue;
                $supported = (bool) preg_match('/^(thing|character_string|time|date|phrase|number)$/D', $rule) && $id <= 999;
                $rows[] = ['kid' => $id, 'name' => $name, 'rule' => $rule, 'keyword' => $supported ? $rule.$id : '', 'supported' => $supported, 'example' => $this->text($row['example'] ?? '', 100)];
            }
            return ['operation' => 'keywords', 'tid' => (string) $tid, 'items' => $rows, 'read_only' => true];
        }
        $categories = $this->categories($site);
        if ($operation === 'categories') return ['operation' => 'categories', 'items' => $categories, 'read_only' => true];
        $category = $this->positive($input['category_id'] ?? null);
        if (!$category || !in_array($category, array_column($categories, 'id'), true)) throw new CommonException('请选择本站小程序已开通的服务类目');
        $start = $this->integer($input['start'] ?? 0, 0, 9999);
        $limit = $this->integer($input['limit'] ?? 30, 1, 30);
        $response = $this->get($site, 'wxaapi/newtmpl/getpubtemplatetitles', ['ids' => (string) $category, 'start' => $start, 'limit' => $limit]);
        $rows = [];
        foreach ($response['data'] ?? [] as $row) {
            if (!is_array($row)) continue;
            $tid = $row['tid'] ?? '';
            $title = $this->text($row['title'] ?? null, 100);
            if ((!is_string($tid) && !is_int($tid)) || !preg_match('/^[1-9][0-9]{0,15}$/D', (string) $tid) || !$title) continue;
            $rows[] = ['tid' => (string) $tid, 'title' => $title, 'type' => (int) ($row['type'] ?? 0)];
        }
        $total = $response['count'] ?? count($rows);
        return ['operation' => 'titles', 'items' => $rows, 'start' => $start, 'limit' => $limit, 'total' => is_numeric($total) ? max(0, (int) $total) : count($rows), 'read_only' => true];
    }

    private function categories(int $site): array
    {
        $response = $this->get($site, 'wxaapi/newtmpl/getcategory', []);
        $rows = [];
        foreach ($response['data'] ?? [] as $row) {
            if (!is_array($row)) continue;
            $id = $this->positive($row['id'] ?? null);
            $name = $this->text($row['name'] ?? null, 80);
            if ($id && $name) $rows[$id] = ['id' => $id, 'name' => $name];
        }
        return array_values($rows);
    }

    private function get(int $site, string $path, array $query): array
    {
        try {
            $response = $this->reader ? ($this->reader)($site, $path, $query) : CoreWeappService::appApiClient($site)->get($path, ['query' => $query])->toArray(false);
        } catch (\Throwable $error) {
            throw new CommonException('微信模板库暂未读取成功，请检查本站小程序配置及接口权限后重试；没有修改模板或发送消息');
        }
        if (!is_array($response) || !is_array($response['data'] ?? null)) {
            $code = is_array($response) && is_numeric($response['errcode'] ?? null) ? (int) $response['errcode'] : 0;
            throw new CommonException($code ? '微信模板库返回错误（代码 '.$code.'），请核对本站小程序的接口权限、IP白名单及服务类目' : '微信模板库返回不完整，请稍后重试；没有修改模板或发送消息');
        }
        if ((int) ($response['errcode'] ?? 0) !== 0) throw new CommonException('微信模板库返回错误（代码 '.(int) $response['errcode'].'），请核对本站小程序配置及接口权限');
        return $response;
    }
    private function positive($value): int
    {
        return (is_int($value) || is_string($value)) && preg_match('/^[1-9][0-9]{0,8}$/D', (string) $value) ? (int) $value : 0;
    }
    private function integer($value, int $min, int $max): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[0-9]+$/D', (string) $value) || (int) $value < $min || (int) $value > $max) throw new CommonException('模板库分页参数无效');
        return (int) $value;
    }
    private function text($value, int $length): string
    {
        return is_string($value) ? mb_substr(trim(strip_tags($value)), 0, $length) : '';
    }
}
