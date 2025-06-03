<?php

namespace support;

use Webman\Http\UploadFile;

use support\exception\ValidationException;

/**
 * 验证器类
 */
class Validator
{
    protected array $data;
    protected array $rules;
    protected array $messages;
    protected array $errors = [];
    protected string $currentField = '';

    public function __construct(array $data, array $rules, array $messages = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->messages = $messages;
    }

    /**
     * 执行验证
     */
    public function validate(): array
    {
        foreach ($this->rules as $field => $ruleSet) {
            $this->validateField($field, $ruleSet);
        }

        if (!empty($this->errors)) {
            throw new ValidationException($this->errors);
        }

        return $this->getValidatedData();
    }

    /**
     * 验证单个规则
     */
    protected function validateRule(string $field, $value, string $rule): bool
    {
        // 解析规则和参数
        [$ruleName, $parameters] = $this->parseRule($rule);

        // 如果值为空且不是required规则，跳过验证
        if ($this->isEmpty($value) && !in_array($ruleName, ['required', 'required_if', 'required_unless', 'required_with', 'required_without'])) {
            return true;
        }

        $method = 'validate' . ucfirst(str_replace('_', '', ucwords($ruleName, '_')));
        if (method_exists($this, $method)) {
            if (!$this->$method($field, $value, $parameters)) {
                $this->addError($field, $ruleName, $parameters);
                return false;
            }
        }

        return true;
    }

    /**
     * 解析规则字符串
     */
    protected function parseRule(string $rule): array
    {
        if (strpos($rule, ':') === false) {
            return [$rule, []];
        }

        [$ruleName, $paramString] = explode(':', $rule, 2);
        $parameters = explode(',', $paramString);

        return [$ruleName, $parameters];
    }

    /**
     * 获取字段值
     */
    protected function getValue(string $field)
    {
        return $this->data[$field] ?? null;
    }

    /**
     * 检查值是否为空
     */
    protected function isEmpty($value): bool
    {
        return $value === null || $value === '' || (is_array($value) && empty($value));
    }

    /**
     * 添加错误消息
     */
    protected function addError(string $field, string $rule, array $parameters = []): void
    {
        $message = $this->getMessage($field, $rule, $parameters);
        $this->errors[$field][] = $message;
    }


    /**
     * 获取默认错误消息
     */
    protected function getDefaultMessage(string $rule): string
    {
        $defaultMessages = [
            'required' => 'The :attribute field is required.',
            'required_if' => 'The :attribute field is required when :other is :value.',
            'required_if_accepted' => 'The :attribute field is required when :other is accepted.',
            'required_unless' => 'The :attribute field is required unless :other is in :values.',
            'required_with' => 'The :attribute field is required when :values is present.',
            'required_with_all' => 'The :attribute field is required when :values are present.',
            'required_without' => 'The :attribute field is required when :values is not present.',
            'required_without_all' => 'The :attribute field is required when none of :values are present.',
            'same' => 'The :attribute field must match :other.',
            'accepted' => 'The :attribute field must be accepted.',
            'string' => 'The :attribute field must be a string.',
            'numeric' => 'The :attribute field must be a number.',
            'integer' => 'The :attribute field must be an integer.',
            'email' => 'The :attribute field must be a valid email address.',
            'between' => 'The :attribute field must be between :min and :max characters.',
            'in' => 'The selected :attribute is invalid.',
            'not_in' => 'The selected :attribute is invalid.',
            'confirmed' => 'The :attribute confirmation does not match.',
            'regex' => 'The :attribute format is invalid.',
            'url' => 'The :attribute must be a valid URL.',
            'date' => 'The :attribute is not a valid date.',
            'alpha' => 'The :attribute may only contain letters.',
            'alpha_num' => 'The :attribute may only contain letters and numbers.',
            'array' => 'The :attribute must be an array.',
            'boolean' => 'The :attribute field must be true or false.',
            'json' => 'The :attribute must be a valid JSON string.',
            'distinct' => 'The :attribute field has a duplicate value.',
            'starts_with' => 'The :attribute must start with one of the following: :values.',
            'ends_with' => 'The :attribute must end with one of the following: :values.',
            'sometimes' => 'The :attribute field is invalid.',
            'nullable' => 'The :attribute field can be null.',
            'prohibited' => 'The :attribute field is prohibited.',
            'prohibited_if' => 'The :attribute field is prohibited when :other is :value.',
            'prohibited_unless' => 'The :attribute field is prohibited unless :other is :value.',
            'file' => 'The :attribute must be a valid file.',
            'image' => 'The :attribute must be an image.',
            'mimetypes' => 'The :attribute must have a MIME type of: :values.',
            'max_size' => 'The :attribute must not be larger than :size kilobytes.',
            'min_size' => 'The :attribute must be at least :size kilobytes.',
            'dimensions' => 'The :attribute has invalid image dimensions (width: :width, height: :height).',
            'max' => [
                'array' => 'The :attribute field must not have more than :max items.',
                'file' => 'The :attribute field must not be greater than :max kilobytes.',
                'numeric' => 'The :attribute field must not be greater than :max.',
                'string' => 'The :attribute field must not be greater than :max characters.',
            ],
            'max_digits' => 'The :attribute field must not have more than :max digits.',
            'mimes' => 'The :attribute field must be a file of type: :values.',
            'min' => [
                'array' => 'The :attribute field must have at least :min items.',
                'file' => 'The :attribute field must be at least :min kilobytes.',
                'numeric' => 'The :attribute field must be at least :min.',
                'string' => 'The :attribute field must be at least :min characters.',
            ],
        ];

        return $defaultMessages[$rule] ?? "The :attribute field is invalid.";
    }

    /**
     * 获取验证通过的数据
     */
    protected function getValidatedData(): array
    {
        $validated = [];
        foreach ($this->rules as $field => $rule) {
            if (array_key_exists($field, $this->data)) {
                $validated[$field] = $this->data[$field];
            }
        }
        return $validated;
    }

    // ==================== 验证规则方法 ====================

    // 基础验证规则
    protected function validateRequired(string $field, $value, array $parameters): bool
    {
        return !$this->isEmpty($value);
    }

    protected function validateAccepted(string $field, $value, array $parameters): bool
    {
        return in_array($value, [true, 1, '1', 'yes', 'on', 'true'], true);
    }

    protected function validateRequiredIf(string $field, $value, array $parameters): bool
    {
        if (count($parameters) < 2) return true;

        $otherField = $parameters[0];
        $expectedValue = $parameters[1];
        $otherValue = $this->getValue($otherField);

        if ($otherValue == $expectedValue) {
            return !$this->isEmpty($value);
        }

        return true;
    }

    protected function validateRequiredUnless(string $field, $value, array $parameters): bool
    {
        if (count($parameters) < 2) return true;

        $otherField = $parameters[0];
        $expectedValue = $parameters[1];
        $otherValue = $this->getValue($otherField);

        if ($otherValue != $expectedValue) {
            return !$this->isEmpty($value);
        }

        return true;
    }

    protected function validateRequiredWith(string $field, $value, array $parameters): bool
    {
        foreach ($parameters as $otherField) {
            if (!$this->isEmpty($this->getValue($otherField))) {
                return !$this->isEmpty($value);
            }
        }
        return true;
    }

    protected function validateRequiredWithout(string $field, $value, array $parameters): bool
    {
        foreach ($parameters as $otherField) {
            if ($this->isEmpty($this->getValue($otherField))) {
                return !$this->isEmpty($value);
            }
        }
        return true;
    }

    // 类型验证
    protected function validateString(string $field, $value, array $parameters): bool
    {
        return is_string($value);
    }

    protected function validateNumeric(string $field, $value, array $parameters): bool
    {
        return is_numeric($value);
    }

    protected function validateInteger(string $field, $value, array $parameters): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    protected function validateArray(string $field, $value, array $parameters): bool
    {
        return is_array($value);
    }

    protected function validateBoolean(string $field, $value, array $parameters): bool
    {
        return in_array($value, [true, false, 0, 1, '0', '1'], true);
    }

    protected function validateJson(string $field, $value, array $parameters): bool
    {
        if (!is_string($value)) return false;
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    // 格式验证
    protected function validateEmail(string $field, $value, array $parameters): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    protected function validateUrl(string $field, $value, array $parameters): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    protected function validateActiveUrl(string $field, $value, array $parameters): bool
    {
        if (!filter_var($value, FILTER_VALIDATE_URL)) return false;

        $host = parse_url($value, PHP_URL_HOST);
        return $host && (checkdnsrr($host, 'A') || checkdnsrr($host, 'AAAA'));
    }

    protected function validateIp(string $field, $value, array $parameters): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    protected function validateIpv4(string $field, $value, array $parameters): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    protected function validateIpv6(string $field, $value, array $parameters): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    protected function validateMac(string $field, $value, array $parameters): bool
    {
        return filter_var($value, FILTER_VALIDATE_MAC) !== false;
    }

    protected function validateUuid(string $field, $value, array $parameters): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
    }

    // 日期验证
    protected function validateDate(string $field, $value, array $parameters): bool
    {
        if (!is_string($value)) return false;
        return strtotime($value) !== false;
    }

    protected function validateDateFormat(string $field, $value, array $parameters): bool
    {
        if (!isset($parameters[0])) return false;

        $format = $parameters[0];
        $date = \DateTime::createFromFormat($format, $value);
        return $date && $date->format($format) === $value;
    }

    protected function validateAfter(string $field, $value, array $parameters): bool
    {
        if (!isset($parameters[0])) return false;

        $compareDate = strtotime($parameters[0]);
        $valueDate = strtotime($value);

        return $valueDate !== false && $compareDate !== false && $valueDate > $compareDate;
    }

    protected function validateAfterOrEqual(string $field, $value, array $parameters): bool
    {
        if (!isset($parameters[0])) return false;

        $compareDate = strtotime($parameters[0]);
        $valueDate = strtotime($value);

        return $valueDate !== false && $compareDate !== false && $valueDate >= $compareDate;
    }

    protected function validateBefore(string $field, $value, array $parameters): bool
    {
        if (!isset($parameters[0])) return false;

        $compareDate = strtotime($parameters[0]);
        $valueDate = strtotime($value);

        return $valueDate !== false && $compareDate !== false && $valueDate < $compareDate;
    }

    protected function validateBeforeOrEqual(string $field, $value, array $parameters): bool
    {
        if (!isset($parameters[0])) return false;

        $compareDate = strtotime($parameters[0]);
        $valueDate = strtotime($value);

        return $valueDate !== false && $compareDate !== false && $valueDate <= $compareDate;
    }

    protected function validateBetween(string $field, $value, array $parameters): bool
    {
        if (count($parameters) < 2) {
            return false;
        }

        $min = (int)$parameters[0];
        $max = (int)$parameters[1];

        if (is_numeric($value)) {
            $numValue = (float)$value;
            return $numValue >= $min && $numValue <= $max;
        }

        if (is_array($value)) {
            $count = count($value);
            return $count >= $min && $count <= $max;
        }

        if ($value instanceof UploadFile) {
            if (!$this->validateFile($field, $value, $parameters)) {
                return false;
            }
            $fileSize = filesize($value->getPathname());
            return $fileSize !== false && $fileSize >= $min * 1024 && $fileSize <= $max * 1024;
        }

        $length = mb_strlen((string)$value);
        return $length >= $min && $length <= $max;
    }
    /**
     * 验证字段大小（支持数字、数组、字符串和文件）
     */
    protected function validateSize(string $field, $value, array $parameters): bool
    {
        if (!isset($parameters[0]) || !is_numeric($parameters[0])) {
            return false;
        }

        $size = (int)$parameters[0];

        if (is_numeric($value)) {
            // Convert to string and count digits, ignoring decimal points and signs
            $digitString = (string)preg_replace('/[^0-9]/', '', (string)$value);
            return strlen($digitString) == $size;
        }

        if (is_array($value)) {
            return count($value) == $size;
        }

        if ($value instanceof UploadFile) {
            if (!$this->validateFile($field, $value, $parameters)) {
                return false;
            }
            $fileSize = filesize($value->getPathname());
            return $fileSize !== false && $fileSize == $size * 1024; // Convert KB to bytes
        }

        if (!is_string($value)) {
            $value = (string)$value;
        }

        // Trim to remove potential whitespace
        $value = trim($value);

        return mb_strlen($value, 'UTF-8') == $size;
    }

    // 比较验证
    protected function validateSame(string $field, $value, array $parameters): bool
    {
        if (!isset($parameters[0])) return false;

        $otherValue = $this->getValue($parameters[0]);
        return $value === $otherValue;
    }

    protected function validateDifferent(string $field, $value, array $parameters): bool
    {
        if (!isset($parameters[0])) return false;

        $otherValue = $this->getValue($parameters[0]);
        return $value !== $otherValue;
    }

    protected function validateConfirmed(string $field, $value, array $parameters): bool
    {
        $confirmField = $field . '_confirmation';
        return isset($this->data[$confirmField]) && $value === $this->data[$confirmField];
    }

    // 包含验证
    protected function validateIn(string $field, $value, array $parameters): bool
    {
        return in_array($value, $parameters);
    }

    protected function validateNotIn(string $field, $value, array $parameters): bool
    {
        return !in_array($value, $parameters);
    }

    // 字符验证
    protected function validateAlpha(string $field, $value, array $parameters): bool
    {
        return preg_match('/^[\pL\pM]+$/u', $value) > 0;
    }

    protected function validateAlphaNum(string $field, $value, array $parameters): bool
    {
        return preg_match('/^[\pL\pM\pN]+$/u', $value) > 0;
    }

    protected function validateAlphaDash(string $field, $value, array $parameters): bool
    {
        return preg_match('/^[\pL\pM\pN_-]+$/u', $value) > 0;
    }

    protected function validateAscii(string $field, $value, array $parameters): bool
    {
        return mb_check_encoding($value, 'ASCII');
    }

    // 正则验证
    protected function validateRegex(string $field, $value, array $parameters): bool
    {
        if (!isset($parameters[0])) return false;

        return preg_match($parameters[0], $value) > 0;
    }

    protected function validateNotRegex(string $field, $value, array $parameters): bool
    {
        if (!isset($parameters[0])) return false;

        return preg_match($parameters[0], $value) === 0;
    }

    // 数字验证
    protected function validateDigits(string $field, $value, array $parameters): bool
    {
        if (!isset($parameters[0])) return false;

        return preg_match('/^\d+$/', $value) && strlen($value) == $parameters[0];
    }

    protected function validateDigitsBetween(string $field, $value, array $parameters): bool
    {
        if (count($parameters) < 2) return false;

        $min = (int)$parameters[0];
        $max = (int)$parameters[1];

        if (!preg_match('/^\d+$/', $value)) return false;

        $length = strlen($value);
        return $length >= $min && $length <= $max;
    }

    protected function validateMultipleOf(string $field, $value, array $parameters): bool
    {
        if (!isset($parameters[0]) || !is_numeric($value)) return false;

        $divisor = (float)$parameters[0];
        return $divisor != 0 && fmod((float)$value, $divisor) == 0;
    }

    /**
     * 验证数组值是否唯一
     */
    protected function validateDistinct(string $field, $value, array $parameters): bool
    {
        if (!is_array($value)) {
            return true;
        }
        $unique = array_unique($value, SORT_REGULAR);
        return count($unique) === count($value);
    }

    /**
     * 验证字符串是否以指定值开头
     */
    protected function validateStartsWith(string $field, $value, array $parameters): bool
    {
        if (!is_string($value) || empty($parameters)) {
            return false;
        }
        foreach ($parameters as $start) {
            if (str_starts_with($value, $start)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 验证字符串是否以指定值结尾
     */
    protected function validateEndsWith(string $field, $value, array $parameters): bool
    {
        if (!is_string($value) || empty($parameters)) {
            return false;
        }
        foreach ($parameters as $end) {
            if (str_ends_with($value, $end)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 验证当所有指定字段存在时，当前字段必须存在
     */
    protected function validateRequiredWithAll(string $field, $value, array $parameters): bool
    {
        foreach ($parameters as $otherField) {
            if ($this->isEmpty($this->getValue($otherField))) {
                return true;
            }
        }
        return !$this->isEmpty($value);
    }

    /**
     * 验证当所有指定字段都不存在时，当前字段必须存在
     */
    protected function validateRequiredWithoutAll(string $field, $value, array $parameters): bool
    {
        foreach ($parameters as $otherField) {
            if (!$this->isEmpty($this->getValue($otherField))) {
                return true;
            }
        }
        return !$this->isEmpty($value);
    }

    /**
     * 仅当字段存在时应用其他验证规则
     */
    protected function validateSometimes(string $field, $value, array $parameters): bool
    {
        // sometimes 规则仅表示字段可选，实际验证由其他规则处理
        return true;
    }

    /**
     * 允许字段为 null 或空
     */
    protected function validateNullable(string $field, $value, array $parameters): bool
    {
        // nullable 规则在 validateField 中处理，跳过其他验证
        return true;
    }

    /**
     * 禁止字段存在
     */
    protected function validateProhibited(string $field, $value, array $parameters): bool
    {
        return $this->isEmpty($value) && !array_key_exists($field, $this->data);
    }

    /**
     * 当另一字段等于指定值时，禁止当前字段存在
     */
    protected function validateProhibitedIf(string $field, $value, array $parameters): bool
    {
        if (count($parameters) < 2) {
            return true;
        }
        $otherField = $parameters[0];
        $expectedValue = $parameters[1];
        $otherValue = $this->getValue($otherField);

        if ($otherValue == $expectedValue) {
            return $this->isEmpty($value) && !array_key_exists($field, $this->data);
        }
        return true;
    }

    /**
     * 除非另一字段等于指定值，禁止当前字段存在
     */
    protected function validateProhibitedUnless(string $field, $value, array $parameters): bool
    {
        if (count($parameters) < 2) {
            return true;
        }
        $otherField = $parameters[0];
        $expectedValue = $parameters[1];
        $otherValue = $this->getValue($otherField);

        if ($otherValue != $expectedValue) {
            return $this->isEmpty($value) && !array_key_exists($field, $this->data);
        }
        return true;
    }

    /**
     * 验证字段是否为有效的上传文件
     */
    protected function validateFile(string $field, $value, array $parameters): bool
    {
        return $value instanceof UploadFile && $value->isValid();
    }

    /**
     * 验证字段是否为图片文件
     */
    protected function validateImage(string $field, $value, array $parameters): bool
    {
        if (!$this->validateFile($field, $value, $parameters)) {
            return false;
        }
        $mimeType = $value->getUploadMimeType();
        return strpos($mimeType, 'image/') === 0;
    }

    /**
     * 验证文件扩展名
     */
    protected function validateMimes(string $field, $value, array $parameters): bool
    {
        if (!$this->validateFile($field, $value, $parameters)) {
            return false;
        }
        $extension = strtolower($value->getUploadExtension());
        return in_array($extension, array_map('strtolower', $parameters));
    }

    /**
     * 验证文件 MIME 类型
     */
    protected function validateMimetypes(string $field, $value, array $parameters): bool
    {
        if (!$this->validateFile($field, $value, $parameters)) {
            return false;
        }
        $mimeType = $value->getUploadMimeType();
        return in_array($mimeType, $parameters);
    }

    /**
     * 验证文件最大大小（KB）
     */
    protected function validateMaxSize(string $field, $value, array $parameters): bool
    {
        if (!$this->validateFile($field, $value, $parameters) || !isset($parameters[0])) {
            return false;
        }
        $maxSize = (int)$parameters[0] * 1024; // Convert KB to bytes
        $fileSize = filesize($value->getPathname());
        return $fileSize !== false && $fileSize <= $maxSize;
    }

    /**
     * 验证文件最小大小（KB）
     */
    protected function validateMinSize(string $field, $value, array $parameters): bool
    {
        if (!$this->validateFile($field, $value, $parameters) || !isset($parameters[0])) {
            return false;
        }
        $minSize = (int)$parameters[0] * 1024; // Convert KB to bytes
        $fileSize = filesize($value->getPathname());
        return $fileSize !== false && $fileSize >= $minSize;
    }
    protected function validateGt(string $field, $value, array $parameters): bool
    {
        if (!isset($parameters[0]) || !is_numeric($value)) return false;
        return (float)$value > (float)$parameters[0];
    }

    /**
     * 验证图片尺寸（宽度和高度）
     */
    protected function validateDimensions(string $field, $value, array $parameters): bool
    {
        if (!$this->validateImage($field, $value, $parameters) || count($parameters) < 2) {
            return false;
        }
        [$expectedWidth, $expectedHeight] = array_map('intval', $parameters);
        $imageInfo = getimagesize($value->getPathname());
        if ($imageInfo === false) {
            return false;
        }
        [$width, $height] = $imageInfo;
        return $width == $expectedWidth && $height == $expectedHeight;
    }

    /**
     * 获取错误消息（确保占位符替换）
     */
    protected function getMessage(string $field, string $rule, array $parameters = []): string
    {
        // 优先级：自定义消息 > 语言文件消息 > 默认消息
        $key = "{$field}.{$rule}";

        // 检查自定义消息
        if (isset($this->messages[$key])) {
            $message = $this->messages[$key];
        } elseif (isset($this->messages[$rule])) {
            $message = $this->messages[$rule];
        } else {
            // 从语言文件获取消息
            $message = $this->getValidationMessage($rule);
        }

        // 确保消息是字符串
        if (!is_string($message)) {
            $message = "The {$field} validation failed.";
        }

        // 替换占位符
        $message = str_replace(':attribute', $field, $message);

        // 替换参数占位符
        $replacements = [
            ':min' => $parameters[0] ?? '',
            ':max' => $parameters[1] ?? $parameters[0] ?? '',
            ':value' => $parameters[0] ?? '',
            ':other' => $parameters[0] ?? '',
            ':date' => $parameters[0] ?? '',
            ':size' => $parameters[0] ?? '',
            ':values' => implode(', ', $parameters),
            ':width' => $parameters[0] ?? '',
            ':height' => $parameters[1] ?? '',
        ];

        foreach ($replacements as $placeholder => $replacement) {
            $message = str_replace($placeholder, $replacement, $message);
        }

        return $message;
    }

    /**
     * 验证字段最小值（支持数字、数组、字符串和文件）
     */
    protected function validateMin(string $field, $value, array $parameters): bool
    {
        if (!isset($parameters[0])) {
            return false;
        }

        $min = (int)$parameters[0];

        if (is_numeric($value)) {
            return (float)$value >= $min;
        }

        if (is_array($value)) {
            return count($value) >= $min;
        }

        if ($value instanceof UploadFile) {
            if (!$this->validateFile($field, $value, $parameters)) {
                return false;
            }
            $fileSize = filesize($value->getPathname());
            return $fileSize !== false && $fileSize >= $min * 1024; // Convert KB to bytes
        }

        return mb_strlen((string)$value) >= $min;
    }

    /**
     * 验证字段最大值（支持数字、数组、字符串和文件）
     */
    protected function validateMax(string $field, $value, array $parameters): bool
    {
        if (!isset($parameters[0])) {
            return false;
        }

        $max = (int)$parameters[0];

        if (is_numeric($value)) {
            return (float)$value <= $max;
        }

        if (is_array($value)) {
            return count($value) <= $max;
        }

        if ($value instanceof UploadFile) {
            if (!$this->validateFile($field, $value, $parameters)) {
                return false;
            }
            $fileSize = filesize($value->getPathname());
            return $fileSize !== false && $fileSize <= $max * 1024; // Convert KB to bytes
        }

        return mb_strlen((string)$value) <= $max;
    }

    /**
     * 从语言文件获取验证消息
     */
    protected function getValidationMessage(string $rule): string
    {
        $langFile = base_path() . '/resource/translations/' . config('translation.locale') . '/validation.php';

        if (file_exists($langFile)) {
            $messages = include $langFile;

            if (is_array($messages)) {
                // 处理 size, min, max 规则的多类型消息
                if (in_array($rule, ['size', 'min', 'max', 'between']) && isset($messages[$rule]) && is_array($messages[$rule])) {
                    $field = $this->currentField;
                    $value = $this->getValue($field);

                    if (is_numeric($value)) {
                        return $messages[$rule]['numeric'] ?? $this->getDefaultMessage($rule);
                    } elseif (is_array($value)) {
                        return $messages[$rule]['array'] ?? $this->getDefaultMessage($rule);
                    } elseif ($value instanceof UploadFile) {
                        return $messages[$rule]['file'] ?? $this->getDefaultMessage($rule);
                    } else {
                        return $messages[$rule]['string'] ?? $this->getDefaultMessage($rule);
                    }
                }

                // 处理其他规则的字符串消息
                if (isset($messages[$rule]) && is_string($messages[$rule])) {
                    return $messages[$rule];
                }
            }
        }

        // 如果语言文件不存在或消息无效，返回默认消息
        return $this->getDefaultMessage($rule);
    }

    /**
     * 执行验证但不抛出异常
     *
     * @return bool 返回 true 表示验证通过，false 表示验证失败
     */
    public function check(): bool
    {
        $this->errors = []; // 清空之前的错误
        foreach ($this->rules as $field => $ruleSet) {
            $this->validateField($field, $ruleSet);
        }
        return empty($this->errors);
    }

    /**
     * 检查验证是否失败
     *
     * @return bool 返回 true 表示验证失败，false 表示验证通过
     */
    public function fails(): bool
    {
        return !$this->check();
    }

    /**
     * 获取验证错误消息
     *
     * @return array 错误消息数组，格式为 [field => [message1, message2, ...]]
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * 获取第一个错误消息
     *
     * @param string|null $attribute 可选，指定字段名称
     * @return string|null 第一个错误消息，如果没有错误或指定字段无错误则返回 null
     */
    public function first(?string $attribute = null): ?string
    {
        if ($attribute !== null) {
            return isset($this->errors[$attribute]) && !empty($this->errors[$attribute])
                ? $this->errors[$attribute][0]
                : null;
        }

        foreach ($this->errors as $messages) {
            if (!empty($messages)) {
                return $messages[0];
            }
        }
        return null;
    }

    /**
     * 获取所有错误消息
     *
     * @param string|null $key 可选，指定字段名称
     * @return array 错误消息数组，如果指定字段则返回该字段的错误，否则返回所有错误
     */
    public function all(?string $key = null): ?array
    {
        if ($key !== null) {
            return isset($this->errors[$key]) ? $this->errors[$key] : [];
        }
        return $this->errors;
    }

    /**
     * 验证单个字段
     */
    protected function validateField(string $field, $ruleSet): void
    {
        $rules = is_string($ruleSet) ? explode('|', $ruleSet) : (array)$ruleSet;
        $value = $this->getValue($field);

        $this->currentField = $field;

        if (in_array('nullable', $rules) && $this->isEmpty($value)) {
            return;
        }

        if (in_array('sometimes', $rules) && !array_key_exists($field, $this->data)) {
            return;
        }

        foreach ($rules as $rule) {
            if ($rule !== 'nullable' && $rule !== 'sometimes') {
                $this->validateRule($field, $value, $rule);
            }
        }
    }
}
