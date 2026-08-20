<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import { email, required, validatePhone } from "@/constants/validationRules";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { computed, ref, watch } from "vue";

const page = usePage();
const can  = page.props.auth.permissions;

interface Props {
    coaches?:     any;
    specialties?: any[];
    amenities?:   any[];
}

const props = defineProps<Props>();
const showModal   = ref(false);
const formSendRef = ref();
const saving      = ref(false);

const DAYS = [
    { label: "Lun", fullLabel: "Lunes", value: 1 },
    { label: "Mar", fullLabel: "Martes", value: 2 },
    { label: "Mié", fullLabel: "Miércoles", value: 3 },
    { label: "Jue", fullLabel: "Jueves", value: 4 },
    { label: "Vie", fullLabel: "Viernes", value: 5 },
    { label: "Sáb", fullLabel: "Sábado", value: 6 },
    { label: "Dom", fullLabel: "Domingo", value: 0 },
];

const dayLabel = (d: number) => DAYS.find((x) => x.value === d)?.fullLabel ?? "-";

const form = useForm({
    id:               null as number | null,
    first_name:       "",
    last_name:        "",
    second_last_name: "",
    phone:            "",
    email:            "",
    amenity_id:       null as number | null,
    specialties:      [] as number[],
    availabilities:   [] as { day_of_week: number; start_time: string; end_time: string }[],
});

const newAvailability = ref({
    day_of_week: null as number | null,
    start_time:  "",
    end_time:    "",
});

const canAddAvailability = computed(() =>
    newAvailability.value.day_of_week !== null &&
    newAvailability.value.start_time !== "" &&
    newAvailability.value.end_time !== "" &&
    newAvailability.value.end_time > newAvailability.value.start_time,
);

// Dos horarios "chocan" si son el mismo día y sus rangos se traslapan (incluye horarios idénticos).
const overlaps = (
    a: { day_of_week: number; start_time: string; end_time: string },
    b: { day_of_week: number; start_time: string; end_time: string },
) => a.day_of_week === b.day_of_week && a.start_time < b.end_time && b.start_time < a.end_time;

const addAvailability = () => {
    if (!canAddAvailability.value) return;

    const candidate = {
        day_of_week: newAvailability.value.day_of_week as number,
        start_time:  newAvailability.value.start_time,
        end_time:    newAvailability.value.end_time,
    };

    if (form.availabilities.some((a) => overlaps(a, candidate))) {
        customToastSwal({ title: "Ese horario ya existe o se traslapa con otro agregado", icon: "warning" });
        return;
    }

    form.availabilities.push(candidate);
    newAvailability.value = { day_of_week: null, start_time: "", end_time: "" };
};

const removeAvailability = (index: number) => {
    form.availabilities.splice(index, 1);
};

const create = () => {
    form.reset();
    newAvailability.value = { day_of_week: null, start_time: "", end_time: "" };
    showModal.value = true;
};

const edit = (item: any) => {
    form.reset();
    form.id               = item.id;
    form.first_name       = item.first_name;
    form.last_name        = item.last_name;
    form.second_last_name = item.second_last_name ?? "";
    form.phone            = item.phone ?? "";
    form.email            = item.email ?? "";
    form.amenity_id       = item.amenity?.id ?? null;
    form.specialties      = item.specialties?.map((s: any) => s.id) ?? [];
    form.availabilities   = item.availabilities?.map((a: any) => ({
        day_of_week: a.day_of_week,
        start_time:  a.start_time?.substring(0, 5) ?? "",
        end_time:    a.end_time?.substring(0, 5) ?? "",
    })) ?? [];
    newAvailability.value  = { day_of_week: null, start_time: "", end_time: "" };
    showModal.value       = true;
};

const save = async () => {
    const { valid } = await formSendRef.value?.validate();
    if (!valid) return;

    // Si quedó un horario capturado pero no se dio clic en "Agregar", lo incluimos igual.
    if (canAddAvailability.value) {
        addAvailability();
    }

    if (saving.value) return;
    saving.value = true;

    const callbacks = {
        onSuccess: () => {
            customToastSwal({ title: page.props.flash.success, icon: "success" });
            showModal.value = false;
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
        form.put(route("coaches.update", form.id), callbacks);
        return;
    }

    form.post(route("coaches.store"), callbacks);
};

const destroy = (item: any) => {
    customConfirmSwal({ title: "¿Eliminar entrenador?" }).then((r) => {
        if (!r.isConfirmed) return;
        router.delete(route("coaches.destroy", item.id), {
            onSuccess: () => {
                customToastSwal({ title: page.props.flash.success, icon: "success" });
                fetchItems();
            },
        });
    });
};

// ── Tabla ─────────────────────────────────────────────────────────────────────
const headers = [
    { title: "Nombre",         key: "full_name" },
    { title: "Amenidad",       key: "amenity" },
    { title: "Especialidades", key: "specialties" },
    { title: "Teléfono",       key: "phone" },
    { title: "Acciones",       key: "actions", sortable: false },
];

const items   = ref<any[]>([]);
const total   = ref(0);
const loading = ref(false);
const search  = ref("");
const options = ref({
    page:         1,
    itemsPerPage: 10,
    sortBy:       [{ key: "first_name", order: "asc" }],
});

const fetchItems = () => {
    loading.value = true;
    router.get(
        route("coaches.index"),
        { page: options.value.page, per_page: options.value.itemsPerPage, search: search.value },
        { preserveState: true, replace: true, only: ["coaches"] }
    );
};

watch(
    () => props.coaches,
    (val) => {
        items.value   = val?.data  ?? [];
        total.value   = val?.total ?? 0;
        loading.value = false;
    },
    { immediate: true }
);

watch([options, search], debounce(fetchItems, 400), { deep: true });
</script>

<template>
    <Head title="Entrenadores" />
    <AppLayout>
        <template #options>
            <BaseButton
                v-if="can.includes('coaches.store')"
                text="Nuevo entrenador"
                action="add"
                :icon-only="false"
                variant="elevated"
                @click="create"
            />
        </template>
        <template #header>Entrenadores</template>

        <v-data-table-server
            :headers="headers"
            :items="items"
            :items-length="total"
            :loading="loading"
            loading-text="Cargando entrenadores..."
            no-data-text="No hay entrenadores registrados"
            v-model:options="options"
            class="elevation-1"
        >
            <template #top>
                <v-text-field
                    v-model="search"
                    label="Buscar entrenador"
                    prepend-inner-icon="mdi-magnify"
                    clearable
                    hide-details
                    class="ma-2"
                />
            </template>

            <template #item.full_name="{ item }">
                {{ item.first_name }} {{ item.last_name }} {{ item.second_last_name }}
            </template>

            <template #item.amenity="{ item }">
                {{ item.amenity?.name ?? "—" }}
            </template>

            <template #item.specialties="{ item }">
                <v-chip
                    v-for="s in item.specialties"
                    :key="s.id"
                    size="small"
                    class="mr-1"
                    color="primary"
                    variant="tonal"
                >
                    {{ s.name }}
                </v-chip>
                <span v-if="!item.specialties?.length">—</span>
            </template>

            <template #item.actions="{ item }">
                <BaseButton
                    v-if="can.includes('coaches.update')"
                    action="edit"
                    @click="edit(item)"
                />
                <BaseButton
                    v-if="can.includes('coaches.destroy')"
                    action="delete"
                    @click="destroy(item)"
                />
            </template>
        </v-data-table-server>

        <!-- Modal crear / editar -->
        <v-dialog v-model="showModal" max-width="500">
            <v-form ref="formSendRef" @submit.prevent="save">
                <v-card :title="form.id ? 'Editar entrenador' : 'Nuevo entrenador'">
                    <v-card-text>
                        <v-row>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="form.first_name"
                                    label="Nombre(s)"
                                    prepend-inner-icon="mdi-account-star-outline"
                                    :rules="[required]"
                                    clearable
                                />
                            </v-col>
                            <v-col cols="6">
                                <v-text-field
                                    v-model="form.last_name"
                                    label="Apellido paterno"
                                    prepend-inner-icon="mdi-account-outline"
                                    :rules="[required]"
                                    clearable
                                />
                            </v-col>
                            <v-col cols="6">
                                <v-text-field
                                    v-model="form.second_last_name"
                                    label="Apellido materno"
                                    prepend-inner-icon="mdi-account-outline"
                                    clearable
                                />
                            </v-col>
                            <v-col cols="6">
                                <v-text-field
                                    v-model="form.phone"
                                    label="Teléfono"
                                    prepend-inner-icon="mdi-phone-outline"
                                    :rules="[validatePhone]"
                                    clearable
                                />
                            </v-col>
                            <v-col cols="6">
                                <v-text-field
                                    v-model="form.email"
                                    label="Correo electrónico"
                                    prepend-inner-icon="mdi-email-outline"
                                    :rules="[email]"
                                    clearable
                                />
                            </v-col>
                            <v-col cols="12">
                                <v-select
                                    v-model="form.amenity_id"
                                    :items="amenities ?? []"
                                    item-title="name"
                                    item-value="id"
                                    label="Amenidad"
                                    prepend-inner-icon="mdi-basketball"
                                    :rules="[required]"
                                    clearable
                                />
                            </v-col>

                            <v-col cols="12">
                                <div class="text-subtitle-2 mb-2">Especialidades</div>
                                <v-checkbox
                                    v-for="s in (specialties ?? [])"
                                    :key="s.id"
                                    v-model="form.specialties"
                                    :label="s.name"
                                    :value="s.id"
                                    density="compact"
                                    hide-details
                                />
                                <div
                                    v-if="form.specialties.length === 0"
                                    class="text-caption text-error mt-1"
                                >
                                    Selecciona al menos una especialidad
                                </div>
                            </v-col>

                            <v-col cols="12">
                                <div class="text-subtitle-2 mb-2">Horarios de disponibilidad</div>
                                <v-row dense>
                                    <v-col cols="12" sm="4">
                                        <v-select
                                            v-model="newAvailability.day_of_week"
                                            :items="DAYS"
                                            item-title="fullLabel"
                                            item-value="value"
                                            label="Día"
                                            density="compact"
                                            hide-details
                                        />
                                    </v-col>
                                    <v-col cols="6" sm="4">
                                        <v-text-field
                                            v-model="newAvailability.start_time"
                                            label="Inicio"
                                            type="time"
                                            density="compact"
                                            hide-details
                                        />
                                    </v-col>
                                    <v-col cols="6" sm="4">
                                        <v-text-field
                                            v-model="newAvailability.end_time"
                                            label="Fin"
                                            type="time"
                                            density="compact"
                                            hide-details
                                        />
                                    </v-col>
                                </v-row>
                                <div class="d-flex justify-end mt-2">
                                    <v-btn
                                        color="primary"
                                        variant="tonal"
                                        prepend-icon="mdi-plus"
                                        type="button"
                                        :disabled="!canAddAvailability"
                                        @click="addAvailability"
                                    >
                                        Agregar horario
                                    </v-btn>
                                </div>

                                <div v-if="form.availabilities.length === 0" class="text-caption text-medium-emphasis mt-2">
                                    Sin horarios agregados. Completa día, inicio y fin, y da clic en "Agregar horario".
                                </div>
                                <v-chip
                                    v-for="(a, index) in form.availabilities"
                                    :key="index"
                                    size="small"
                                    class="mr-1 mt-2"
                                    color="primary"
                                    variant="tonal"
                                    closable
                                    @click:close="removeAvailability(index)"
                                >
                                    {{ dayLabel(a.day_of_week) }} {{ a.start_time }} - {{ a.end_time }}
                                </v-chip>
                            </v-col>
                        </v-row>
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
    </AppLayout>
</template>
