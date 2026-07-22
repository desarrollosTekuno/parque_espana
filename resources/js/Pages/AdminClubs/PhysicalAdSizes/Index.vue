<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { debounce } from 'lodash';
import { customConfirmSwal, customToastSwal } from '@/utils/swal';
import BaseButton from '@/Components/BaseButton.vue';

const page = usePage();
const can = page.props.auth.permissions;

interface PhysicalAdSize {
    id: number | null;
    label: string;
    price: number | string;
    is_active: boolean;
    display_order: number;
}

interface Props {
    physicalAdSizes?: any;
}

const props = withDefaults(defineProps<Props>(), {
    physicalAdSizes: null,
});

const showModal = ref(false);
const formRef = ref();

const form = useForm<PhysicalAdSize>({
    id:            null,
    label:         '',
    price:         0,
    is_active:     true,
    display_order: 0,
});

const openCreate = () => {
    form.reset();
    form.id            = null;
    form.label         = '';
    form.price         = 0;
    form.is_active     = true;
    form.display_order = 0;
    showModal.value    = true;
};

const openEdit = (item: any) => {
    form.id            = item.id;
    form.label         = item.label;
    form.price         = item.price;
    form.is_active     = item.is_active;
    form.display_order = item.display_order;
    showModal.value    = true;
};

const save = () => {
    formRef.value?.validate().then(({ valid }: { valid: boolean }) => {
        if (!valid) return;

        const opts = {
            onSuccess: () => {
                customToastSwal({ title: page.props.flash.success || 'Guardado correctamente.', icon: 'success' });
                showModal.value = false;
                form.reset();
                fetchItems();
            },
            onError: () => {
                customToastSwal({ title: `Error: ${form.errors.messageError}`, icon: 'error' });
            },
        };

        if (form.id) {
            form.put(route('physical-ad-sizes.update', form.id), opts);
        } else {
            form.post(route('physical-ad-sizes.store'), opts);
        }
    });
};

const destroy = (item: any) => {
    customConfirmSwal({ title: '¿Eliminar este tamaño?' }).then((result) => {
        if (!result.isConfirmed) return;
        form.delete(route('physical-ad-sizes.destroy', item.id), {
            onSuccess: () => {
                customToastSwal({ title: page.props.flash.success || 'Eliminado correctamente.', icon: 'success' });
                fetchItems();
            },
            onError: () => {
                customToastSwal({ title: `Error: ${form.errors.messageError}`, icon: 'error' });
            },
        });
    });
};

const close = () => {
    form.reset();
    showModal.value = false;
};

// ── Tabla server-side ──────────────────────────────────────────────────────
const headers = [
    { title: '#',       key: 'id',            sortable: true  },
    { title: 'Etiqueta', key: 'label',         sortable: true  },
    { title: 'Precio',  key: 'price',          sortable: true  },
    { title: 'Activo',  key: 'is_active',      sortable: false },
    { title: 'Orden',   key: 'display_order',  sortable: true  },
    { title: 'Acciones', key: 'actions',       sortable: false },
];

const items   = ref<any[]>([]);
const total   = ref(0);
const loading = ref(false);
const search  = ref('');
const options = ref({ page: 1, itemsPerPage: 25, sortBy: [{ key: 'display_order', order: 'asc' }] });
const prefix  = 'physicalAdSizes';

const fetchItems = () => {
    loading.value = true;
    const params: Record<string, any> = {
        [`${prefix}_page`]:     options.value.page,
        [`${prefix}_per_page`]: options.value.itemsPerPage,
        [`${prefix}_search`]:   search.value || null,
        [`${prefix}_sort`]:     options.value.sortBy?.[0]?.key ?? 'display_order',
        [`${prefix}_order`]:    options.value.sortBy?.[0]?.order ?? 'asc',
    };

    router.get(route('physical-ad-sizes.index'), params, {
        preserveState: true,
        replace: true,
        onSuccess: (p: any) => {
            items.value   = p.props[prefix]?.data ?? [];
            total.value   = p.props[prefix]?.total ?? 0;
            loading.value = false;
        },
    });
};

watch([options, search], debounce(fetchItems, 400), { deep: true });

watch(
    () => props.physicalAdSizes,
    (val) => {
        items.value = val?.data ?? [];
        total.value = val?.total ?? 0;
    },
    { immediate: true }
);

watch(() => page.props.auth.currentClub, () => fetchItems());

// ── Validación ─────────────────────────────────────────────────────────────
const requiredRule   = (v: any) => !!v || v === 0 || 'Campo requerido';
const maxLength100   = (v: string) => !v || v.length <= 100 || 'Máximo 100 caracteres';
const positiveNumber = (v: any) => (!isNaN(Number(v)) && Number(v) >= 0) || 'Debe ser un número mayor o igual a 0';
</script>

<template>
    <Head title="Tamaños de anuncios físicos" />
    <AppLayout>
        <template #header>Tamaños de anuncios físicos</template>
        <template #options>
            <BaseButton
                v-if="can.includes('physical-ad-sizes.store')"
                variant="elevated"
                :icon-only="false"
                action="add"
                @click="openCreate"
            />
        </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <v-row>
                <v-col cols="12">
                    <v-data-table-server
                        fixed-header
                        hover
                        height="500px"
                        :headers="headers"
                        :items="items"
                        :items-length="total"
                        :loading="loading"
                        v-model:options="options"
                        class="elevation-1"
                        :items-per-page-options="[10, 25, 50]"
                        items-per-page-text="Mostrar"
                        no-data-text="No hay tamaños registrados. Crea el primero con el botón +"
                    >
                        <template #top>
                            <v-text-field
                                v-model="search"
                                label="Buscar"
                                class="mx-4 mt-2"
                                clearable
                                density="compact"
                            />
                        </template>

                        <template #item.price="{ item }">
                            ${{ Number(item.price).toFixed(2) }}
                        </template>

                        <template #item.is_active="{ item }">
                            <v-chip :color="item.is_active ? 'green' : 'grey'" size="small">
                                {{ item.is_active ? 'Activo' : 'Inactivo' }}
                            </v-chip>
                        </template>

                        <template #item.actions="{ item }">
                            <BaseButton
                                v-if="can.includes('physical-ad-sizes.update')"
                                action="edit"
                                @click="openEdit(item)"
                            />
                            <BaseButton
                                v-if="can.includes('physical-ad-sizes.destroy')"
                                action="delete"
                                @click="destroy(item)"
                            />
                        </template>
                    </v-data-table-server>
                </v-col>
            </v-row>
        </div>

        <!-- Modal -->
        <v-dialog v-model="showModal" max-width="480" persistent>
            <v-form @submit.prevent="save" ref="formRef">
                <v-card
                    prepend-icon="mdi-ruler"
                    :title="form.id ? 'Editar tamaño' : 'Nuevo tamaño'"
                >
                    <v-card-text>
                        <v-text-field
                            v-model="form.label"
                            label="Etiqueta *"
                            placeholder="Ej. Carta, Oficio, Doble Carta..."
                            :rules="[requiredRule, maxLength100]"
                            class="mb-2"
                        />
                        <v-text-field
                            v-model.number="form.price"
                            label="Precio *"
                            type="number"
                            min="0"
                            step="0.01"
                            prefix="$"
                            :rules="[requiredRule, positiveNumber]"
                            class="mb-2"
                        />
                        <v-text-field
                            v-model.number="form.display_order"
                            label="Orden de visualización"
                            type="number"
                            min="0"
                            hint="Los tamaños se muestran ordenados por este valor de menor a mayor"
                            persistent-hint
                            class="mb-2"
                        />
                        <v-switch
                            v-model="form.is_active"
                            label="Activo"
                            color="primary"
                            hide-details
                        />
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer />
                        <BaseButton
                            :icon-only="false"
                            variant="tonal"
                            action="cancel"
                            :disabled="form.processing"
                            @click="close"
                        />
                        <BaseButton
                            :text="form.id ? 'Actualizar' : 'Guardar'"
                            variant="flat"
                            :icon-only="false"
                            type="submit"
                            action="save"
                            :loading="form.processing"
                        />
                    </v-card-actions>
                </v-card>
            </v-form>
        </v-dialog>
    </AppLayout>
</template>
