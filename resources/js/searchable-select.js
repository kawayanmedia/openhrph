/**
 * Searchable select enhancement for admin dropdowns.
 *
 * The native <select> stays in the DOM as the source of truth (Livewire model,
 * form value, automation), while a teleported listbox provides a search field
 * and keyboard navigation. Registered on `alpine:init` because Livewire ships
 * Alpine itself, so no extra dependency is needed.
 */
const normalize = (value) =>
    String(value ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

const registerSearchableSelect = () => {
    window.Alpine.data('searchableSelect', ({ placeholder = 'Select an option' } = {}) => ({
        open: false,
        query: '',
        activeIndex: 0,
        label: '',
        value: '',
        isDisabled: false,
        options: [],
        listboxId: '',
        position: { top: 0, left: 0, width: 0 },

        init() {
            this.listboxId = `searchable-select-${Math.random().toString(36).slice(2, 10)}`;

            this.refresh();

            this.observer = new MutationObserver(() => this.refresh());
            this.observer.observe(this.$refs.native, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['disabled'],
            });

            this.onNativeChange = () => this.refresh();
            this.$refs.native.addEventListener('change', this.onNativeChange);
            this.$refs.native.addEventListener('input', this.onNativeChange);

            this.onDocumentPointer = (event) => this.handleOutsidePointer(event);
            document.addEventListener('mousedown', this.onDocumentPointer, true);
        },

        destroy() {
            this.observer?.disconnect();
            document.removeEventListener('mousedown', this.onDocumentPointer, true);
            this.$refs.native?.removeEventListener('change', this.onNativeChange);
            this.$refs.native?.removeEventListener('input', this.onNativeChange);
        },

        refresh() {
            const select = this.$refs.native;

            if (! select) {
                return;
            }

            this.isDisabled = select.disabled;
            this.value = select.value;
            this.options = Array.from(select.options).map((option) => ({
                value: option.value,
                label: (option.textContent ?? '').trim(),
                disabled: option.disabled,
            }));

            const selected = select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null;
            const selectedLabel = selected ? (selected.textContent ?? '').trim() : '';

            this.label = selectedLabel !== '' ? selectedLabel : placeholder;
        },

        get matches() {
            const terms = normalize(this.query).split(/\s+/).filter(Boolean);

            if (terms.length === 0) {
                return this.options;
            }

            return this.options.filter((option) => {
                const haystack = normalize(`${option.label} ${option.value}`);

                return terms.every((term) => haystack.includes(term));
            });
        },

        get visibleOptions() {
            return this.matches.slice(0, 100);
        },

        get activeOption() {
            return this.visibleOptions[this.activeIndex] ?? null;
        },

        toggle() {
            if (this.isDisabled) {
                return;
            }

            if (this.open) {
                this.close();
            } else {
                this.openList();
            }
        },

        openList() {
            if (this.isDisabled || this.open) {
                return;
            }

            this.query = '';
            this.activeIndex = Math.max(
                0,
                Math.min(
                    this.visibleOptions.findIndex((option) => option.value === this.value),
                    this.visibleOptions.length - 1,
                ),
            );
            this.open = true;

            this.$nextTick(() => {
                this.positionPanel();
                this.$refs.search?.focus();
                this.$refs.search?.select();
                this.scrollActiveIntoView();
            });
        },

        close({ restoreFocus = false } = {}) {
            this.open = false;

            if (restoreFocus) {
                this.$refs.trigger?.focus();
            }
        },

        handleNativeFocus() {
            if (! this.open) {
                this.openList();
            }
        },

        handleOutsidePointer(event) {
            if (! this.open) {
                return;
            }

            if (this.$el.contains(event.target) || this.$refs.panel?.contains(event.target)) {
                return;
            }

            this.open = false;
        },

        positionPanel() {
            const trigger = this.$refs.trigger;

            if (! trigger) {
                return;
            }

            const rect = trigger.getBoundingClientRect();
            const height = this.$refs.panel?.offsetHeight ?? 320;
            const width = Math.min(Math.max(rect.width, 220), window.innerWidth - 16);
            const openAbove = window.innerHeight - rect.bottom < height + 8 && rect.top > height + 8;

            this.position = {
                top: openAbove ? Math.max(8, rect.top - height - 6) : rect.bottom + 6,
                left: Math.max(8, Math.min(rect.left, window.innerWidth - width - 8)),
                width,
            };
        },

        move(step) {
            const options = this.visibleOptions;

            if (options.length === 0) {
                return;
            }

            let index = this.activeIndex + step;

            if (index < 0) {
                index = options.length - 1;
            } else if (index >= options.length) {
                index = 0;
            }

            this.activeIndex = index;

            this.$nextTick(() => this.scrollActiveIntoView());
        },

        scrollActiveIntoView() {
            this.$refs.results
                ?.querySelector(`[data-index="${this.activeIndex}"]`)
                ?.scrollIntoView({ block: 'nearest' });
        },

        choose(option) {
            if (! option || option.disabled) {
                return;
            }

            const select = this.$refs.native;

            if (select && select.value !== option.value) {
                select.value = option.value;
                select.dispatchEvent(new Event('input', { bubbles: true }));
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }

            this.value = option.value;
            this.label = option.label !== '' ? option.label : placeholder;
            this.open = false;
            this.$refs.trigger?.focus();
        },
    }));
};

if (window.Alpine) {
    registerSearchableSelect();
} else {
    document.addEventListener('alpine:init', registerSearchableSelect, { once: true });
}
