<?php

namespace Flamix;

use Tygh\Registry;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Flamix\Bitrix24\Lead;

class Helpers
{
    /**
     * Set true to debugging
     * @var bool
     */
    private static $trace = true;

    /**
     * Логируем ключевые узлы
     *
     * @param string $message
     * @param array $context
     */
    public static function trace(string $message, array $context = [])
    {
        if (self::$trace)
            self::log($message, $context);
    }

    /**
     * Лог
     *
     * @param string $message
     * @param array $context
     * @return Logger
     */
    public static function log(string $message, array $context = [])
    {
        $log = new Logger('flamix');
        $log->pushHandler(new StreamHandler($_SERVER['DOCUMENT_ROOT'] . '/var/files/flamix_bitrix24/' . date('Ydm') . '_bitrix24.log', Logger::WARNING));
        $log->warning($message, $context);
        return $log;
    }

    /**
     * Самая глупая система получения тайтал в мире
     * Причем если false - не факт что это не страница
     *
     * @param $views
     * @return bool
     */
    public static function getTitle($views)
    {
        if (is_array($views->getTemplateVars('location_data')) && !empty($views->getTemplateVars('location_data')['title']))
            return $views->getTemplateVars('location_data')['title'] ?? false;

        if (is_array($views->getTemplateVars('provider_meta_data')))
            return $views->getTemplateVars('provider_meta_data')['all']['title'] ?? false;

        return false;
    }

    /**
     * Every APP has own domain
     *
     * @return string
     */
    public static function getSubDomain(): string
    {
        if ($_SERVER['SERVER_NAME'] === 'cs-cart.test.chosten.com')
            return 'devlead';

        return 'leadcscart';
    }

    /**
     * Получаем почту на которую шлем ошибки
     *
     * @return bool
     */
    public static function get_backup_email()
    {
        $email = Registry::get('addons.flamix_bitrix24.backup_email');
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
            return $email;

        return false;
    }

    /**
     * Prepare product
     *
     * @param int $product_id
     * @return array
     */
    public static function getProduct(int $product_id): array
    {
        $products = [];
        $tmp = fn_get_product_data($product_id, $_SESSION['auth'], CART_LANGUAGE ?? 'ru');
        if (empty($tmp))
            return $products;

        self::trace('Get product info #' . $product_id, $tmp);

        $products['NAME'] = $tmp['product'];
        $products['PRICE'] = $tmp['price'];
        $products['SKU'] = $tmp['product_code'] ?? '';

        // Discount
        if (isset($tmp['list_price']) && $tmp['list_price'] > $tmp['price']) {
            $products['DISCOUNT_TYPE_ID'] = 1;
            $products['DISCOUNT_SUM'] = round($tmp['list_price'] - $tmp['price'], 2);
        }

        if (self::getFindBy()) {
            $products['FIND_BY'] = 'XML_ID';
            $products['XML_ID'] = $tmp[self::getFindBy()] ?? $tmp['product_code'] ?? '';
        }

        unset($tmp);
        return $products;
    }

    /**
     * Получаем настройки по которой будем искать товар
     *
     * @return bool
     */
    private static function getFindBy()
    {
        $is_enable = Registry::get('addons.flamix_bitrix24.find_on') ?? false;
        if (!$is_enable)
            return false;

        return Registry::get('addons.flamix_bitrix24.find_site');
    }

    /**
     * Базовая валюта магазина
     *
     * @return bool
     */
    public static function getBaseCurrency()
    {
        $currencies = \fn_block_manager_get_currencies();
        if (empty($currencies) || !is_array($currencies))
            return false;

        foreach ($currencies as $currency) {
            if ($currency['is_primary'] == 'Y') {
                return $currency['currency_code'];
            }
        }

        return false;
    }

    /**
     * When saving email - check
     *
     * @param $option
     * @return bool|string
     */
    public static function parseDomain($option)
    {
        $tmp = parse_url($option);
        if (!empty($tmp['host']))
            return $tmp['host'];

        return $option;
    }

    /**
     * Отправка ошибок
     * @param $msg
     * @todo Изучить и сделать
     */
    public static function sendError($msg)
    {

    }

    /**
     * Sending data to Bitrix24 plugin
     *
     * @param array $data
     * @param string $actions
     * @return mixed
     */
    public static function send(array $data, string $actions = 'lead/add')
    {
        $domain = self::parseDomain(Registry::get('addons.flamix_bitrix24.portal') ?? false);
        $token = Registry::get('addons.flamix_bitrix24.api_key') ?? false;
        self::trace('Sending data to domain ' . $domain . ' with data:', $data);
        return Lead::getInstance()->changeSubDomain(self::getSubDomain())->setDomain($domain)->setToken($token)->send($data, $actions);
    }

    /**
     * Отправляем поля и товары в Битрикс24
     *
     * @param array $fields
     * @param array $products
     * @return mixed
     */
    public static function sendWithProduct(array $fields, array $products)
    {
        $data = [
            'FIELDS' => $fields,
            'PRODUCTS' => $products
        ];

        // Докидываем базовую валюту магазина
        $currency = self::getBaseCurrency();
        if ($currency)
            $data['CURRENCY_ID'] = $currency;

        try {
            return self::send($data);
        } catch (\Exception $e) {
//                echo 'Error: ',  $e->getMessage();
            self::log('Sending Status Error: ' . $e->getMessage());
            self::sendError($e->getMessage());
        }
    }
}