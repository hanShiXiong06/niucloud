<?php

namespace addon\phone_shop\app\upgrade\v165;

use app\model\diy\Diy;
use think\facade\Log;

/**
 * 将二手机商城 DIY 页面、组件和链接迁移到 Phone 独立命名空间。
 *
 * shop 与 phone_shop 同时安装时，两套字典不能共用 SHOP_* / DIY_SHOP_* 标识。
 * 这里只迁移能够从页面内容确认属于 phone_shop 的记录，避免改写 shop 页面。
 */
class Upgrade
{
    private array $componentNameMap = [
        'GoodsCoupon' => 'PhoneGoodsCoupon',
        'GoodsList' => 'PhoneGoodsList',
        'ManyGoodsList' => 'PhoneManyGoodsList',
        'ShopExchangeGoods' => 'PhoneShopExchangeGoods',
        'ShopExchangeInfo' => 'PhoneShopExchangeInfo',
        'ShopGoodsDetailAttr' => 'PhoneShopGoodsDetailAttr',
        'ShopGoodsDetailBasicInfo' => 'PhoneShopGoodsDetailBasicInfo',
        'ShopGoodsDetailBottom' => 'PhoneShopGoodsDetailBottom',
        'ShopGoodsDetailDesc' => 'PhoneShopGoodsDetailDesc',
        'ShopGoodsDetailEvaluate' => 'PhoneShopGoodsDetailEvaluate',
        'ShopGoodsDetailPurchaseService' => 'PhoneShopGoodsDetailPurchaseService',
        'ShopGoodsDetailQc' => 'PhoneShopGoodsDetailQc',
        'ShopGoodsDetailSow' => 'PhoneShopGoodsDetailSow',
        'ShopGoodsHot' => 'PhoneShopGoodsHot',
        'ShopGoodsRanking' => 'PhoneShopGoodsRanking',
        'ShopGoodsRecommend' => 'PhoneShopGoodsRecommend',
        'ShopMemberBarcode' => 'PhoneShopMemberBarcode',
        'ShopMemberInfo' => 'PhoneShopMemberInfo',
        'ShopNewcomer' => 'PhoneShopNewcomer',
        'ShopOrderInfo' => 'PhoneShopOrderInfo',
        'ShopSearch' => 'PhoneShopSearch',
        'SingleRecommend' => 'PhoneSingleRecommend',
    ];

    private array $editorPathMap = [
        'edit-goods-coupon' => 'edit-phone-goods-coupon',
        'edit-goods-list' => 'edit-phone-goods-list',
        'edit-many-goods-list' => 'edit-phone-many-goods-list',
        'edit-shop-exchange-goods' => 'edit-phone-shop-exchange-goods',
        'edit-shop-exchange-info' => 'edit-phone-shop-exchange-info',
        'edit-shop-goods-detail-attr' => 'edit-phone-shop-goods-detail-attr',
        'edit-shop-goods-detail-basic-info' => 'edit-phone-shop-goods-detail-basic-info',
        'edit-shop-goods-detail-bottom' => 'edit-phone-shop-goods-detail-bottom',
        'edit-shop-goods-detail-desc' => 'edit-phone-shop-goods-detail-desc',
        'edit-shop-goods-detail-evaluate' => 'edit-phone-shop-goods-detail-evaluate',
        'edit-shop-goods-detail-purchase-service' => 'edit-phone-shop-goods-detail-purchase-service',
        'edit-shop-goods-detail-qc' => 'edit-phone-shop-goods-detail-qc',
        'edit-shop-goods-detail-sow' => 'edit-phone-shop-goods-detail-sow',
        'edit-shop-goods-hot' => 'edit-phone-shop-goods-hot',
        'edit-shop-goods-ranking' => 'edit-phone-shop-goods-ranking',
        'edit-shop-goods-recommend' => 'edit-phone-shop-goods-recommend',
        'edit-shop-member-barcode' => 'edit-phone-shop-member-barcode',
        'edit-shop-member-info' => 'edit-phone-shop-member-info',
        'edit-shop-newcomer' => 'edit-phone-shop-newcomer',
        'edit-shop-order-info' => 'edit-phone-shop-order-info',
        'edit-shop-search' => 'edit-phone-shop-search',
        'edit-single-recommend' => 'edit-phone-single-recommend',
    ];

    private array $pageIdentityMap = [
        'DIY_SHOP_INDEX' => 'DIY_PHONE_SHOP_INDEX',
        'DIY_SHOP_MEMBER_INDEX' => 'DIY_PHONE_SHOP_MEMBER_INDEX',
        'DIY_SHOP_POINT_INDEX' => 'DIY_PHONE_SHOP_POINT_INDEX',
        'DIY_SHOP_GOODS_DETAIL' => 'DIY_PHONE_SHOP_GOODS_DETAIL',
    ];

    private array $linkIdentityMap = [
        'SHOP_LINK' => 'PHONE_SHOP_LINK',
        'SHOP_INDEX' => 'PHONE_SHOP_INDEX',
        'SHOP_GOODS_CATEGORY' => 'PHONE_SHOP_GOODS_CATEGORY',
        'SHOP_GOODS_LIST' => 'PHONE_SHOP_GOODS_LIST',
        'SHOP_GOODS_RANK' => 'PHONE_SHOP_GOODS_RANK',
        'SHOP_GOODS_SEARCH' => 'PHONE_SHOP_GOODS_SEARCH',
        'SHOP_GOODS_CART' => 'PHONE_SHOP_GOODS_CART',
        'SHOP_COUPON_LIST' => 'PHONE_SHOP_COUPON_LIST',
        'SHOP_MEMBER_INDEX' => 'PHONE_SHOP_MEMBER_INDEX',
        'SHOP_MY_COUPON' => 'PHONE_SHOP_MY_COUPON',
        'SHOP_MY_GOODS_COLLECT' => 'PHONE_SHOP_MY_GOODS_COLLECT',
        'SHOP_MY_GOODS_BROWSE' => 'PHONE_SHOP_MY_GOODS_BROWSE',
        'SHOP_ORDER_LIST' => 'PHONE_SHOP_ORDER_LIST',
        'SHOP_REFUND_LIST' => 'PHONE_SHOP_REFUND_LIST',
        'SHOP_POINT_INDEX' => 'PHONE_SHOP_POINT_INDEX',
        'SHOP_POINT_LIST' => 'PHONE_SHOP_POINT_LIST',
        'SHOP_POINT_ORDER_LIST' => 'PHONE_SHOP_POINT_ORDER_LIST',
        'SHOP_DISCOUNT_LIST' => 'PHONE_SHOP_DISCOUNT_LIST',
        'SHOP_NEWCOMER_LIST' => 'PHONE_SHOP_NEWCOMER_LIST',
        'SHOP_INVOICE_LIST' => 'PHONE_SHOP_INVOICE_LIST',
    ];

    public function handle(): void
    {
        try {
            $this->migrateDiyPages();
        } catch (\Throwable $throwable) {
            Log::write('二手机商城升级v1.6.5迁移DIY命名空间失败，Line：'
                . $throwable->getLine() . '，Message：' . $throwable->getMessage()
                . '，File：' . $throwable->getFile());
        }
    }

    private function migrateDiyPages(): void
    {
        $pageTypes = array_values(array_unique(array_merge(
            array_keys($this->pageIdentityMap),
            array_values($this->pageIdentityMap)
        )));

        $model = new Diy();
        $list = $model
            ->where([ [ 'type', 'in', $pageTypes ], [ 'value', '<>', '' ] ])
            ->field('id,name,type,template,value')
            ->select()
            ->toArray();

        foreach ($list as $item) {
            $data = is_array($item['value'])
                ? $item['value']
                : json_decode((string) $item['value'], true);

            if (!is_array($data) || !$this->isPhoneShopPage($data)) {
                continue;
            }

            $changed = false;
            $this->replaceComponentIdentity($data, $changed);
            $this->replaceDiyIdentity($data, $changed);

            $update = [];
            foreach ([ 'name', 'type', 'template' ] as $field) {
                $value = $item[$field] ?? '';
                if (isset($this->pageIdentityMap[$value])) {
                    $update[$field] = $this->pageIdentityMap[$value];
                }
            }
            if ($changed) {
                $update['value'] = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            if (!empty($update)) {
                $model->where([ [ 'id', '=', $item['id'] ] ])->update($update);
            }
        }
    }

    private function isPhoneShopPage(array $data): bool
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return false;
        }
        foreach ([ 'addon/phone_shop/', '/addon/phone_shop/', 'edit-phone-', 'PhoneGoods', 'PhoneShop', 'PhoneMany', 'PhoneSingle' ] as $marker) {
            if (str_contains($json, $marker)) {
                return true;
            }
        }
        return false;
    }

    private function replaceComponentIdentity(array &$data, bool &$changed): void
    {
        foreach ($data as $key => &$value) {
            if ($key === 'componentName' && is_string($value) && isset($this->componentNameMap[$value])) {
                $value = $this->componentNameMap[$value];
                $changed = true;
            } elseif ($key === 'path' && is_string($value) && isset($this->editorPathMap[$value])) {
                $value = $this->editorPathMap[$value];
                $changed = true;
            } elseif (is_array($value)) {
                $this->replaceComponentIdentity($value, $changed);
            }
        }
        unset($value);
    }

    private function replaceDiyIdentity(array &$data, bool &$changed): void
    {
        $identityMap = array_merge($this->pageIdentityMap, $this->linkIdentityMap);
        foreach ($data as &$value) {
            if (is_string($value) && isset($identityMap[$value])) {
                $value = $identityMap[$value];
                $changed = true;
            } elseif (is_array($value)) {
                $this->replaceDiyIdentity($value, $changed);
            }
        }
        unset($value);
    }
}
