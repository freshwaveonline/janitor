<?php

declare(strict_types=1);

namespace FreshwaveOnline\Janitor\Data;

use Carbon\CarbonInterface;
use FreshwaveOnline\Janitor\Enums\Theme;
use FreshwaveOnline\Janitor\Support\Palette;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Throwable;

/**
 * Everything the error page needs, resolved once and passed around as one value.
 *
 * The views, the JSON response and the Livewire pop-up all render from this
 * single object, which is what keeps the three presentations in sync.
 *
 * @implements Arrayable<string, mixed>
 */
final class ErrorContext implements Arrayable
{
    /**
     * @param  list<string>  $suggestions
     * @param  list<ErrorAction>  $actions
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly string $title,
        public readonly string $message,
        public readonly ?string $reason,
        public readonly ?string $explanation,
        public readonly array $suggestions,
        public readonly string $icon,
        public readonly ?string $messageNumber,
        public readonly ?string $requestId,
        public readonly ?CarbonInterface $retryAt,
        public readonly ?ExceptionDetails $details,
        public readonly array $actions,
        public readonly Branding $branding,
        public readonly Palette $palette,
        public readonly Theme $theme,
        public readonly CarbonInterface $occurredAt,
        public readonly string $locale,
        public readonly ?Throwable $exception = null,
        public readonly ?Request $request = null,
    ) {}

    public function hasRetry(): bool
    {
        return $this->retryAt !== null;
    }

    public function retryInSeconds(): ?int
    {
        if ($this->retryAt === null) {
            return null;
        }

        return max(0, $this->retryAt->getTimestamp() - time());
    }

    public function hasDetails(): bool
    {
        return $this->details !== null;
    }

    /**
     * @return list<ErrorAction>
     */
    public function actions(): array
    {
        return $this->actions;
    }

    /**
     * The support address for this status, or null when support should not be
     * offered here.
     */
    public function supportEmail(): ?string
    {
        return $this->branding->supportEmail;
    }

    /**
     * `mailto:` link pre-filled with the message number, so the first reply from
     * support does not have to be "what was the error code?".
     */
    public function supportMailto(?string $subjectTemplate = null): ?string
    {
        if ($this->branding->supportEmail === null) {
            return null;
        }

        $subject = strtr($subjectTemplate ?? '[:brand] :status — :message_number', [
            ':brand' => (string) ($this->branding->name ?? ''),
            ':status' => (string) $this->statusCode,
            ':message_number' => $this->messageNumber ?? '—',
        ]);

        $body = implode("\n", array_filter([
            $this->messageNumber !== null ? 'Message number: '.$this->messageNumber : null,
            $this->requestId !== null ? 'Request ID: '.$this->requestId : null,
            'Status: '.$this->statusCode,
            'Time: '.$this->occurredAt->toIso8601String(),
            $this->request !== null ? 'Page: '.$this->request->fullUrl() : null,
            '',
            '---',
            '',
        ]));

        return 'mailto:'.$this->branding->supportEmail
            .'?subject='.rawurlencode(trim($subject))
            .'&body='.rawurlencode($body);
    }

    /**
     * Plain-text report placed on the clipboard by the copy button.
     *
     * @param  array<string, bool>  $includes
     */
    public function copyReport(array $includes = []): string
    {
        $lines = [];

        if ($this->messageNumber !== null) {
            $lines[] = 'Message number : '.$this->messageNumber;
        }

        if ($this->requestId !== null) {
            $lines[] = 'Request ID     : '.$this->requestId;
        }

        $lines[] = 'Status         : '.$this->statusCode.' '.$this->title;

        if (($includes['timestamp'] ?? true)) {
            $lines[] = 'Timestamp      : '.$this->occurredAt->toIso8601String();
        }

        if ($this->request !== null) {
            if (($includes['method'] ?? true)) {
                $lines[] = 'Method         : '.$this->request->getMethod();
            }

            if (($includes['url'] ?? true)) {
                $lines[] = 'URL            : '.$this->request->fullUrl();
            }

            if (($includes['user_agent'] ?? false)) {
                $lines[] = 'User agent     : '.(string) $this->request->userAgent();
            }
        }

        if ($this->details !== null) {
            $lines[] = '';
            $lines[] = $this->details->class.': '.$this->details->message;
            $lines[] = 'at '.$this->details->location();

            if ($this->details->previous !== null) {
                $lines[] = 'caused by '.$this->details->previous;
            }

            if ($this->details->frames !== []) {
                $lines[] = '';
                $lines[] = 'Stack trace:';

                foreach ($this->details->frames as $index => $frame) {
                    $lines[] = sprintf('#%-2d %s:%d — %s', $index, $frame['file'], $frame['line'], $frame['call']);
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->statusCode,
            'title' => $this->title,
            'message' => $this->message,
            'reason' => $this->reason,
            'explanation' => $this->explanation,
            'suggestions' => $this->suggestions,
            'icon' => $this->icon,
            'message_number' => $this->messageNumber,
            'request_id' => $this->requestId,
            'retry_at' => $this->retryAt?->toIso8601String(),
            'retry_in' => $this->retryInSeconds(),
            'actions' => array_map(static fn (ErrorAction $action): array => $action->toArray(), $this->actions),
            'details' => $this->details?->toArray(),
            'occurred_at' => $this->occurredAt->toIso8601String(),
            'branding' => $this->branding->toArray(),
        ];
    }
}
