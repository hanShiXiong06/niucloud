<?php
// +----------------------------------------------------------------------
// | 二手商城 phone_shop · 商品详情内容清洗
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\service\core\goods;

/**
 * 商品详情承载面向客户的卖点、售后、购买说明及商品实拍图。
 *
 * 质检结果使用 goods.qc_report 独立存储，并由商品详情质检组件渲染。
 * 这里同时拦截历史版本误写进 goods_desc 的结构化质检 JSON，避免技术字段
 * 直接暴露给用户。
 */
class CoreGoodsDescriptionService
{
    public function sanitize(?string $description): string
    {
        $description = trim((string)$description);
        if ($description === '') return '';

        if ($this->containsTechnicalQcPayload($description)) {
            return '';
        }

        return $description;
    }

    public function defaultDescription(): string
    {
        return '<p>本商品为一机一物，具体成色、配置与交付信息请以页面展示及下单前确认为准。</p>';
    }

    /** ERP 上架时把实拍图同时放入详情；只引用原图片，不重新上传或复制文件。 */
    public function withProductImages(?string $description, array $images): string
    {
        $description = $this->sanitize($description);
        if ($description === '') $description = $this->defaultDescription();

        // 保留人工详情；已有的同一张图片不重复追加，重试结果保持一致。
        $seen = [];
        preg_match_all('/<img\b[^>]*\ssrc\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $description, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $url = $this->productImageUrl($match[1] ?: ($match[2] ?? '') ?: ($match[3] ?? ''));
            if ($url !== '') $seen[$url] = true;
        }

        $imageHtml = [];
        foreach ($images as $image) {
            if (!is_string($image)) continue;
            $url = $this->productImageUrl($image);
            if ($url === '' || isset($seen[$url])) continue;
            $seen[$url] = true;
            $src = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $imageHtml[] = '<p><img src="' . $src . '" alt="商品实拍图" style="display:block;width:100%;max-width:100%;height:auto;" /></p>';
        }
        return $description . ($imageHtml === [] ? '' : "\n" . implode("\n", $imageHtml));
    }

    private function productImageUrl(string $image): string
    {
        $image = trim(html_entity_decode($image, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($image === '' || preg_match('/[<>\x00-\x1f\x7f\\\\]/', $image)) return '';
        if (str_starts_with($image, '//')) $image = 'https:' . $image;
        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $image)) {
            return preg_match('#^https?://#i', $image) && parse_url($image, PHP_URL_HOST) ? $image : '';
        }
        // 富文本不经过前端 img()，本地上传路径必须转成完整地址才能在小程序展示。
        if (!preg_match('#^/?(?:upload|uploads|attachment|static|addon)/#i', $image)) return '';
        return get_file_url(ltrim($image, '/'));
    }

    protected function containsTechnicalQcPayload(string $description): bool
    {
        $plain = html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $decoded = json_decode(trim($plain), true);
        if (is_array($decoded) && (
            isset($decoded['result_items'])
            || isset($decoded['check_result'])
            || isset($decoded['report'])
            || isset($decoded['source_plugin'])
        )) {
            return true;
        }

        $patterns = [
            '/["\']?source_plugin["\']?\s*[:：]/i',
            '/["\']?source_device_id["\']?\s*[:：]/i',
            '/["\']?result_items["\']?\s*[:：]/i',
            '/["\']?template_id["\']?\s*[:：]/i',
            '/["\']?synced_at["\']?\s*[:：]/i',
            '/["\']?inspector["\']?\s*[:：]/i',
            '/["\']?severity_summary["\']?\s*[:：]/i',
            '/["\']?abnormal_items["\']?\s*[:：]/i',
        ];
        $matched = 0;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $plain)) $matched++;
            if ($matched >= 2) return true;
        }

        return false;
    }
}
