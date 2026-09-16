@pushOnce('scripts', 'bagisto-category-tree-scope')
    <script type="module">
        (() => {
            const CHANNEL = @js($bagistoChannelField);
            const ENDPOINT = "{{ route('bagisto.category.tree') }}";
            const LOCALE = "{{ core()->getRequestedLocaleCode() }}";

            const channelCodes = () => {
                const value = (window.unopim?.bridgedValues ?? {})[CHANNEL];

                if (! value) {
                    return [];
                }

                if (Array.isArray(value)) {
                    return value.filter(Boolean);
                }

                return `${value}`.split(',').map(code => code.trim()).filter(Boolean);
            };

            const patch = () => {
                const field = app.component('v-field-category-tree');

                if (! field?.methods?.getRoots || field.methods.getRoots.bagistoScoped) {
                    return;
                }

                const original = field.methods.getRoots;

                field.methods.getRoots = function () {
                    const channel = channelCodes();

                    if (! channel.length) {
                        return original.call(this);
                    }

                    this.$axios.post(ENDPOINT, {
                        locale: LOCALE,
                        selected: this.selectedCodes,
                        channel,
                    })
                        .then(response => {
                            this.isLoading = false;
                            this.categories = response.data.data ?? [];
                            this.selectedCategoryTree = response.data.selected_tree ?? [];
                        })
                        .catch(() => {
                            this.isLoading = false;
                        });
                };

                field.methods.getRoots.bagistoScoped = true;

                (field.mixins ??= []).push({
                    mounted() {
                        this.$emitter.on('filter-value-changed', this.bagistoChannelChanged);
                    },

                    beforeUnmount() {
                        this.$emitter.off('filter-value-changed', this.bagistoChannelChanged);
                    },

                    methods: {
                        bagistoChannelChanged(changed) {
                            if (changed.filterName !== CHANNEL) {
                                return;
                            }

                            this.isLoading = true;
                            this.selectedCodes = [];
                            this.getRoots();
                        },
                    },
                });
            };

            if (document.readyState === 'complete') {
                patch();
            } else {
                document.addEventListener('DOMContentLoaded', patch, { once: true });
                window.addEventListener('load', patch, { once: true });
            }
        })();
    </script>
@endPushOnce
