<?php
namespace SalePaymentRobokassa;

class Gateway extends \Sale\PaymentGateway\GatewayAbstract
{
    public $orderId = null;
    public $goodCode = "2";
    public $currency = "";
    public $culture = "ru";
    public $encoding = "utf-8";
    
    const URL = 'https://auth.robokassa.ru/Merchant/Index.aspx';
    const URL_FISCAL = 'https://ws.roboxchange.com/RoboFiscal/Receipt/Attach';

    public static function getInfo()
    {
        $t = \Cetera\Application::getInstance()->getTranslator();

        return array(
            'name' => 'Robokassa',
            'description' => '',
            'icon' => '/cms/plugins/sale-payment-robokassa/images/icon.gif',
            'params' => array(
                [
                    "xtype" => 'textfield',
                    "labelWidth" => 200,
                    "width" => 400,
                    "fieldLabel" => $t->_('Идентификатор магазина'),
                    "name" => 'shop_login',
                ],
                [
                    "xtype" => 'textfield',
                    "labelWidth" => 200,
                    "width" => 400,
                    "fieldLabel" => $t->_('Пароль #1'),
                    "name" => 'shop_password1',
                ],
                [
                    "xtype" => 'textfield',
                    "labelWidth" => 200,
                    "width" => 400,
                    "fieldLabel" => $t->_('Пароль #2'),
                    "name" => 'shop_password2',
                ],
                [
                    "xtype" => 'checkbox',
                    "name" => 'test_mode',
                    "boxLabel" => $t->_('Тестовый режим'),
                    "inputValue" => 1,
                    "uncheckeDvalue" => 0
                ],
                [
                    "xtype" => 'textfield',
                    "labelWidth" => 200,
                    "width" => 400,
                    "fieldLabel" => $t->_('Тестовый пароль #1'),
                    "name" => 'test_shop_password1',
                ],
                [
                    "xtype" => 'textfield',
                    "labelWidth" => 200,
                    "width" => 400,
                    "fieldLabel" => $t->_('Тестовый пароль #2'),
                    "name" => 'test_shop_password2',
                ],
				[
					'name'       => 'reciept',
					'xtype'      => 'checkbox',
					'fieldLabel' => 'Передача корзины товаров (кассовый чек 54-ФЗ)',
				],
                [
                    'name'       => 'payment_object',
                    'xtype'      => 'combobox',
                    'fieldLabel' => 'Тип оплачиваемой позиции',
                    'value'      => 1,
                    'store'      => [
                        ["commodity", 'товар'],
                        ["excise", 'подакцизный товар'],
                        ["job", 'работа'],
                        ["service", 'услуга'],
                        ["payment", 'платёж'],
                        ["agent_commission", 'агентское вознаграждение'],
                    ],
                ],                 
                [
                    'name'       => 'payment_method',
                    'xtype'      => 'combobox',
                    'fieldLabel' => 'Тип оплаты',
                    'value'      => 1,
                    'store'      => [
                        ["full_prepayment", 'полная предварительная оплата до момента передачи предмета расчёта'],
                        ["prepayment", 'частичная предварительная оплата до момента передачи предмета расчёта'],
                        ["advance", 'аванс'],
                        ["full_payment", 'полная оплата в момент передачи предмета расчёта'],
                        ["partial_payment", 'частичная оплата предмета расчёта в момент его передачи с последующей оплатой в кредит'],
                        ["credit", 'передача предмета расчёта без его оплаты в момент его передачи с последующей оплатой в кредит'],
                        ["credit_payment", 'оплата предмета расчёта после его передачи с оплатой в кредит'],
                    ],
                ],                
                [
                    'name'       => 'sno',
                    'xtype'      => 'combobox',
                    'fieldLabel' => 'Система налогообложения',
                    'value'      => 0,
                    'store'      => [
                        ["osn", 'общая СН'],
                        ["usn_income", 'упрощенная СН (доходы)'],
                        ["usn_income_outcome", 'упрощенная СН (доходы минус расходы)'],
                        ["envd", 'единый налог на вмененный доход'],
                        ["esn", 'единый сельскохозяйственный налог'],
                        ["patent", 'патентная СН'],
                    ],
                ],
                [
                    'name'       => 'vat',
                    'xtype'      => 'combobox',
                    'fieldLabel' => 'Ставка налога',
                    'value'      => 0,
                    'store'      => [
                        ["none", 'без НДС'],
                        ["vat0", 'НДС по ставке 0%'],
                        ["vat10", 'НДС чека по ставке 10%'],
                        ["vat18", 'НДС чека по ставке 18%'],
                        ["vat110", 'НДС чека по расчетной ставке 10/110'],
                        ["vat118", 'НДС чека по расчетной ставке 18/118'],
                        ["vat20", 'НДС чека по ставке 20%'],
                        ["vat120", 'НДС чека по расчётной ставке 20/120'],
                    ],
                ], 
                [
					'xtype'      => 'displayfield',
					'fieldLabel' => 'URL-адрес для callback уведомлений',
					'value'      => '//'.$_SERVER['HTTP_HOST'].'/cms/plugins/sale-payment-robokassa/callback.php'
				],                
            )
        );
    }

    public function pay($return = '')
    {
        $test = $this->params["test_mode"];
        $password = !$test ? $this->params["shop_password1"] : $this->params["test_shop_password1"];
        $orderId = $this->order->getId();
        $orderSumm = $this->order->getTotal();
        $shopLogin = $this->params["shop_login"];

        $crcStr = $shopLogin . ":" . $orderSumm . ":" .$orderId;
        $url = self::URL . "?MerchantLogin=" . $shopLogin . "&OutSum=" . $orderSumm . "&InvId=" . $orderId;
        if ($test) {
            $url .= '&IsTest=1';
        }
        
        if ($this->params['reciept']) {
            $receipt = urlencode(json_encode($this->getReceipt()));
            $crcStr .= ':' . $receipt;
            $url .= '&Receipt=' . urlencode($receipt);
            $url .= '&Email=' . urlencode($this->order->getEmail());
        }

        $url .= '&SignatureValue=' . md5($crcStr. ":" . $password);
        
        header('Location: '.$url);
        die();

    }
    
    public function getReceipt() {
        $receipt = [
            'sno' => $this->params['sno'],
            'items' => $this->getItems(),
        ];
        
        return $receipt;
    }
        
	public function getItems() {
        $items = [];
        
        foreach ($this->order->getProducts() as $p) {
            $items[] = [
                'name' => $p['name'],
                'quantity' => floatval($p['quantity']),
                'sum' => $p['price']*$p['quantity'],
                'payment_method' => $this->params['payment_method'],
                'payment_object' => $this->params['payment_object'],
                'tax' => $this->params['vat'],
            ];
        }
        return $items;
    }

    public function sendSecondReceipt($products = false) {
        
        if (!$products) {
            $items = $this->getItems();
        }
        else {
            $items = [];
            foreach($products as $p) {
                $items[] = [
                    'name' => $p['name'],
                    'quantity' => floatval($p['quantity']),
                    'sum' => $p['price']*$p['quantity'],
                    'payment_method' => $this->params['payment_method'],
                    'payment_object' => $this->params['payment_object'],
                    'tax' => $this->params['vat'],
                ];
            }
        }
        
        $total = 0;
        foreach ($items as $i) {
            $total += $i['sum'];
        }
        
        $vat = (int)$this->params['vat'];
        if ($vat > 100) $vat = $vat - 100;
        
        $data = [
            "merchantId" => $this->params["shop_login"],
            "id" => time(),
            "originId" => $this->order->getId(),
            "operation" => "sell",
            "sno" => $this->params['sno'],
            "url" => '//'.$_SERVER['HTTP_HOST'],
            "total" => $total,
            "items" => $items,
            "client" => [
                "email" => $this->order->getEmail(),
                "phone" => $this->order->getPhone()
            ],
            "payments" => [
                [
                  "type" => 2,
                  "sum" => $total,
                ]
            ],
            "vats" => [
                [
                  "type" => $this->params['vat'],
                  "sum" => sprintf("%01.2f", $total * $vat / 100),
                ]
            ]
        ];
        
        $test = $this->params["test_mode"];
        $password = !$test ? $this->params["shop_password1"] : $this->params["test_shop_password1"];
        $base64 = $this->base64url_encode(json_encode($data));
        
        $signature = $this->base64url_encode(md5($base64.$password));
        
        $body = $base64.'.'.$signature;
        
        $client = new \GuzzleHttp\Client();
        try {
            $response = $client->request('POST', self::URL_FISCAL, [
                'verify' => false,
                'body' => $body,
            ]); 
        } 
        catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
        }
        $res = json_decode($response->getBody(), true);
        
        return $res;
        
    }
    
    protected function base64url_encode($data) {
      return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }     
}