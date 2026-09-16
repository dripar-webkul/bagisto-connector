@pushOnce('scripts', 'bagisto-credential-scope-bridge')
    <script type="module">
        (() => {
            const BRIDGED = @js($bagistoBridgedFields);

            const fieldSet = app.component('v-field-set');

            if (! fieldSet) {
                return;
            }

            const listeners = new WeakMap();

            window.unopim = window.unopim || {};
            window.unopim.bridgedValues = window.unopim.bridgedValues || {};

            const published = window.unopim.bridgedValues;

            (fieldSet.mixins ??= []).push({
                created() {
                    if (typeof this.scopedField !== 'function' || ! this.values) {
                        return;
                    }

                    BRIDGED.forEach(name => {
                        if (this.fieldList.some(field => field.name === name)) {
                            if (this.values[name] !== undefined && this.values[name] !== null) {
                                published[name] = this.values[name];
                            }

                            return;
                        }

                        if (published[name] !== undefined) {
                            this.values[name] = published[name];
                        }
                    });

                    const mirror = (changed) => {
                        if (! BRIDGED.includes(changed.filterName)) {
                            return;
                        }

                        if (this.fieldList.some(field => field.name === changed.filterName)) {
                            return;
                        }

                        const previous = this.values[changed.filterName];

                        this.values[changed.filterName] = changed.value;

                        published[changed.filterName] = changed.value;

                        if (this.toCodes(previous).join(',') === this.toCodes(changed.value).join(',')) {
                            return;
                        }

                        this.fieldList
                            .filter(field => field.depends_on?.field === changed.filterName)
                            .forEach(field => { this.values[field.name] = null; });
                    };

                    listeners.set(this, mirror);

                    this.$emitter.on('filter-value-changed', mirror);
                },

                mounted() {
                    if (! this.values) {
                        return;
                    }

                    BRIDGED.forEach(name => {
                        if (! this.fieldList.some(field => field.name === name)) {
                            return;
                        }

                        if (this.values[name] === undefined || this.values[name] === null) {
                            return;
                        }

                        this.$emitter.emit('filter-value-changed', { filterName: name, value: this.values[name] });
                    });
                },

                beforeUnmount() {
                    const mirror = listeners.get(this);

                    if (mirror) {
                        this.$emitter.off('filter-value-changed', mirror);
                    }
                },
            });
        })();
    </script>
@endPushOnce
