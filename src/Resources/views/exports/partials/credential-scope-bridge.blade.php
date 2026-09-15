@pushOnce('scripts', 'bagisto-credential-scope-bridge')
    <script type="module">
        (() => {
            const CREDENTIAL = @js($bagistoCredentialField);

            const fieldSet = app.component('v-field-set');

            if (! fieldSet) {
                return;
            }

            const listeners = new WeakMap();

            (fieldSet.mixins ??= []).push({
                created() {
                    if (typeof this.scopedField !== 'function' || ! this.values) {
                        return;
                    }

                    const mirror = (changed) => {
                        if (changed.filterName !== CREDENTIAL) {
                            return;
                        }

                        if (this.fieldList.some(field => field.name === CREDENTIAL)) {
                            return;
                        }

                        const previous = this.values[CREDENTIAL];

                        this.values[CREDENTIAL] = changed.value;

                        if (this.toCodes(previous).join(',') === this.toCodes(changed.value).join(',')) {
                            return;
                        }

                        this.fieldList
                            .filter(field => field.depends_on?.field === CREDENTIAL)
                            .forEach(field => { this.values[field.name] = null; });
                    };

                    listeners.set(this, mirror);

                    this.$emitter.on('filter-value-changed', mirror);
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
