<?php

namespace App\Libraries;

use Anthropic\Client;
use RuntimeException;

/**
 * Claude-powered workshop intelligence via the official Anthropic PHP SDK.
 * POPIA: only vehicle and job information is sent, never customer names, phone numbers or emails.
 */
class AiAssistant
{
    public const MODEL = 'claude-opus-5-5';

    private const SYSTEM = <<<'TXT'
You are the workshop assistant inside Wheelsturn Automotive's garage management system in South Africa.
You help qualified technicians and service advisors diagnose and repair light vehicles (cars and bakkies common in South Africa: Toyota, VW, Ford, Nissan, Isuzu, Hyundai, Kia, Suzuki, BMW, Mercedes-Benz, Haval, Chery and others).

How to answer:
- Be practical and specific: likely causes ranked by probability, the tests that confirm each one (with expected values where known), then the repair steps.
- Mention relevant safety precautions (lifting, high-voltage hybrids, airbags, fuel, brakes) when they apply.
- If a torque value, fluid specification or capacity varies by engine or year and you are not certain, say so and tell the technician to confirm it in the manufacturer's data rather than inventing a number.
- Use metric units and South African terms (bakkie, robot, petrol, R for rand). Keep it concise; use short headings and bullet points.
- You cannot see the vehicle. Never claim a fault is confirmed.
TXT;

    public static function enabled(): bool
    {
        return (int) Settings::get('ai_enabled', 0) === 1 && self::apiKey() !== null;
    }

    private static function apiKey(): ?string
    {
        $key = (string) Settings::get('ai_api_key', '');
        if ($key === '') {
            $key = (string) (getenv('ANTHROPIC_API_KEY') ?: env('anthropic.apiKey', ''));
        }
        return $key !== '' ? $key : null;
    }

    /**
     * Send a conversation to Claude and return the text reply.
     *
     * @param array  $messages [['role' => 'user'|'assistant', 'content' => '...'], ...]
     * @param string $effort   low | medium | high
     */
    public static function ask(array $messages, string $effort = 'medium', int $maxTokens = 4000, ?string $extraSystem = null): string
    {
        if (!self::enabled()) {
            throw new RuntimeException('The AI assistant is not set up. An administrator can enable it in Settings → AI assistant.');
        }
        @set_time_limit(180);
        $client = new Client(apiKey: self::apiKey());
        $system = [['type' => 'text', 'text' => self::SYSTEM, 'cacheControl' => ['type' => 'ephemeral']]];
        if ($extraSystem) {
            $system[] = ['type' => 'text', 'text' => $extraSystem];
        }
        try {
            $response = $client->beta->messages->create(
                maxTokens: $maxTokens,
                messages: $messages,
                model: self::MODEL,
                system: $system,
                outputConfig: ['effort' => $effort],
                betas: ['server-side-fallback-2026-07-01'],
                fallbacks: 'default', // if the request is declined by a safety classifier, the API retries on a suitable fallback model
            );
        } catch (\Anthropic\Core\Exceptions\APIStatusException $e) {
            log_message('error', 'Claude API error: ' . $e->getMessage());
            $type = $e->type?->value ?? '';
            throw new RuntimeException(match ($type) {
                'rate_limit_error' => 'The AI assistant is busy right now. Please try again in a minute.',
                'overloaded_error' => 'The AI service is temporarily overloaded. Please try again shortly.',
                'authentication_error', 'permission_error' => 'The AI API key is invalid. Check Settings → AI assistant.',
                default => 'The AI assistant could not answer (' . ($type ?: 'error') . ').',
            });
        } catch (\Throwable $e) {
            log_message('error', 'Claude request failed: ' . $e->getMessage());
            throw new RuntimeException('Could not reach the AI service. Check the server\'s internet connection.');
        }

        if (($response->stopReason ?? null) === 'refusal') {
            return 'I can\'t help with that request. Please rephrase it as a vehicle diagnosis or repair question.';
        }
        $text = '';
        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }
        return trim($text);
    }

    /** Vehicle + job context (no personal information). */
    public static function jobContext(array $job, ?array $vehicle, array $inspections = []): string
    {
        $v = $vehicle ? trim(implode(' ', array_filter([$vehicle['year'], $vehicle['make'], $vehicle['model'], $vehicle['variant'], $vehicle['engine_size'], $vehicle['fuel_type'], $vehicle['transmission']]))) : 'unknown vehicle';
        $lines = ["Vehicle: {$v}", 'Odometer: ' . ($job['odometer_in'] ? number_format((int) $job['odometer_in']) . ' km' : 'unknown')];
        foreach (['customer_complaint' => 'Customer complaint', 'diagnosis' => 'Technician findings so far', 'work_performed' => 'Work performed'] as $k => $l) {
            if (!empty($job[$k])) {
                $lines[] = "{$l}: " . $job[$k];
            }
        }
        $flagged = array_filter($inspections, static fn ($i) => in_array($i['status'], ['advisory', 'urgent'], true));
        if ($flagged) {
            $lines[] = 'Inspection flags: ' . implode('; ', array_map(static fn ($i) => $i['item'] . ' (' . $i['status'] . ($i['note'] ? ': ' . $i['note'] : '') . ')', $flagged));
        }
        return implode("\n", $lines);
    }

    /** Rewrite technical notes into a clear, friendly explanation for the customer's invoice. */
    public static function customerSummary(string $context, array $items): string
    {
        $work = implode("\n", array_map(static fn ($i) => '- ' . $i['description'] . ' x' . (float) $i['quantity'], $items));
        return self::ask([[
            'role' => 'user',
            'content' => "Write the 'Work performed' summary that will print on the customer's invoice.\n\n{$context}\n\nLabour and parts on the job:\n{$work}\n\n"
                . "Rules: 3-6 short sentences in plain English a non-technical customer understands; explain what was wrong, what we did and why it matters for safety or reliability; mention any follow-up recommendations; no prices, no markdown, no headings, no greeting or sign-off.",
        ]], 'low', 1000);
    }

    public static function explainFaultCodes(string $codes, string $vehicle): string
    {
        return self::ask([[
            'role' => 'user',
            'content' => "Explain these OBD-II / manufacturer fault codes for a {$vehicle}: {$codes}\n\nFor each code give: meaning, the most common causes on this kind of vehicle (most likely first), how to test each cause, and whether it is safe to drive. Finish with a suggested diagnostic order if there are several codes.",
        ]], 'medium', 3000);
    }

    /** Suggest services the vehicle is likely due for, based on age, mileage and history. */
    public static function upsellSuggestions(string $vehicle, int $odometer, array $history): string
    {
        $hist = $history ? implode("\n", array_map(static fn ($h) => '- ' . $h, array_slice($history, 0, 40))) : '- No previous work recorded at this workshop';
        return self::ask([[
            'role' => 'user',
            'content' => "Vehicle: {$vehicle}, odometer " . number_format($odometer) . " km.\nService history at our workshop (most recent first):\n{$hist}\n\n"
                . "List up to 6 maintenance items this vehicle is likely due or overdue for (timing belt/chain, spark plugs, brake fluid, coolant, gearbox oil, diff oil, fuel filter, battery, shocks, etc.), based on typical manufacturer intervals for this model and South African conditions. "
                . "For each: the item, why it is likely due (interval vs. history), and urgency (now / next service / monitor). Do not repeat items done recently. Be concise; say clearly that intervals must be confirmed in the service schedule.",
        ]], 'low', 2000);
    }
}
