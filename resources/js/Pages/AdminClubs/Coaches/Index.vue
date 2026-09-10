<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import { email, required, validatePhone } from "@/constants/validationRules";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { computed, onMounted, onUnmounted, ref, watch } from "vue";

const page = usePage();
const can  = page.props.auth.permissions;

interface Props {
    coaches?:   any;
    amenities?: any[];
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
    availabilities:   [] as { day_of_week: number; start_time: string; end_time: string }[],
});

// ── Cuadrícula semanal de disponibilidad ────────────────────────────────────
// La cuadrícula es la fuente de verdad mientras se edita: cada celda es un slot de 30 min
// de un día concreto, así que pintar o borrar nunca puede afectar accidentalmente a otro día.
const GRID_START_HOUR = 6;   // 06:00
const GRID_END_HOUR   = 22;  // 22:00
const SLOT_MINUTES    = 30;
const TOTAL_SLOTS      = ((GRID_END_HOUR - GRID_START_HOUR) * 60) / SLOT_MINUTES;
const SLOTS            = Array.from({ length: TOTAL_SLOTS }, (_, i) => i);

const pad2 = (n: number) => n.toString().padStart(2, "0");

const slotToTime = (slot: number) => {
    const totalMinutes = GRID_START_HOUR * 60 + slot * SLOT_MINUTES;
    return `${pad2(Math.floor(totalMinutes / 60))}:${pad2(totalMinutes % 60)}`;
};

const timeToSlot = (time: string) => {
    const [h, m] = time.split(":").map(Number);
    const slot = ((h * 60 + m) - GRID_START_HOUR * 60) / SLOT_MINUTES;
    return Math.min(Math.max(Math.round(slot), 0), TOTAL_SLOTS);
};

const slotLabel = (slot: number) => (slot % 2 === 0 ? formatTime(slotToTime(slot)) : "");

const emptyGrid = () => {
    const g: Record<number, Set<number>> = {};
    DAYS.forEach((d) => { g[d.value] = new Set(); });
    return g;
};

const grid = ref<Record<number, Set<number>>>(emptyGrid());

// Convierte los horarios planos (uno por día) que vienen del backend en celdas marcadas en la cuadrícula.
const availabilitiesToGrid = (availabilities: { day_of_week: number; start_time: string; end_time: string }[]) => {
    const g = emptyGrid();
    (availabilities ?? []).forEach((a) => {
        const from = timeToSlot(a.start_time);
        const to   = timeToSlot(a.end_time);
        for (let s = from; s < to; s++) g[a.day_of_week]?.add(s);
    });
    return g;
};

// Convierte la cuadrícula pintada en horarios planos, fusionando slots consecutivos del mismo día en un solo rango.
const gridToAvailabilities = () => {
    const result: { day_of_week: number; start_time: string; end_time: string }[] = [];

    DAYS.forEach((d) => {
        const slots = [...(grid.value[d.value] ?? [])].sort((a, b) => a - b);
        let rangeStart: number | null = null;
        let prev: number | null = null;

        slots.forEach((s) => {
            if (rangeStart === null) {
                rangeStart = s;
            } else if (s !== (prev as number) + 1) {
                result.push({ day_of_week: d.value, start_time: slotToTime(rangeStart), end_time: slotToTime((prev as number) + 1) });
                rangeStart = s;
            }
            prev = s;
        });

        if (rangeStart !== null) {
            result.push({ day_of_week: d.value, start_time: slotToTime(rangeStart), end_time: slotToTime((prev as number) + 1) });
        }
    });

    return result;
};

const isSelected = (day: number, slot: number) => grid.value[day]?.has(slot) ?? false;

// Arrastre: al iniciar se decide si el gesto pinta o borra según el estado de la celda donde comenzó,
// y solo se aplica al soltar el mouse, sobre el rango final dentro de la misma columna (mismo día).
const dragState = ref<{ day: number; startSlot: number; erase: boolean } | null>(null);
const previewRange = ref<{ day: number; from: number; to: number } | null>(null);

const isPreview = (day: number, slot: number) =>
    previewRange.value !== null &&
    previewRange.value.day === day &&
    slot >= previewRange.value.from &&
    slot <= previewRange.value.to;

const startDrag = (day: number, slot: number) => {
    dragState.value    = { day, startSlot: slot, erase: isSelected(day, slot) };
    previewRange.value = { day, from: slot, to: slot };
};

const dragOver = (day: number, slot: number) => {
    if (!dragState.value || dragState.value.day !== day) return;
    previewRange.value = {
        day,
        from: Math.min(dragState.value.startSlot, slot),
        to:   Math.max(dragState.value.startSlot, slot),
    };
};

const endDrag = () => {
    if (dragState.value && previewRange.value) {
        const { day, from, to } = previewRange.value;
        const set = new Set(grid.value[day] ?? []);
        for (let s = from; s <= to; s++) {
            if (dragState.value.erase) set.delete(s); else set.add(s);
        }
        grid.value = { ...grid.value, [day]: set };
    }
    dragState.value    = null;
    previewRange.value = null;
};

onMounted(() => window.addEventListener("mouseup", endDrag));
onUnmounted(() => window.removeEventListener("mouseup", endDrag));

const clearDay = (day: number) => {
    grid.value = { ...grid.value, [day]: new Set() };
};

// Copiar horario de un día a otros: útil para replicar el mismo bloque en varios días sin pintarlo cada vez.
const copySourceDay  = ref<number | null>(null);
const copyTargetDays = ref<number[]>([]);

const applyCopy = () => {
    if (copySourceDay.value === null || copyTargetDays.value.length === 0) return;
    const source = grid.value[copySourceDay.value] ?? new Set();
    const next   = { ...grid.value };
    copyTargetDays.value.forEach((day) => { next[day] = new Set(source); });
    grid.value = next;
    copySourceDay.value  = null;
    copyTargetDays.value = [];
};

// Agrupa disponibilidades planas (una fila por día) en bloques de días consecutivos con el mismo horario,
// para mostrarlas y quitarlas como una sola unidad en vez de día por día.
const groupIntoBlocks = (availabilities: { day_of_week: number; start_time: string; end_time: string }[]) => {
    if (!availabilities?.length) return [];

    const orderIndex = (dow: number) => DAYS.findIndex((d) => d.value === dow);
    const sorted = [...availabilities].sort((a, b) =>
        orderIndex(a.day_of_week) - orderIndex(b.day_of_week) || a.start_time.localeCompare(b.start_time),
    );

    const groups: { days: number[]; start_time: string; end_time: string }[] = [];

    sorted.forEach((a) => {
        const last = groups[groups.length - 1];
        if (
            last &&
            last.start_time === a.start_time &&
            last.end_time === a.end_time &&
            orderIndex(a.day_of_week) === orderIndex(last.days[last.days.length - 1]) + 1
        ) {
            last.days.push(a.day_of_week);
        } else {
            groups.push({ days: [a.day_of_week], start_time: a.start_time, end_time: a.end_time });
        }
    });

    return groups;
};

const blockLabel = (block: { days: number[] }) => {
    const first = dayLabel(block.days[0]);
    const last  = dayLabel(block.days[block.days.length - 1]);
    return block.days.length === 1 ? first : `${first} - ${last}`;
};

const formatTime = (time: string) => {
    if (!time) return '—';

    const [hours, minutes] = time.split(':');
    const date = new Date();

    date.setHours(Number(hours), Number(minutes), 0);

    return date.toLocaleTimeString('es-MX', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
};
const create = () => {
    form.reset();
    grid.value      = emptyGrid();
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
    grid.value             = availabilitiesToGrid(item.availabilities?.map((a: any) => ({
        day_of_week: a.day_of_week,
        start_time:  a.start_time?.substring(0, 5) ?? "",
        end_time:    a.end_time?.substring(0, 5) ?? "",
    })) ?? []);
    showModal.value = true;
};

const save = async () => {
    const { valid } = await formSendRef.value?.validate();
    if (!valid) return;

    form.availabilities = gridToAvailabilities();

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
    { title: "Disponibilidad", key: "availabilities" },
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
                {{ item.first_name }} {{ item.last_name }}
            </template>

            <template #item.amenity="{ item }">
                {{ item.amenity?.name ?? "—" }}
            </template>

            <template #item.availabilities="{ item }">
                <div v-if="item.availabilities?.length" class="d-flex flex-column ga-1 align-start">
                    <v-chip
                        v-for="(block, index) in groupIntoBlocks(item.availabilities)"
                        :key="index"
                        size="small"
                        class="mr-1"
                        color="green"
                        variant="tonal"
                    >
                        {{ blockLabel(block) }}:
                        {{ formatTime(block.start_time) }} -
                        {{ formatTime(block.end_time) }}
                    </v-chip>
                </div>

                <span v-else>—</span>
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
        <v-dialog v-model="showModal" max-width="820">
            <v-form ref="formSendRef" @submit.prevent="save">
                <v-card :title="form.id ? 'Editar entrenador' : 'Nuevo entrenador'">
                    <v-card-text>
                        <v-row>
                            <v-col cols="6">
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
                            <v-col cols="6">
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
                                <div class="text-subtitle-2 mb-2">Horarios de disponibilidad</div>
                                <div class="text-caption text-medium-emphasis mb-2">
                                    Da clic y arrastra sobre la cuadrícula para pintar los horarios disponibles de cada día.
                                    Vuelve a hacer clic sobre un horario ya pintado para borrarlo.
                                </div>

                                <div class="availability-grid" @mouseleave="endDrag">
                                    <div class="grid-row grid-header">
                                        <div class="time-col" />
                                        <div v-for="d in DAYS" :key="d.value" class="day-col day-col-header">
                                            <span>{{ d.label }}</span>
                                            <div class="day-col-actions">
                                                <v-btn
                                                    icon="mdi-content-copy"
                                                    size="x-small"
                                                    variant="text"
                                                    density="compact"
                                                    type="button"
                                                    title="Copiar horario a otros días"
                                                    @click="copySourceDay = d.value"
                                                />
                                                <v-btn
                                                    icon="mdi-eraser"
                                                    size="x-small"
                                                    variant="text"
                                                    density="compact"
                                                    type="button"
                                                    title="Borrar horario del día"
                                                    @click="clearDay(d.value)"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div v-for="slot in SLOTS" :key="slot" class="grid-row">
                                        <div class="time-col">{{ slotLabel(slot) }}</div>
                                        <div
                                            v-for="d in DAYS"
                                            :key="d.value"
                                            class="day-col grid-cell"
                                            :class="{
                                                selected: isSelected(d.value, slot),
                                                preview:  isPreview(d.value, slot),
                                                'hour-start': slot % 2 === 0,
                                            }"
                                            @mousedown="startDrag(d.value, slot)"
                                            @mouseenter="dragOver(d.value, slot)"
                                        />
                                    </div>
                                </div>

                                <!-- Copiar horario de un día a otros -->
                                <v-alert
                                    v-if="copySourceDay !== null"
                                    type="info"
                                    variant="tonal"
                                    density="compact"
                                    class="mt-3"
                                >
                                    <div class="d-flex flex-wrap align-center ga-2">
                                        <span>Copiar horario de <strong>{{ dayLabel(copySourceDay) }}</strong> a:</span>
                                        <v-chip-group v-model="copyTargetDays" multiple column>
                                            <v-chip
                                                v-for="d in DAYS.filter((x) => x.value !== copySourceDay)"
                                                :key="d.value"
                                                :value="d.value"
                                                size="small"
                                                filter
                                                variant="outlined"
                                            >
                                                {{ d.label }}
                                            </v-chip>
                                        </v-chip-group>
                                        <v-spacer />
                                        <v-btn size="small" variant="text" type="button" @click="copySourceDay = null; copyTargetDays = []">
                                            Cancelar
                                        </v-btn>
                                        <v-btn size="small" color="primary" variant="tonal" type="button" :disabled="!copyTargetDays.length" @click="applyCopy">
                                            Aplicar
                                        </v-btn>
                                    </div>
                                </v-alert>
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

<style scoped>
.availability-grid {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 4px;
    overflow-x: auto;
    user-select: none;
}

.grid-row {
    display: grid;
    grid-template-columns: 56px repeat(7, minmax(48px, 1fr));
}

.grid-header {
    position: sticky;
    top: 0;
    z-index: 1;
    background: rgb(var(--v-theme-surface));
    font-size: 0.75rem;
    font-weight: 600;
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.time-col {
    display: flex;
    align-items: flex-start;
    justify-content: flex-end;
    padding: 0 6px;
    font-size: 0.7rem;
    color: rgba(var(--v-theme-on-surface), 0.6);
    white-space: nowrap;
}

.day-col-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4px 0;
    text-align: center;
}

.day-col-actions {
    display: flex;
}

.grid-cell {
    height: 16px;
    border-top: 1px solid rgba(var(--v-border-color), 0.08);
    border-left: 1px solid rgba(var(--v-border-color), 0.08);
    cursor: pointer;
    background: transparent;
}

.grid-cell.hour-start {
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.grid-cell.selected {
    background: rgb(var(--v-theme-primary));
    opacity: 0.7;
}

.grid-cell.preview {
    background: rgb(var(--v-theme-primary));
    opacity: 0.35;
}
</style>
