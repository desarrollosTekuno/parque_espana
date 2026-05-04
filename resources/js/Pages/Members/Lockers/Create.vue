<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/BaseButton.vue";
import { ref, onMounted, computed, watch } from "vue";
import { router, Head, usePage } from "@inertiajs/vue3";
import { customToastSwal } from "@/utils/swal";
import axios from "axios";

const props = defineProps({
    membershipId: Number,
    accountId: Number,
    members: Array,
    clubId: Number,
});
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

const loadLockers = async () => {
    try {
        loading.value = true;
        hasSearched.value = true;
        const res = await axios.get(route("lockers.available"), {
            params: {
                club_id: props.clubId,
                category: category.value,
            },
        });
        console.log(res.data); 
        lockers.value = res.data;
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
    } catch (e) {
        console.error(e);
    }
};
const calculateCost = () => {
    const month = new Date().getMonth() + 1;
    const monthsRemaining = 12 - month + 1;
    cost.value = (1100 / 12) * monthsRemaining;
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

const assign = () => {
    if (!canAssign.value) {
        showErrors.value = true;

        customToastSwal({
            title: "Completa los campos requeridos",
            icon: "warning"
        });

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
        clubId: props.clubId,
    }, {
        onSuccess: () => {
            customToastSwal({
                title: "Casillero reservado hasta que se efectue el pago",
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
            disabled: m.locked,
            subtitle: m.locker_assignment
                ? 'Ya tiene casillero'
                : m.has_pending_locker
                ? 'Pendiente de pago'
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
            <v-alert
                v-if="myLockers.length"
                type="info"
                variant="tonal"
                class="mb-4"
            >
                Ya cuentas con los siguientes casilleros asignados:
            </v-alert>

            <v-row v-if="myLockers.length" class="mb-4">
                <v-col
                    v-for="item in myLockers"
                    :key="item.id"
                    cols="6"
                    sm="4"
                    md="3"
                    lg="2"
                >
                    <v-card class="pa-2 text-center" color="primary" variant="tonal">
                        <v-icon size="22">mdi-locker</v-icon>

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

                        <v-chip size="x-small" class="mt-2">
                            Activo
                        </v-chip>
                    </v-card>
                </v-col>
            </v-row>
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
            <v-row class="mb-5">
                <v-col
                    v-for="locker in visibleLockers"
                    :key="locker.id"
                    cols="6"
                    sm="4"
                    md="3"
                    lg="2"
                >
                    <v-card
                        class="pa-2 text-center cursor-pointer transition-all border-sm"
                        :elevation="selectedLocker === locker.id ? 8 : 2"
                        :color="selectedLocker === locker.id ? 'primary' : ''"
                        :variant="selectedLocker === locker.id ? 'flat' : 'outlined'"
                        @click="toggleLocker(locker.id)"
                    >
                        <v-icon size="22">mdi-locker</v-icon>
                        <div class="text-caption text-medium-emphasis">
                            Casillero
                        </div>

                        <div class="text-h6 font-weight-bold">
                            {{ locker.number }}
                        </div>

                        <v-icon
                            v-if="selectedLocker === locker.id"
                            icon="mdi-check-circle"
                            class="mt-2"
                        />
                    </v-card>
                </v-col>
            </v-row>
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
</style>