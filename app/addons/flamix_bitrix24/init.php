<?php if (!defined('BOOTSTRAP')) die('Access denied');

include_once 'include/vendor/autoload.php';

fn_register_hooks(
    'dispatch_before_display', // Загрузка страницы
    'do_call_request', // Заказ в один клик или заказ обратного звонка
    'place_order', // Заказ через корзину
    'change_order_status' // Смена статуса заказа
);