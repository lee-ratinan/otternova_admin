<?php

use CodeIgniter\HTTP\Response;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * admin/inbox (the contact form's messages), and on admin/clients/*: the subscription status change and the invoice
 * and receipt pages, with LINK_INTERNAL_API stood in for
 *
 * @internal
 */
final class InboxAndBillingTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    /** [endpoint, payload] of every API call */
    public array $calls = [];

    /** endpoint => [status, body (array as JSON, string as is), Content-Type] the stand-in answers */
    public array $answers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $test = $this;
        Services::injectMock('curlrequest', new class ($test) {
            public function __construct(private InboxAndBillingTest $test) {}

            public function post(string $url, array $options = []): Response
            {
                return $this->answer($url, $options['json'] ?? null);
            }

            public function get(string $url, array $options = []): Response
            {
                return $this->answer($url, null);
            }

            private function answer(string $url, mixed $payload): Response
            {
                $endpoint            = substr($url, strlen((string) getenv('LINK_INTERNAL_API')));
                $this->test->calls[] = [$endpoint, json_decode(json_encode($payload), true)];
                [$status, $body, $type] = ($this->test->answers[$endpoint] ?? [404, ['status' => 'error', 'message' => 'Not stubbed']]) + [2 => 'application/json'];

                return (new Response(config('App')))->setStatusCode($status)->setHeader('Content-Type', $type)
                    ->setBody(is_string($body) ? $body : json_encode($body));
            }
        });
    }

    private function signIn(string $department = 'FIN'): void
    {
        $_SESSION = [];
        $this->withSession([
            'access_token' => 'test-token',
            'expires_at'   => time() + 3600,
            'user'         => ['uid' => 'tester', 'email_address' => 'staff@otternova.com', 'full_name' => 'Otter Tester',
                'account_type' => 'S', 'department_code' => $department, 'avatar_path' => null],
        ]);
    }

    // ---- inbox ----

    public function testInboxListIsForwardedAndEscaped(): void
    {
        $this->signIn('SUP');
        $this->ok($this->get('admin/inbox'), 200)->assertSee('Contact Form Messages');

        $this->answers['v1/staff/inbox/messages'] = [200, ['status' => 'success', 'data' => [
            'records_total' => 3, 'records_filtered' => 1, 'rows' => [
                ['id' => 7, 'name' => '<b>Ann</b>', 'email_address' => 'ann@example.com', 'phone_number' => null, 'subject' => 'Pricing <i>?</i>',
                    'excerpt' => 'Hello <script>', 'locale' => 'en-US', 'status' => 'N', 'spam_reason' => null,
                    'handled_at' => null, 'handled_by_name' => null, 'created_at' => '2026-11-08 01:02:03'],
            ],
        ]]];
        $response = $this->post('admin/inbox', ['draw' => 2, 'start' => 0, 'length' => 25, 'search' => ['value' => 'ann'], 'filters' => ['status' => 'N']]);
        $response->assertStatus(200);
        $json = json_decode($response->getJSON(), true);

        $this->assertSame(['v1/staff/inbox/messages', ['start' => 0, 'length' => 25, 'search' => 'ann', 'filters' => ['status' => 'N']]], end($this->calls));
        $this->assertSame([2, 3, 1], [$json['draw'], $json['recordsTotal'], $json['recordsFiltered']]);
        $row = $json['data'][0];
        $this->assertStringContainsString('&lt;b&gt;Ann&lt;/b&gt;', $row['sender']);
        $this->assertStringContainsString('Hello &lt;script&gt;', $row['topic']);
        $this->assertSame('—', $row['handled']);
        $this->assertStringEndsWith('admin/inbox/view/7', $row['edit_url'] ?? end($row));
    }

    public function testMessagePageShowsTheMessageAndItsActions(): void
    {
        $this->signIn('SUP');
        $this->answers['v1/staff/inbox/message'] = [200, ['status' => 'success', 'data' => ['message' => [
            'id' => 7, 'name' => 'Ann', 'email_address' => 'ann@example.com', 'phone_number' => '+66812345678', 'subject' => 'Pricing',
            'message' => "Line one\n<b>two</b>", 'locale' => 'th', 'status' => 'R', 'spam_reason' => null, 'ip_address' => '203.0.113.9',
            'user_agent' => 'Firefox', 'handled_at' => '2026-11-08 02:00:00', 'handled_by_name' => 'Otter Tester', 'created_at' => '2026-11-08 01:02:03',
        ]]]];

        $html = $this->ok($this->get('admin/inbox/view/7'), 200)->getBody();
        $this->assertSame(['v1/staff/inbox/message', ['id' => 7]], end($this->calls));
        $this->assertStringContainsString("Line one\n&lt;b&gt;two&lt;/b&gt;", $html);
        $this->assertStringContainsString('mailto:ann@example.com?subject=Re%3A%20Pricing', $html);
        $this->assertStringContainsString('data-status="D"', $html);
        $this->assertStringContainsString('data-status="S"', $html);
        $this->assertStringContainsString('data-status="N"', $html);
        $this->assertStringNotContainsString('data-status="R"', $html); // only offered for spam

        $this->answers['v1/staff/inbox/message'] = [404, ['status' => 'error', 'message' => 'This message could not be found.']];
        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $this->get('admin/inbox/view/8');
    }

    public function testMessageStatusIsForwarded(): void
    {
        $this->signIn('SUP');
        $this->answers['v1/staff/inbox/message/status'] = [200, ['status' => 'success', 'message' => 'Message updated.', 'data' => []]];
        $json = json_decode($this->ok($this->post('admin/inbox/status', ['id' => '7', 'status' => 'd']), 200)->getJSON(), true);
        $this->assertTrue($json['success']);
        $this->assertSame(['v1/staff/inbox/message/status', ['id' => 7, 'status' => 'D']], end($this->calls));

        $this->answers['v1/staff/inbox/message/status'] = [422, ['status' => 'error', 'message' => 'Please choose new, read, done or spam.']];
        $json = json_decode($this->ok($this->post('admin/inbox/status', ['id' => '7', 'status' => 'z']), 422)->getJSON(), true);
        $this->assertSame('Please choose new, read, done or spam.', $json['message']);
    }

    // ---- subscriptions and documents ----

    public function testSubscriptionOffersTheChangesItsStatusAllows(): void
    {
        $this->signIn();
        $this->answers['v1/staff/subscriptions/subscription'] = [200, ['status' => 'success', 'data' => [
            'subscription' => $this->subscription('A'),
            'invoices'     => [
                $this->invoice('INV-202611-000002', null, 'P'),
                $this->invoice('INV-202610-000001', 'RCT-202610-000001', 'D'),
            ],
        ]]];
        $html = $this->ok($this->get('admin/clients/subscriptions/view/SUB123'), 200)->getBody();
        $this->assertStringContainsString('data-status="S"', $html);
        $this->assertStringContainsString('data-status="C"', $html);
        $this->assertStringNotContainsString('data-status="A"', $html);
        $this->assertStringContainsString('admin/clients/documents/invoice/INV-202611-000002', $html);
        $this->assertStringContainsString('admin/clients/documents/receipt/RCT-202610-000001?download=1', $html);
        $this->assertStringNotContainsString('RCT-202611', $html);

        // suspended within its period: reactivate or cancel; ended: nothing
        $this->answers['v1/staff/subscriptions/subscription'][1]['data']['subscription'] = $this->subscription('S');
        $html = $this->get('admin/clients/subscriptions/view/SUB123')->getBody();
        $this->assertStringContainsString('data-status="A"', $html);
        $this->assertStringNotContainsString('data-status="S"', $html);
        $this->answers['v1/staff/subscriptions/subscription'][1]['data']['subscription'] = $this->subscription('C');
        $this->assertStringNotContainsString('data-status-actions', $this->get('admin/clients/subscriptions/view/SUB123')->getBody());
    }

    public function testStatusChangeIsForwardedAndRefusalsShown(): void
    {
        $this->signIn();
        $this->answers['v1/staff/subscriptions/subscription/status'] = [200, ['status' => 'success', 'message' => 'Subscription updated.', 'data' => []]];
        $json = json_decode($this->ok($this->post('admin/clients/subscriptions/status', ['public_id' => 'SUB123', 'subscription_status' => 's']), 200)->getJSON(), true);
        $this->assertSame('Subscription updated.', $json['message']);
        $this->assertSame(['v1/staff/subscriptions/subscription/status', ['public_id' => 'SUB123', 'subscription_status' => 'S']], end($this->calls));

        $this->answers['v1/staff/subscriptions/subscription/status'] = [409, ['status' => 'error', 'message' => 'Only an active subscription can be suspended.']];
        $json = json_decode($this->ok($this->post('admin/clients/subscriptions/status', ['public_id' => 'SUB123', 'subscription_status' => 'S']), 409)->getJSON(), true);
        $this->assertFalse($json['success']);
        $this->assertSame('Only an active subscription can be suspended.', $json['message']);
    }

    public function testOnlyBillingStaffChangeStatusOrOpenDocuments(): void
    {
        $this->signIn('SUP');
        $this->post('admin/clients/subscriptions/status', ['public_id' => 'SUB123', 'subscription_status' => 'C'])->assertStatus(403);
        $this->get('admin/clients/documents/invoice/INV-202611-000002')->assertStatus(403);
        $this->assertSame([], array_filter($this->calls, static fn (array $call): bool => str_contains($call[0], 'subscriptions') || str_contains($call[0], 'documents')));
    }

    public function testDocumentIsPassedOnInlineOrAsADownload(): void
    {
        $this->signIn();
        $this->answers['v1/staff/documents/invoice/INV-202611-000002'] = [200, '<!doctype html><title>INV</title>', 'text/html; charset=UTF-8'];

        $view = $this->get('admin/clients/documents/invoice/inv-202611-000002');
        $view->assertStatus(200);
        $this->assertStringContainsString('<title>INV</title>', $view->getBody());
        $this->assertSame('inline; filename="INV-202611-000002.html"', $view->response()->getHeaderLine('Content-Disposition'));
        $this->assertStringContainsString("default-src 'none'", $view->response()->getHeaderLine('Content-Security-Policy'));

        $download = $this->get('admin/clients/documents/invoice/INV-202611-000002?download=1');
        $this->assertSame('attachment; filename="INV-202611-000002.html"', $download->response()->getHeaderLine('Content-Disposition'));

        // a number that is not one is never asked for; one the API does not know is a 404
        $calls = count($this->calls);
        foreach (['receipt/INV-202611-000002', 'receipt/RCT-202611-000009'] as $path) {
            try {
                $this->get('admin/clients/documents/' . $path);
                $this->fail('No 404 for ' . $path);
            } catch (\CodeIgniter\Exceptions\PageNotFoundException) {
            }
        }
        $this->assertCount($calls + 1, $this->calls); // the INV- number as a receipt was never sent
    }

    private function ok(\CodeIgniter\Test\TestResponse $response, int $status): \CodeIgniter\Test\TestResponse
    {
        $response->assertStatus($status);

        return $response;
    }

    private function subscription(string $status): array
    {
        return ['public_id' => 'SUB123', 'business_name' => 'Otter Cafe', 'business_slug' => 'otter-cafe', 'business_country_code' => 'TH',
            'subscription_status' => $status, 'cancel_at_period_end' => 'N', 'plan_code' => 'PLUS', 'billing_cycle' => 'M',
            'currency_code' => 'THB', 'price' => '299.00', 'billing_mode' => 'M', 'billing_anchor_day' => 8,
            'current_period_start' => '2026-11-01 00:00:00', 'current_period_end' => gmdate('Y-m-d H:i:s', time() + 86400 * 10),
            'next_billing_at' => null, 'created_at' => '2026-10-01 00:00:00'];
    }

    private function invoice(string $invoiceNo, ?string $receiptNo, string $status): array
    {
        return ['public_id' => 'I' . substr($invoiceNo, -6), 'invoice_no' => $invoiceNo, 'receipt_no' => $receiptNo, 'invoice_type' => 'S',
            'plan_code' => 'PLUS', 'billing_cycle' => 'M', 'currency_code' => 'THB', 'amount_due' => '299.00', 'amount_payable' => '299.00',
            'period_start' => null, 'period_end' => null, 'due_at' => '2026-11-15 00:00:00', 'invoice_status' => $status, 'payments' => []];
    }
}
