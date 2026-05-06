```vue
<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import { ref, watch, computed } from "vue";
import { debounce } from "lodash";

interface Props {
    acts?: any;
    account?: any;
}

const props = defineProps<Props>();

// Tabla
const headers = ref([
    { title: "Folio", key: "folio" },
    { title: "Tipo", key: "violation_type" },
    { title: "Advertencia", key: "warning" },
    { title: "Multa", key: "fine" },
    { title: "Fecha", key: "date" },
    { title: "Acciones", key: "actions" }
]);

const items = ref([]);
const total = ref(0);
const loading = ref(false);

// Opciones tabla
const options = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{ key: "id", order: "desc" }]
});

const search = ref("");

// Modal
const selectedAct = ref<any>(null);
const showModal = ref(false);

const form = ref({
    id: null,
    violation_type: null,
    other_violation: "",
    description: "",
    date: null,
    time: null,

    hasFine: false,
    amount: null,
    concept: "",
    due_date: null,

    warning_type: null,
    has_suspension: false,
    suspension_start: null,
    suspension_end: null,

    files: []
});

const memberOptions = computed(() => {
    return props.account?.members?.map((m: any) => ({
        title: `${m.first_name} ${m.last_name}`,
        value: m.id
    })) || [];
});

const openCreate = () => {
    form.value = {
        id: null,
        member_id: null,

        violation_type: null,
        other_violation: "",

        description: "",
        date: null,
        time: null,

        hasFine: false,
        amount: null,
        concept: "",
        due_date: null,

        warning_type: null,
        has_suspension: false,
        suspension_start: null,
        suspension_end: null,

        files: []
    };

    showModal.value = true;
};
const save = () => {
    const routeName = form.value.id
        ? route("acts.update", form.value.id)
        : route("acts.store");

    const method = form.value.id ? "post" : "post";

    router[method](routeName, {
        ...form.value,
        _method: form.value.id ? "PUT" : "POST",
        account_id: props.account?.id
    }, {
        forceFormData: true,
        onSuccess: () => {
            showModal.value = false;
            fetchItems();
        }
    });
};

// Fetch
const fetchItems = () => {
    loading.value = true;

    router.get(
        route("acts.index"),
        {
            page: options.value.page,
            per_page: options.value.itemsPerPage,
            search: search.value.trim() || null
        },
        {
            preserveState: true,
            replace: true,
            only: ["acts"]
        }
    );
};


// Watch datos
watch(
    () => props.acts,
    (val) => {
        items.value = val?.data ?? [];
        total.value = val?.total ?? 0;
        loading.value = false;
    },
    { immediate: true }
);

// Watch búsqueda
watch(search, debounce(() => {
    options.value.page = 1;
    fetchItems();
}, 400));

watch([options], debounce(fetchItems, 400), { deep: true });

// Funciones
const viewDetail = (item: any) => {
    selectedAct.value = item;
};

const edit = (item: any) => {
    form.value = {
        id: item.id,

        member_id: item.member_id,

        violation_type: item.violation_type,
        other_violation: "",

        description: item.description,
        date: item.date,
        time: item.time,

        hasFine: !!item.fine,
        amount: item.fine?.amount || null,
        concept: item.fine?.concept || "",
        due_date: item.fine?.due_date || null,

        warning_type: item.warning?.type,
        has_suspension: item.warning?.has_suspension,
        suspension_start: item.warning?.suspension_start,
        suspension_end: item.warning?.suspension_end,

        files: []
    };

    showModal.value = true;
};

const formatDate = (val: string | null) => {
    if (!val) return "-";
    return new Date(val).toLocaleDateString("es-MX");
};
</script>

<template>
    <Head title="Actas y multas" />

    <AppLayout>
        <!-- HEADER -->
        <template #header>
            <div>
                <h2 class="text-h5 font-weight-bold d-flex align-center gap-2">
                    <v-icon size="22">mdi-file-document-outline</v-icon>
                    Actas y multas
                </h2>
                <span class="text-caption text-medium-emphasis">
                    Registro y control de incidencias
                </span>
            </div>
        </template>

        <template #options>
            <BaseButton
                :text="'Volver'"
                :icon-only="false"
                action="cancel"
                icon="mdi-chevron-left"
                @click="router.visit(route('members.manage.show', props.account?.membership_id))"
            />
        </template>
            <BaseButton
                :text="'Registrar multa'"
                :icon-only="false"
                icon="mdi-plus"
                variant="tonal"
                @click="openCreate"
            />
        <!-- TABLA -->
        <v-data-table-server
            :headers="headers"
            :items="items"
            :items-length="total"
            :loading="loading"
            v-model:options="options"
            class="elevation-1"
            no-data-text="No hay actas registradas"
        >
            <template #top>
                <v-text-field v-model="search" label="Buscar acta" />
            </template>

            <!-- Advertencia -->
            <template #item.warning="{ item }">
                <v-chip
                    :color="{
                        leve: 'green',
                        moderada: 'orange',
                        grave: 'red'
                    }[item.warning?.type]"
                >
                    {{ item.warning?.type ?? '-' }}
                </v-chip>
            </template>

            <!-- Multa -->
            <template #item.fine="{ item }">
                <v-chip v-if="item.fine" color="red">
                    ${{ item.fine.amount }}
                </v-chip>
                <span v-else>-</span>
            </template>

            <!-- Fecha -->
            <template #item.date="{ item }">
                {{ formatDate(item.date) }}
            </template>

            <!-- Acciones -->
            <template #item.actions="{ item }">
                <BaseButton
                    action="view"
                    @click="viewDetail(item)"
                />

                <BaseButton
                    icon="mdi-pencil"
                    action="edit"
                    @click="edit(item)"
                />
            </template>
        </v-data-table-server>

        <!-- MODAL -->
        <v-dialog v-model="showModal" max-width="650" persistent>
            <v-form @submit.prevent="save">
                <v-card :title="form.id ? 'Editar acta' : 'Nueva acta'">

                    <v-card-text style="max-height:70vh; overflow:auto">
                        <v-row>
                            <v-col cols="12">
                                <v-select
                                    v-model="form.member_id"
                                    label="Miembro"
                                    prepend-inner-icon="mdi-account"
                                    :items="memberOptions"
                                    item-title="title"
                                    item-value="value"
                                />
                            </v-col>
                            <!-- Tipo -->
                            <v-col cols="12">
                                <v-select
                                    v-model="form.violation_type"
                                    label="Tipo de falta"
                                    prepend-inner-icon="mdi-alert"
                                    :items="[
                                        { title: 'Daños', value: 'danos' },
                                        { title: 'Reglamento', value: 'reglamento' },
                                        { title: 'Conducta', value: 'conducta' },
                                        { title: 'Otro', value: 'otro' }
                                    ]"
                                />
                            </v-col>

                            <!-- Otro -->
                            <v-col cols="12" v-if="form.violation_type === 'otro'">
                                <v-text-field
                                    v-model="form.other_violation"
                                    label="Especifica la falta"
                                />
                            </v-col>

                            <!-- Descripción -->
                            <v-col cols="12">
                                <v-textarea
                                    v-model="form.description"
                                    label="Descripción"
                                />
                            </v-col>

                            <!-- Fecha -->
                            <v-col cols="6">
                                <v-text-field v-model="form.date" type="date" label="Fecha" />
                            </v-col>

                            <v-col cols="6">
                                <v-text-field v-model="form.time" type="time" label="Hora" />
                            </v-col>

                            <!-- Multa -->
                            <v-col cols="12">
                                <v-switch v-model="form.hasFine" label="¿Aplica multa?" />
                            </v-col>

                            <v-col cols="4" v-if="form.amount">
                                <v-text-field v-model="form.amount" label="Monto" type="number" />
                            </v-col>

                            <v-col cols="4" v-if="form.amount">
                                <v-text-field v-model="form.concept" label="Concepto" />
                            </v-col>

                            <v-col cols="4" v-if="form.amount">
                                <v-text-field v-model="form.due_date" type="date" label="Fecha límite" />
                            </v-col>

                            <!-- Advertencia -->
                            <v-col cols="12">
                                <v-select
                                    v-model="form.warning_type"
                                    label="Advertencia"
                                    :items="[
                                        { title: 'Leve', value: 'leve' },
                                        { title: 'Moderada', value: 'moderada' },
                                        { title: 'Grave', value: 'grave' }
                                    ]"
                                />
                            </v-col>

                            <!-- Suspensión -->
                            <v-col cols="12">
                                <v-switch v-model="form.has_suspension" label="¿Suspensión?" />
                            </v-col>

                            <v-col cols="6" v-if="form.has_suspension">
                                <v-text-field v-model="form.suspension_start" type="date" label="Inicio" />
                            </v-col>

                            <v-col cols="6" v-if="form.has_suspension">
                                <v-text-field v-model="form.suspension_end" type="date" label="Fin" />
                            </v-col>

                            <!-- Archivos -->
                            <v-col cols="12">
                                <v-file-input
                                    v-model="form.files"
                                    label="Evidencia"
                                    multiple
                                />
                            </v-col>

                        </v-row>
                    </v-card-text>

                    <v-card-actions>
                        <v-spacer />

                        <BaseButton
                            text="Cancelar"
                            action="cancel"
                            :icon-only="false"
                            variant="tonal"
                            @click="showModal = false"
                        />

                        <BaseButton
                            :text="form.id ? 'Actualizar' : 'Guardar'"
                            :icon-only="false"
                            variant="tonal"
                            action="save"
                            type="submit"
                        />
                    </v-card-actions>

                </v-card>
            </v-form>
        </v-dialog>
    </AppLayout>
</template>