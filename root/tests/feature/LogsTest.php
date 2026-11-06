<?php

use CodeIgniter\HTTP\Response;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * admin/logs/activities: the activity log page and its JSON endpoint, with LINK_INTERNAL_API stood in for
 *
 * @internal
 */
final class LogsTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    /** [endpoint, payload] of every API call */
    public array $calls = [];

    /** endpoint => [status, body] the stand-in answers */
    public array $answers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $test = $this;
        Services::injectMock('curlrequest', new class ($test) {
            public function __construct(private LogsTest $test) {}

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
                [$status, $body]     = $this->test->answers[$endpoint] ?? [404, ['status' => 'error', 'message' => 'Not stubbed']];

                return (new Response(config('App')))->setStatusCode($status)->setBody(json_encode($body));
            }
        });
        $this->answers['v1/staff/logs/tables'] = [200, ['status' => 'success', 'data' => ['tables' => ['payment_method', 'plan_master']]]];
    }

    private function signIn(string $department = 'EXE'): void
    {
        $_SESSION = [];
        $this->withSession([
            'access_token' => 'test-token',
            'expires_at'   => time() + 3600,
            'user'         => ['uid' => 'tester', 'email_address' => 'exec@otternova.com', 'full_name' => 'Otter Tester',
                'account_type' => 'S', 'department_code' => $department, 'avatar_path' => null],
        ]);
    }

    public function testOtherDepartmentsAreTurnedAway(): void
    {
        $this->signIn('FIN');
        $this->get('admin/logs/activities')->assertStatus(403);
    }

    public function testPageTakesTheTableAndRecordFromTheUrl(): void
    {
        $this->signIn();
        $response = $this->get('admin/logs/activities?table=plan_master&id=12&search=PLUS');
        $response->assertStatus(200);
        $html = $response->getBody();

        $this->assertMatchesRegularExpression('/<option value="plan_master" selected>/', $html);
        $this->assertMatchesRegularExpression('/data-lb-filter="table_id_updated"[^>]*value="12"/', $html);
        $this->assertStringContainsString('"search":{"search":"PLUS"}', $html);
        $this->assertStringContainsString('Activity Logs', $html);
    }

    public function testRecordIdAndSearchNeedATable(): void
    {
        $this->signIn();
        $html = $this->get('admin/logs/activities?id=12&search=PLUS')->getBody();

        $this->assertMatchesRegularExpression('/data-lb-filter="table_id_updated"[^>]*value=""/', $html);
        $this->assertStringNotContainsString('"search":{"search"', $html);
    }

    public function testUnknownTableFromALinkIsStillOffered(): void
    {
        $this->signIn();
        $html = $this->get('admin/logs/activities?table=artifact_master')->getBody();
        $this->assertMatchesRegularExpression('/<option value="artifact_master" selected>/', $html);
        $this->assertStringNotContainsString('value="Robert\');', $this->get('admin/logs/activities?table=Robert%27)%3B')->getBody());
    }

    public function testListIsForwardedAndFormatted(): void
    {
        $this->signIn();
        $this->answers['v1/staff/logs/activities'] = [200, ['status' => 'success', 'data' => [
            'records_total' => 2, 'records_filtered' => 2, 'rows' => [
                ['id' => 9, 'activity_summary' => 'Changed <b>plan</b>', 'table_involved' => 'plan_master', 'table_id_updated' => 12,
                    'activity_detail' => ['action' => 'update', 'changes' => ['plan_code' => ['old' => 'BASIC', 'new' => '<i>PLUS</i>'], 'note' => ['old' => null, 'new' => '']]],
                    'actor' => 'account', 'actor_id' => 1, 'actor_name' => 'Otter Tester', 'actor_email' => 'exec@otternova.com', 'created_at' => '2026-11-06 01:02:03'],
                ['id' => 8, 'activity_summary' => 'Created the plan', 'table_involved' => 'plan_master', 'table_id_updated' => 12,
                    'activity_detail' => ['action' => 'insert', 'new' => ['plan_code' => 'BASIC']],
                    'actor' => 'system', 'actor_id' => null, 'actor_name' => null, 'actor_email' => null, 'created_at' => '2026-11-06 01:00:00'],
            ],
        ]]];

        $response = $this->post('admin/logs/activities', [
            'draw' => 3, 'start' => 0, 'length' => 25, 'search' => ['value' => 'PLUS'],
            'filters' => ['table_involved' => 'plan_master', 'table_id_updated' => '12'],
        ]);
        $response->assertStatus(200);
        $json = json_decode($response->getJSON(), true);

        $this->assertSame(['v1/staff/logs/activities', ['start' => 0, 'length' => 25, 'search' => 'PLUS',
            'filters' => ['table_involved' => 'plan_master', 'table_id_updated' => '12']]], end($this->calls));
        $this->assertSame([3, 2, 2], [$json['draw'], $json['recordsTotal'], $json['recordsFiltered']]);

        [$update, $insert] = $json['data'];
        $this->assertSame('Changed <b>plan</b>', $update['summary']); // escaped by the table's text renderer
        $this->assertStringContainsString('admin/logs/activities?table=plan_master&id=12"', html_entity_decode($update['record']));
        $this->assertStringContainsString('<code>plan_code</code>: BASIC &rarr; &lt;i&gt;PLUS&lt;/i&gt;', $update['changes']);
        $this->assertStringContainsString('<em class="text-secondary">null</em> &rarr; <em class="text-secondary">empty</em>', $update['changes']);
        $this->assertStringContainsString('Otter Tester', $update['actor']);
        $this->assertStringContainsString('exec@otternova.com', $update['actor']);
        $this->assertStringContainsString('Created with 1 value', $insert['changes']);
        $this->assertStringContainsString('>System<', $insert['actor']);
    }

    public function testApiRefusalIsShown(): void
    {
        $this->signIn();
        $this->answers['v1/staff/logs/activities'] = [422, ['status' => 'error', 'message' => 'Please choose the table of the record ID.']];

        $json = json_decode($this->post('admin/logs/activities', ['draw' => 1, 'filters' => ['table_id_updated' => '3']])->getJSON(), true);
        $this->assertSame('Please choose the table of the record ID.', $json['error']);
        $this->assertSame([], $json['data']);
    }
}
