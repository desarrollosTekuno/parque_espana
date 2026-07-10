<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import { required } from "@/constants/validationRules";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import axios from "axios";
import { debounce } from "lodash";
import { computed, nextTick, ref, watch } from "vue";

const page = usePage();
const can = page.props.auth.permissions;

interface Props {
    classSchedules?: any;
    coaches?: any[];
    amenityResources?: any[];
}

const props = defineProps<Props>();

/* ====================== Variables ====================== */
const showModal = ref(false);
const showPreviewModal = ref(false);
const formSendRef = ref();
const saving = ref(false);

const showSessionsModal = ref(false);
const sessionsLoading = ref(false);
const sessionsItems = ref<any[]>([]);
const sessionsClassName = ref("");

const sessionHeaders = [
    { title: "Fecha", key: "date" },
    { title: "Horario", key: "schedule", sortable: false },
    { title: "Entrenador", key: "coach", sortable: false },
    { title: "Cancha", key: "amenity_resource", sortable: false },
    { title: "Cupo", key: "capacity", sortable: false },
    { title: "Estado", key: "status", sortable: false },
];

const DAYS = [
    { label: "Lun", fullLabel: "Lunes", value: 1 },
    { label: "Mar", fullLabel: "Martes", value: 2 },
    { label: "Mie", fullLabel: "Miercoles", value: 3 },
    { label: "Jue", fullLabel: "Jueves", value: 4 },
    { label: "Vie", fullLabel: "Viernes", value: 5 },
    { label: "Sab", fullLabel: "Sabado", value: 6 },
    { label: "Dom", fullLabel: "Domingo", value: 0 },
];

const TYPES = [
    { label: "Adultos", value: "adults", color: "primary", icon: "mdi-account-group-outline" },
    { label: "Ninos", value: "kids", color: "orange", icon: "mdi-human-child" },
];

const headers = [
    { title: "Clase", key: "name" },
    { title: "Tipo", key: "type" },
    { title: "Entrenador", key: "coach", sortable: false },
    { title: "Cancha", key: "amenity_resource", sortable: false },
    { title: "Dia", key: "day_of_week" },
    { title: "Horario", key: "schedule", sortable: false },
    { title: "Cupo", key: "capacity" },
    { title: "Vigencia", key: "validity", sortable: false },
    { title: "Estado", key: "is_active", sortable: false },
    { title: "Acciones", key: "actions", sortable: false },
];

const batchPreview = ref<any[]>([]);

const items = ref<any[]>([]);
const total = ref(0);
const loading = ref(false);
const search = ref("");
const coachFilter = ref<number | null>(null);
const amenityResourceFilter = ref<number | null>(null);
const options = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [] as any[],
});

/* ====================== useForm ====================== */
const form = useForm({
    id: null as number | null,
    name: "",
    type: "adults" as string,
    coach_id: null as number | null,
    amenity_resource_id: null as number | null,
    day_of_week: null as number | null,
    start_time: "",
    end_time: "",
    capacity: 1,
    start_date: null as string | null,
    end_date: null as string | null,
    is_active: true,
    schedules: [] as { day_of_week: number; start_time: string; end_time: string; capacity: number }[],
});

/* ====================== Validaciones ====================== */
const endAfterStart = (v: string) => {
    if (!v || !form.start_time) return true;
    return v > form.start_time || "La hora de fin debe ser mayor a la de inicio";
};

const minCapacity = (v: number | string) => {
    const n = Number(v);
    return (!isNaN(n) && n >= 1) || "El cupo mínimo es 1";
};

const toMinutes = (t: string) => {
    const [h, m] = t.split(":").map(Number);
    return h * 60 + m;
};

const durationInRange = (v: string) => {
    if (!v || !form.start_time || v <= form.start_time) return true;
    const hours = (toMinutes(v) - toMinutes(form.start_time)) / 60;
    return (hours >= 1 && hours <= 2) || "La clase debe durar entre 1 y 2 horas";
};

/* ====================== Computed ====================== */
const canAddSchedule = computed(() => {
    if (form.day_of_week === null) return false;
    if (!form.start_time || !form.end_time) return false;
    if (form.end_time <= form.start_time) return false;
    if (!form.capacity || Number(form.capacity) < 1) return false;

    const hours = (toMinutes(form.end_time) - toMinutes(form.start_time)) / 60;
    return hours >= 1 && hours <= 2;
});

const selectedCoach = computed(() =>
    props.coaches?.find((coach: any) => coach.id === form.coach_id),
);

const selectedResource = computed(() =>
    props.amenityResources?.find((resource: any) => resource.id === form.amenity_resource_id),
);

const filteredAmenityResources = computed(() =>
    (props.amenityResources ?? []).filter((resource: any) =>
        resource.amenity?.name?.toLowerCase().includes("cancha"),
    ),
);

const previewSchedules = computed(() => {
    if (batchPreview.value.length > 0) {
        return batchPreview.value;
    }

    if (!canAddSchedule.value) {
        return [];
    }

    return [
        {
            day: form.day_of_week,
            start: form.start_time,
            end: form.end_time,
            capacity: form.capacity,
        },
    ];
});

/* ====================== Funciones ====================== */
const resetForm = () => {
    form.reset();
    form.type = "adults";
    form.capacity = 1;
    form.start_date = null;
    form.end_date = null;
    form.is_active = true;
    form.schedules = [];
    batchPreview.value = [];
    nextTick(() => formSendRef.value?.resetValidation());
};

const create = () => {
    resetForm();
    showModal.value = true;
};

const edit = (item: any) => {
    form.reset();
    form.id = item.id;
    form.name = item.name;
    form.type = item.type;
    form.coach_id = item.coach_id;
    form.amenity_resource_id = item.amenity_resource_id;
    form.day_of_week = item.day_of_week;
    form.start_time = item.start_time?.substring(0, 5) ?? "";
    form.end_time = item.end_time?.substring(0, 5) ?? "";
    form.capacity = item.capacity;
    form.start_date = item.start_date?.split("T")[0] ?? null;
    form.end_date = item.end_date?.split("T")[0] ?? null;
    form.is_active = item.is_active ?? true;
    batchPreview.value = [
        {
            day: item.day_of_week,
            start: item.start_time?.substring(0, 5) ?? "",
            end: item.end_time?.substring(0, 5) ?? "",
            capacity: item.capacity,
        },
    ];
    showModal.value = true;
};

const save = async () => {
    const { valid } = await formSendRef.value?.validate();
    if (!valid) return;

    if (previewSchedules.value.length === 0) {
        customToastSwal({ title: "Agrega al menos un horario", icon: "warning" });
        return;
    }

    showPreviewModal.value = true;
};

const confirmSave = () => {
    showPreviewModal.value = false;

    if (saving.value) return;
    saving.value = true;

    const callbacks = {
        onSuccess: () => {
            customToastSwal({ title: page.props.flash.success, icon: "success" });
            showModal.value = false;
            resetForm();
            fetchItems();
            saving.value = false;
        },
        onError: () => {
            const firstError = Object.values(form.errors)[0] as string;
            customToastSwal({ title: firstError ?? "Error al guardar", icon: "error" });
            saving.value = false;
        },
    };

    if (form.id) {
        form.put(route("classSchedules.update", form.id), callbacks);
        return;
    }

    form.schedules = previewSchedules.value.map((s) => ({
        day_of_week: s.day,
        start_time: s.start,
        end_time: s.end,
        capacity: Number(s.capacity),
    }));

    form.post(route("classSchedules.store"), callbacks);
};

const destroy = (item: any) => {
    customConfirmSwal({ title: "Eliminar horario?" }).then((r) => {
        if (!r.isConfirmed) return;
        router.delete(route("classSchedules.destroy", item.id), {
            onSuccess: () => {
                customToastSwal({ title: page.props.flash.success, icon: "success" });
                fetchItems();
            },
        });
    });
};

const viewSessions = async (item: any) => {
    sessionsClassName.value = item.name;
    sessionsItems.value = [];
    showSessionsModal.value = true;
    sessionsLoading.value = true;

    try {
        const { data } = await axios.get(route("classSchedules.sessions", item.id));
        sessionsItems.value = data;
    } catch (e) {
        customToastSwal({ title: "Error al cargar las sesiones", icon: "error" });
    } finally {
        sessionsLoading.value = false;
    }
};

const dayLabel = (d: number) => DAYS.find((x) => x.value === d)?.fullLabel ?? "-";
const typeLabel = (t: string) => TYPES.find((x) => x.value === t)?.label ?? t;
const coachName = (coach: any) =>
    coach
        ? `${coach.first_name} ${coach.last_name} ${coach.second_last_name ?? ""}`.trim()
        : "-";
const resourceName = (resource: any) =>
    resource
        ? `${resource.name} - ${resource.amenity?.name ?? ""}`.trim().toUpperCase()
        : "-";
const formatDate = (date: string) => date?.split("T")[0].split("-").reverse().join("/") ?? "-";

const setDay = (day: number) => {
    form.day_of_week = day;
};

const addPreviewRow = () => {
    if (!canAddSchedule.value) return;

    batchPreview.value.push({
        day: form.day_of_week ?? 1,
        start: form.start_time || "08:00",
        end: form.end_time || "09:00",
        capacity: Number(form.capacity || 1),
    });
};

const removePreviewRow = (index: number) => {
    batchPreview.value.splice(index, 1);
};

const fetchItems = () => {
    loading.value = true;
    router.get(
        route("classSchedules.index"),
        {
            page: options.value.page,
            per_page: options.value.itemsPerPage,
            search: search.value,
            coach_id: coachFilter.value,
            amenity_resource_id: amenityResourceFilter.value,
            sort: options.value.sortBy?.[0]?.key ?? "",
            order: options.value.sortBy?.[0]?.order ?? "",
        },
        { preserveState: true, replace: true, only: ["classSchedules"] },
    );
};

/* ====================== Watchers ====================== */
watch(
    () => props.classSchedules,
    (val) => {
        items.value = val?.data ?? [];
        total.value = val?.total ?? 0;
        loading.value = false;
    },
    { immediate: true },
);

watch([options, search, coachFilter, amenityResourceFilter], debounce(fetchItems, 400), { deep: true });
</script>

<template>
    <Head title="Horarios de clases" />
    <AppLayout>
        <template #options>
            <BaseButton
                v-if="can.includes('classSchedules.store')"
                text="Nueva clase"
                action="add"
                :icon-only="false"
                variant="elevated"
                @click="create"
            />
        </template>
        <template #header>Registro de clases</template>

        <v-data-table-server
            :headers="headers"
            :items="items"
            :items-length="total"
            :loading="loading"
            loading-text="Cargando horarios..."
            no-data-text="No hay horarios registrados"
            v-model:options="options"
            class="elevation-1"
        >
            <template #top>
                <div class="flex-wrap mx-4 d-flex ga-2">
                    <v-autocomplete
                        v-model="coachFilter"
                        :items="props.coaches ?? []"
                        :item-title="(c: any) => `${c.first_name} ${c.last_name} ${c.second_last_name ?? ''}`.trim()"
                        item-value="id"
                        label="Maestro"
                        prepend-inner-icon="mdi-account-star-outline"
                        clearable
                        class="flex-grow-1"
                        style="min-width: 160px; max-width: 220px;"
                    />
                    <v-autocomplete
                        v-model="amenityResourceFilter"
                        :items="filteredAmenityResources"
                        :item-title="resourceName"
                        item-value="id"
                        label="Cancha"
                        prepend-inner-icon="mdi-tennis-ball"
                        clearable
                        class="flex-grow-1"
                        style="min-width: 160px; max-width: 220px;"
                    />
                </div>
                <div>
                    <v-text-field
                        v-model="search"
                        label="Buscar por clase"
                        prepend-inner-icon="mdi-magnify"
                        clearable
                        class="mx-4 mt-2"
                    />
                </div>
            </template>

            <template #item.type="{ item }">
                <v-chip
                    size="small"
                    :color="item.type === 'adults' ? 'primary' : 'orange'"
                    variant="tonal"
                >
                    {{ typeLabel(item.type) }}
                </v-chip>
            </template>

            <template #item.coach="{ item }">
                {{ coachName(item.coach) }}
            </template>

            <template #item.amenity_resource="{ item }">
                {{ resourceName(item.amenity_resource) }}
            </template>

            <template #item.day_of_week="{ item }">
                {{ dayLabel(item.day_of_week) }}
            </template>

            <template #item.schedule="{ item }">
                <strong>{{ item.start_time?.substring(0, 5) }}</strong>
                <span class="text-slate-500"> a {{ item.end_time?.substring(0, 5) }}</span>
            </template>

            <template #item.validity="{ item }">
                <span v-if="!item.start_date && !item.end_date" class="text-slate-500">Permanente</span>
                <span v-else class="text-sm">
                    {{ item.start_date ? formatDate(item.start_date) : "..." }} a {{ item.end_date ? formatDate(item.end_date) : "..." }}
                </span>
            </template>

            <template #item.is_active="{ item }">
                <v-chip size="small" :color="item.is_active ? 'success' : 'slate'" variant="tonal">
                    {{ item.is_active ? "Activa" : "Inactiva" }}
                </v-chip>
            </template>

            <template #item.actions="{ item }">
                <BaseButton
                    v-if="can.includes('classSchedules.index')"
                    action="view"
                    tooltip="Ver sesiones"
                    @click="viewSessions(item)"
                />
                <BaseButton
                    v-if="can.includes('classSchedules.update')"
                    action="edit"
                    @click="edit(item)"
                />
                <BaseButton
                    v-if="can.includes('classSchedules.destroy')"
                    action="delete"
                    @click="destroy(item)"
                />
            </template>
        </v-data-table-server>

        <v-dialog v-model="showModal" max-width="800">
            <v-form ref="formSendRef" @submit.prevent="save">
                <v-card rounded="lg">
                    <div class="flex flex-col gap-4 px-6 pt-5 pb-3 border-b border-gray-200 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="mb-1 text-xs font-bold uppercase text-slate-500">
                                Planificador
                            </p>
                            <h2 class="m-0 text-xl font-bold text-slate-900">
                                {{ form.id ? "Editar clase" : "Nueva clase" }}
                            </h2>
                        </div>
                    </div>

                    <v-card-text>
                        <div class="grid gap-4">
                            <div>
                                <div class="p-4 mb-4 border border-gray-200 rounded-lg">
                                    <span class="block mb-3 font-bold text-slate-900">Clase</span>
                                    <v-row>
                                        <v-col cols="12" md="7">
                                            <v-text-field
                                                v-model="form.name"
                                                label="Nombre de la clase"
                                                prepend-inner-icon="mdi-whistle-outline"
                                                :rules="[required]"
                                                clearable
                                            />
                                        </v-col>
                                        <v-col cols="12" md="5">
                                            <v-select
                                                v-model="form.type"
                                                :items="TYPES"
                                                item-title="label"
                                                item-value="value"
                                                label="Tipo"
                                                prepend-inner-icon="mdi-account-group-outline"
                                                :rules="[required]"
                                            />
                                        </v-col>
                                    </v-row>
                                </div>

                                <div class="p-4 mb-4 border border-gray-200 rounded-lg">
                                    <span class="block mb-3 font-bold text-slate-900">
                                        Responsables y espacio
                                    </span>
                                    <v-row>
                                        <v-col cols="12" md="6">
                                            <v-autocomplete
                                                v-model="form.coach_id"
                                                :items="props.coaches ?? []"
                                                :item-title="(c: any) => `${c.first_name} ${c.last_name} ${c.second_last_name ?? ''}`.trim()"
                                                item-value="id"
                                                label="Entrenador"
                                                prepend-inner-icon="mdi-account-star-outline"
                                                :rules="[required]"
                                                clearable
                                            />
                                        </v-col>
                                        <v-col cols="12" md="6">
                                            <v-autocomplete
                                                v-model="form.amenity_resource_id"
                                                :items="filteredAmenityResources"
                                                :item-title="resourceName"
                                                item-value="id"
                                                label="Cancha / recurso"
                                                prepend-inner-icon="mdi-tennis-ball"
                                                :rules="[required]"
                                                clearable
                                            />
                                        </v-col>
                                    </v-row>
                                </div>

                                <div class="p-4 mb-4 border border-gray-200 rounded-lg">
                                    <span class="block mb-3 font-bold text-slate-900">Vigencia</span>
                                    <v-row>
                                        <v-col cols="12" md="4">
                                            <v-text-field
                                                v-model="form.start_date"
                                                label="Desde (opcional)"
                                                type="date"
                                                prepend-inner-icon="mdi-calendar-start"
                                                clearable
                                            />
                                        </v-col>
                                        <v-col cols="12" md="4">
                                            <v-text-field
                                                v-model="form.end_date"
                                                label="Hasta (opcional)"
                                                type="date"
                                                prepend-inner-icon="mdi-calendar-end"
                                                clearable
                                            />
                                        </v-col>
                                        <v-col cols="12" md="4" class="d-flex align-center">
                                            <v-switch
                                                v-model="form.is_active"
                                                label="Activa"
                                                color="primary"
                                                hide-details
                                            />
                                        </v-col>
                                    </v-row>
                                </div>

                                <div class="p-4 mb-4 border border-gray-200 rounded-lg">
                                    <div class="mb-3">
                                        <span class="font-bold text-slate-900">Dia y horario</span>
                                    </div>
                                    <div class="grid grid-cols-4 gap-2 md:grid-cols-7">
                                        <button
                                            v-for="day in DAYS"
                                            :key="day.value"
                                            type="button"
                                            class="font-bold border rounded-lg h-11"
                                            :class="form.day_of_week === day.value
                                                ? 'border-yellow-400 bg-yellow-400 text-slate-900'
                                                : 'border-slate-300 bg-slate-50 text-slate-900'"
                                            @click="setDay(day.value)"
                                        >
                                            {{ day.label }}
                                        </button>
                                    </div>
                                    <v-row class="mt-2">
                                        <v-col cols="12" md="4">
                                            <v-text-field
                                                v-model="form.start_time"
                                                label="Inicio"
                                                type="time"
                                                prepend-inner-icon="mdi-clock-start"
                                                :rules="[required]"
                                            />
                                        </v-col>
                                        <v-col cols="12" md="4">
                                            <v-text-field
                                                v-model="form.end_time"
                                                label="Fin"
                                                type="time"
                                                prepend-inner-icon="mdi-clock-end"
                                                :rules="[required, endAfterStart, durationInRange]"
                                            />
                                        </v-col>
                                        <v-col cols="12" md="4">
                                            <v-text-field
                                                v-model.number="form.capacity"
                                                label="Cupo"
                                                type="number"
                                                min="1"
                                                prepend-inner-icon="mdi-account-multiple-outline"
                                                :rules="[required, minCapacity]"
                                            />
                                        </v-col>
                                    </v-row>
                                    <div class="flex mt-1 mb-4 justify-stretch md:justify-end">
                                        <v-btn
                                            color="primary"
                                            variant="tonal"
                                            prepend-icon="mdi-plus"
                                            type="button"
                                            :disabled="!canAddSchedule"
                                            @click="addPreviewRow"
                                        >
                                            Agregar horario
                                        </v-btn>
                                    </div>

                                    <div class="p-4 border border-dashed rounded-lg border-slate-300 bg-slate-50">
                                        <div class="flex items-center justify-between gap-3 mb-3">
                                            <span class="font-bold text-slate-900">
                                                Horarios agregados
                                            </span>
                                            <v-chip size="small" variant="tonal">
                                                {{ batchPreview.length }}
                                            </v-chip>
                                        </div>

                                        <v-alert
                                            v-if="batchPreview.length === 0"
                                            type="info"
                                            variant="tonal"
                                            density="compact"
                                        >
                                            Agrega uno o mas horarios para esta clase.
                                        </v-alert>

                                        <div v-else class="grid gap-3">
                                            <div
                                                v-for="(row, index) in batchPreview"
                                                :key="index"
                                                class="grid gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 md:grid-cols-[minmax(0,1fr)_auto_auto] md:items-center"
                                            >
                                                <div class="grid gap-1">
                                                    <strong>{{ dayLabel(row.day) }}</strong>
                                                    <span class="text-sm text-slate-500">
                                                        {{ row.start }} a {{ row.end }}
                                                    </span>
                                                </div>
                                                <v-chip size="small" variant="tonal">
                                                    Cupo {{ row.capacity }}
                                                </v-chip>
                                                <v-btn
                                                    icon="mdi-delete-outline"
                                                    variant="text"
                                                    color="error"
                                                    type="button"
                                                    @click="removePreviewRow(index)"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>

                        </div>
                    </v-card-text>

                    <v-card-actions>
                        <v-spacer />
                        <BaseButton
                            text="Cancelar"
                            :icon-only="false"
                            action="cancel"
                            variant="elevated"
                            @click="showModal = false"
                        />
                        <BaseButton
                            :text="form.id ? 'Actualizar' : 'Guardar'"
                            :icon-only="false"
                            action="save"
                            type="submit"
                            variant="elevated"
                            :loading="saving"
                        />
                    </v-card-actions>
                </v-card>
            </v-form>
        </v-dialog>

        <v-dialog v-model="showPreviewModal" max-width="720">
            <v-card rounded="lg">
                <div class="border-b border-gray-200 px-6 py-5">
                    <p class="mb-1 text-xs font-bold uppercase text-slate-500">
                        Revision antes de guardar
                    </p>
                    <h2 class="m-0 text-xl font-bold text-slate-900">
                        Confirmar clase
                    </h2>
                </div>

                <v-card-text>
                    <div class="grid gap-4">
                        <div class="rounded-lg border border-gray-200 p-4">
                            <div class="grid gap-3 md:grid-cols-2">
                                <div>
                                    <p class="mb-1 text-xs font-bold uppercase text-slate-500">
                                        Clase
                                    </p>
                                    <p class="m-0 font-bold text-slate-900">
                                        {{ form.name || "-" }}
                                    </p>
                                    <v-chip
                                        class="mt-2"
                                        size="small"
                                        :color="form.type === 'adults' ? 'primary' : 'orange'"
                                        variant="tonal"
                                    >
                                        {{ typeLabel(form.type) }}
                                    </v-chip>
                                </div>

                                <div>
                                    <p class="mb-1 text-xs font-bold uppercase text-slate-500">
                                        Entrenador
                                    </p>
                                    <p class="m-0 font-bold text-slate-900">
                                        {{ coachName(selectedCoach) }}
                                    </p>
                                    <p class="mb-0 mt-3 text-xs font-bold uppercase text-slate-500">
                                        Cancha / recurso
                                    </p>
                                    <p class="m-0 font-bold text-slate-900">
                                        {{ resourceName(selectedResource) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <span class="font-bold text-slate-900">
                                    Horarios por guardar
                                </span>
                                <v-chip size="small" variant="tonal">
                                    {{ previewSchedules.length }}
                                </v-chip>
                            </div>

                            <div class="grid gap-3">
                                <div
                                    v-for="(row, index) in previewSchedules"
                                    :key="index"
                                    class="grid gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 md:grid-cols-[minmax(0,1fr)_auto] md:items-center"
                                >
                                    <div class="grid gap-1">
                                        <strong>{{ dayLabel(row.day) }}</strong>
                                        <span class="text-sm text-slate-500">
                                            {{ row.start }} a {{ row.end }}
                                        </span>
                                    </div>
                                    <v-chip size="small" variant="tonal">
                                        Cupo {{ row.capacity }}
                                    </v-chip>
                                </div>
                            </div>
                        </div>
                    </div>
                </v-card-text>

                <v-card-actions>
                    <v-spacer />
                    <BaseButton
                        text="Volver"
                        :icon-only="false"
                        action="cancel"
                        variant="elevated"
                        @click="showPreviewModal = false"
                    />
                    <BaseButton
                        text="Confirmar guardado"
                        :icon-only="false"
                        action="save"
                        variant="elevated"
                        :loading="saving"
                        @click="confirmSave"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="showSessionsModal" max-width="900">
            <v-card rounded="lg">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-5">
                    <div>
                        <p class="mb-1 text-xs font-bold uppercase text-slate-500">
                            Calendario generado
                        </p>
                        <h2 class="m-0 text-xl font-bold text-slate-900">
                            Sesiones de {{ sessionsClassName }}
                        </h2>
                    </div>
                </div>

                <v-card-text>
                    <v-data-table
                        :headers="sessionHeaders"
                        :items="sessionsItems"
                        :loading="sessionsLoading"
                        loading-text="Cargando sesiones..."
                        no-data-text="Aun no hay sesiones generadas para esta clase"
                        items-per-page="10"
                    >
                        <template #item.date="{ item }">
                            {{ formatDate(item.date) }}
                        </template>

                        <template #item.schedule="{ item }">
                            <strong>{{ item.start_time?.substring(0, 5) }}</strong>
                            <span class="text-slate-500"> a {{ item.end_time?.substring(0, 5) }}</span>
                        </template>

                        <template #item.coach="{ item }">
                            {{ coachName(item.coach) }}
                        </template>

                        <template #item.amenity_resource="{ item }">
                            {{ resourceName(item.amenity_resource) }}
                        </template>

                        <template #item.capacity="{ item }">
                            {{ item.current_capacity }} / {{ item.capacity }}
                        </template>

                        <template #item.status="{ item }">
                            <v-chip
                                size="small"
                                :color="item.status === 'cancelled' ? 'error' : 'success'"
                                variant="tonal"
                            >
                                {{ item.status === "cancelled" ? "Cancelada" : "Programada" }}
                            </v-chip>
                            <div v-if="item.cancellation_reason" class="mt-1 text-xs text-slate-500">
                                {{ item.cancellation_reason }}
                            </div>
                        </template>
                    </v-data-table>
                </v-card-text>

                <v-card-actions>
                    <v-spacer />
                    <BaseButton
                        text="Cerrar"
                        :icon-only="false"
                        action="cancel"
                        variant="elevated"
                        @click="showSessionsModal = false"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>
    </AppLayout>
</template>
