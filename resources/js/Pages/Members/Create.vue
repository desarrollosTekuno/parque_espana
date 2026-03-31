<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import CustomFileUploadField from "@/Components/CustomFileUploadField.vue";
import {
    required,
    fileTypeRule,
    fileMaxCountRule,
    requiredFileRule
} from "@/constants/validationRules";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { computed, ref, watch } from "vue";
const can = usePage().props.auth.permissions;
const canRole = usePage().props.auth.roles;
const page = usePage<any>();
interface Props {
    membershipTypes?: any;
    relationships?: any;
}
interface Relationship {
    id: number;
    name: string;
}

interface DocumentType {
    id: number;
    name: string;
    allowed_extensions: string;
    pivot: {
        membership_type_id: number;
        document_type_id: number;
        is_required: boolean;
        allow_multiple: boolean;
        number_files: number;
    };
    relationships: Relationship[];
}

interface MembershipType {
    id: number;
    name: string;
    description: string;
    allows_multiple_members: boolean;
    document_types: DocumentType[];
}
interface MemberForm {
    local_id: number;
    first_name: string;
    last_name: string;
    second_last_name: string | null;
    birthdate: string | null;
    nationality: string | null;
    marital_status: string | null;
    phone: string | null;
    email: string | null;
    occupation: string | null;

    relationship_id: number | null;
    relationship_name: string | null;
    is_primary_holder: boolean;

    documents: MemberDocumentForm[];
}

interface MemberDocumentForm {
    document_type_id: number;
    name: string;
    allowed_extensions: string[];
    is_required: boolean;
    allow_multiple: boolean;
    number_files: number;
    files: File[];
}

interface Memberships {
    id: number | null;
    membershipType: MembershipType | null;
    members: MemberForm[];
}

// const props = defineProps<Props>();
const props = withDefaults(defineProps<Props>(), {
    membershipTypes: null,
    relationships: null,
});

/* refs */
let showModal = ref(false);
const formSendRef = ref();

/* forms */
const form = useForm<Memberships>({
    id: null,
    membershipType: null,
    members: [],
});

// al seleccionar un tipo de membresía, se crea un miembro vacío con la relación correspondiente (Titular, Cónyuge, Hijo, etc) y se agrega al formulario
const createEmptyMember = (
    relationshipId: number | null,
    relationshipName: string | null,
    isPrimaryHolder = false,
): MemberForm => ({
    local_id: Date.now() + Math.floor(Math.random() * 1000),
    first_name: "",
    last_name: "",
    second_last_name: "",
    birthdate: null,
    nationality: "",
    marital_status: "",
    phone: "",
    email: "",
    occupation: "",
    relationship_id: relationshipId,
    relationship_name: relationshipName,
    is_primary_holder: isPrimaryHolder,
    documents: [],
});

const TITULAR_RELATIONSHIP_ID = 1;

const getRelationshipIdForDocuments = (member: MemberForm) => {
    if (member.is_primary_holder) {
        return TITULAR_RELATIONSHIP_ID;
    }

    return member.relationship_id;
};
const getDocumentsForMember = (member: MemberForm) => {
    if (!form.membershipType) return [];

    const relationshipId = getRelationshipIdForDocuments(member);

    return form.membershipType.document_types.filter((doc) =>
        doc.relationships.some((rel) => rel.id === relationshipId),
    );
};
const buildMemberDocuments = (member: MemberForm) => {
    return getDocumentsForMember(member).map((doc) => ({
        document_type_id: doc.id,
        name: doc.name,
        allowed_extensions: doc.allowed_extensions
            ? doc.allowed_extensions
                  .split(",")
                  .map((ext) => ext.trim().toLowerCase())
            : [],
        is_required: doc.pivot.is_required,
        allow_multiple: doc.pivot.allow_multiple,
        number_files: doc.pivot.number_files,
        files: [],
    }));
};

const buildDocumentsForRelationship = (relationshipId: number) => {
    if (!form.membershipType) return [];

    return form.membershipType.document_types
        .filter((doc) =>
            doc.relationships.some((rel) => rel.id === relationshipId),
        )
        .map((doc) => ({
            document_type_id: doc.id,
            name: doc.name,
            allowed_extensions: doc.allowed_extensions
                ? doc.allowed_extensions
                      .split(",")
                      .map((ext) => ext.trim().toLowerCase())
                : [],
            is_required: doc.pivot.is_required,
            allow_multiple: doc.pivot.allow_multiple,
            number_files: doc.pivot.number_files,
            files: [],
        }));
};
const createPrimaryHolder = (): MemberForm => {
    const member: MemberForm = {
        local_id: Date.now(),
        first_name: "",
        last_name: "",
        second_last_name: "",
        birthdate: null,
        nationality: "",
        marital_status: "",
        phone: "",
        email: "",
        occupation: "",
        relationship_id: null,
        relationship_name: null,
        is_primary_holder: true,
        documents: [],
    };

    member.documents = buildMemberDocuments(member);

    return member;
};
const addFamilyMember = () => {
    form.members.push({
        local_id: Date.now() + Math.floor(Math.random() * 1000),
        first_name: "",
        last_name: "",
        second_last_name: "",
        birthdate: null,
        nationality: "",
        marital_status: "",
        phone: "",
        email: "",
        occupation: "",
        relationship_id: null,
        relationship_name: null,
        is_primary_holder: false,
        documents: [],
    });
};

const onRelationshipChange = (member: MemberForm) => {
    if (!member.relationship_id) {
        member.documents = [];
        return;
    }

    const relationship = props.relationships.find(
        (rel) => rel.id === member.relationship_id,
    );

    member.relationship_name = relationship?.name ?? null;
    member.documents = buildDocumentsForRelationship(member.relationship_id);
};

const removeMember = (localId: number) => {
    form.members = form.members.filter((member) => member.local_id !== localId);
};

// documents

const create = () => {
    showModal.value = true;
};
const save = () => {
    formSendRef.value?.validate().then(({ valid: isValid }) => {
        console.log(isValid);
        if (!isValid) {
            return;
        } else {
            if (form.id) {
                // form.put(route("head-quarters.update", form.id), {
                //     onSuccess: () => {
                //         customToastSwal({
                //             title: page.props.flash.success || "",
                //             icon: "success",
                //         });
                //         showModal.value = false;
                //         form.reset();
                // fetchItems();
                //     },
                //     onError: () => {
                //         customToastSwal({
                //             title: `Error: ${form.errors.messageError}`,
                //             text: `${form.errors.exception}`,
                //             icon: "error",
                //         });
                //         // console.log(form.errors);
                //     },
                // });
            } else {
                // form.post(route("head-quarters.store"), {
                //     onSuccess: () => {
                //         customToastSwal({
                //             title: page.props.flash.success || "",
                //             icon: "success",
                //         });
                //         showModal.value = false;
                //         form.reset();
                // fetchItems();
                //     },
                //     onError: () => {
                //         customToastSwal({
                //             title: `Error: ${form.errors.messageError}`,
                //             text: `${form.errors.exception}`,
                //             icon: "error",
                //         });
                //         // console.log(form.errors);
                //     },
                // });
            }
        }
    });
};
const edit = (data: any) => {
    console.log(data);

    // headQuarterForm.id = data.id;
    // headQuarterForm.name = data.name;
    // headQuarterForm.latitude = data.latitude;
    // headQuarterForm.longitude = data.longitude;
    showModal.value = true;
};

const destroy = (data: any) => {
    // headQuarterForm.delete(route('head-quarters.destroy', data.id), {
    //     onSuccess: () => { },
    // });
    customConfirmSwal({
        title: "¿Está segur@ que desea eliminar este registro?",
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route("Modules.destroy", data.id), {
                onSuccess: () => {
                    customToastSwal({
                        title: page.props.flash.success || "",
                        icon: "success",
                    });
                    fetchItems();
                },
                onError: (err) => {
                    console.error(err);
                    customToastSwal({
                        title: `Error: ${form.errors.messageError}`,
                        text: `${form.errors.exception}`,
                        icon: "error",
                    });
                },
            });
        }
    });
};
const close = () => {
    form.reset();
    showModal.value = false;
};
//* INICIO DATATABLE SERVER SIDE */
// Aquí se definen los encabezados de la tabla, donde key es el nombre de la columna en la base de datos
const headers = [
    { title: "ID", key: "id" },
    { title: "Nombre", key: "name" },
    { title: "Contexto", key: "context" },
    { title: "Descripción", key: "description" },
    { title: "Acciones", key: "actions", sortable: false },
];

// variables reactivas
const items = ref([]);
const total = ref(0);
const loading = ref(false);
const search = ref("");
const options = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{ key: "id", order: "desc" }],
});
const prefix = "permissions";
// función para cargar datos desde Laravel
const fetchItems = async () => {
    loading.value = true;
    const params = {
        [`${prefix}_page`]: options.value.page,
        [`${prefix}_per_page`]: options.value.itemsPerPage,
        [`${prefix}_search`]: search.value,
        [`${prefix}_sort`]: options.value.sortBy?.[0]?.key ?? "id",
        [`${prefix}_order`]: options.value.sortBy?.[0]?.order ?? "desc",
    };

    // router.get(route("superadmin.permissions.index"), params, {
    //     preserveState: true,
    //     replace: true,
    //     onSuccess: (page) => {
    //         const data = page.props[prefix]?.data ?? [];
    //         const totalCount = page.props[prefix]?.total ?? 0;

    //         items.value = data;
    //         total.value = totalCount;
    //         loading.value = false;
    //     },
    // });
};

// 🔁 Observadores con debounce para evitar muchas peticiones
watch([options, search], debounce(fetchItems, 400), { deep: true });
/* FIN DATATABLE SERVER SIDE */

const step = ref(1);
const steps = ["Membresía", "Datos y Familia", "Documentos", "Confirmación"];
const selectType = (membershipType: MembershipType) => {
    form.membershipType = { ...membershipType };
    form.members = [createPrimaryHolder()];
};
const isNextDisabled = computed(() => {
    if (step.value === 1) {
        return !form.membershipType;
    }

    return false;
});
</script>

<template>
    <Head title="Alta de Socios" />

    <AppLayout>
        <template #header> Alta de Socios </template>
        <template #options>
            <BaseButton
                variant="elevated"
                :icon-only="false"
                @click="create"
                action="add"
            />
        </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <!-- <div class="p-6 border-b border-gray-200"> -->
            <v-form @submit.prevent="save" ref="formSendRef">
                <v-row>
                    <v-col cols="12">
                        <v-stepper v-model="step" :items="steps" show-actions>
                            <template v-slot:item.1>
                                <!-- {{ form.membershipType }} -->
                                <!-- h3, que tipo de membresía necesita? -->
                                <v-container class="h-[500px] overflow-auto">
                                    <h3 class="text-lg font-medium text-center">
                                        ¿Qué tipo de membresía necesita?
                                    </h3>
                                    <!-- El tipo de membresía determina los documentos e información que se solicitarán -->
                                    <p
                                        class="mb-6 text-center text-sm text-gray-600"
                                    >
                                        El tipo de membresía determina los
                                        documentos e información que se
                                        solicitarán
                                    </p>
                                    <v-row>
                                        <!-- Individual -->
                                        <v-col
                                            v-for="membershipType in membershipTypes"
                                            :key="membershipType.id"
                                            cols="12"
                                            md="6"
                                        >
                                            <v-card
                                                class="pa-6 membership-card"
                                                elevation="2"
                                                :class="{
                                                    'selected-membership-card':
                                                        form.membershipType
                                                            ?.id ===
                                                        membershipType.id,
                                                }"
                                                @click="
                                                    selectType(membershipType)
                                                "
                                            >
                                                <v-row no-gutters>
                                                    <v-col cols="auto">
                                                        <v-avatar
                                                            size="56"
                                                            color="grey-lighten-3"
                                                        >
                                                            <v-icon
                                                                :icon="
                                                                    membershipType.allows_multiple_members
                                                                        ? 'mdi-account-multiple'
                                                                        : 'mdi-account'
                                                                "
                                                            ></v-icon>
                                                        </v-avatar>
                                                    </v-col>

                                                    <v-col class="ml-4">
                                                        <h3
                                                            class="text-h6 font-weight-medium"
                                                        >
                                                            {{
                                                                membershipType.name
                                                            }}
                                                        </h3>
                                                        <p
                                                            class="text-body-2 mt-2"
                                                        >
                                                            {{
                                                                membershipType.description
                                                            }}
                                                        </p>
                                                        <!-- <p>
                                                        {{
                                                            membershipType.document_types
                                                        }}
                                                    </p> -->

                                                        <div
                                                            class="mt-4 h-64 overflow-auto"
                                                        >
                                                            <strong
                                                                >Documentos
                                                                requeridos:</strong
                                                            >
                                                            <ul class="mt-2">
                                                                <li
                                                                    v-for="doc in membershipType.document_types"
                                                                    :key="
                                                                        doc.id
                                                                    "
                                                                >
                                                                    {{
                                                                        doc
                                                                            .pivot
                                                                            .number_files ==
                                                                        1
                                                                            ? ""
                                                                            : `${doc.pivot.number_files} x `
                                                                    }}
                                                                    {{
                                                                        doc.name
                                                                    }}
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </v-col>
                                                </v-row>
                                            </v-card>
                                        </v-col>
                                    </v-row>
                                </v-container>
                            </template>

                            <template v-slot:item.2>
                                <v-container class="overflow-auto h-[500px]">
                                    <div
                                    v-for="member in form.members"
                                    :key="member.local_id"
                                    class="mb-6"
                                >
                                    <v-card class="pa-4">
                                        <div
                                            class="d-flex justify-space-between align-center mb-4"
                                        >
                                            <h4>
                                                {{
                                                    member.is_primary_holder
                                                        ? "Titular"
                                                        : "Familiar"
                                                }}
                                            </h4>

                                            <v-btn
                                                v-if="!member.is_primary_holder"
                                                color="error"
                                                variant="text"
                                                @click="
                                                    removeMember(
                                                        member.local_id,
                                                    )
                                                "
                                            >
                                                Eliminar
                                            </v-btn>
                                        </div>

                                        <v-row>
                                            <v-col
                                                v-if="!member.is_primary_holder"
                                                cols="12"
                                                md="4"
                                            >
                                                <v-select
                                                    v-model="
                                                        member.relationship_id
                                                    "
                                                    :items="props.relationships"
                                                    item-title="name"
                                                    item-value="id"
                                                    label="Parentesco"
                                                    @update:modelValue="
                                                        onRelationshipChange(
                                                            member,
                                                        )
                                                    "
                                                />
                                            </v-col>

                                            <v-col cols="12" md="4">
                                                <v-text-field
                                                    v-model="member.first_name"
                                                    label="Nombre(s)"
                                                    :rules="[required]"
                                                />
                                            </v-col>

                                            <v-col cols="12" md="4">
                                                <v-text-field
                                                    v-model="member.last_name"
                                                    label="Apellido paterno"
                                                />
                                            </v-col>

                                            <v-col cols="12" md="4">
                                                <v-text-field
                                                    v-model="
                                                        member.second_last_name
                                                    "
                                                    label="Apellido materno"
                                                />
                                            </v-col>

                                            <v-col cols="12" md="4">
                                                <v-text-field
                                                    v-model="member.birthdate"
                                                    type="date"
                                                    label="Fecha de nacimiento"
                                                />
                                            </v-col>

                                            <v-col cols="12" md="4">
                                                <v-text-field
                                                    v-model="member.nationality"
                                                    label="Nacionalidad"
                                                />
                                            </v-col>

                                            <v-col cols="12" md="4">
                                                <v-text-field
                                                    v-model="
                                                        member.marital_status
                                                    "
                                                    label="Estado civil"
                                                />
                                            </v-col>

                                            <v-col cols="12" md="4">
                                                <v-text-field
                                                    v-model="member.phone"
                                                    label="Teléfono"
                                                />
                                            </v-col>

                                            <v-col cols="12" md="4">
                                                <v-text-field
                                                    v-model="member.email"
                                                    label="Correo"
                                                />
                                            </v-col>

                                            <v-col cols="12" md="4">
                                                <v-text-field
                                                    v-model="member.occupation"
                                                    label="Ocupación"
                                                />
                                            </v-col>
                                        </v-row>
                                    </v-card>
                                </div>
                                    <v-btn
                                        v-if="
                                            form.membershipType
                                                ?.allows_multiple_members
                                        "
                                        color="primary"
                                        @click="addFamilyMember"
                                    >
                                        Agregar familiar
                                    </v-btn>
                                </v-container>
                            </template>

                            <template v-slot:item.3>
                                <v-container class="h-[500px] overflow-auto">
                                    <div class="mb-4">
                                        <h3 class="text-h6 mb-1">Documentos</h3>
                                        <p
                                            class="text-body-2 text-medium-emphasis mb-0"
                                        >
                                            Cargue los documentos requeridos por
                                            cada integrante.
                                        </p>
                                    </div>

                                    <div
                                        v-for="member in form.members"
                                        :key="member.local_id"
                                        class="mb-6"
                                    >
                                        <v-card class="pa-4">
                                            <h4
                                                class="text-subtitle-1 font-weight-bold mb-4"
                                            >
                                                {{
                                                    member.is_primary_holder
                                                        ? "Titular"
                                                        : member.relationship_name
                                                }}
                                            </h4>

                                            <v-row>
                                                <v-col
                                                    v-for="doc in member.documents"
                                                    :key="`${member.local_id}-${doc.document_type_id}`"
                                                    cols="12"
                                                    md="6"
                                                >
                                                    <div
                                                        class="mb-2 font-weight-medium"
                                                    >
                                                        {{ doc.name }}
                                                        <span
                                                            v-if="
                                                                doc.is_required
                                                            "
                                                            class="text-error"
                                                            >*</span
                                                        >
                                                    </div>
                                                    <CustomFileUploadField
                                                        v-model="doc.files"
                                                        :label="doc.name"
                                                        :hint="
                                                            doc.allowed_extensions.join(
                                                                ', ',
                                                            )
                                                        "
                                                        :accept="
                                                            doc.allowed_extensions
                                                                .map(
                                                                    (ext) =>
                                                                        `.${ext}`,
                                                                )
                                                                .join(',')
                                                        "
                                                        :multiple="
                                                            doc.allow_multiple
                                                        "
                                                        :rules="[
                                                            fileMaxCountRule(1),
                                                            requiredFileRule,
                                                            fileTypeRule(
                                                                doc.allowed_extensions,
                                                            ),
                                                        ]"
                                                        clearable
                                                    />
                                                </v-col>
                                            </v-row>
                                        </v-card>
                                    </div>
                                </v-container>
                            </template>
                            <!-- Confirmación -->
                            <template v-slot:item.4>
                                <h3 class="text-title-large my-0">
                                    Confirmación
                                </h3>
                            </template>
                            <template v-slot:actions="{ next, prev }">
                                <div class="d-flex w-100">
                                    <v-btn
                                        variant="text"
                                        @click="prev"
                                        :disabled="step === 1"
                                    >
                                        Anterior
                                    </v-btn>

                                    <v-spacer />

                                    <v-btn
                                        color="primary"
                                        @click="next"
                                        :disabled="isNextDisabled"
                                    >
                                        Siguiente
                                    </v-btn>
                                </div>
                            </template>
                        </v-stepper>
                        <!-- <v-data-table-server
                        fixed-header
                        hover
                        height="500px"
                        :headers="headers"
                        :items="items"
                        :items-length="total"
                        :loading="loading"
                        v-model:options="options"
                        class="elevation-1"
                        :items-per-page-options="[10, 25, 50, 100]"
                        items-per-page-text=" Mostrar"
                        no-data-text="No hay registros para mostrar"
                    >
                        <template #top>
                            <v-text-field
                                v-model="search"
                                label="Buscar permisos"
                                class="mx-4 mt-2"
                                clearable
                            />
                        </template>

                        <template #item.actions="{ item }">
                            <BaseButton
                                action="edit"
                                @click="edit(item)"
                                v-if="
                                    can.includes(
                                        'superadmin.permissions.update',
                                    )
                                "
                            />
                            <BaseButton
                                @click="destroy(item)"
                                action="delete"
                                v-if="
                                    can.includes(
                                        'superadmin.permissions.destroy',
                                    )
                                "
                            />
                        </template>
                    </v-data-table-server> -->
                    </v-col>
                </v-row>
            </v-form>
            <!-- </div> -->
        </div>
        <v-dialog v-model="showModal" max-width="600" persistent>
            <v-form @submit.prevent="save" ref="formSendRef">
                <v-card
                    prepend-icon="mdi-account"
                    :title="`Form|${form.id ? 'Edit' : 'Create'}`"
                >
                    <v-card-text class="overflow-y-auto h-full">
                        <v-text-field
                            v-model="form.name"
                            label="Nombre"
                            persistent-hint
                            :rules="[required]"
                        />
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <BaseButton
                            :icon-only="false"
                            variant="tonal"
                            action="cancel"
                            @click="close"
                        />
                        <BaseButton
                            :text="form.id ? 'Actualizar' : 'Guardar'"
                            variant="flat"
                            :icon-only="false"
                            type="submit"
                            action="save"
                        />
                    </v-card-actions>
                </v-card>
            </v-form>
        </v-dialog>
        <!-- <Loader :overlay="form.processing" /> -->
    </AppLayout>
</template>
<style>
.membership-card {
    cursor: pointer;
    border-radius: 12px;
    transition: all 0.2s ease;
}

.membership-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
}
.selected-membership-card {
    border: 2px solid #1976d2;
    background-color: #e3f2fd;
    transition: all 0.2s ease;
}
</style>
