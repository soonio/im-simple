<?php

require __DIR__ . '/../vendor/autoload.php';

go(function () {
    $cli = new Co\http\Client("127.0.0.1", 9501);
    $cli->setHeaders([
        'authorize' => 'member',
        'type'      => 'system'
    ]);
    $ret = $cli->upgrade("/");
    if ($ret) {
        $cli->push(buildMessage('system', 'oZXtU5jmUf-GqRjYUtqs6AWcWAII', json_encode([
            'name' => '张三',
            'data' => [
                'value' => 1001,
                'key'   => 'test string'
            ]
        ], JSON_UNESCAPED_UNICODE)));
        if ($frame = $cli->recv()) {
            print_r(json_decode($frame->data, true));
        }
    }
});

