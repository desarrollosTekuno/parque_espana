```vue
<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { ref, watch, computed } from "vue";
import { debounce } from "lodash";
import { customToastSwal } from "@/utils/swal";
const page = usePage();
const can = usePage().props.auth.permissions;
interface Props {
    acts?: any;
    account?: any;
    membershipId: Number,
}

const previews = ref<any[]>([]);
const existingFiles = ref<number[]>([]);
const fileInput = ref<HTMLInputElement | null>(null);
const date = new Date();
const today = new Date().toISOString().split('T')[0];
const props = defineProps({
    acts: Object,
    account_id: Number,
    account: Object,
    membershipId: Number
});

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
const formRef = ref();

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
    member_id: null,
    club_id: null,
    violation_type: null,
    other_violation: "",
    description: "",
    date: null,
    time: null,
    folio: null,

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
    previews.value = [];
    form.value = {
        id: null,
        member_id: null,
        club_id: props.account?.club_id || null,
        folio: `ACT-${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${Math.floor(Math.random()*9999)}`,

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
const save = async () => {
    const { valid } = await formRef.value.validate();
    if (!valid) return;
    const payload = {
        ...form.value,

        existing_files: existingFiles.value,

        account_id: props.account?.id,
        membership_id: props.membershipId?.id,
        club_id: form.value.club_id || props.account?.club_id || null,

        date: form.value.date || null,
        time: form.value.time || null,

        hasFine: !!form.value.hasFine,
        has_suspension: !!form.value.has_suspension,
    };

    if (!payload.hasFine) {
        payload.amount = null;
        payload.concept = null;
        payload.due_date = null;
    }

    const routeName = form.value.id
        ? route("acts.update", form.value.id)
        : route("acts.store");

    router.post(routeName, {
        ...payload,
        existing_files: previews.value
    .filter(f => f.isExisting)
    .map(f => f.id),
        _method: form.value.id ? 'PUT' : 'POST'
    }, {
        forceFormData: true,

        onSuccess: () => {
            customToastSwal({
                title: form.value.id ? "Acta actualizada" : "Acta registrada",
                icon: "success"
            });
            showModal.value = false;
            fetchItems();
        },

        onError: (errors: any) => {
            customToastSwal({
                title: errors.messageError || 'Error al guardar',
                icon: "error",
            });
        }
    });
};

// Fetch
const fetchItems = () => {
    loading.value = true;

    router.get(
        route("acts.index", props.account_id),
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

const edit = (item: any) => {
    const predefinedTypes = [
        'danos',
        'reglamento',
        'conducta'
    ];

    const isCustomViolation =
        !predefinedTypes.includes(item.violation_type);
        previews.value = [];
        existingFiles.value = [];

    form.value = {
        id: item.id,

        member_id: item.member_id,
        club_id: item.club_id,
        folio: item.folio,
        violation_type: isCustomViolation
        ? 'otro'
        : item.violation_type,

        other_violation: isCustomViolation
            ? item.violation_type
            : "",

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

    if (item.files?.length) {

        previews.value = item.files.map((file: any) => ({
            id: file.id,
            name: file.path.split('/').pop(),
            type: file.mime_type || file.path,
            url: file.url,
            isExisting: true
        }));

        existingFiles.value = item.files.map((f:any) => f.id);
    }

    showModal.value = true;
};

const formatDate = (val: string | null) => {
    if (!val) return "-";
    return new Date(val).toLocaleDateString("es-MX");
};
watch(() => form.value.has_suspension, (val) => {
    if (!val) {
        form.value.suspension_start = null;
        form.value.suspension_end = null;
    }
});
const requiredRule = [
    (v:any) => !!v || 'Este campo es obligatorio'
];
const dateRule = [
    (v:any) => !!v || 'La fecha es obligatoria',
    (v:any) => v <= today || 'La fecha no puede ser futura'
];
// Funciones para manejo de archivos
const MAX_SIZE = 2 * 1024 * 1024; // 2MB

const handleFiles = (files: File[] | FileList) => {
    const fileArray = Array.from(files);
    const validFiles = fileArray.filter(file => {
        if (file.size > MAX_SIZE) {
            customToastSwal({
                title: `El archivo ${file.name} excede 2MB`,
                icon: "error"
            });
            return false;
        }
        return true;
    });
    form.value.files = [
        ...(form.value.files || []),
        ...validFiles
    ];
    const newPreviews = validFiles.map(file => ({
        name: file.name,
        type: file.type,
        url: file.type.startsWith("image/")
            ? URL.createObjectURL(file)
            : null,
        isExisting: false
    }));
    previews.value = [
        ...previews.value,
        ...newPreviews
    ];
};
const isDragging = ref(false);

const onDrop = (e: DragEvent) => {
    e.preventDefault();
    e.stopPropagation();

    isDragging.value = false;

    if (e.dataTransfer?.files?.length) {
        handleFiles(e.dataTransfer.files);
    }
};

const onDragOver = (e: DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    isDragging.value = true;
};

const onDragLeave = (e: DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    isDragging.value = false;
};

const removeFile = (index: number) => {
    const file = previews.value[index];
    // si es archivo existente
    if (file.isExisting) {
        existingFiles.value =
            existingFiles.value.filter(id => id !== file.id);
    } else {
        form.value.files.splice(index, 1);
        if (file.url) {
            URL.revokeObjectURL(file.url);
        }
    }
    previews.value.splice(index, 1);
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
                @click="router.visit(route('members.manage.show', props.membershipId))"
            />
        </template>
            <BaseButton  v-if="can.includes('acts.store')"
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
                <BaseButton  v-if="can.includes('acts.update')"
                    icon="mdi-pencil"
                    action="edit"
                    @click="edit(item)"
                />
            </template>
        </v-data-table-server>

        <!-- MODAL -->
        <v-dialog v-model="showModal" max-width="650" persistent>
            <v-form @submit.prevent="save" ref="formRef">
                <v-card>
                    <template #title>
                        <div class="d-flex align-center gap-2">
                            <v-icon size="22">
                                {{ form.id ? 'mdi-pencil' : 'mdi-file-document-plus-outline' }}
                            </v-icon>

                            <span>
                                {{ form.id ? 'Editar acta' : 'Nueva acta' }}
                            </span>
                        </div>

                        <div class="text-caption text-medium-emphasis">
                            {{ form.id ? 'Modifica la información de la incidencia' : 'Registra una nueva incidencia' }}
                        </div>
                    </template>
                    <v-card-text style="max-height:70vh; overflow:auto">
                        <v-row>
                             <v-col cols="12">
                                <strong>Vista previa:</strong>

                                <v-card class="mt-2" elevation="3">
                                    <v-card-text>

                                        <div class="text-caption text-grey">
                                            {{ form.folio || 'Folio' }}
                                        </div>

                                        <div class="text-h6 font-weight-bold">
                                            {{ form.violation_type || 'Tipo de falta' }}
                                        </div>

                                        <div class="text-body-2 mb-2">
                                            {{ form.description || 'Descripción de la incidencia...' }}
                                        </div>

                                        <v-chip v-if="form.warning_type" color="orange" size="small">
                                            {{ form.warning_type }}
                                        </v-chip>

                                        <v-chip v-if="form.hasFine" color="red" size="small">
                                            ${{ form.amount || 0 }}
                                        </v-chip>

                                        <div class="text-caption mt-2">
                                            {{ form.date || 'Fecha' }} {{ form.time || '' }}
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-col>
                            <v-col cols="12">
                                <v-select
                                    v-model="form.member_id"
                                    label="Miembro"
                                    prepend-inner-icon="mdi-account"
                                    :items="memberOptions"
                                    item-title="title"
                                    item-value="value"
                                    :rules="requiredRule"
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
                                    :rules="requiredRule"
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
                                    :rules="requiredRule"
                                />
                            </v-col>

                            <!-- Fecha -->
                            <v-col cols="6">
                                <v-text-field v-model="form.date" type="date" label="Fecha" :max="today" :rules="dateRule" />
                            </v-col>

                            <v-col cols="6">
                                <v-text-field v-model="form.time" type="time" label="Hora" />
                            </v-col>

                            <!-- Multa -->
                            <v-col cols="12">
                                <v-switch
                                    v-model="form.hasFine"
                                    inset
                                    color="red"
                                >
                                    <template #label>
                                        <div class="d-flex align-center gap-2">
                                            <v-icon size="20">
                                                mdi-cash-remove
                                            </v-icon>

                                            <span>¿Aplica multa?</span>
                                        </div>
                                    </template>
                                </v-switch>
                            </v-col>

                            <v-row v-if="form.hasFine">
                                <v-col cols="4">
                                    <v-text-field v-model="form.amount" label="Monto" type="number" />
                                </v-col>

                                <v-col cols="4">
                                    <v-text-field v-model="form.concept" label="Concepto" />
                                </v-col>

                                <v-col cols="4">
                                    <v-text-field v-model="form.due_date" type="date" label="Fecha límite" />
                                </v-col>
                            </v-row>

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
                                    :rules="requiredRule"
                                />
                            </v-col>

                            <!-- Suspensión -->
                             <v-col cols="12">
                                <v-switch
                                    v-model="form.has_suspension"
                                    inset
                                    color="warning"
                                >
                                    <template #label>
                                        <div class="d-flex align-center gap-2">
                                            <v-icon size="18">mdi-account-off</v-icon>
                                            <span>¿Aplica suspensión?</span>
                                        </div>
                                    </template>
                                </v-switch>
                            </v-col>
                            <v-row v-if="form.has_suspension">
                                <v-col cols="6">
                                    <v-text-field
                                        v-model="form.suspension_start"
                                        type="date"
                                        label="Inicio de suspensión"
                                    />
                                </v-col>

                                <v-col cols="6">
                                    <v-text-field
                                        v-model="form.suspension_end"
                                        type="date"
                                        label="Fin de suspensión"
                                    />
                                </v-col>
                            </v-row>

                            <!-- Archivos -->
                            <v-col cols="12">
                                <div
                                    class="drop-zone"
                                    :class="{ dragging: isDragging }"
                                    @drop="onDrop"
                                    @dragover="onDragOver"
                                    @dragleave="onDragLeave"
                                    @click="fileInput?.click()"
                                >
                                    <v-icon size="40" class="mb-2">mdi-cloud-upload</v-icon>
                                    <div class="text-body-2" @click="fileInput?.click()">
                                        Arrastra archivos aquí o da clic
                                    </div>
                                    <input
                                        ref="fileInput"
                                        type="file"
                                        multiple
                                        class="hidden-input"
                                        @change="(e:any) => handleFiles(e.target.files)"; e.target.value = null;
                                    />
                                </div>
                            </v-col>
                            <v-col cols="12" v-if="previews.length">
                                <v-row>
                                    <v-col
                                        v-for="(file, i) in previews"
                                        :key="i"
                                        cols="4"
                                    >
                                        <v-card class="pa-2 text-center">

                                            <!-- Imagen -->
                                            <v-img
                                                v-if="
                                                    file.type?.includes('png') ||
                                                    file.type?.includes('jpg') ||
                                                    file.type?.includes('jpeg') ||
                                                    file.type?.includes('webp')
                                                "
                                                :src="file.url"
                                                height="100"
                                                cover
                                            />

                                            <!-- Documento -->
                                            <div v-else class="py-4">

                                                <v-icon
                                                    size="40"
                                                    :color="
                                                        file.type?.includes('pdf')
                                                            ? 'red'
                                                            : 'primary'
                                                    "
                                                >
                                                    {{
                                                        file.type?.includes('pdf')
                                                            ? 'mdi-file-pdf-box'
                                                            : file.type?.includes('doc')
                                                                ? 'mdi-file-word'
                                                                : file.type?.includes('xls')
                                                                    ? 'mdi-file-excel'
                                                                    : 'mdi-file-document'
                                                    }}
                                                </v-icon>

                                                <div class="text-caption mt-2">
                                                    {{ file.name }}
                                                </div>
                                            </div>
                                            <v-btn
                                                icon="mdi-close"
                                                size="x-small"
                                                color="red"
                                                class="position-absolute"
                                                style="top: 5px; right: 5px;"
                                                @click="removeFile(i)"
                                            />
                                        </v-card>
                                    </v-col>
                                </v-row>
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
                            @click="save" 
                        />
                    </v-card-actions>
                </v-card>
            </v-form>
        </v-dialog>
    </AppLayout>
</template>
<style scoped>
    .drop-zone {
        border: 2px dashed #ccc;
        border-radius: 12px;
        padding: 30px;
        text-align: center;
        cursor: pointer;
        transition: 0.2s;
    }

    .drop-zone.dragging {
        border-color: #1976d2;
        background: #e3f2fd;
    }

    .hidden-input {
        display: none;
    }
</style>