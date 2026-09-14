<x-admin::layouts.with-history
    :active-tab="\Webkul\Bagisto\Enums\CredentialTab::ATTRIBUTE_MAPPING->value"
    :tab-items="\Webkul\Bagisto\Enums\CredentialTab::items($credential->id)"
    :history-id="$credential->id"
    :history-url="\Webkul\Bagisto\Enums\CredentialTab::historyUrl($credential->id)"
>
    <x-slot:entityName>
        bagitsto_credentials
    </x-slot>

    <x-slot:title>
        @lang('bagisto::app.bagisto.export.mapping.attributes.title')
    </x-slot>

    <x-slot:tabContents>
        @unless (request()->has('history'))
            <v-attribute-mapping
                :bagisto-attributes='@json($bagistoAttributes)'
                :attributes='@json($attributes)'
            />
        @endunless
    </x-slot>

    @pushOnce('scripts')
        <script type="text/x-template" id="v-attribute-mapping-template">
            <x-admin::form
                id="bagisto-attribute-mapping-form"
                ajax
                :action="route('admin.bagisto.credentials.attribute_mapping.store', $credential->id)"
            >
                    <div class="flex justify-between items-center">
                        <p class="text-xl text-gray-800 dark:text-slate-50 font-bold">
                            @lang('bagisto::app.bagisto.export.mapping.attributes.title')
                        </p>

                    </div>

                    <div class="flex gap-2.5 mt-3.5 max-xl:flex-wrap">
                        <div class="flex flex-col gap-2 flex-1 max-xl:flex-auto">
                            <div class="p-4 bg-white dark:bg-cherry-900 rounded box-shadow">
                                <div class="grid grid-cols-3 gap-10 items-center px-4 py-2.5 border-b bg-violet-50 dark:border-cherry-800 dark:bg-cherry-900 font-semibold">
                                    <p class="break-words font-bold dark:text-slate-50">@lang('bagisto::app.bagisto.export.mapping.attributes.bagisto-attribute')</p>
                                    <p class="break-words font-bold dark:text-slate-50">@lang('bagisto::app.bagisto.export.mapping.attributes.unopim-attribute')</p>
                                    <p class="break-words font-bold dark:text-slate-50">@lang('bagisto::app.bagisto.export.mapping.attributes.fixed-value')</p>
                                </div>

                                <div
                                    v-for="(bagistoAttribute, index) in standardBagistoAttributes"
                                    :key="index"
                                    data-control-group
                                    class="grid grid-cols-3 gap-x-5 items-center px-4 py-4 border-b dark:border-cherry-800 text-gray-600 dark:text-gray-300 transition-all hover:bg-violet-50 hover:bg-opacity-30 dark:hover:bg-cherry-800"
                                >
                                    <div class="break-words">
                                        <x-admin::form.control-group.label ::title="bagistoAttribute.title">
                                            <span class="font-bold">@{{ bagistoAttribute.name }} [@{{ bagistoAttribute.code }}]</span>

                                            <span
                                                class="required text-red-600"
                                                v-if="bagistoAttribute.required"
                                            >
                                            </span>
                                        </x-admin::form.control-group.label>

                                        <small v-if="bagistoAttribute.title" class="block text-gray-500">
                                            <i class="icon-information text-xs"></i> @{{ bagistoAttribute.title }}
                                        </small>

                                        <v-error-message
                                            :name="'standard_attributes[' + bagistoAttribute.name + ']'"
                                            v-slot="{ message }"
                                        >
                                            <p class="mt-1 text-red-600 text-xs italic" v-text="message"></p>
                                        </v-error-message>

                                        <v-error-message
                                            :name="'standard_attributes_default[' + bagistoAttribute.code + ']'"
                                            v-slot="{ message }"
                                        >
                                            <p class="mt-1 text-red-600 text-xs italic" v-text="message"></p>
                                        </v-error-message>
                                    </div>

                                    <div>
                                        <template v-if="bagistoAttribute.multiple">
                                            <x-admin::form.control-group.control
                                                type="multiselect"
                                                ::id="'standard_attributes[' + bagistoAttribute.name + ']'"
                                                ::name="'standard_attributes[' + bagistoAttribute.name + ']'"
                                                @input="handleSelectChange($event, bagistoAttribute.code)"
                                                ::options="getAttributesByType(bagistoAttribute)"
                                                ::label="bagistoAttribute.name"
                                                ::placeholder="bagistoAttribute.name"
                                                ::value="selectMappedStandardAttribute(bagistoAttribute.code)"
                                                track-by="code"
                                                label-by="name"
                                            />
                                        </template>

                                        <template v-else>
                                            <x-admin::form.control-group.control
                                                type="select"
                                                ::id="'standard_attributes[' + bagistoAttribute.name + ']'"
                                                ::name="'standard_attributes[' + bagistoAttribute.name + ']'"
                                                @input="handleSelectChange($event, bagistoAttribute.code )"
                                                ::options="getAttributesByType(bagistoAttribute)"
                                                ::label="bagistoAttribute.name"
                                                ::placeholder="bagistoAttribute.name"
                                                ::value="selectMappedStandardAttribute(bagistoAttribute.code)"
                                                track-by="code"
                                                label-by="name"
                                            />
                                        </template>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <x-admin::form.control-group.control
                                            type="text"
                                            class="flex-1"
                                            ::id="'standard_attributes_default[' + bagistoAttribute.code + ']'"
                                            ::name="'standard_attributes_default[' + bagistoAttribute.code + ']'"
                                            ::value="selectMappedStandardAttributeDefault(bagistoAttribute.code) ?? bagistoAttribute.fixedValue"
                                            ::label="bagistoAttribute.name"
                                            ::disabled="isDisabled(bagistoAttribute.code)"
                                        />

                                        <button
                                            v-if="!bagistoAttribute.id"
                                            type="button"
                                            class="cursor-pointer"
                                            :title="'@lang('bagisto::app.bagisto.export.mapping.attributes.remove')'"
                                            @click="removeAttribute(index)"
                                        >
                                            <i class="icon-delete"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <v-additional-attribute-mapping
                                :attributes="attributes"
                                @add-attribute="addAdditionalAttribute"
                                @remove-attribute="removeAdditionalAttribute"
                            />
                        </div>
                    </div>

                    <input
                        type="hidden"
                        name="standard_attributes"
                        :value="JSON.stringify(standardAttributes)"
                    />
            </x-admin::form>
        </script>

        <script type="text/x-template" id="v-additional-attribute-mapping-template">
            <div class="p-4 bg-white dark:bg-cherry-900 rounded box-shadow">
                <div class="mb-4">
                    <p class="text-base text-gray-800 dark:text-white font-semibold mb-0">
                        @lang('bagisto::app.bagisto.export.mapping.additional-attributes.title')
                    </p>
                    <span class="mt-2 block text-xs text-gray-500 leading-snug">
                        @lang('bagisto::app.bagisto.export.mapping.additional-attributes.description')
                    </span>
                </div>

                <div>
                    <div class="grid grid-flow-row grid-cols-3 items-center justify-start gap-4">
                        <x-admin::form.control-group class="!mb-0 w-full">
                            <x-admin::form.control-group.label>
                                @lang('bagisto::app.bagisto.export.mapping.additional-attributes.attribute-code')
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.control
                                type="text"
                                id="newBagistoAttributes"
                                name="newBagistoAttributes"
                                v-model="newBagistoAttributes"
                                :label="trans('bagisto::app.bagisto.export.mapping.additional-attributes.attribute-code')"
                                value=""
                                :placeholder="trans('bagisto::app.bagisto.export.mapping.additional-attributes.attribute-code')"
                            />

                            <x-admin::form.control-group.error control-name="newBagistoAttributes" />
                        </x-admin::form.control-group>

                        <x-admin::form.control-group class="!mb-0 w-full">
                            <x-admin::form.control-group.label>
                                @lang('bagisto::app.bagisto.export.mapping.additional-attributes.attribute-type')
                            </x-admin::form.control-group.label>

                        @php
                            $supportedTypes = ['text', 'textarea', 'price', 'boolean', 'select', 'multiselect', 'datetime', 'date', 'image', 'gallery', 'file', 'checkbox'];

                            $attributeTypes = [];

                            foreach($supportedTypes as $type) {
                                $attributeTypes[] = [
                                    'id'    => $type,
                                    'label' => trans('admin::app.catalog.attributes.create.'. $type)
                                ];
                            }

                            $attributeTypesJson = json_encode($attributeTypes);

                        @endphp

                        <x-admin::form.control-group.control
                            type="select"
                            id="type"
                            class="cursor-pointer"
                            name="type"
                            :value="old('type')"
                            ref="attributeTypeGroup"
                            @input="handleTypeChange"
                            :label="trans('admin::app.catalog.attributes.create.type')"
                            :placeholder="trans('bagisto::app.bagisto.export.mapping.additional-attributes.attribute-type')"
                            :options="$attributeTypesJson"
                            track-by="id"
                            label-by="label"
                        >
                        </x-admin::form.control-group.control>

                        <x-admin::form.control-group.error control-name="type" />
                    </x-admin::form.control-group>
                    <div class="self-end">
                        <button
                            type="button"
                            class="primary-button cursor-pointer"
                            @click="addBagistoAttribute"
                        >
                            @lang('bagisto::app.bagisto.export.mapping.attributes.add')
                        </button>
                    </div>
                    </div>
                </div>
            </div>
            <div class="p-4 bg-white dark:bg-cherry-900 rounded box-shadow">
                <div class="mb-4">
                    <p class="text-base text-gray-800 dark:text-white font-semibold mb-0">
                        @lang('bagisto::app.bagisto.export.mapping.configurable-attributes.title')
                    </p>
                    <span class="mt-2 block text-xs text-gray-500 leading-snug">
                        @lang('bagisto::app.bagisto.export.mapping.configurable-attributes.description')
                    </span>
                </div>

                <div class="grid grid-flow-row grid-cols-2 items-center justify-start gap-4 max-w-[60%]">
                    <x-admin::form.control-group class="!mb-0 w-full">
                        <x-admin::form.control-group.label>
                            @lang('bagisto::app.bagisto.export.mapping.configurable-attributes.title')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="multiselect"
                            id="configurable_attribute"
                            name="configurable_attribute"
                            ref="configurableAttributes"
                            v-model="selectedAttributes"
                            :label="trans('bagisto::app.bagisto.export.mapping.configurable-attributes.title')"
                            :options="$configurableAttributes"
                            track-by="code"
                            label-by="name"
                        >
                        </x-admin::form.control-group.control>

                        <x-admin::form.control-group.error control-name="configurable_attribute"/>
                    </x-admin::form.control-group>
                    
                    <div class="flex gap-2.5 self-end">
                        <button
                            type="button"
                            class="primary-button"
                            @click="selectAll"
                        >
                            @lang('bagisto::app.bagisto.export.mapping.attributes.select')
                        </button>

                        <button
                            type="button"
                            class="primary-button"
                            @click="deselectAll"
                        >
                            @lang('bagisto::app.bagisto.export.mapping.attributes.deselect')
                        </button>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-additional-attribute-mapping', {
                template: '#v-additional-attribute-mapping-template',
                props: ['attributes', 'configurableAttributes'],
                data() {
                    return {
                        timeout: null,
                        attributeType: null,
                        selectedAttributes: @json($configurableSelectedAttributes),
                        newBagistoAttributes: '',
                        allConfigurableOption: @json($configurableAttributes),
                        mappedAdditionalAttributes: @json($additionalAttributes),
                    };
                },
                watch: {
                    newBagistoAttributes() {
                        clearTimeout(this.timeout);

                        this.timeout = setTimeout(() => this.fetchValue(), 1000);
                    },
                },
                mounted() {
                    if (this.selectedAttributes.length === 1 && this.selectedAttributes[0] === '') {
                        this.selectAll();
                    }
                },
                methods: {
                    selectAll() {
                        this.selectedAttributes = JSON.parse(this.allConfigurableOption);
                        this.$refs['configurableAttributes'].selectedValue = this.selectedAttributes;
                        this.dispatchTouch('configurable_attribute');
                    },
                    deselectAll() {
                        this.$refs['configurableAttributes'].selectedValue = null;
                        this.selectedAttributes = [];
                        this.dispatchTouch('configurable_attribute');
                    },
                    dispatchTouch(name) {
                        const form = this.$el.closest('form');

                        if (form) {
                            form.dispatchEvent(new CustomEvent('unsaved-changes:touch', {
                                bubbles: true,
                                detail: { name },
                            }));
                        }
                    },
                    handleTypeChange(value) {
                        this.attributeType = value ? JSON.parse(value) : null;
                    },
                    fetchValue() {
                        if (! this.newBagistoAttributes) {
                            this.attributeType = null;

                            return;
                        }

                        this.$axios.get("{{ route('admin.bagisto.attributes.fetch') }}", {
                                params: { code: this.newBagistoAttributes },
                            })
                            .then(response => {
                                if (! response.data.data) {
                                    return;
                                }

                                this.attributeType = {
                                    id:    response.data.attribute.type,
                                    label: response.data.attribute.label,
                                };

                                this.$refs.attributeTypeGroup.selectedValue = this.attributeType;
                            })
                            .catch(() => {});
                    },
                    addBagistoAttribute() {
                        if (!this.newBagistoAttributes || !this.attributeType) {
                            this.$emitter.emit('add-flash', {
                                type: 'warning',
                                message: @json(__('bagisto::app.bagisto.export.mapping.attributes.flash-message'))
                            });
                            return;
                        }
                        if (!window.translations) {
                            window.translations = {};
                        }
                        window.translations.title = "@lang('bagisto::app.bagisto.bagisto-attributes.title.title')";
                        const titleTemplate = window.translations.title;

                        const newAttribute = {
                            name: this.capitalizeFirstLetter(this.newBagistoAttributes),
                            code: this.newBagistoAttributes,
                            type: this.attributeType.id,
                            title: titleTemplate
                                    .replace(':code', this.newBagistoAttributes)
                                    .replace(':type', this.attributeType.id),
                        };

                        this.$emit('add-attribute', newAttribute);
                        this.mappedAdditionalAttributes.push(newAttribute);

                        this.$axios.post("{{ route('admin.bagisto.credentials.attribute_mapping.add', $credential->id) }}", newAttribute)
                            .then(response => {
                                this.$emitter.emit('add-flash', {
                                    type: 'success',
                                    message: response.data.message
                                });

                                if (this.$refs.attributeTypeGroup) {
                                    this.$refs.attributeTypeGroup.selectedValue = null;
                                }

                                this.newBagistoAttributes = '';
                                this.attributeType = null;
                            })
                            .catch(error => {
                                console.error(error);
                                this.mappedAdditionalAttributes.pop();
                            });
                    },
                    capitalizeFirstLetter(string) {
                        return string.charAt(0).toUpperCase() + string.slice(1);
                    },
                }
            });
        </script>

        <script type="module">
            app.component('v-attribute-mapping', {
                template: '#v-attribute-mapping-template',
                props: ['bagistoAttributes', 'attributes'],
                data() {
                    const mappedValues = Object.assign({}, @json($standardAttributes?->mapped_value));

                    if (mappedValues['images'] && !Array.isArray(mappedValues['images'])) {
                        mappedValues['images'] = [mappedValues['images']];
                    }

                    return {
                        standardAttributes: mappedValues,
                        standardAttributesDefaults: Object.assign({}, @json($standardAttributes?->fixed_value)),
                        additionalAttributes: [],
                        standardBagistoAttributes: this.bagistoAttributes,
                        mappedAdditionalAttributes: @json($additionalAttributes)
                    };
                },
                
                mounted() {
                    if (!window.translations) {
                        window.translations = {};
                    }
                    window.translations.title = "@lang('bagisto::app.bagisto.bagisto-attributes.title.title')";
                    const titleTemplate = window.translations.title;

                    const attributesWithTitle = this.mappedAdditionalAttributes.map(attribute => ({
                        ...attribute,
                        title: titleTemplate
                            .replace(':code', attribute.code)
                            .replace(':type', attribute.type),
                    }));

                    this.standardBagistoAttributes.push(...attributesWithTitle);
                },

                methods: {
                    handleSelectChange(value, fieldCode) {
                        try {
                            if (! value) {
                                delete this.standardAttributes[fieldCode];
                                this.dispatchTouch('standard_attributes[' + fieldCode + ']');

                                return;
                            }

                            let selectedValue = Array.isArray(value) ? value : JSON.parse(value);

                            if (Array.isArray(selectedValue)) {
                                if (selectedValue.length) {
                                    this.standardAttributes[fieldCode] = selectedValue.map(item => item.code);
                                } else {
                                    delete this.standardAttributes[fieldCode];
                                }

                                this.dispatchTouch('standard_attributes[' + fieldCode + ']');

                                return;
                            }

                            this.standardAttributes[fieldCode] = selectedValue.code;
                            this.dispatchTouch('standard_attributes[' + fieldCode + ']');
                        } catch (e) {}
                    },

                    isDisabled(code) {
                        return this.standardAttributes[code] ? true : false;
                    },

                    selectMappedStandardAttribute(fieldCode) {
                        return this.standardAttributes[fieldCode] ?? null;
                    },

                    selectMappedStandardAttributeDefault(fieldCode) {
                        return this.standardAttributesDefaults[fieldCode] ?? null;
                    },

                    getAttributesByType(bagistoAttribute) {
                        if (!bagistoAttribute || !bagistoAttribute.type) {
                            return [];
                        }

                        const types = bagistoAttribute.type.split(',').map(type => type.trim());

                        return this.attributes
                            .map(attribute => ({
                                ...attribute,
                                name: attribute.name && attribute.name.trim() ? attribute.name : attribute.code
                            }))
                            .filter(attribute => {
                                const matchesType = types.includes(attribute.type);
                                const matchesUnique = !bagistoAttribute.unique || attribute.is_unique;

                                return matchesType && matchesUnique;
                            });
                    },

                    addAdditionalAttribute(newAttribute) {
                        if (! newAttribute) {
                            return;
                        }

                        this.additionalAttributes.push(newAttribute);
                        this.standardBagistoAttributes.push(newAttribute);
                    },

                    removeAttribute(index) {
                        this.$emitter.emit('open-delete-modal', {
                            title: "@lang('bagisto::app.bagisto.export.mapping.modal-message.title')",
                            message: "@lang('bagisto::app.bagisto.export.mapping.modal-message.message')",
                            options: {
                                btnDisagree: "@lang('admin::app.components.modal.delete.disagree-btn')",
                                btnAgree: "@lang('bagisto::app.bagisto.export.mapping.attributes.remove')",
                                btnAgreeClass: 'danger-button',
                                btnDisagreeClass: 'transparent-button',
                            },
                            agree: () => {
                                let attributeCode = {
                                    code: this.standardBagistoAttributes[index].code
                                };
                                this.$axios.post("{{ route('admin.bagisto.credentials.attribute_mapping.remove', $credential->id) }}",
                                    attributeCode);
                                let removedItem = this.standardBagistoAttributes.splice(index, 1);
                            }
                        });
                    },
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>
