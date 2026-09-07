<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../support/bootstrap.php';

use app\model\Order;
use app\service\printer\ReceiptBuilder;

$order = Order::query()->with('items')->find((int)($argv[1] ?? 21));
if ($order === null) {
    echo "order not found\n";
    exit(1);
}

foreach (ReceiptBuilder::forOrder($order) as $line) {
    echo json_encode($line, JSON_UNESCAPED_UNICODE), "\n";
}
