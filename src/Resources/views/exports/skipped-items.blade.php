<v-bagisto-skipped-items
    :initial-panels='@json($bagistoPanels)'
    endpoint="{{ $bagistoEndpoint }}"
></v-bagisto-skipped-items>

@pushOnce('scripts', 'bagisto-skipped-items')
    <script type="text/x-template" id="v-bagisto-skipped-items-template">
        <div>
            <div
                v-for="panel in panels"
                :key="panel.key"
                :class="panelClass(panel)"
            >
                <div class="flex items-start gap-3">
                    <span :class="iconClass(panel)"></span>

                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-gray-800 dark:text-gray-100" v-text="panel.heading"></p>

                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300" v-text="panel.description"></p>

                        <div class="mt-3 overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr :class="headRowClass(panel)">
                                        <th class="py-2 pr-4 font-semibold">@lang('bagisto::app.bagisto.export.skipped.product')</th>
                                        <th class="py-2 font-semibold" v-text="panel.reason"></th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr
                                        v-for="(row, index) in panel.rows"
                                        :key="panel.key + '-' + index"
                                        :class="bodyRowClass(panel)"
                                    >
                                        <td
                                            class="py-2 pr-4 align-top font-medium text-gray-800 dark:text-gray-100 whitespace-nowrap"
                                            v-text="row.identifier"
                                        ></td>

                                        <td class="py-2 align-top text-gray-600 dark:text-gray-300" v-text="row.reason"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </script>

    <script type="module">
        app.component('v-bagisto-skipped-items', {
            template: '#v-bagisto-skipped-items-template',

            props: {
                initialPanels: { type: Array, default: () => ([]) },
                endpoint: { type: String, default: '' },
            },

            data() {
                return {
                    panels: this.initialPanels,
                    retryTimeout: null,
                };
            },

            mounted() {
                this.load();

                this.retryTimeout = setTimeout(() => this.load(true), 1500);
            },

            beforeUnmount() {
                clearTimeout(this.retryTimeout);
            },

            methods: {
                load(keepWhenEmpty = false) {
                    if (! this.endpoint) {
                        return;
                    }

                    this.$axios.get(this.endpoint)
                        .then(({ data }) => {
                            const panels = data?.panels ?? [];

                            if (panels.length || ! keepWhenEmpty) {
                                this.panels = panels;
                            }
                        })
                        .catch(() => {});
                },

                panelClass(panel) {
                    return [
                        'rounded-lg border p-4 mb-4',
                        panel.tone === 'red'
                            ? 'bg-red-50 dark:bg-red-900/20 border-red-300 dark:border-red-800'
                            : 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-300 dark:border-yellow-800',
                    ];
                },

                iconClass(panel) {
                    return [
                        'icon-information text-2xl shrink-0',
                        panel.tone === 'red' ? 'text-red-600' : 'text-yellow-600',
                    ];
                },

                headRowClass(panel) {
                    return [
                        'text-left text-gray-700 dark:text-gray-200 border-b',
                        panel.tone === 'red'
                            ? 'border-red-300 dark:border-red-800'
                            : 'border-yellow-300 dark:border-yellow-800',
                    ];
                },

                bodyRowClass(panel) {
                    return [
                        'border-b last:border-0',
                        panel.tone === 'red'
                            ? 'border-red-200 dark:border-red-900'
                            : 'border-yellow-200 dark:border-yellow-900',
                    ];
                },
            },
        });
    </script>
@endPushOnce
