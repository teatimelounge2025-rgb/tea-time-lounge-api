<?php

declare(strict_types=1);

namespace TeaTimeLounge\ApiGateway\Controllers;

use TeaTimeLounge\ApiGateway\Http\Request;
use TeaTimeLounge\ApiGateway\lib\OpenAiClient;

class LeadImportController
{
    public function __invoke(Request $request): array
    {
        $receivedToken = $this->getHeaderValue('x-lead-import-token');
        $expectedToken = trim((string) getenv('LEAD_IMPORT_TOKEN'));

        $isAuthorized =
            $receivedToken !== '' &&
            $expectedToken !== '' &&
            hash_equals($expectedToken, $receivedToken);

        if (!$isAuthorized) {
            return $this->response(401, [
                'success' => false,
                'error' => 'Unauthorized',
                'debug' => [
                    'received_token_length' => strlen($receivedToken),
                    'expected_token_length' => strlen($expectedToken),
                    'env_token_loaded' => $expectedToken !== '',
                    'server_http_x_lead_import_token' => $_SERVER['HTTP_X_LEAD_IMPORT_TOKEN'] ?? null,
                    'all_headers' => function_exists('getallheaders') ? getallheaders() : null,
                ],
            ]);
        }

        $body = $this->getJsonBody();

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
        $serviceRoleKey = trim((string) getenv('SUPABASE_SERVICE_ROLE_KEY'));

        if ($supabaseUrl === '' || $serviceRoleKey === '') {
            return $this->response(500, [
                'success' => false,
                'error' => 'Supabase environment variables missing',
            ]);
        }

        $baseHeaders = $this->getSupabaseHeaders($serviceRoleKey);

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

        $existing = json_decode((string) $checkResult['body'], true);

        if (is_array($existing) && count($existing) > 0) {
            return $this->response(200, [
                'success' => true,
                'status' => 'duplicate',
                'lead_id' => $existing[0]['id'] ?? null,
            ]);
        }

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
            'generation_status' => 'idle',
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

        $inserted = json_decode((string) $insertResult['body'], true);
        $leadId = is_array($inserted) && isset($inserted[0]['id'])
            ? $inserted[0]['id']
            : null;

        return $this->response(200, [
            'success' => true,
            'status' => 'imported',
            'lead_id' => $leadId,
        ]);
    }

   public function generateEmail(Request $request): array
{
    $body = $this->getJsonBody();
    $leadId = $this->detectLeadId($body);

    if ($leadId === null) {
        return $this->response(400, [
            'success' => false,
            'error' => 'Lead id is required',
        ]);
    }

    $supabaseUrl = rtrim((string) getenv('SUPABASE_URL'), '/');
    $serviceRoleKey = trim((string) getenv('SUPABASE_SERVICE_ROLE_KEY'));

    if ($supabaseUrl === '' || $serviceRoleKey === '') {
        return $this->response(500, [
            'success' => false,
            'error' => 'Supabase environment variables missing',
        ]);
    }

    $supabaseHeaders = $this->getSupabaseHeaders($serviceRoleKey);

    $leadResult = $this->fetchLeadById($supabaseUrl, $supabaseHeaders, $leadId);

    if (($leadResult['success'] ?? false) === false) {
        return $this->response((int) ($leadResult['status'] ?? 500), [
            'success' => false,
            'error' => $leadResult['error'] ?? 'Lead fetch failed',
            'details' => $leadResult['details'] ?? null,
        ]);
    }

    $lead = $leadResult['lead'];

    $markGenerating = $this->updateLead($supabaseUrl, $supabaseHeaders, $leadId, [
        'generation_status' => 'generating',
        'updated_at' => gmdate('c'),
    ]);

    if (($markGenerating['success'] ?? false) === false) {
        return $this->response(500, [
            'success' => false,
            'error' => 'Could not mark lead as generating',
            'details' => $markGenerating['details'] ?? null,
        ]);
    }

    $prompt = $this->buildLeadEmailPrompt($lead);

    try {
        $openAiClient = new OpenAiClient();
        $draft = $openAiClient->generateLeadDraft($prompt);
    } catch (\Throwable $e) {
        $this->updateLead($supabaseUrl, $supabaseHeaders, $leadId, [
            'generation_status' => 'failed',
            'updated_at' => gmdate('c'),
        ]);

        return $this->response(500, [
            'success' => false,
            'error' => 'OpenAI generation failed',
            'details' => $e->getMessage(),
        ]);
    }

    $finalPayload = [
        'draft_subject' => trim((string) $draft['subject']),
        'draft_body' => trim((string) $draft['body']),
        'draft_language' => $this->nullableString($draft['language'] ?? null) ?? 'en',
        'generation_status' => 'generated',
        'generated_at' => gmdate('c'),
        'prompt_version' => 'lead_outreach_v1',
        'personalization_notes' => is_array($draft['personalization_notes'] ?? null)
            ? array_values(array_filter(
                array_map(
                    fn($item) => is_scalar($item) ? trim((string) $item) : '',
                    $draft['personalization_notes']
                ),
                fn(string $item) => $item !== ''
            ))
            : [],
        'updated_at' => gmdate('c'),
    ];

    $saveDraft = $this->updateLead(
        $supabaseUrl,
        $supabaseHeaders,
        $leadId,
        $finalPayload,
        true
    );

    if (($saveDraft['success'] ?? false) === false) {
        $this->updateLead($supabaseUrl, $supabaseHeaders, $leadId, [
            'generation_status' => 'failed',
            'updated_at' => gmdate('c'),
        ]);

        return $this->response(500, [
            'success' => false,
            'error' => 'Could not save generated draft',
            'details' => $saveDraft['details'] ?? null,
        ]);
    }

    return $this->response(200, [
        'success' => true,
        'status' => 'generated',
        'lead_id' => $leadId,
        'draft' => $saveDraft['row'] ?? $finalPayload,
    ]);
}

    private function response(int $status, array $body): array
    {
        return [
            'status' => $status,
            'body' => $body,
        ];
    }

    private function getJsonBody(): ?array
    {
        $rawBody = file_get_contents('php://input');
        $body = json_decode($rawBody ?: '', true);

        return is_array($body) ? $body : null;
    }

    private function getHeaderValue(string $name): string
    {
        $target = strtolower($name);

        if (function_exists('getallheaders')) {
            $headers = getallheaders();

            foreach ($headers as $key => $value) {
                if (strtolower((string) $key) === $target) {
                    return trim((string) $value);
                }
            }
        }

        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        if (isset($_SERVER[$serverKey])) {
            return trim((string) $_SERVER[$serverKey]);
        }

        return '';
    }

    private function detectLeadId(?array $body): ?string
    {
        $candidate = $this->nullableString($body['lead_id'] ?? null);

        if ($candidate !== null) {
            return $candidate;
        }

        $candidate = $this->nullableString($_GET['id'] ?? null);

        if ($candidate !== null) {
            return $candidate;
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if ($uri !== '') {
            if (preg_match('#/api/leads/([^/]+)/generate-email#', $uri, $matches) === 1) {
                $candidate = $this->nullableString(urldecode((string) $matches[1]));
                if ($candidate !== null) {
                    return $candidate;
                }
            }
        }

        return null;
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

    private function getSupabaseHeaders(string $serviceRoleKey): array
    {
        return [
            'apikey: ' . $serviceRoleKey,
            'Authorization: Bearer ' . $serviceRoleKey,
            'Content-Type: application/json',
        ];
    }

    private function fetchLeadById(string $supabaseUrl, array $headers, string $leadId): array
    {
        $url = $supabaseUrl
            . '/rest/v1/leads?id=eq.'
            . rawurlencode($leadId)
            . '&select=*'
            . '&limit=1';

        $result = $this->curlJsonRequest('GET', $url, $headers);

        if ($result['ok'] === false) {
            return [
                'success' => false,
                'status' => 500,
                'error' => 'Supabase fetch failed',
                'details' => $result['error'],
            ];
        }

        if ($result['http_code'] >= 400) {
            return [
                'success' => false,
                'status' => 500,
                'error' => 'Supabase fetch error',
                'details' => $result['body'],
            ];
        }

        $rows = json_decode((string) $result['body'], true);

        if (!is_array($rows) || count($rows) === 0 || !is_array($rows[0])) {
            return [
                'success' => false,
                'status' => 404,
                'error' => 'Lead not found',
                'details' => null,
            ];
        }

        return [
            'success' => true,
            'lead' => $rows[0],
        ];
    }

    private function updateLead(
        string $supabaseUrl,
        array $headers,
        string $leadId,
        array $payload,
        bool $returnRepresentation = false
    ): array {
        $requestHeaders = $headers;

        if ($returnRepresentation) {
            $requestHeaders[] = 'Prefer: return=representation';
        }

        $url = $supabaseUrl
            . '/rest/v1/leads?id=eq.'
            . rawurlencode($leadId);

        $result = $this->curlJsonRequest('PATCH', $url, $requestHeaders, $payload);

        if ($result['ok'] === false) {
            return [
                'success' => false,
                'details' => $result['error'],
            ];
        }

        if ($result['http_code'] >= 400) {
            return [
                'success' => false,
                'details' => $result['body'],
            ];
        }

        $row = null;

        if ($returnRepresentation) {
            $decoded = json_decode((string) $result['body'], true);
            if (is_array($decoded) && isset($decoded[0]) && is_array($decoded[0])) {
                $row = $decoded[0];
            }
        }

        return [
            'success' => true,
            'row' => $row,
        ];
    }

    private function buildLeadEmailPrompt(array $lead): string
    {
        $company = $this->nullableString($lead['company'] ?? $lead['company_name'] ?? null) ?? '';
        $website = $this->nullableString($lead['website'] ?? $lead['website_address'] ?? null) ?? '';
        $domain = $this->nullableString($lead['domain'] ?? null) ?? '';
        $industry = $this->nullableString($lead['industry'] ?? $lead['type_of_business'] ?? null) ?? '';
        $managerName = $this->nullableString($lead['manager_name'] ?? $lead['contact_name'] ?? null) ?? '';
        $cityCountry = $this->nullableString($lead['city_country'] ?? null)
            ?? $this->combineLocation(
                $this->nullableString($lead['city'] ?? null),
                $this->nullableString($lead['country'] ?? null)
            )
            ?? '';
        $notes = $this->nullableString($lead['notes_about_industry'] ?? $lead['notes'] ?? $lead['reason'] ?? null) ?? '';

        return <<<PROMPT
You are a lead outreach email assistant for Tea Time Lounge.

Your task is to write short, natural, human-sounding first outreach emails based on lead data.

Goal:
Introduce Tea Time Lounge in a simple, credible way and encourage a reply or short conversation.

Core rules:
- Keep emails between 60–120 words
- Write like a real person, not marketing copy
- Do NOT invent facts about the lead or their business
- Do NOT over-describe services or operations
- Avoid hype, buzzwords, or long explanations
- Keep tone warm, professional, and light
- Focus on curiosity, not selling

Structure:
1. Short greeting
2. Why you are reaching out
3. One simple reason there could be a fit
4. Soft call to action

Personalization:
- Use company name, contact name, or location if available
- If data is missing, stay neutral (no guessing)

Tone:
- Warm
- Clear
- Confident
- Slightly informal but professional

Avoid:
- “I hope this email finds you well”
- Long paragraphs
- Generic phrases like “memorable experiences”
- Overly detailed service descriptions

Subject line:
- Max 6–7 words
- Simple and natural
- No hype or forced questions

Output format (strict JSON):
{
  "subject": "string",
  "body": "string",
  "language": "string"
}
PROMPT;
    }

    private function combineLocation(?string $city, ?string $country): ?string
    {
        $parts = array_values(array_filter([$city, $country], fn($value) => $value !== null && $value !== ''));

        if (count($parts) === 0) {
            return null;
        }

        return implode(', ', $parts);
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

        if (is_resource($ch) || $ch instanceof \CurlHandle) {
            curl_close($ch);
        }

        return [
            'ok' => $responseBody !== false,
            'http_code' => $httpCode,
            'body' => $responseBody === false ? null : $responseBody,
            'error' => $responseBody === false ? $error : null,
        ];
    }
}