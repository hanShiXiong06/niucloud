<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\express;

use addon\hsx_recycle\app\model\express\ExpressAddressBook;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 快递常用地址服务
 */
class ExpressAddressBookService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new ExpressAddressBook();
    }

    public function getList(array $where = []): array
    {
        $where['site_id'] = $this->site_id;
        $keyword = trim((string)($where['keyword'] ?? ''));

        $query = $this->model
            ->withSearch(['site_id', 'address_type'], $where)
            ->where('status', '=', 1);

        if ($keyword !== '') {
            $query->where(function ($query) use ($keyword) {
                $query->whereLike('name|mobile|province|city|district|address|tag', '%' . $keyword . '%');
            });
        }

        return $query
            ->order('is_top desc,is_default desc,sort desc,update_at desc,id desc')
            ->limit(200)
            ->select()
            ->toArray();
    }

    public function saveAddress(array $data): int
    {
        return $this->saveAddressForSite($this->site_id, $data);
    }

    public function saveAddressForSite(int $siteId, array $data): int
    {
        $record = $this->normalizeAddress($data);
        $record['site_id'] = $siteId;

        if (!empty($record['is_default'])) {
            $this->unsetDefault($siteId, $record['address_type']);
        }

        $id = (int)($data['id'] ?? 0);
        if ($id > 0) {
            $this->model->where([['id', '=', $id], ['site_id', '=', $siteId]])->update($record);
            return $id;
        }

        $exists = $this->model->where([
            ['site_id', '=', $siteId],
            ['address_type', '=', $record['address_type']],
            ['mobile', '=', $record['mobile']],
            ['province', '=', $record['province']],
            ['city', '=', $record['city']],
            ['district', '=', $record['district']],
            ['address', '=', $record['address']],
        ])->find();

        if ($exists) {
            $exists->save($record);
            return (int)$exists['id'];
        }

        $res = $this->model->create($record);
        return (int)$res->id;
    }

    public function del(int $id): bool
    {
        return (bool)$this->model
            ->where([['id', '=', $id], ['site_id', '=', $this->site_id]])
            ->update(['status' => 0]);
    }

    public function setDefault(int $id): bool
    {
        $address = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id], ['status', '=', 1]])->find();
        if (!$address) {
            throw new CommonException('地址不存在');
        }

        $this->unsetDefault($this->site_id, (string)$address['address_type']);
        $address->save(['is_default' => 1]);
        return true;
    }

    public function setTop(int $id, int $isTop): bool
    {
        return (bool)$this->model
            ->where([['id', '=', $id], ['site_id', '=', $this->site_id], ['status', '=', 1]])
            ->update(['is_top' => $isTop ? 1 : 0]);
    }

    private function unsetDefault(int $siteId, string $addressType): void
    {
        $this->model->where([
            ['site_id', '=', $siteId],
            ['address_type', '=', $addressType],
        ])->update(['is_default' => 0]);
    }

    private function normalizeAddress(array $data): array
    {
        $addressType = (string)($data['address_type'] ?? '');
        if (!in_array($addressType, ['sender', 'receiver'], true)) {
            throw new CommonException('地址类型错误');
        }

        $record = [
            'address_type' => $addressType,
            'name' => trim((string)($data['name'] ?? '')),
            'mobile' => trim((string)($data['mobile'] ?? '')),
            'province' => trim((string)($data['province'] ?? '')),
            'city' => trim((string)($data['city'] ?? '')),
            'district' => trim((string)($data['district'] ?? '')),
            'address' => trim((string)($data['address'] ?? '')),
            'tag' => trim((string)($data['tag'] ?? '')),
            'is_default' => (int)($data['is_default'] ?? 0),
            'is_top' => (int)($data['is_top'] ?? 0),
            'sort' => (int)($data['sort'] ?? 0),
            'status' => 1,
        ];

        foreach (['name' => '姓名', 'mobile' => '手机号', 'province' => '省份', 'city' => '城市', 'district' => '区县', 'address' => '详细地址'] as $field => $label) {
            if ($record[$field] === '') {
                throw new CommonException("请填写{$label}");
            }
        }

        return $record;
    }
}
