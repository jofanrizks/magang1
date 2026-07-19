<?php

namespace Tests\Unit;

use App\Support\ActivityLogContext;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class ActivityLogContextTest extends TestCase
{
    public function test_request_context_includes_ip_and_user_agent_details(): void
    {
        $userAgent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 '
            . '(KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36';

        $context = ActivityLogContext::fromRequest($this->requestWithUserAgent(
            $userAgent,
            '192.168.1.15'
        ));

        $this->assertSame('192.168.1.15', $context['ip_address']);
        $this->assertSame('Google Chrome 147', $context['browser']);
        $this->assertSame('Linux', $context['operating_system']);
        $this->assertSame('Desktop', $context['device_type']);
        $this->assertSame($userAgent, $context['user_agent']);
    }

    public function test_android_mobile_is_detected_as_android_mobile(): void
    {
        $userAgent = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) '
            . 'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 '
            . 'Mobile Safari/537.36';

        $context = ActivityLogContext::fromRequest($this->requestWithUserAgent($userAgent));

        $this->assertSame('Android', $context['operating_system']);
        $this->assertSame('Mobile', $context['device_type']);
    }

    public function test_ipad_is_detected_as_ios_tablet(): void
    {
        $userAgent = 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) '
            . 'AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 '
            . 'Mobile/15E148 Safari/604.1';

        $context = ActivityLogContext::fromRequest($this->requestWithUserAgent($userAgent));

        $this->assertSame('iOS', $context['operating_system']);
        $this->assertSame('Tablet', $context['device_type']);
    }

    public function test_edge_and_opera_are_not_detected_as_chrome(): void
    {
        $edge = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
            . 'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 '
            . 'Safari/537.36 Edg/147.0.0.0';

        $opera = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
            . 'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 '
            . 'Safari/537.36 OPR/147.0.0.0';

        $this->assertSame(
            'Microsoft Edge 147',
            ActivityLogContext::fromRequest($this->requestWithUserAgent($edge))['browser']
        );

        $this->assertSame(
            'Opera 147',
            ActivityLogContext::fromRequest($this->requestWithUserAgent($opera))['browser']
        );
    }

    public function test_null_user_agent_does_not_throw(): void
    {
        $context = ActivityLogContext::fromRequest($this->requestWithUserAgent(null));

        $this->assertNull($context['browser']);
        $this->assertNull($context['operating_system']);
        $this->assertNull($context['device_type']);
        $this->assertNull($context['user_agent']);
    }

    private function requestWithUserAgent(
        ?string $userAgent,
        string $ipAddress = '127.0.0.1'
    ): Request {
        $server = [
            'REMOTE_ADDR' => $ipAddress,
        ];

        $server['HTTP_USER_AGENT'] = $userAgent ?? '';

        return Request::create(
            '/',
            'GET',
            [],
            [],
            [],
            $server
        );
    }
}
