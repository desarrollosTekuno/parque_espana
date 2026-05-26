<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/BaseButton.vue";
import { ref, onMounted, computed, watch } from "vue";
import { router, Head, usePage } from "@inertiajs/vue3";
import { customToastSwal } from "@/utils/swal";
import Swal from "sweetalert2";
import axios from "axios";

const props = defineProps({
    membershipId: Number,
    accountId: Number,
    members: Array,
    clubId: Number,
    anualCost: Number,
});
console.log(props);
const page = usePage();
const selectedMember = ref(null);
const lockers = ref([]);
const selectedLocker = ref(null);
const loading = ref(false);
const cost = ref(0);
const category = ref(null);
const hasSearched = ref(false); 
const showErrors = ref(false);
const myLockers = ref([]);
const search = ref('');
const total = ref(0);
const currentPage  = ref(1);
const perPage = ref(30);
const totalPages = ref(1);
const options = ref({
    page: 1,
    itemsPerPage: 30,
    sortBy: [
        {
            key: 'number',
            order: 'asc'
        }
    ]
});
const loadLockers = async () => {
    if (!category.value) {
        lockers.value = [];
        return;
    }
    try {
        loading.value = true;
        hasSearched.value = true;

        const res = await axios.get(route("lockers.available"), {
            params: {
                club_id: props.clubId,
                category: category.value,
                lockers_search: search.value,
                page: currentPage.value,
                lockers_per_page: perPage.value,
            },
        });

        lockers.value = res.data.data;

        total.value = res.data.total;
        totalPages.value = res.data.last_page;

    } catch (e) {

        console.error(e);

    } finally {

        loading.value = false;
    }
};
const loadMyLockers = async () => {
    try {
        const res = await axios.get(route('lockers.assigned.by.account'), {
            params: {
                account_id: props.accountId
            }
        });

        myLockers.value = res.data;
        console.log(myLockers.value);
    } catch (e) {
        console.error(e);
    }
};
const calculateCost = () => {
    const month = new Date().getMonth() + 1;
    const monthsRemaining = 12 - month + 1;
    cost.value = (props.anualCost / 12) * monthsRemaining;
};

onMounted(() => {
    selectedMember.value = null;
    calculateCost();
    loadMyLockers(); 
});

const resetForm = () => {
    selectedMember.value = null;
    selectedLocker.value = null;
    category.value = null;
    lockers.value = [];
    hasSearched.value = false;
    showErrors.value = false;
};

const assign = async () => {
    if (!canAssign.value) {
        showErrors.value = true;

        customToastSwal({
            title: "Completa los campos requeridos",
            icon: "warning"
        });

        return;
    }

    const result = await Swal.fire({
        title: '¿Reservar casillero?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, reservar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true
    });

    if (!result.isConfirmed) {
        return;
    }

    const memberId =
        typeof selectedMember.value === "object"
            ? selectedMember.value.value
            : selectedMember.value;

    router.post(route("members.lockers.reserve"), {
        locker_id: selectedLocker.value,
        member_id: memberId,
        membership_id: props.membershipId,
        account_id: props.accountId,
        club_id: props.clubId,
    }, {
        onSuccess: () => {
            customToastSwal({
                title: "Casillero asignado correctamente",
                icon: "success"
            });
            resetForm();
            loadMyLockers(); 
        },
        onError: () => {
            customToastSwal({
                title: "Error al reservar casillero",
                icon: "error"
            });
        }
    });
};
const memberOptions = computed(() =>
    props.members.map((m: any) => ({
        title: `${m.first_name} ${m.last_name} ${m.second_last_name}`,
        value: m.id,
        props: {
            disabled: props.clubId !== 2 && !!m.locker_assignment,
            subtitle: m.locker_assignment
                ? (props.clubId === 2
                    ? 'Puede tener más casilleros'
                    : 'Ya tiene casillero')
                : null
        }
    }))
);
const canAssign = computed(() => {
    return selectedMember.value && selectedLocker.value && category.value;
});
const toggleLocker = (id: number) => {
    selectedLocker.value = selectedLocker.value === id ? null : id;
};
const visibleLockers = computed(() => {
    if (!selectedLocker.value) {
        return lockers.value; 
    }
    return lockers.value.filter(l => l.id === selectedLocker.value);
});
watch(category, () => {
    selectedLocker.value = null;
    lockers.value = [];

    if (category.value) {
        loadLockers();
    }
});
watch(
    () => page.props.errors,
    (errors: any) => {
        if (errors?.member_id) {
            customToastSwal({
                title: errors.member_id,
                icon: "error"
            });
        }
    }
);
/*const pendingLockers = computed(() => {
    return myLockers.value.filter(
        (x: any) => x.status === 'pago_pendiente'
    );
});

const activeLockers = computed(() => {
    return myLockers.value.filter(
        (x: any) => x.status !== 'pago_pendiente'
    );
});*/

// Paginación y búsqueda
watch(search, () => {
    currentPage.value = 1;
    loadLockers();

});
watch(currentPage, () => {
    loadLockers();
});
watch(perPage, () => {
    currentPage.value = 1;
    loadLockers();

});
/*const cancelRequest = async (id: number) => {
    const result = await Swal.fire({
        title: '¿Cancelar solicitud?',
        text: 'El casillero volverá a estar disponible.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'No',
        reverseButtons: true
    });
    if (!result.isConfirmed) {
        return;
    }
    router.delete(route('members.lockers.cancel', id), {
        onSuccess: () => {
            customToastSwal({
                title: 'Solicitud cancelada',
                icon: 'success'
            });

            loadMyLockers();
            if (category.value) {
                loadLockers();
            }
        },
        onError: () => {

            customToastSwal({
                title: 'No se pudo cancelar',
                icon: 'error'
            });
        }
    });
};
const removeLocker = async (id: number) => {
    const result = await Swal.fire({
        title: '¿Dar de baja el casillero?',
        text: 'El casillero volverá a estar disponible.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, dar de baja',
        cancelButtonText: 'Cancelar',
        reverseButtons: true
    });
    if (!result.isConfirmed) {
        return;
    }
    router.delete(route('members.lockers.remove', { assignment: id }), {
        onSuccess: () => {
            customToastSwal({
                title: 'Casillero dado de baja',
                icon: 'success'
            });

            loadMyLockers();
            loadLockers();
        },
        onError: () => {
            customToastSwal({
                title: 'No se pudo dar de baja',
                icon: 'error'
            });
        }
    });
};*/
</script>

<template>
  <Head title="Casilleros" />
    <AppLayout>
        <template #header>
            <div>
                <h2 class="text-h5 font-weight-bold d-flex align-center gap-2">
                    <v-icon size="22">mdi-locker</v-icon>
                    Casilleros
                </h2>
                <span class="text-caption text-medium-emphasis">
                    Asignación de casilleros
                </span>
            </div>
        </template>
        <template #options>
            <BaseButton
                text="Volver"
                action="cancel"
                :icon-only="false"
                icon="mdi-chevron-left"
                @click="router.visit(route('members.manage.show', props.membershipId))"
            />
        </template>

        <div class="p-6 bg-white rounded shadow">
            <!--<v-row
                v-if="pendingLockers.length"
                class="mb-6"
            >
                <v-col cols="12">

                    <div class="text-subtitle-1 font-weight-bold mb-3">
                        Solicitudes pendientes
                    </div>

                </v-col>

                <v-col
                    v-for="item in pendingLockers"
                    :key="item.id"
                    cols="6"
                    sm="4"
                    md="3"
                    lg="2"
                >
                    <v-card
                        class="pa-2 text-center"
                        color="orange"
                        variant="tonal"
                    >
                    <div class="d-flex justify-end">
                        <v-tooltip text="Cancelar solicitud">
                                <template #activator="{ props }">

                                    <v-btn
                                        v-bind="props"
                                        icon="mdi-close"
                                        size="x-small"
                                        variant="text"
                                        color="error"
                                        @click.stop="cancelRequest(item.id)"
                                    />

                                </template>
                            </v-tooltip>
                        </div>
                        <div class="text-caption">
                            Casillero
                        </div>

                        <div class="text-h6 font-weight-bold">
                            {{ item.locker.number }}
                        </div>

                        <div class="text-caption mt-1">
                            👤 {{ item.member.first_name }}
                        </div>

                        <div class="text-caption text-medium-emphasis">
                            {{ item.member.last_name }}
                        </div>

                        <v-chip
                            size="x-small"
                            color="orange"
                            class="mt-2 mb-3"
                        >
                            Pendiente
                        </v-chip>
                    </v-card>
                </v-col>
            </v-row>-->
            <v-alert
                type="info"
                variant="tonal"
                class="mb-4"
            >
                Selecciona un integrante y una categoría para ver los casilleros disponibles.
            </v-alert>
            <!-- Selección miembro -->
            <v-select
                v-model="selectedMember"
                :items="memberOptions" 
                item-title="title"
                item-value="value"
                label="Selecciona integrante"
                :return-object="false"
                :error="!selectedMember && showErrors"
                :error-messages="!selectedMember && showErrors ? 'Selecciona un integrante' : ''"
            />
            <v-select
                v-model="category"
                :items="[
                    { title: 'Niños', value: 'ninos' },
                    { title: 'Niñas', value: 'ninas' },
                    { title: 'Caballeros', value: 'caballeros' },
                    { title: 'Damas', value: 'damas' }
                ]"
                label="Categoría"
                class="mt-3"
                :error="!category && showErrors"
                :error-messages="!category && showErrors ? 'Selecciona una categoría' : ''"
            />
            <v-progress-linear
                v-if="loading"
                indeterminate
                color="primary"
                class="mb-4"
            />
            <v-alert v-if="hasSearched && !loading && lockers.length === 0" type="info" variant="tonal">
                No hay casilleros disponibles.
            </v-alert>
            <v-row v-if="lockers.length > 0">
                <v-col cols="6" class="d-flex align-center">
                    <v-text-field
                    v-model="search"
                    label="Buscar casillero..."
                    prepend-inner-icon="mdi-magnify"
                    variant="outlined"
                    density="comfortable"
                    class="mb-4"
                />
                </v-col>
                <v-col cols="6" class="d-flex justify-end align-center">
                    <v-chip variant="outlined" size="small">
                        Total: {{ totalPages }} {{ totalPages === 1 ? 'página' : 'páginas' }}
                    </v-chip>
                </v-col>
            </v-row>
               <div class="locker-grid mb-5">
                    <v-card
                        v-for="locker in visibleLockers"
                        :key="locker.id"
                        class="text-center cursor-pointer transition-all border-sm locker-card"
                        :elevation="selectedLocker === locker.id ? 8 : 2"
                        :color="selectedLocker === locker.id ? 'primary' : ''"
                        :variant="selectedLocker === locker.id ? 'flat' : 'outlined'"
                        @click="toggleLocker(locker.id)"
                    >
                        <div class="text-h6 font-weight-bold">
                            {{ locker.number }}
                        </div>

                        <v-icon
                            v-if="selectedLocker === locker.id"
                            icon="mdi-check-circle"
                            class="mt-2"
                        />
                    </v-card>
                </div>
                <div class="d-flex align-center justify-space-between px-4 py-3 border-t" style="background:#fff;"
>
                    <div class="text-caption text-medium-emphasis">
                        Mostrando
                        {{ ((currentPage - 1) * perPage) + 1 }}
                        -
                        {{
                            Math.min(
                                currentPage * perPage,
                                total
                            )
                        }}
                        de {{ total }} casilleros
                    </div>
                    <v-pagination
                        v-model="currentPage"
                        :length="totalPages"
                        density="comfortable"
                        rounded="circle"
                        :total-visible="7"
                    />

                </div>
            <v-row v-if="selectedLocker">
                <v-col cols="12">
                    <v-alert type="success" variant="tonal">
                        Casillero seleccionado. Puedes deseleccionarlo dando click nuevamente.
                    </v-alert>
                </v-col>
            </v-row>
            <!-- Costo -->
            <div class="mt-4">
                <strong>Costo:</strong> ${{ cost.toFixed(2) }}
            </div>

            <!-- Mensaje -->
            <v-alert type="info" class="mt-4 mb-5">
                El casillero se entrega sin candado. Recuerda al miembro llevar el suyo.
            </v-alert>

            <!-- Acción -->
            <BaseButton
                class="mt-5"
                :text="'Asignar casillero'"
                :disabled="!canAssign"
                :loading="loading"
                :icon-only="false"
                action="edit"
                icon="mdi-locker"
                variant="tonal"
                @click="assign"
            />
        </div>
    </AppLayout>
</template>
<style scoped>
.cursor-pointer {
    cursor: pointer;
}
.transition-all {
    transition: all 0.2s ease;
}
.locker-grid {
    display: grid;
    grid-template-columns: repeat(10, 1fr);
    gap: 12px;
}
.locker-card {
    padding: 30px;
    border: 2px solid transparent;
}
@media (max-width: 1400px) {
    .locker-grid {
        grid-template-columns: repeat(8, 1fr);
    }
}

@media (max-width: 1100px) {
    .locker-grid {
        grid-template-columns: repeat(6, 1fr);
    }
}

@media (max-width: 768px) {
    .locker-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 500px) {
    .locker-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>