import template from './muwa-log-viewer-index.html.twig';
import './muwa-log-viewer-index.scss';

const { Component } = Shopware;

Component.register('muwa-log-viewer-index', {
    template,

    inject: ['muwaLogViewerService'],

    data() {
        return {
            files: [],
            selectedFile: null,
            content: '',
            startByte: 0,
            hasMore: false,
            isLoading: false,
            errorMessage: '',
            defaultLines: 2000,
            searchTerm: '',
            caseSensitive: false,
            matchCount: 0,
            currentMatch: 0,
        };
    },

    computed: {
        fileOptions() {
            return this.files.map((file) => ({
                value: file.name,
                label: `${file.name} (${this.formatSize(file.size)})`,
            }));
        },

        highlightedContent() {
            const escaped = this.escapeHtml(this.content);

            if (!this.searchTerm) {
                return escaped;
            }

            const flags = this.caseSensitive ? 'g' : 'gi';
            const escapedTerm = this.escapeRegExp(this.escapeHtml(this.searchTerm));
            const pattern = new RegExp(escapedTerm, flags);
            let index = 0;

            return escaped.replace(pattern, (match) => {
                index += 1;
                const active = index === this.currentMatch ? ' is-active' : '';
                return `<mark id="muwa-match-${index}" class="muwa-log-match${active}">${match}</mark>`;
            });
        },
    },

    watch: {
        searchTerm(newVal) {
            const count = this.countMatches(this.content, newVal, this.caseSensitive);
            this.matchCount = count;
            this.currentMatch = count > 0 ? 1 : 0;
            this.$nextTick(() => this.scrollToCurrentMatch());
        },

        caseSensitive(newVal) {
            const count = this.countMatches(this.content, this.searchTerm, newVal);
            this.matchCount = count;
            if (this.currentMatch > count) {
                this.currentMatch = count > 0 ? count : 0;
            }
            this.$nextTick(() => this.scrollToCurrentMatch());
        },

        content(newVal) {
            const count = this.countMatches(newVal, this.searchTerm, this.caseSensitive);
            this.matchCount = count;
            if (count === 0) {
                this.currentMatch = 0;
            } else if (this.currentMatch === 0) {
                this.currentMatch = 1;
            } else if (this.currentMatch > count) {
                this.currentMatch = count;
            }
            this.$nextTick(() => this.scrollToCurrentMatch());
        },
    },

    created() {
        this.loadFiles();
    },

    methods: {
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        escapeRegExp(text) {
            return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        },

        countMatches(text, term, caseSensitive) {
            if (!term || !text) {
                return 0;
            }
            const escapedText = this.escapeHtml(text);
            const escapedTerm = this.escapeRegExp(this.escapeHtml(term));
            const flags = caseSensitive ? 'g' : 'gi';
            return (escapedText.match(new RegExp(escapedTerm, flags)) || []).length;
        },

        onSearchInput(value) {
            this.searchTerm = value;
        },

        toggleCaseSensitive() {
            this.caseSensitive = !this.caseSensitive;
        },

        nextMatch() {
            if (this.matchCount === 0) {
                return;
            }
            this.currentMatch = this.currentMatch >= this.matchCount ? 1 : this.currentMatch + 1;
            this.$nextTick(() => this.scrollToCurrentMatch());
        },

        previousMatch() {
            if (this.matchCount === 0) {
                return;
            }
            this.currentMatch = this.currentMatch <= 1 ? this.matchCount : this.currentMatch - 1;
            this.$nextTick(() => this.scrollToCurrentMatch());
        },

        scrollToCurrentMatch() {
            const element = this.$el.querySelector(`#muwa-match-${this.currentMatch}`);
            if (element) {
                element.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }
        },

        formatSize(bytes) {
            if (bytes < 1024) {
                return `${bytes} B`;
            }
            if (bytes < 1024 * 1024) {
                return `${(bytes / 1024).toFixed(1)} KB`;
            }
            return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
        },

        async loadFiles() {
            this.isLoading = true;
            this.errorMessage = '';
            try {
                const response = await this.muwaLogViewerService.listFiles();
                this.files = response.files || [];
            } catch (error) {
                this.errorMessage = error?.response?.data?.errors?.[0]?.detail || error.message;
            } finally {
                this.isLoading = false;
            }
        },

        async onFileChange(fileName) {
            this.selectedFile = fileName;
            this.content = '';
            this.startByte = 0;
            this.hasMore = false;
            if (!fileName) {
                return;
            }
            await this.loadContent(false);
        },

        async loadContent(loadMore) {
            if (!this.selectedFile) {
                return;
            }
            this.isLoading = true;
            this.errorMessage = '';
            try {
                const before = loadMore ? this.startByte : null;
                const chunk = await this.muwaLogViewerService.getContent(
                    this.selectedFile,
                    this.defaultLines,
                    before,
                );
                if (loadMore) {
                    this.content = chunk.content + this.content;
                } else {
                    this.content = chunk.content;
                }
                this.startByte = chunk.startByte;
                this.hasMore = chunk.hasMore;
            } catch (error) {
                this.errorMessage = error?.response?.data?.errors?.[0]?.detail || error.message;
            } finally {
                this.isLoading = false;
            }
        },

        refresh() {
            this.loadContent(false);
        },

        loadMore() {
            this.loadContent(true);
        },

        async onDownload() {
            if (!this.selectedFile) {
                return;
            }
            try {
                const blob = await this.muwaLogViewerService.download(this.selectedFile);
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = this.selectedFile;
                link.click();
                window.URL.revokeObjectURL(url);
            } catch (error) {
                this.errorMessage = error?.response?.data?.errors?.[0]?.detail || error.message;
            }
        },
    },
});
