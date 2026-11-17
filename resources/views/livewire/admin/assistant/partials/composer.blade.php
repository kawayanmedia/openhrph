<form wire:submit="send" class="border-t border-slate-200 px-3 py-3 dark:border-navy-800">
    <label for="assistant-draft-{{ $variant }}" class="sr-only">Message the HR assistant</label>

    <textarea id="assistant-draft-{{ $variant }}"
              rows="2"
              wire:model="draft"
              wire:loading.attr="disabled"
              wire:target="send,applyDecisions"
              x-on:keydown.enter="if (! $event.shiftKey && ! $event.isComposing) { $event.preventDefault(); $wire.send(); }"
              placeholder="Ask about hours, requests or approvals…"
              class="ui-textarea !min-h-[4.5rem]"></textarea>

    <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
        <p class="text-[0.7rem] text-slate-500 dark:text-slate-400">
            Enter to send · Shift + Enter for a new line{{ $variant === 'page' ? ' · Changes always need your confirmation' : '' }}
        </p>

        <button type="submit" class="ui-btn-primary !py-1.5 text-xs"
                wire:loading.attr="disabled" wire:target="send,applyDecisions">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.7">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.126A59.768 59.768 0 0 1 21.485 12 59.77 59.77 0 0 1 3.27 20.876L5.999 12Zm0 0h7.5" />
            </svg>
            Send
        </button>
    </div>
</form>
