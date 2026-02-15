<?php

namespace Yfsns\SmsTencent\Channels;

use App\Modules\Sms\Contracts\SmsChannelInterface;
use Illuminate\Support\Facades\Config;
use Exception;

class TencentChannel implements SmsChannelInterface
{
    protected array $config;

    public function __construct()
    {
        $this->config = $this->getDefaultConfig();
    }

    protected function getDefaultConfig(): array
    {
        return [
            'secret_id' => Config::get('sms.tencent.secret_id', ''),
            'secret_key' => Config::get('sms.tencent.secret_key', ''),
            'region_id' => Config::get('sms.tencent.region_id', 'ap-guangzhou'),
            'sdk_app_id' => Config::get('sms.tencent.sdk_app_id', ''),
            'sign_name' => Config::get('sms.tencent.sign_name', ''),
            'timeout' => Config::get('sms.tencent.timeout', 30),
        ];
    }

    public function getName(): string
    {
        return '腾讯云短信';
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function send(string $phone, string $templateCode, array $templateData = []): array
    {
        try {
            $this->validateCurrentConfig();

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
            $req->setTemplateId($templateCode);
            $req->setPhoneNumberSet([$phone]);
            $req->setTemplateParamSet(array_values($templateData));

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
            \Illuminate\Support\Facades\Log::error('腾讯云短信发送失败', [
                'phone' => $phone,
                'template' => $templateCode,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '短信发送失败：' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    public function getChannelType(): string
    {
        return 'tencent';
    }

    public function getConfigFields(): array
    {
        return [
            [
                'name' => 'secret_id',
                'label' => 'Secret ID',
                'type' => 'text',
                'required' => true,
                'default' => '',
            ],
            [
                'name' => 'secret_key',
                'label' => 'Secret Key',
                'type' => 'password',
                'required' => true,
                'default' => '',
            ],
            [
                'name' => 'region_id',
                'label' => '区域 ID',
                'type' => 'text',
                'required' => false,
                'default' => 'ap-guangzhou',
            ],
            [
                'name' => 'sdk_app_id',
                'label' => 'SDK App ID',
                'type' => 'text',
                'required' => true,
                'default' => '',
            ],
            [
                'name' => 'sign_name',
                'label' => '短信签名',
                'type' => 'text',
                'required' => true,
                'default' => '',
            ],
        ];
    }

    public function validateConfig(array $config): array
    {
        $errors = [];

        if (empty($config['secret_id'] ?? $this->config['secret_id'])) {
            $errors[] = 'Secret ID 不能为空';
        }
        if (empty($config['secret_key'] ?? $this->config['secret_key'])) {
            $errors[] = 'Secret Key 不能为空';
        }
        if (empty($config['sdk_app_id'] ?? $this->config['sdk_app_id'])) {
            $errors[] = 'SDK App ID 不能为空';
        }
        if (empty($config['sign_name'] ?? $this->config['sign_name'])) {
            $errors[] = '短信签名不能为空';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => [],
        ];
    }

    public function getCapabilities(): array
    {
        return ['verification', 'notification', 'marketing', 'international'];
    }

    public function testConnection(array $config): array
    {
        try {
            $testConfig = array_merge($this->config, $config);
            $this->validateConfig($testConfig);

            return [
                'success' => true,
                'message' => '腾讯云短信服务连接正常',
                'data' => [
                    'region' => $testConfig['region_id'],
                    'sdk_app_id' => $testConfig['sdk_app_id'],
                    'sign_name' => $testConfig['sign_name'],
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

    public function getProviderInfo(): array
    {
        return [
            'name' => '腾讯云',
            'website' => 'https://cloud.tencent.com/',
            'description' => '腾讯云 SMS 短信服务',
            'regions' => [
                'ap-guangzhou', 'ap-beijing', 'ap-shanghai',
                'ap-hongkong', 'ap-singapore', 'na-siliconvalley',
            ],
        ];
    }

    public function supportsInternational(): bool
    {
        return true;
    }

    public function getSupportedRegions(): array
    {
        return ['CN', 'HK', 'US', 'SG', 'JP', 'KR'];
    }

    protected function validateCurrentConfig(): void
    {
        $result = $this->validateConfig([]);
        if (!$result['valid']) {
            throw new Exception(implode(', ', $result['errors']));
        }
    }
}
