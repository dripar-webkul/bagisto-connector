@pushOnce('scripts', 'bagisto-category-tree-field')
    <script type="text/x-template" id="v-field-bagisto-category-tree-template">
        <div class="flex flex-col gap-2">
            <input
                v-for="code in selectedCodes"
                :key="'selected-' + code"
                type="hidden"
                :name="name + '[]'"
                :value="code"
            />

            <div class="overflow-y-auto border rounded-md dark:border-gray-700 p-2.5" style="max-height: 360px;">
                <template v-if="isLoading">
                    <x-admin::shimmer.tree />
                </template>

                <template v-else-if="! categories.length">
                    <p class="p-2 text-sm text-gray-500 dark:text-gray-300">
                        @lang('admin::app.settings.data-transfer.exports.create.no-categories')
                    </p>
                </template>

                <template v-else>
                    <x-admin::tree.category.view
                        input-type="checkbox"
                        selection-type="hierarchical"
                        name-field="bagisto_category_filter"
                        id-field="code"
                        value-field="code"
                        children-page-size="100"
                        ::items="categories"
                        ::value="selectedJson"
                        ::expanded-branch="selectedCategoryTree"
                        :fallback-locale="config('app.fallback_locale')"
                        @change-input="onTreeSelection"
                    >
                    </x-admin::tree.category.view>
                </template>
            </div>
        </div>
    </script>

    <script type="module">
        app.component('v-field-bagisto-category-tree', {
            template: '#v-field-bagisto-category-tree-template',

            mixins: [window.unopim.fieldBase],

            data() {
                return {
                    isLoading: true,
                    categories: [],
                    selectedCategoryTree: [],
                    selectedCodes: this.toCodes(this.modelValue),
                };
            },

            computed: {
                selectedJson() {
                    return JSON.stringify(this.selectedCodes);
                },

                channelCodes() {
                    return this.toCodes(this.field?.query_params?.[this.field?.depends_on?.as ?? 'channel']);
                },
            },

            watch: {
                selectedCodes: {
                    deep: true,
                    handler(codes) {
                        this.setValue([...codes]);
                    },
                },
            },

            mounted() {
                this.getRoots();
            },

            methods: {
                toCodes(value) {
                    if (! value) {
                        return [];
                    }

                    if (Array.isArray(value)) {
                        return value.filter(code => code !== '' && code !== null);
                    }

                    const parsed = this.parseJson(value);

                    if (Array.isArray(parsed)) {
                        return parsed.filter(Boolean);
                    }

                    return `${value}`.split(',').map(code => code.trim()).filter(Boolean);
                },

                getRoots() {
                    this.$axios.post("{{ route('bagisto.category.tree') }}", {
                        locale: "{{ core()->getRequestedLocaleCode() }}",
                        selected: this.selectedCodes,
                        channel: this.channelCodes,
                    })
                        .then(response => {
                            this.isLoading = false;
                            this.categories = response.data.data ?? [];
                            this.selectedCategoryTree = response.data.selected_tree ?? [];
                        })
                        .catch(() => {
                            this.isLoading = false;
                        });
                },

                onTreeSelection(codes) {
                    this.selectedCodes = Array.isArray(codes) ? [...codes] : [];
                },
            },
        });
    </script>
@endPushOnce
