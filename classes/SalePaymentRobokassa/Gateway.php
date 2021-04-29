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

    public static function getInfo()
    {
        $t = \Cetera\Application::getInstance()->getTranslator();
        $locale = $t->getAdapter()->getLocale();

        return array(
            'name' => 'Robokassa',
            'description' => '',
            'icon' => '/cms/plugins/sale-payment-robokassa/images/icon.gif',
            'params' => array(
                [
                    "xtype" => 'textfield',
                    "labelWidth" => 200,
                    "width" => 400,
                    "fieldLabel" => $t->_('Логин магазина'),
                    "name" => 'shop_login',
                ],
                [
                    "xtype" => 'textfield',
                    "labelWidth" => 200,
                    "width" => 400,
                    "fieldLabel" => $t->_('Пароль магазина'),
                    "name" => 'shop_password1',
                ],
                [
                    "xtype" => 'textfield',
                    "labelWidth" => 200,
                    "width" => 400,
                    "fieldLabel" => $t->_('Пароль магазина #2'),
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
                    "fieldLabel" => $t->_('Тестовый пароль магазина'),
                    "name" => 'test_shop_password1',
                ],
                [
                    "xtype" => 'textfield',
                    "labelWidth" => 200,
                    "width" => 400,
                    "fieldLabel" => $t->_('Тестовый пароль магазина #2'),
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
            )
        );
    }

    public function pay($return = '')
    {
        $test = $this->params["test_mode"];
        $password = !$test ? $this->params["shop_password1"] : $this->params["test_shop_password1"];
        $personalEmail = $this->order->getEmail();
        $orderId = $this->order->getId();
        $orderSumm = $this->order->getTotal();
        $url = $this->params["url"];
        $shopLogin = $this->params["shop_login"];

        $crcStr = $shopLogin . ":" . $orderSumm . ":" .$orderId;
        $url = self::URL . "?MerchantLogin=" . $shopLogin . "&OutSum=" . $orderSumm . "&InvId=" . $orderId;
        
        if ($this->params['reciept']) {
            $reciept = urlencode(json_encode($this->getReceipt()));
            $crcStr .= ':' . $receipt;
            $url .= '&Receipt=' . $receipt;
        }

        $crc = md5($crcStr. ":" . $password);
        $url .= '&SignatureValue=' . $crc;
        
        header($url);
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
}