<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import axios from "axios";
import { computed, ref, watch } from "vue";

interface MemberItem {
    id: number;
    full_name: string;
    email?: string;
    relationship?: string;
    is_primary_holder?: boolean;
}

interface Specialty {
    id: number;
    name: string;
    code: string;
}

interface Props {
    members: MemberItem[];
    specialties: Specialty[];
    enrollments: any[];
}

const props = defineProps<Props>();
const page = usePage();
const can = page.props.auth.permissions;

/* ====================== Variables ====================== */
const selectedAppMemberId = ref<number | null>(null);
const accountData = ref<any>(null);
const reservationMembers = ref<MemberItem[]>([]);
const selectedClassSchedule = ref<any>(null);
const classSchedules = ref<any[]>([]);
const loadingAccount = ref(false);
const loadingSchedules = ref(false);
const selectedType = ref<string | null>(null);
const selectedSport = ref<string | null>(null);

const typeItems = [
    { title: "Adultos", value: "adults" },
    { title: "Ninos", value: "kids" },
];

/* ====================== useForm ====================== */
const form = useForm({
    logged_member_id: null as number | null,
    member_id: null as number | null,
    class_schedule_id: null as number | null,
    reservation_date: new Date().toISOString().substring(0, 10),
});

const cancelForm = useForm({
    logged_member_id: null as number | null,
});

/* ====================== Computed ====================== */
const selectedAppMember = computed(() =>
    props.members.find((member) => member.id === selectedAppMemberId.value) ?? null,
);

const canSave = computed(() =>
    !!form.logged_member_id &&
    !!form.member_id &&
    !!form.class_schedule_id &&
    !!form.reservation_date,
);

/* ====================== Funciones ====================== */
const fetchAccount = async () => {
    accountData.value = null;
    reservationMembers.value = [];
    selectedClassSchedule.value = null;
    form.member_id = null;
    form.class_schedule_id = null;

    if (!selectedAppMemberId.value) return;

    loadingAccount.value = true;

    try {
        const response = await axios.get(route("classReservations.account"), {
            params: { member_id: selectedAppMemberId.value },
        });

        accountData.value = response.data.data;
        reservationMembers.value = response.data.data.members ?? [];
        form.logged_member_id = selectedAppMemberId.value;
        form.member_id = reservationMembers.value[0]?.id ?? null;
    } catch (e: any) {
        customToastSwal({
            title: e.response?.data?.message ?? "No se pudo consultar la cuenta",
            icon: "error",
        });
    } finally {
        loadingAccount.value = false;
    }
};

const fetchSchedules = async () => {
    classSchedules.value = [];
    selectedClassSchedule.value = null;
    form.class_schedule_id = null;

    if (!form.reservation_date) return;

    loadingSchedules.value = true;

    try {
        const response = await axios.get(route("classReservations.schedules"), {
            params: {
                date: form.reservation_date,
                type: selectedType.value,
                sport: selectedSport.value,
            },
        });

        classSchedules.value = response.data.data ?? [];
    } catch (e: any) {
        customToastSwal({
            title: e.response?.data?.message ?? "No se pudieron consultar las clases",
            icon: "error",
        });
    } finally {
        loadingSchedules.value = false;
    }
};

const selectSchedule = (item: any) => {
    selectedClassSchedule.value = item;
    form.class_schedule_id = item.id;
};

const save = () => {
    if (!canSave.value) {
        customToastSwal({ title: "Selecciona socio, integrante, fecha y clase", icon: "warning" });
        return;
    }

    form.post(route("classReservations.store"), {
        preserveScroll: true,
        onSuccess: () => {
            customToastSwal({ title: page.props.flash.success, icon: "success" });
            fetchSchedules();
            router.reload({ only: ["enrollments"] });
        },
        onError: () => {
            const firstError = Object.values(form.errors)[0] as string;
            customToastSwal({ title: firstError ?? "Error al reservar", icon: "error" });
        },
    });
};

const cancelReservation = (item: any) => {
    if (!form.logged_member_id) {
        customToastSwal({ title: "Selecciona primero el socio de la app", icon: "warning" });
        return;
    }

    customConfirmSwal({ title: "Cancelar reservacion?" }).then((result) => {
        if (!result.isConfirmed) return;

        cancelForm.logged_member_id = form.logged_member_id;
        cancelForm.patch(route("classReservations.cancel", item.id), {
            preserveScroll: true,
            onSuccess: () => {
                customToastSwal({ title: page.props.flash.success, icon: "success" });
                fetchSchedules();
                router.reload({ only: ["enrollments"] });
            },
            onError: () => {
                const firstError = Object.values(cancelForm.errors)[0] as string;
                customToastSwal({ title: firstError ?? "Error al cancelar", icon: "error" });
            },
        });
    });
};

const scheduleSubtitle = (item: any) => {
    const resource = item.amenity_resource?.name ?? "-";
    const amenity = item.amenity_resource?.amenity?.name ?? "";

    return `${item.start_time} a ${item.end_time} | ${resource} ${amenity}`;
};

/* ====================== Watchers ====================== */
watch(selectedAppMemberId, fetchAccount);
watch([() => form.reservation_date, selectedType, selectedSport], fetchSchedules);
</script>

<template>
    <Head title="Reservar clases" />

    <AppLayout>
        <template #header>Reservar clases</template>

        <div class="grid gap-4">
            <div class="grid gap-4 md:grid-cols-3">
                <div class="p-4 border border-gray-200 rounded-lg">
                    <v-autocomplete
                        v-model="selectedAppMemberId"
                        :items="props.members"
                        item-title="full_name"
                        item-value="id"
                        label="Socio que entra a la app"
                        prepend-inner-icon="mdi-account-circle-outline"
                        :loading="loadingAccount"
                        clearable
                    />

                    <div v-if="selectedAppMember" class="text-sm text-slate-600">
                        {{ selectedAppMember.email ?? "" }}
                    </div>
                </div>

                <div class="p-4 border border-gray-200 rounded-lg md:col-span-2">
                    <div v-if="accountData" class="grid gap-2 md:grid-cols-3">
                        <div>
                            <p class="mb-1 text-xs font-bold uppercase text-slate-500">Cuenta</p>
                            <strong>{{ accountData.membership_account.membership_number }}</strong>
                        </div>
                        <div>
                            <p class="mb-1 text-xs font-bold uppercase text-slate-500">Membresia</p>
                            <strong>{{ accountData.membership.membership_type ?? "-" }}</strong>
                        </div>
                        <div>
                            <p class="mb-1 text-xs font-bold uppercase text-slate-500">Titular</p>
                            <v-chip size="small" :color="accountData.is_primary_holder ? 'success' : undefined" variant="tonal">
                                {{ accountData.is_primary_holder ? "Si" : "No" }}
                            </v-chip>
                        </div>
                    </div>
                    <v-alert v-else type="info" variant="tonal" density="compact">
                        Selecciona un socio para consultar su cuenta.
                    </v-alert>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-4">
                <v-select
                    v-model="form.member_id"
                    :items="reservationMembers"
                    item-title="full_name"
                    item-value="id"
                    label="Integrante"
                    prepend-inner-icon="mdi-account-group-outline"
                    :disabled="reservationMembers.length === 0"
                />

                <v-text-field
                    v-model="form.reservation_date"
                    label="Fecha"
                    type="date"
                    prepend-inner-icon="mdi-calendar"
                />

                <v-select
                    v-model="selectedType"
                    :items="typeItems"
                    label="Tipo"
                    prepend-inner-icon="mdi-account-group-outline"
                    clearable
                />

                <v-select
                    v-model="selectedSport"
                    :items="props.specialties"
                    item-title="name"
                    item-value="code"
                    label="Especialidad"
                    prepend-inner-icon="mdi-tag-outline"
                    clearable
                />
            </div>

            <div class="grid gap-3">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="m-0 text-lg font-bold text-slate-900">Clases disponibles</h3>
                    <BaseButton
                        v-if="can.includes('classReservations.store')"
                        text="Reservar"
                        :icon-only="false"
                        action="save"
                        variant="elevated"
                        :disabled="!canSave"
                        :loading="form.processing"
                        @click="save"
                    />
                </div>

                <v-progress-linear v-if="loadingSchedules" indeterminate />

                <v-alert
                    v-if="!loadingSchedules && classSchedules.length === 0"
                    type="info"
                    variant="tonal"
                    density="compact"
                >
                    No hay clases para la fecha seleccionada.
                </v-alert>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <button
                        v-for="item in classSchedules"
                        :key="item.id"
                        type="button"
                        class="p-4 text-left border rounded-lg"
                        :class="form.class_schedule_id === item.id ? 'border-yellow-400 bg-yellow-50' : 'border-gray-200 bg-white'"
                        @click="selectSchedule(item)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <strong class="block text-slate-900">{{ item.name }}</strong>
                                <span class="block mt-1 text-sm text-slate-600">{{ scheduleSubtitle(item) }}</span>
                                <span class="block mt-1 text-sm text-slate-600">
                                    Entrenador: {{ item.coach?.full_name ?? "-" }}
                                </span>
                            </div>
                            <v-chip size="small" variant="tonal" :color="item.available_spots > 0 ? 'success' : 'error'">
                                {{ item.available_spots }} libres
                            </v-chip>
                        </div>
                    </button>
                </div>
            </div>

            <div class="grid gap-3">
                <h3 class="m-0 text-lg font-bold text-slate-900">Reservaciones activas</h3>

                <v-table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Clase</th>
                            <th>Socio</th>
                            <th>Reservo</th>
                            <th>Horario</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in props.enrollments" :key="item.id">
                            <td>{{ item.reservation_date }}</td>
                            <td>{{ item.class_name }}</td>
                            <td>{{ item.member?.full_name }}</td>
                            <td>{{ item.reserved_by_member?.full_name }}</td>
                            <td>{{ item.start_time }} a {{ item.end_time }}</td>
                            <td class="text-right">
                                <BaseButton
                                    v-if="can.includes('classReservations.cancel')"
                                    action="delete"
                                    @click="cancelReservation(item)"
                                />
                            </td>
                        </tr>
                        <tr v-if="props.enrollments.length === 0">
                            <td colspan="6" class="text-center text-slate-500">
                                No hay reservaciones activas.
                            </td>
                        </tr>
                    </tbody>
                </v-table>
            </div>
        </div>
    </AppLayout>
</template>
