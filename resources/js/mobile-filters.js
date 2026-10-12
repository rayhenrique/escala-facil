// Local mobile drafts; committed filters still belong to Livewire and its URL hooks.
export function mobileFilterPanel({ fields, defaults = {}, labels = {} }) {
    return {
        filterMobile: false, filtersOpen: false, draft: {}, applying: false, filterError: false,
        media: null, mediaChanged: null, filterRoot: null, fields, defaults, labels,
        init() {
            this.filterRoot = this.$el;
            this.media = window.matchMedia('(max-width: 1023px)');
            this.mediaChanged = () => {
                this.filterMobile = this.media.matches;
                if (!this.filterMobile) this.filtersOpen = false;
                this.syncDraft();
            };
            this.mediaChanged();
            this.media.addEventListener('change', this.mediaChanged);
            for (const key of fields) this.$watch(() => this.$wire.$get(key), () => {
                if (!this.filtersOpen && !this.applying) this.syncDraft();
            });
        },
        syncDraft() { this.draft = Object.fromEntries(fields.map(key => [key, String(this.$wire.$get(key) ?? '')])); },
        openFilters() { this.syncDraft(); this.filterError = false; this.filtersOpen = true; },
        cancelFilters() { if (!this.applying) { this.filtersOpen = false; this.syncDraft(); } },
        async changeFilter(key, event) {
            if (this.filterMobile || event?.isComposing || this.applying) return;
            await this.$wire.$set(key, this.draft[key]);
        },
        defaultValue(key) {
            if (fields.includes('sort') && fields.includes('swapStatusFilter')) {
                const pending = this.$wire.$get('listView') === 'pending';
                if (key === 'swapStatusFilter') return pending ? 'pending_manager' : '';
                if (key === 'sort') return pending ? 'oldest' : 'newest';
            }
            return String(Object.hasOwn(defaults, key) ? defaults[key] : '');
        },
        clearDraft() { this.draft = Object.fromEntries(fields.map(key => [key, this.defaultValue(key)])); },
        async applyFilters() {
            if (this.applying) return;
            this.applying = true; this.filterError = false;
            try {
                for (const key of fields) this.$wire.$set(key, this.draft[key], false);
                await this.$wire.$commit();
                await this.$nextTick();
                // Livewire schedules its DOM morph after resolving the action.
                // Inspect the rendered validation state, not the previous response.
                if (globalThis.requestAnimationFrame) await new Promise(requestAnimationFrame);
                const invalid = this.filterRoot.querySelector('[aria-invalid="true"]');
                if (invalid) { this.applying = false; await this.$nextTick(); invalid.focus(); return; }
                this.filtersOpen = false;
                this.syncDraft();
            } catch {
                this.filterError = true;
            } finally { this.applying = false; }
        },
        activeFilters() {
            return fields.filter(key => String(this.$wire.$get(key) ?? '') !== this.defaultValue(key)).map(key => {
                const value = String(this.$wire.$get(key) ?? '');
                const select = this.filterRoot.querySelector(`[data-filter-field="${key}"]`);
                const option = select?.tagName === 'SELECT' ? [...select.options].find(option => option.value === value) : null;
                const display = ['dateFrom', 'dateTo'].includes(key) && /^\d{4}-\d{2}-\d{2}$/.test(value) ? value.split('-').reverse().join('/') : value;
                return { key, label: `${labels[key] || key}: ${option?.textContent.trim() || display}` };
            });
        },
        async removeFilter(key) {
            if (this.applying) return;
            this.applying = true;
            try {
                await this.$wire.$set(key, this.defaultValue(key)); this.syncDraft();
                await this.$nextTick();
                const target = this.filterRoot.querySelector(this.filterMobile ? '[aria-haspopup="dialog"]' : `[data-filter-field="${key}"]`);
                this.applying = false; await this.$nextTick(); target?.focus();
            }
            finally { this.applying = false; }
        },
        destroy() { this.media?.removeEventListener('change', this.mediaChanged); },
    };
}

