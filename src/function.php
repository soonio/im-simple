<?php

/**
 * 响应的消息结构
 * @param int $code
 * @param string $message
 * @param array $data
 * @return false|string
 */
function message(int $code=200, string $message='success', $data=[])
{
    return json_encode([
        'code'  => $code,
        'msg'   => $message,
        'data'  => $data,
    ], JSON_UNESCAPED_UNICODE);
}


/**
 * server可接收的消息类型
 * @param string $type system|user|heart
 * @param string $to unionID
 * @param string $msg json
 * @return string
 */
function buildMessage(string $type, string $to, string $msg): string
{
    return json_encode([
        'type' => $type,
        'data' => [
            'to' => $to,
            'msg'=> $msg
        ]
    ], JSON_UNESCAPED_UNICODE);
}
