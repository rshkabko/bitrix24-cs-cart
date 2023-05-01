<?php if (!defined('BOOTSTRAP')) die('Access denied');

use Tygh\Registry;
use Flamix\Helpers;
use Flamix\Bitrix24\Trace;
use Flamix\Bitrix24\SmartUTM;

/**
 * Запуск на каждой странице.
 */
function fn_flamix_bitrix24_dispatch_before_display()
{
    if (version_compare(PHP_VERSION, '7.4.0') < 0)
        dump('Bitrix24 and CS-Cart integration: Upgrade your PHP version. Minimum version - 7.4+. Your PHP version ' . PHP_VERSION . '! If you don\'t know how to upgrade PHP version, just ask in your hosting provider! If you can\'t upgrade - delete this plugin!');

    // Bitrix24 change status
    if (isset($_REQUEST['flamix_status']) && $_REQUEST['flamix_status'] == 'Y' && isset($_REQUEST['status']) && isset($_REQUEST['order_id']) && isset($_REQUEST['hash'])) {
        $token = Registry::get('addons.flamix_bitrix24.api_key') ?? false;
        if (!empty($token) && $_REQUEST['hash'] == md5($token . '_' . strtoupper($_REQUEST['status']))) {
            fn_change_order_status((int)$_REQUEST['order_id'], $_REQUEST['status']);
            header('HTTP/1.1 200 Ok');
            dump('Status changed');
        }

        dd($_REQUEST);
    }

    // Title here is no good
    $title = Helpers::getTitle(Registry::get('view'));
    ($title) ? Trace::init($title) : SmartUTM::init();
}

/**
 * Быстрый заказ.
 */
function fn_flamix_bitrix24_do_call_request($params, $product_data, $cart, $auth, $company_id)
{
    // На всякий случай, потому что будем чистить массив
    $fields = $params;
    Helpers::trace('do_call_request $fields:', $fields);

    // Если это быстры заказ, то тут ID товара
    if ($fields['product_id'] ?? 0) {
        $products = [$fields['product_id'] => Helpers::getProduct($fields['product_id']) ?? []];
        $products[$fields['product_id']]['QUANTITY'] = 1;
        Helpers::trace('do_call_request $products:', $products);
        unset($fields['product_id']);
    }

    unset($fields['company_id'], $fields['cart_products']);
//    dump($fields, $products);

    Helpers::sendWithProduct($fields, $products ?? []);
}

/**
 * Заказ с корзины.
 */
function fn_flamix_bitrix24_place_order($order_id, $action, $order_status, $cart, $auth)
{
    $fields_left = [
        'order_id', 'phone', 'email', 'firstname', 'lastname', 'user_id',
        'discount', 'shipping_cost', 'status', 'notes', 'company', 'ip_address',
    ];
    $fields = $products = [];
    $order = fn_get_order_info($order_id);
    Helpers::trace('place_order $order:', $order);

    if (!is_array($order)) return false;

    // Чистим и формируем все поля
    foreach ($order as $key => $value) {
        if (empty($value) || is_array($value)) continue;

        if (in_array($key, $fields_left) || strpos($key, 'b_') === 0 || strpos($key, 'p_')) {
            $fields[strtoupper($key)] = $value;
        }
    }

    // Payment
    if ($order['payment_method']['payment'] ?? false) {
        $fields['payment'] = $order['payment_method']['payment'];
    }

    // Shipping
    if ($order['shipping']['shipping'] ?? false) {
        $fields['shipping'] = $order['shipping']['shipping'];
        // В какое отделение
        if (isset($order['shipping']['store_data']['name'])) {
            $fields['shipping_store_name'] = $order['shipping']['store_data']['name'];
        }
    }

    // Products
    if ($order['products'] ?? false) {
        foreach ($order['products'] as $product) {
            $products[$product['product_id']] = Helpers::getProduct($product['product_id']) ?? [];
            $products[$product['product_id']]['QUANTITY'] = $product['amount'] ?? 1;
        }
    }

    Helpers::trace('Filtered Fields $fields:', $fields);
    Helpers::trace('Filtered Fields $products:', $products);
    Helpers::sendWithProduct($fields, $products);
}

/**
 * Вывод статуса конфигурации.
 */
function fn_flamix_bitrix24_config_check(): string
{
    ob_start();
    include_once 'include/settings_views.php';
    $views = ob_get_contents();
    ob_end_clean();

    return $views;
}

/**
 * Смена статуса заказа.
 */
function fn_flamix_bitrix24_change_order_status($status_to, $status_from, $order_info, $force_notification, $order_statuses, $place_order)
{
    $order_id = intval($order_info['order_id'] ?? 0);
    if ($order_id) {
        Helpers::trace('Change status from ' . $status_from . ' to ' . $status_to . ' to ID:' . $order_id, $order_info);

        try {
            Helpers::send(['ORDER_ID' => $order_id, 'STATUS' => strtoupper(trim($status_to)), 'HOSTNAME' => SmartUTM::getMyHostname()], 'status/change');
        } catch (\Exception $e) {
            Helpers::log('Sending Status Error: ' . $e->getMessage());
            Helpers::sendError($e->getMessage());
        }
    }
}