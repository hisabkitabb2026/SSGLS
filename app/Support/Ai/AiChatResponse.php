<?php

namespace App\Support\Ai;

use InvoiceShelf\Modules\Ai\Data\AiChatResponse as ModuleAiChatResponse;

/**
 * Provider-agnostic chat completion response.
 *
 * Extends the module contract's AiChatResponse so that drivers registered
 * with the module Registry (which expects InvoiceShelf\Modules\Ai\Data\AiChatResponse)
 * can return this app-local subclass.
 *
 * Shape is deliberately modelled after OpenAI's response so drivers translating
 * from other shapes only have to do it once, and the AiAssistantService loop
 * stays vendor-neutral.
 */
class AiChatResponse extends ModuleAiChatResponse
{
    /**
     * @param  string|null  $message  The assistant's text reply. Null when the response is a tool-call-only turn.
     * @param  array<int, array{id: string, name: string, arguments: array<string, mixed>}>  $toolCalls
     *                                                                                                   Tool calls the model wants the host to execute. Empty array if none.
     * @param  string  $finishReason  Why generation stopped: 'stop', 'tool_calls', 'length', 'error', etc.
     * @param  array{tokens_in?: int, tokens_out?: int}  $usage  Token usage for cost tracking (optional).
     * @param  string|null  $model  Echoed model ID that produced this response (optional).
     */
    public function __construct(
        public readonly ?string $message,
        public readonly array $toolCalls = [],
        public readonly string $finishReason = 'stop',
        public readonly array $usage = [],
        public readonly ?string $model = null,
    ) {}

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }
}
