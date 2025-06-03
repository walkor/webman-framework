<?php

namespace support\exception;

use Webman\Http\Request;
use Webman\Http\Response;

/**
 * 验证异常类
 */
class ValidationException extends BusinessException
{
    protected array $errors;

    public function __construct(array $errors, string $message = 'Validation failed', int $code = 422)
    {
        $this->errors = $errors;
        parent::__construct($message, $code);
        // 将验证错误设置到 BusinessException 的 data 属性中
        $this->data(['errors' => $errors]);
    }

    /**
     * 重写 render 方法以提供与 BusinessException 一致的响应格式
     */
    public function render(Request $request): ?Response
    {
        if ($request->expectsJson()) {
            $json = [
                'code' => $this->getCode() ?: 422,
                'msg' => $this->getMessage(),
                'data' => $this->getData()
            ];
            return new Response(422, ['Content-Type' => 'application/json; charset=UTF-8'],
                json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        // 非 JSON 请求的处理
        return new Response(422, ['Content-Type' => 'text/html'],
            $this->renderValidationErrorPage());
    }

    /**
     * 渲染验证错误页面
     */
    protected function renderValidationErrorPage(): string
    {
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Validation Error</title></head><body>';
        $html .= '<h1>Validation Error</h1>';
        $html .= '<p>' . htmlspecialchars($this->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
        $html .= '<ul>';
        foreach ($this->errors as $field => $messages) {
            foreach ((array)$messages as $message) {
                $html .= '<li><strong>' . htmlspecialchars($field, ENT_QUOTES, 'UTF-8') . '</strong>: ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</li>';
            }
        }
        $html .= '</ul></body></html>';
        return $html;
    }
}