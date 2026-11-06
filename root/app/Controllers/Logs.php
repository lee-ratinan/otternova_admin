<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * The activity log (log_activity), behind the auth and department:EXE filters, read through LINK_INTERNAL_API
 * v1/staff/logs/*, which checks the department again. A record's history has its own link:
 * admin/logs/activities?table=plan_master&id=12 (and &search=… to look within it). Every time from the API is UTC
 * and shown in the viewer's timezone.
 */
class Logs extends BaseController
{
    /** A table_involved as the API writes it */
    private const TABLE_PATTERN = '/^[a-z][a-z0-9_]{0,127}$/';

    public const ACTORS = [
        'system'   => ['label' => 'System', 'class' => 'bg-secondary'],
        'customer' => ['label' => 'Customer', 'class' => 'bg-info text-dark'],
        'account'  => ['label' => 'Account', 'class' => 'bg-primary'],
    ];

    /**
     * The log, newest first; ?table= and ?id= filter it to a table or one record, and ?search= looks within that
     * record's history (the search box is off until a record is chosen, so it never scans the whole log)
     * @return string
     */
    public function activities(): string
    {
        $table  = trim((string) $this->request->getGet('table'));
        $table  = preg_match(self::TABLE_PATTERN, $table) ? $table : '';
        $id     = filter_var($this->request->getGet('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $id     = $table !== '' && $id !== false ? (string) $id : ''; // a record ID means nothing without its table
        $search = $id !== '' ? trim((string) $this->request->getGet('search')) : '';

        $tables = $this->tableOptions();
        if ($table !== '' && ! isset($tables[$table])) {
            $tables[$table] = $table; // a link to a table with no log yet still shows what it asked for
            ksort($tables);
        }

        $config           = $this->listConfig($tables);
        $config['values'] = ['table_involved' => $table, 'table_id_updated' => $id];
        if ($search !== '') {
            $config['dt_options']['search'] = ['search' => $search];
        }

        return view('admin_logs_activities', [
            'page_title' => 'Activity Logs',
            'slug'       => 'logs-activities',
            'listConfig' => $config,
        ]);
    }

    /**
     * One page of the log: the DataTables request forwarded to LINK_INTERNAL_API v1/staff/logs/activities
     * @return ResponseInterface
     */
    public function activitiesList(): ResponseInterface
    {
        $config  = $this->listConfig([]);
        $params  = lb_request($this->request, $config);
        $filters = [];
        foreach ($params['filters'] as $item) {
            $filters[$item['def']['name']] = (string) $item['value'];
        }
        [$code, $body] = $this->callApi('v1/staff/logs/activities', [
            'start'   => $params['start'],
            'length'  => $params['length'],
            'search'  => $params['search'],
            'filters' => $filters,
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, [])
                + ['error' => $body['message'] ?? 'Could not load the activity log. Please try again.', 'csrf_hash' => csrf_hash()]);
        }

        $result = (array) ($body['data'] ?? []);
        $rows   = array_map(static function (array $row): array {
            return [
                'id'         => $row['id'],
                'created_at' => (string) ($row['created_at'] ?? ''),
                'summary'    => (string) ($row['activity_summary'] ?? ''),
                'table'      => self::tableLink($row['table_involved'] ?? null),
                'record'     => self::recordLink($row['table_involved'] ?? null, $row['table_id_updated'] ?? null),
                'actor'      => self::actorHtml($row),
                'changes'    => self::detailHtml($row['activity_detail'] ?? null),
            ];
        }, (array) ($result['rows'] ?? []));

        return $this->response->setJSON(lb_response(
            $params['draw'],
            (int) ($result['records_total'] ?? 0),
            (int) ($result['records_filtered'] ?? 0),
            $rows
        ) + ['csrf_hash' => csrf_hash()]);
    }

    // ---- configuration ----

    /**
     * Table Builder configuration of the log, shared by the page and its JSON endpoint
     * @param array<string, string> $tables table => label for the table filter; none for the JSON endpoint (the API checks it)
     * @return array
     */
    private function listConfig(array $tables): array
    {
        return [
            'table_id'    => 'activityLogTable',
            'title'       => 'Activity Logs',
            'subtitle'    => 'Every change in the system, newest first. Choose a table and a record ID to see one record\'s history; the search box then looks within it.',
            'id_field'    => 'id',
            'editable'    => false,
            'urls'        => ['data' => base_url('admin/logs/activities')],
            'columns'     => [
                self::column('created_at', 'Time', ['type' => 'datetime', 'class' => 'text-nowrap']),
                self::column('summary', 'Summary'),
                self::column('table', 'Table', ['type' => 'html']),
                self::column('record', 'Record ID', ['type' => 'html', 'class' => 'text-end']),
                self::column('actor', 'Changed by', ['type' => 'html']),
                self::column('changes', 'Changes', ['type' => 'html', 'width' => '35%']),
            ],
            'filters'     => [
                ['name' => 'table_involved', 'type' => 'select', 'label' => 'Table', 'options' => $tables, 'col' => 4],
                ['name' => 'table_id_updated', 'type' => 'number', 'label' => 'Record ID', 'min' => 1, 'step' => 1,
                    'placeholder' => 'Choose a table first', 'col' => 3],
            ],
            'order'       => [],
            'page_length' => 25,
            'dt_options'  => ['ordering' => false],
        ];
    }

    /**
     * A Table Builder column the API orders (never by the table) and searches itself
     * @param string $data
     * @param string $title
     * @param array  $extra
     * @return array
     */
    private static function column(string $data, string $title, array $extra = []): array
    {
        return $extra + ['data' => $data, 'db' => $data, 'title' => $title, 'orderable' => false, 'searchable' => false];
    }

    /**
     * The tables the log has rows of, as table => table; none when they cannot be loaded
     * @return array<string, string>
     */
    private function tableOptions(): array
    {
        [$code, $body] = $this->callApi('v1/staff/logs/tables', []);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return [];
        }
        $tables = array_values(array_filter((array) ($body['data']['tables'] ?? []), 'is_string'));

        return array_combine($tables, $tables);
    }

    // ---- formatting ----

    /**
     * The link to this page filtered to a table, or a record of it
     * @param string   $table
     * @param int|null $id
     * @return string
     */
    public static function url(string $table, ?int $id = null): string
    {
        return base_url('admin/logs/activities') . '?' . http_build_query(array_filter(['table' => $table, 'id' => $id]));
    }

    /**
     * @param string|null $table
     * @return string
     */
    private static function tableLink(?string $table): string
    {
        return null === $table || '' === $table ? '<span class="text-muted">&mdash;</span>'
            : '<a href="' . esc(self::url($table), 'attr') . '"><code>' . esc($table) . '</code></a>';
    }

    /**
     * @param string|null $table
     * @param mixed       $id
     * @return string
     */
    private static function recordLink(?string $table, mixed $id): string
    {
        if (null === $id || '' === $id) {
            return '<span class="text-muted">&mdash;</span>';
        }
        return null === $table || '' === $table ? esc((string) $id)
            : '<a href="' . esc(self::url($table, (int) $id), 'attr') . '" title="This record\'s history">' . esc((string) $id) . '</a>';
    }

    /**
     * The actor's badge with the account's name and email, or the customer's email
     * @param array $row
     * @return string
     */
    private static function actorHtml(array $row): string
    {
        $actor = (string) ($row['actor'] ?? 'system');
        $badge = self::ACTORS[$actor] ?? self::ACTORS['system'];
        $html  = '<span class="badge ' . esc($badge['class'], 'attr') . '">' . esc($badge['label']) . '</span>';
        if ($actor === 'system') {
            return $html;
        }
        $name  = trim((string) ($row['actor_name'] ?? ''));
        $email = trim((string) ($row['actor_email'] ?? ''));
        $who   = $name !== '' ? esc($name) : ($email !== '' ? esc($email) : '#' . (int) ($row['actor_id'] ?? 0));
        if ($name !== '' && $email !== '') {
            $who .= '<div class="small text-secondary">' . esc($email) . '</div>';
        }

        return $html . '<div class="text-break">' . $who . '</div>';
    }

    /**
     * activity_detail as ActivityLog writes it: an update lists each field's old and new value; an insert or delete
     * lists the row's values in a collapsed block; anything else is shown as JSON
     * @param mixed $detail
     * @return string
     */
    private static function detailHtml(mixed $detail): string
    {
        if (! is_array($detail) || $detail === []) {
            return '<span class="text-muted">&mdash;</span>';
        }
        $action = $detail['action'] ?? null;
        if ($action === 'update' && is_array($detail['changes'] ?? null)) {
            $html = '<ul class="list-unstyled small mb-0">';
            foreach ($detail['changes'] as $field => $change) {
                $html .= '<li class="text-break"><code>' . esc((string) $field) . '</code>: '
                    . self::value(is_array($change) ? ($change['old'] ?? null) : null) . ' &rarr; '
                    . self::value(is_array($change) ? ($change['new'] ?? null) : $change) . '</li>';
            }
            return $html . '</ul>';
        }
        if (in_array($action, ['insert', 'delete'], true) && is_array($detail[$action === 'insert' ? 'new' : 'old'] ?? null)) {
            $values = $detail[$action === 'insert' ? 'new' : 'old'];
            $html   = '<details class="small"><summary>' . ($action === 'insert' ? 'Created' : 'Deleted') . ' with '
                . count($values) . ' ' . (count($values) === 1 ? 'value' : 'values') . '</summary><ul class="list-unstyled mb-0 mt-1">';
            foreach ($values as $field => $value) {
                $html .= '<li class="text-break"><code>' . esc((string) $field) . '</code>: ' . self::value($value) . '</li>';
            }
            return $html . '</ul></details>';
        }

        return '<details class="small"><summary>Details</summary><pre class="mb-0 mt-1 text-wrap text-break">'
            . esc((string) json_encode($detail, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre></details>';
    }

    /**
     * One logged value, escaped; null and '' are marked so they are not mistaken for each other
     * @param mixed $value
     * @return string
     */
    private static function value(mixed $value): string
    {
        if (null === $value) {
            return '<em class="text-secondary">null</em>';
        }
        if ('' === $value) {
            return '<em class="text-secondary">empty</em>';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return '<span class="font-monospace">' . esc((string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</span>';
        }

        return esc((string) $value);
    }

    // ---- API ----

    /**
     * POST to LINK_INTERNAL_API with the signed-in user's JWT and return [http status, decoded body]
     * @param string $endpoint
     * @param array  $payload Sent as JSON
     * @return array
     */
    private function callApi(string $endpoint, array $payload): array
    {
        try {
            $response = service('curlrequest')->post(getenv('LINK_INTERNAL_API') . $endpoint, [
                'json'        => (object) $payload,
                'headers'     => [
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . session()->get('access_token'),
                ],
                'timeout'     => 30,
                'http_errors' => false,
            ]);
            $code = $response->getStatusCode();
            $body = json_decode($response->getBody(), true);
            // a 404 without the API's own error body means the endpoint is missing, not the record
            if (! is_array($body) || $code >= 500 || ($code === 404 && ($body['status'] ?? '') !== 'error')) {
                log_message('error', 'LINK_INTERNAL_API ' . $endpoint . ' returned HTTP ' . $code);
                return [503, ['message' => 'The service is unavailable. Please try again later.']];
            }
            return [$code, $body];
        } catch (Throwable $e) {
            log_message('error', 'LINK_INTERNAL_API ' . $endpoint . ' failed: ' . $e->getMessage());
            return [503, ['message' => 'The service is unavailable. Please try again later.']];
        }
    }
}
