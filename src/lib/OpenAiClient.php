<?php

declare(strict_types=1);

namespace TeaTimeLounge\ApiGateway\Lib;

final class OpenAiClient
{
    private string $apiKey;
    private string $model;

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        $this->apiKey = trim((string) ($apiKey ?? getenv('OPENAI_API_KEY')));
        $this->model = trim((string) ($model ?? getenv('OPENAI_MODEL')));

        if ($this->model === '') {
            $this->model = 'gpt-5';
        }

        if ($this->apiKey === '') {
            throw new \RuntimeException('OPENAI_API_KEY is missing');
        }
    }

    public function generateLeadDraft(string $prompt): array
    {
        $payload = [
            'model' => $this->model,
            'input' => $prompt,
        ];

        $result = $this->request(
            'POST',
            'https://api.openai.com/v1/responses',
            [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            $payload
        );

        if ($result['ok'] === false) {
            throw new \RuntimeException('OpenAI request failed: ' . (string) $result['error']);
        }

        if ($result['http_code'] >= 400) {
            throw new \RuntimeException('OpenAI API error: ' . (string) $result['body']);
        }

        $decoded = json_decode((string) $result['body'], true);

        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid JSON returned by OpenAI');
        }

        $outputText = $this->extractOutputText($decoded);

        if ($outputText === null || trim($outputText) === '') {
            throw new \RuntimeException('OpenAI response contained no output text');
        }

        $draft = $this->extractJsonObject($outputText);

        if (!is_array($draft)) {
            throw new \RuntimeException('Could not parse JSON draft from OpenAI output');
        }

        if (
            !isset($draft['subject'], $draft['body']) ||
            !is_string($draft['subject']) ||
            !is_string($draft['body']) ||
            trim($draft['subject']) === '' ||
            trim($draft['body']) === ''
        ) {
            throw new \RuntimeException('OpenAI returned an invalid draft payload');
        }

        return [
            'subject' => trim((string) $draft['subject']),
            'body' => trim((string) $draft['body']),
            'language' => isset($draft['language']) && is_string($draft['language']) && trim($draft['language']) !== ''
                ? trim((string) $draft['language'])
                : 'en',
            'personalization_notes' => $this->normalizeStringArray($draft['personalization_notes'] ?? []),
        ];
    }

    private function extractOutputText(array $decoded): ?string
    {
        if (isset($decoded['output_text']) && is_string($decoded['output_text'])) {
            return $decoded['output_text'];
        }

        if (!isset($decoded['output']) || !is_array($decoded['output'])) {
            return null;
        }

        $parts = [];

        foreach ($decoded['output'] as $outputItem) {
            if (!is_array($outputItem)) {
                continue;
            }

            if (!isset($outputItem['content']) || !is_array($outputItem['content'])) {
                continue;
            }

            foreach ($outputItem['content'] as $contentItem) {
                if (!is_array($contentItem)) {
                    continue;
                }

                if (isset($contentItem['text']) && is_string($contentItem['text'])) {
                    $parts[] = $contentItem['text'];
                }
            }
        }

        $joined = trim(implode("\n", $parts));

        return $joined === '' ? null : $joined;
    }

    private function extractJsonObject(string $text): ?array
    {
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $json = substr($text, $start, $end - $start + 1);
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function normalizeStringArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            $string = trim((string) $item);

            if ($string !== '') {
                $result[] = $string;
            }
        }

        return array_values($result);
    }

    private function request(
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
            $json = json_encode($payload);

            if ($json === false) {
                throw new \RuntimeException('Failed to encode OpenAI payload to JSON');
            }

            $options[CURLOPT_POSTFIELDS] = $json;
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