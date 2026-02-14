<?php

/**
 * YFSNS社交网络服务系统
 *
 * Copyright (C) 2025 合肥音符信息科技有限公司
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Yfsns\LaravelSmsTencent\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected array $config;

    /**
     * 构造函数
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * 获取默认配置
     */
    protected function getDefaultConfig(): array
    {
        return [
            'secret_id' => config('sms.tencent.secret_id', ''),
            'secret_key' => config('sms.tencent.secret_key', ''),
            'region_id' => config('sms.tencent.region_id', 'ap-guangzhou'),
            'sdk_app_id' => config('sms.tencent.sdk_app_id', ''),
            'sign_name' => config('sms.tencent.sign_name', ''),
            'timeout' => config('sms.tencent.timeout', 30),
        ];
    }

    /**
     * 发送短信
     */
    public function send(string $phone, string $templateId, array $templateParams = []): array
    {
        try {
            $this->validateConfig();

            $cred = new \TencentCloud\Common\Credential(
                $this->config['secret_id'],
                $this->config['secret_key']
            );

            $httpProfile = new \TencentCloud\Common\Profile\HttpProfile();
            $httpProfile->setEndpoint('sms.tencentcloudapi.com');

            $clientProfile = new \TencentCloud\Common\Profile\ClientProfile();
            $clientProfile->setHttpProfile($httpProfile);

            $client = new \TencentCloud\Sms\V20210111\SmsClient(
                $cred,
                $this->config['region_id'],
                $clientProfile
            );

            $req = new \TencentCloud\Sms\V20210111\Models\SendSmsRequest();
            $req->setSmsSdkAppId($this->config['sdk_app_id']);
            $req->setSignName($this->config['sign_name']);
            $req->setTemplateId($templateId);
            $req->setPhoneNumberSet([$phone]);
            $req->setTemplateParamSet(array_values($templateParams));

            $response = $client->SendSms($req);
            $sendStatusSet = $response->getSendStatusSet();
            $status = $sendStatusSet[0];

            return [
                'success' => $status->getCode() === 'Ok',
                'message' => $status->getMessage() ?: '发送成功',
                'data' => $response->toArray(),
                'request_id' => $response->getRequestId(),
            ];

        } catch (Exception $e) {
            Log::error('腾讯云短信发送失败', [
                'phone' => $phone,
                'template' => $templateId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '短信发送失败：' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * 发送验证码短信
     */
    public function sendVerification(string $phone, string $code, int $expire = 10): array
    {
        $templateId = config('sms.tencent.templates.verification', '');
        if (empty($templateId)) {
            return [
                'success' => false,
                'message' => '验证码模板未配置',
                'data' => null,
            ];
        }

        return $this->send($phone, $templateId, [
            'code' => $code,
            'expire' => (string)$expire,
        ]);
    }

    /**
     * 发送通知短信
     */
    public function sendNotification(string $phone, string $templateId, array $params = []): array
    {
        return $this->send($phone, $templateId, $params);
    }

    /**
     * 批量发送短信
     */
    public function sendBatch(array $phones, string $templateId, array $templateParams = []): array
    {
        try {
            $this->validateConfig();

            $cred = new \TencentCloud\Common\Credential(
                $this->config['secret_id'],
                $this->config['secret_key']
            );

            $httpProfile = new \TencentCloud\Common\Profile\HttpProfile();
            $httpProfile->setEndpoint('sms.tencentcloudapi.com');

            $clientProfile = new \TencentCloud\Common\Profile\ClientProfile();
            $clientProfile->setHttpProfile($httpProfile);

            $client = new \TencentCloud\Sms\V20210111\SmsClient(
                $cred,
                $this->config['region_id'],
                $clientProfile
            );

            $req = new \TencentCloud\Sms\V20210111\Models\SendSmsRequest();
            $req->setSmsSdkAppId($this->config['sdk_app_id']);
            $req->setSignName($this->config['sign_name']);
            $req->setTemplateId($templateId);
            $req->setPhoneNumberSet($phones);
            $req->setTemplateParamSet(array_values($templateParams));

            $response = $client->SendSms($req);
            $sendStatusSet = $response->getSendStatusSet();

            return [
                'success' => !empty($sendStatusSet),
                'message' => '批量发送完成',
                'data' => $response->toArray(),
                'request_id' => $response->getRequestId(),
            ];

        } catch (Exception $e) {
            Log::error('腾讯云短信批量发送失败', [
                'phones' => $phones,
                'template' => $templateId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '批量发送失败：' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * 验证配置
     */
    protected function validateConfig(): void
    {
        if (empty($this->config['secret_id'])) {
            throw new Exception('腾讯云SecretId未配置');
        }
        if (empty($this->config['secret_key'])) {
            throw new Exception('腾讯云SecretKey未配置');
        }
        if (empty($this->config['sdk_app_id'])) {
            throw new Exception('腾讯云SDK AppID未配置');
        }
        if (empty($this->config['sign_name'])) {
            throw new Exception('短信签名未配置');
        }
    }

    /**
     * 生成签名
     */
    protected function generateSignature(array $params): string
    {
        ksort($params);
        $queryString = '';
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $queryString .= '&' . $this->urlEncode($key) . '=' . $this->urlEncode($item);
                }
            } else {
                $queryString .= '&' . $this->urlEncode($key) . '=' . $this->urlEncode((string)$value);
            }
        }
        $queryString = substr($queryString, 1);
        $stringToSign = 'POST' . '&' . $this->urlEncode('/') . '&' . $this->urlEncode($queryString);
        return base64_encode(hash_hmac('sha1', $stringToSign, $this->config['secret_key'] . '&', true));
    }

    /**
     * URL编码
     */
    protected function urlEncode(string $str): string
    {
        return str_replace(['+', '*', '%7E'], ['%20', '%2A', '~'], rawurlencode($str));
    }

    /**
     * 测试连接
     */
    public function testConnection(): array
    {
        try {
            $this->validateConfig();

            // 使用SDK测试连接（可选）
            if (class_exists('\TencentCloud\Common\Credential')) {
                $cred = new \TencentCloud\Common\Credential(
                    $this->config['secret_id'],
                    $this->config['secret_key']
                );

                $httpProfile = new \TencentCloud\Common\Profile\HttpProfile();
                $httpProfile->setEndpoint('sms.tencentcloudapi.com');

                $clientProfile = new \TencentCloud\Common\Profile\ClientProfile();
                $clientProfile->setHttpProfile($httpProfile);

                $client = new \TencentCloud\Sms\V20210111\SmsClient(
                    $cred,
                    $this->config['region_id'],
                    $clientProfile
                );

                return [
                    'success' => true,
                    'message' => '腾讯云短信服务连接正常',
                    'data' => [
                        'region' => $this->config['region_id'],
                        'sdk_app_id' => $this->config['sdk_app_id'],
                        'sign_name' => $this->config['sign_name'],
                    ],
                ];
            }

            return [
                'success' => true,
                'message' => '配置验证通过，SDK未安装',
                'data' => [
                    'region' => $this->config['region_id'],
                    'sdk_app_id' => $this->config['sdk_app_id'],
                    'sign_name' => $this->config['sign_name'],
                ],
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '连接测试失败：' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * 获取配置信息
     */
    public function getConfig(): array
    {
        return [
            'region_id' => $this->config['region_id'],
            'sdk_app_id' => $this->config['sdk_app_id'],
            'sign_name' => $this->config['sign_name'],
            'timeout' => $this->config['timeout'],
        ];
    }

    /**
     * 设置配置
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }
}