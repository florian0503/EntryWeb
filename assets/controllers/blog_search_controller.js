import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'results', 'clear'];
    static values = { url: String };

    connect() {
        this.timeout = null;
        this.abortController = null;
        this.toggleClear();
    }

    disconnect() {
        clearTimeout(this.timeout);
        if (this.abortController) {
            this.abortController.abort();
        }
    }

    search() {
        this.toggleClear();
        clearTimeout(this.timeout);
        this.timeout = setTimeout(() => this.fetchResults(), 300);
    }

    submit(event) {
        event.preventDefault();
        clearTimeout(this.timeout);
        this.fetchResults();
    }

    clear() {
        this.inputTarget.value = '';
        this.toggleClear();
        this.inputTarget.focus();
        clearTimeout(this.timeout);
        this.fetchResults();
    }

    toggleClear() {
        if (this.hasClearTarget) {
            this.clearTarget.style.display = this.inputTarget.value ? '' : 'none';
        }
    }

    async fetchResults() {
        const query = this.inputTarget.value.trim();
        const url = query ? `${this.urlValue}?q=${encodeURIComponent(query)}` : this.urlValue;

        if (this.abortController) {
            this.abortController.abort();
        }
        this.abortController = new AbortController();

        let html;
        try {
            const response = await fetch(url, { signal: this.abortController.signal });
            html = await response.text();
        } catch (error) {
            if ('AbortError' === error.name) {
                return;
            }
            throw error;
        }

        const doc = new DOMParser().parseFromString(html, 'text/html');
        const fresh = doc.querySelector('#blog-results');

        if (fresh && this.hasResultsTarget) {
            this.resultsTarget.innerHTML = fresh.innerHTML;
            this.resultsTarget.querySelectorAll('[data-scroll-animation-target]').forEach((el) => {
                el.classList.add('is-visible');
            });
            history.replaceState(null, '', url);
        }
    }
}
