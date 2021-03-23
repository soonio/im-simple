<?php

declare(strict_types=1);

define('ROOT_PATH', __DIR__ . '/..');

require ROOT_PATH . '/vendor/autoload.php';

use Swoole\Table;
use Swoole\WebSocket\Server;

// 创建共享内存
$table = new Table(2048);
$table->column('fd', Table::TYPE_INT);
$table->column('unionID', Table::TYPE_STRING, 50);
$table->create();

$server = new Server("0.0.0.0", 9501); // 创建websocket服务器
$server->set([ // 设置
    'daemonize'     => false, // 进程守护
    'log_file'      => ROOT_PATH . '/runtime/ws.log',
    'pid_file'      => ROOT_PATH . '/runtime/server.pid',
    'reactor_num'   => 2,
    'worker_num'    => 2,
]);

$server->on('open', function (Server $server, $request) use ($table) {
    echo "server: handshake success with fd{$request->fd}\n";
    // 验证路由
    $path = ltrim($request->server['path_info'], '/ws/');
    // 解析路由
    if ($path && strpos($path, '/')) {
        list($type, $authorize) = explode('/', $path, 2);
    } else {
        $authorize  = $request->header['authorize'] ?? null;
        $type       = $request->header['type'] ?? null;
    }

    if (!($authorize && $type)) {
        $server->disconnect($request->fd, 5000, '参数缺失，无法验证');
        return;
    }
    // 验证接入类型
    if ($type == 'user') {
        if (
            !(strlen($authorize) == 28 && preg_match('/^[0-9a-zA-Z_\-]*$/', $authorize))
        ) { // TODO 验证用户unionID ,28位， oZXtU5开头的
            $server->disconnect($request->fd, 5001, '用户验证失败.');
            return;
        }
        $data = [
            'fd'        => $request->fd,
            'unionID'   => $authorize
        ];
        $table->set("FD-{$request->fd}", $data);
        $table->set("unionID-{$authorize}", $data);
    } elseif ($type = 'system') {
        if ($authorize != 'member') {
            $server->disconnect($request->fd, 5003, '验证对接系统失败.');
            return;
        }
    } else {
        $server->disconnect($request->fd, 5002, '接入类型错误.');
    }
});

$server->on('message', function (Server $server, $frame) use ($table) {
    // 解码指令
    $command = json_decode($frame->data, true);
    if (!$command) {
        $server->disconnect($frame->fd, 6001, '消息体非json类型.');
        return;
    }
    $type = $command['type'] ?? '';

    // 心跳
    if ($type != 'heart') { // 不记录心跳记录
        echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
    }

    // 处理来自服务端的请求
    if ($type == 'heart') {
        $server->push($frame->fd, message(200, 'normal'));
        return;
    }

    if ($type == 'system') {
        if ($key = $command['data']['to'] ?? '') {
            $relation = $table->get("unionID-{$key}");
            if ($relation) {
                $server->push($relation['fd'], $command['data']['msg'] ?? '');
                $server->push($frame->fd, message(200, '发送成功'));
            } else {
                $server->push($frame->fd, message(201, '用户已下线'));
            }
        } else {
            $server->disconnect($frame->fd, 6003, '数据格式错误.');
        }
        return;
    }

    // TODO 处理来自用户平台的请求

    $server->disconnect($frame->fd, 6002, '非法消息类型.');
});

$server->on('close', function ($ser, $fd) use ($table) {
    // 销毁fd与用户标示的绑定
    $data = $table->get("FD-{$fd}");
    if ($data) {
        $table->del("FD-{$data['fd']}");
        $table->del("unionID-{$data['unionID']}");
        echo "前端释放FD-{$data['fd']}" . "unionID-{$data['unionID']}\n";
    } else {
        echo "服务释放-{$fd}\n";
    }
});

$server->start();

