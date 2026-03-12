<?php

declare(strict_types=1);

namespace TeaTimeLounge\ApiGateway\Controllers;

use Teatimelounge\ApiGateway\Http\Request;

class LeadImportController
{
    public function __invoke(Request $request): array
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $expectedAuth = 'Bearer ' . (string) getenv('LEAD_IMPORT_TOKEN');

        if ($authHeader !== $expectedAuth) {
            return $this->response(401, [
                'success' => false,
                'error' => 'Unauthorized',
            ]);
        }

        $rawBody = file_get_contents('php://input');
        $body = json_decode($rawBody ?: '', true);

        if (!is_array($body)) {
            return $this->response(400, [
                'success' => false,
                'error' => 'Invalid JSON body',
            ]);
        }

        $source = $this->stringOrDefault($body['source'] ?? null, 'google_sheets');
        $sourceSheet = $this->nullableString($body['source_sheet'] ?? null);
        $sourceRow = isset($body['source_row']) ? (int) $body['source_row'] : null;
        $sourceBatch = $this->nullableString($body['source_batch'] ?? null);
        $externalKey = $this->nullableString($body['external_key'] ?? null);

        $company = $this->nullableString($body['company'] ?? null);
        $website = $this->nullableString($body['website'] ?? null);
        $domain = $this->normalizeDomain($body['domain'] ?? $body['website'] ?? null);
        $industry = $this->nullableString($body['industry'] ?? null);

        $managerName = $this->nullableString($body['manager_name'] ?? null);
        $phone1 = $this->nullableString($body['phone_1'] ?? null);
        $phone2 = $this->nullableString($body['phone_2'] ?? null);
        $email1 = $this->nullableString($body['email_1'] ?? null);
        $email2 = $this->nullableString($body['email_2'] ?? null);
        $cityCountry = $this->nullableString($body['city_country'] ?? null);
        $notesAboutIndustry = $this->nullableString($body['notes_about_industry'] ?? null);

        if ($company === null) {
            return $this->response(400, [
                'success' => false,
                'error' => 'Company is required',
            ]);
        }

        if ($domain === null) {
            return $this->response(400, [
                'success' => false,
                'error' => 'Domain is required',
            ]);
        }

        if ($externalKey === null) {
            return $this->response(400, [
                'success' => false,
                'error' => 'external_key is required',
            ]);
        }

        $supabaseUrl = rtrim((string) getenv('SUPABASE_URL'), '/');
        $serviceRoleKey = (string) getenv('SUPABASE_SERVICE_ROLE_KEY');

        if ($supabaseUrl === '' || $serviceRoleKey === '') {
            return $this->response(500, [
                'success' => false,
                'error' => 'Supabase environment variables missing',
            ]);
        }

        $baseHeaders = [
            'apikey: ' . $serviceRoleKey,
            'Authorization: Bearer ' . $serviceRoleKey,
            'Content-Type: application/json',
        ];

        // 1) Duplicate check by external_key
        $checkUrl = $supabaseUrl
            . '/rest/v1/leads?external_key=eq.'
            . rawurlencode($externalKey)
            . '&select=id';

        $checkResult = $this->curlJsonRequest('GET', $checkUrl, $baseHeaders);

        if ($checkResult['ok'] === false) {
            return $this->response(500, [
                'success' => false,
                'error' => 'Supabase duplicate check failed',
                'details' => $checkResult['error'],
            ]);
        }

        if ($checkResult['http_code'] >= 400) {
            return $this->response(500, [
                'success' => false,
                'error' => 'Supabase duplicate check error',
                'details' => $checkResult['body'],
            ]);
        }

        $existing = json_decode($checkResult['body'], true);

        if (is_array($existing) && count($existing) > 0) {
            return $this->response(200, [
                'success' => true,
                'status' => 'duplicate',
                'lead_id' => $existing[0]['id'] ?? null,
            ]);
        }

        // 2) Insert new lead
        $payload = [
            'source' => $source,
            'source_sheet' => $sourceSheet,
            'source_row' => $sourceRow,
            'source_batch' => $sourceBatch,
            'external_key' => $externalKey,

            'company' => $company,
            'website' => $website,
            'domain' => $domain,
            'industry' => $industry,

            'manager_name' => $managerName,
            'phone_1' => $phone1,
            'phone_2' => $phone2,
            'email_1' => $email1,
            'email_2' => $email2,
            'city_country' => $cityCountry,
            'notes_about_industry' => $notesAboutIndustry,

            'status' => 'new',
            'updated_at' => gmdate('c'),
        ];

        $insertHeaders = array_merge($baseHeaders, [
            'Prefer: return=representation',
        ]);

        $insertUrl = $supabaseUrl . '/rest/v1/leads';
        $insertResult = $this->curlJsonRequest('POST', $insertUrl, $insertHeaders, $payload);

        if ($insertResult['ok'] === false) {
            return $this->response(500, [
                'success' => false,
                'error' => 'Supabase insert failed',
                'details' => $insertResult['error'],
            ]);
        }

        if ($insertResult['http_code'] >= 400) {
            return $this->response(500, [
                'success' => false,
                'error' => 'Supabase insert error',
                'details' => $insertResult['body'],
            ]);
        }

        $inserted = json_decode($insertResult['body'], true);
        $leadId = is_array($inserted) && isset($inserted[0]['id'])
            ? $inserted[0]['id']
            : null;

        return $this->response(200, [
            'success' => true,
            'status' => 'imported',
            'lead_id' => $leadId,
        ]);
    }

    private function response(int $status, array $body): array
    {
        return [
            'status' => $status,
            'body' => $body,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function stringOrDefault(mixed $value, string $default): string
    {
        $string = $this->nullableString($value);

        return $string ?? $default;
    }

    private function normalizeDomain(mixed $value): ?string
    {
        $string = $this->nullableString($value);

        if ($string === null) {
            return null;
        }

        $normalized = preg_replace('#^https?://#i', '', $string);
        $normalized = preg_replace('#^www\.#i', '', (string) $normalized);
        $normalized = preg_replace('#/.*$#', '', (string) $normalized);
        $normalized = strtolower(trim((string) $normalized));

        return $normalized === '' ? null : $normalized;
    }

    private function curlJsonRequest(
        string $method,
        string $url,
        array $headers,
        ?array $payload = null
    ): array {
        $ch = curl_init($url);

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,

            // Local Windows development fix only.
            // Remove these 2 lines in production once CA certs are configured properly.
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ];

        if ($payload !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($payload);
        }

        curl_setopt_array($ch, $options);

        $responseBody = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        return [
            'ok' => $responseBody !== false,
            'http_code' => $httpCode,
            'body' => $responseBody === false ? null : $responseBody,
            'error' => $responseBody === false ? $error : null,
        ];
    }
}