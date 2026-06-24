<?php

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\core\CardService;
use core\base\BaseApiController;

class Card extends BaseApiController
{
    public function lists()
    {
        return success((new CardService())->getList());
    }

    public function detail()
    {
        $card_type = (string)$this->request->param('card_type', '');
        return success((new CardService())->getDetail($card_type));
    }

    public function createOrder()
    {
        $card_type = (string)$this->request->param('card_type', '');
        return success((new CardService())->createOrder($card_type));
    }

    public function my()
    {
        return success((new CardService())->getMyCards());
    }

    public function useLogs()
    {
        $card_type = (string)$this->request->param('card_type', '');
        $page = (int)$this->request->param('page', 1);
        $limit = (int)$this->request->param('limit', 20);
        return success((new CardService())->getUseLogs($card_type, $page, $limit));
    }
}
