<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseButton from "@/Components/BaseButton.vue";
import { Head, useForm, usePage, router } from "@inertiajs/vue3";
import { ref, watch } from "vue";
import { customToastSwal } from "@/utils/swal";

interface ClubData {
    id: number;
    name: string;
    address: string | null;
    is_active: boolean;
    logo_url: string | null;
    mapa_url: string | null;
    social_whatsapp: string | null;
    social_instagram: string | null;
    social_facebook: string | null;
    social_youtube: string | null;
}

interface Props {
    club: ClubData;
}
const props  = defineProps<Props>();
const can    = usePage().props.auth.permissions as string[];
const formRef = ref<{ validate(): Promise<{ valid: boolean }> } | null>(null);

const logoFiles   = ref<File[]>([]);
const logoPreview = ref<string | null>(props.club.logo_url);
const mapaFiles   = ref<File[]>([]);
const mapaPreview = ref<string | null>(props.club.mapa_url);

watch(logoFiles, (files) => {
    const file = files?.[0] ?? null;
    if (file) {
        form.remove_logo = false;
        const reader = new FileReader();
        reader.onload = (e) => {
            logoPreview.value = e.target?.result as string;
        };

        reader.readAsDataURL(file);
    }
});

watch(mapaFiles, (files) => {
    const file = files?.[0] ?? null;
    if (file) {
        form.remove_mapa = false;
        const reader = new FileReader();
        reader.onload = (e) => {
            mapaPreview.value = e.target?.result as string;
        };

        reader.readAsDataURL(file);
    }
});
const form = useForm({
    name:             props.club.name ?? '',
    address:          props.club.address ?? '',
    is_active:        props.club.is_active,
    logo:             null as File | null,
    mapa:             null as File | null,
    remove_logo:       false,
    remove_mapa:        false,
    social_whatsapp:  props.club.social_whatsapp ?? '',
    social_instagram: props.club.social_instagram ?? '',
    social_facebook:  props.club.social_facebook ?? '',
    social_youtube:   props.club.social_youtube ?? '',
});

const submit = async () => {
    const result = await formRef.value?.validate();
    if (!result?.valid) return;

    // Asignar archivo de logo antes de enviar
    form.logo = logoFiles.value?.[0] ?? null;
    form.mapa = mapaFiles.value?.[0] ?? null;

    form.post(route('club-settings.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            logoFiles.value = [];
            mapaFiles.value = [];
            customToastSwal({ title: 'Club actualizado correctamente.', icon: 'success' });
        },
        onError: () => {
            customToastSwal({
                title: `Error: ${form.errors.messageError || 'No se pudo actualizar el club.'}`,
                text: form.errors.exception ?? '',
                icon: 'error',
            });
        },
    });
};

const requiredRule  = (v: string) => !!v?.trim() || 'Este campo es requerido';
const maxRule       = (max: number) => (v: string) => !v || v.length <= max || `Máximo ${max} caracteres`;
const urlRule       = (v: string) =>
    !v || /^https?:\/\/.+\..+/.test(v.trim()) || 'Ingresa una URL válida (ej. https://facebook.com/...)';
const logoSizeRule  = (files: File[]) =>
    !files?.length || files[0].size <= 2 * 1024 * 1024 || 'El logo no puede superar 2 MB';
const logoTypeRule  = (files: File[]) =>
    !files?.length || ['image/jpeg', 'image/png', 'image/webp'].includes(files[0].type)
    || 'Solo se permiten imágenes JPG, PNG o WEBP';

const socialNetworks = [
    {
        key:         'social_whatsapp' as const,
        label:       'WhatsApp',
        placeholder: '+52 55 1234 5678 o https://wa.me/...',
        icon:        'mdi-whatsapp',
        color:       '#25D366',
        hint:        'Número de teléfono o enlace de WhatsApp',
        rules:       [maxRule(200)],
    },
    {
        key:         'social_instagram' as const,
        label:       'Instagram',
        placeholder: 'https://instagram.com/tu-club',
        icon:        'mdi-instagram',
        color:       '#E1306C',
        hint:        'URL del perfil de Instagram',
        rules:       [urlRule, maxRule(500)],
    },
    {
        key:         'social_facebook' as const,
        label:       'Facebook',
        placeholder: 'https://facebook.com/tu-club',
        icon:        'mdi-facebook',
        color:       '#1877F2',
        hint:        'URL de la página de Facebook',
        rules:       [urlRule, maxRule(500)],
    },
    {
        key:         'social_youtube' as const,
        label:       'YouTube',
        placeholder: 'https://youtube.com/@tu-canal',
        icon:        'mdi-youtube',
        color:       '#FF0000',
        hint:        'URL del canal de YouTube',
        rules:       [urlRule, maxRule(500)],
    },
] as const;

// Eliminar mapa y logo
const removeLogo = () => {
    logoFiles.value = [];
    logoPreview.value = null;
    form.remove_logo = true;
};

const removeMapa = () => {
    mapaFiles.value = [];
    mapaPreview.value = null;
    form.remove_mapa = true;
};
</script>

<template>
    <Head title="Configuración del Club" />

    <AppLayout>
        <template #header>Configuración del Club</template>
        <template #options>
            
        </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <v-row>
                <v-col cols="12" md="9" lg="7" class="mx-auto">
                    <v-container class="py-6">

                        <v-form ref="formRef">

                            <!-- ══ Card: Información general ══ -->
                            <v-card class="pa-6 mb-5" variant="outlined">
                                <div class="d-flex align-center ga-2 mb-5">
                                    <v-icon color="primary" size="22">mdi-office-building-cog</v-icon>
                                    <div>
                                        <div class="text-subtitle-1 font-weight-bold">Información general</div>
                                        <div class="text-body-2 text-medium-emphasis">Nombre, dirección, estado y logo del club.</div>
                                    </div>
                                </div>

                                <!-- Logo -->
                                <div class="mb-5">
                                    <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-3">Logo del club</div>
                                    <div class="d-flex flex-wrap align-center ga-4">
                                        <!-- Previsualización -->
                                        <v-avatar
                                            size="96"
                                            rounded="lg"
                                            :color="logoPreview ? undefined : 'grey-lighten-3'"
                                            class="border"
                                        >
                                            <v-img
                                                v-if="logoPreview"
                                                :src="logoPreview"
                                                cover
                                                alt="Logo del club"
                                            />
                                            <v-icon v-else size="40" color="grey">mdi-image-off-outline</v-icon>
                                        </v-avatar>

                                        <!-- Input de archivo -->
                                        <div class="flex-grow-1" style="min-width: 220px">
                                            <v-file-input
                                                v-model="logoFiles"
                                                label="Cambiar logo"
                                                accept="image/jpeg,image/png,image/webp"
                                                prepend-icon=""
                                                prepend-inner-icon="mdi-camera-outline"
                                                variant="outlined"
                                                density="compact"
                                                clearable
                                                show-size
                                                :rules="[logoSizeRule, logoTypeRule]"
                                                :error-messages="form.errors.logo"
                                                hint="JPG, PNG o WEBP · Máximo 2 MB"
                                                persistent-hint
                                                multiple
                                            />
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <v-btn
                                            color="error"
                                            variant="text"
                                            size="small"
                                            prepend-icon="mdi-delete"
                                            @click="removeLogo"
                                        >
                                            Eliminar logo
                                        </v-btn>
                                    </div>
                                </div>

                                <v-divider class="mb-5" />

                                <!-- Nombre -->
                                <v-row dense>
                                    <v-col cols="12">
                                        <v-text-field
                                            v-model="form.name"
                                            label="Nombre del club *"
                                            variant="outlined"
                                            density="compact"
                                            maxlength="200"
                                            counter
                                            :rules="[requiredRule, maxRule(200)]"
                                            :error-messages="form.errors.name"
                                        />
                                    </v-col>

                                    <!-- Dirección -->
                                    <v-col cols="12">
                                        <v-text-field
                                            v-model="form.address"
                                            label="Dirección"
                                            variant="outlined"
                                            density="compact"
                                            maxlength="500"
                                            counter
                                            :rules="[maxRule(500)]"
                                            :error-messages="form.errors.address"
                                            placeholder="Ej. Av. Insurgentes Sur 1234, Ciudad de México"
                                        />
                                    </v-col>
                                    <!-- Mapa -->
                                    <v-col cols="12">
                                        <v-file-input
                                            v-model="mapaFiles"
                                            label="Mapa del club"
                                            accept="image/jpeg,image/png,image/webp"
                                                prepend-icon=""
                                                prepend-inner-icon="mdi-camera-outline"
                                                variant="outlined"
                                                density="compact"
                                                clearable
                                                show-size
                                                :rules="[logoSizeRule, logoTypeRule]"
                                                :error-messages="form.errors.logo"
                                                hint="JPG, PNG o WEBP · Máximo 2 MB"
                                                persistent-hint
                                                multiple
                                            />
                                            <v-avatar
                                                v-if="mapaPreview"
                                                size="120"
                                                rounded="lg"
                                                :color="mapaPreview ? undefined : 'grey-lighten-3'"
                                                class="border mt-3"
                                            >
                                                <v-img
                                                    :src="mapaPreview"
                                                    cover
                                                    alt="Mapa del club"
                                                />
                                            </v-avatar>
                                        <div class="mt-2">
                                            <v-btn
                                                color="error"
                                                variant="text"
                                                size="small"
                                                prepend-icon="mdi-delete"
                                                @click="removeMapa"
                                            >
                                                Eliminar mapa
                                            </v-btn>
                                        </div>
                                    </v-col>
                                    <!-- Estado -->
                                    <v-col cols="12">
                                        <div class="d-flex align-center justify-space-between pa-3 border rounded-lg">
                                            <div>
                                                <div class="text-body-2 font-weight-medium">Estado del club</div>
                                                <div class="text-caption text-medium-emphasis">
                                                    {{ form.is_active ? 'El club está activo y visible.' : 'El club está inactivo.' }}
                                                </div>
                                            </div>
                                            <v-switch
                                                v-model="form.is_active"
                                                :color="form.is_active ? 'success' : 'default'"
                                                hide-details
                                                inset
                                                density="compact"
                                            />
                                        </div>
                                    </v-col>
                                </v-row>
                            </v-card>

                            <!-- ══ Card: Redes sociales ══ -->
                            <v-card class="pa-6 mb-5" variant="outlined">
                                <div class="d-flex align-center ga-2 mb-5">
                                    <v-icon color="primary" size="22">mdi-share-variant</v-icon>
                                    <div>
                                        <div class="text-subtitle-1 font-weight-bold">Redes sociales</div>
                                        <div class="text-body-2 text-medium-emphasis">
                                            Agrega los canales de comunicación del club. Todos los campos son opcionales.
                                        </div>
                                    </div>
                                </div>

                                <v-row dense>
                                    <v-col
                                        v-for="network in socialNetworks"
                                        :key="network.key"
                                        cols="12"
                                        sm="6"
                                    >
                                        <v-text-field
                                            v-model="form[network.key]"
                                            :label="network.label"
                                            :placeholder="network.placeholder"
                                            variant="outlined"
                                            density="compact"
                                            clearable
                                            :hint="network.hint"
                                            persistent-hint
                                            :rules="network.rules"
                                            :error-messages="(form.errors as any)[network.key]"
                                        >
                                            <template #prepend-inner>
                                                <v-icon :color="network.color" size="20">{{ network.icon }}</v-icon>
                                            </template>
                                        </v-text-field>
                                    </v-col>
                                </v-row>
                            </v-card>

                            <!-- ══ Acciones ══ -->
                            <div class="d-flex justify-end ga-3">
                                <base-button
                                    variant="text"
                                    color="secondary"
                                    :icon-only="false"
                                    action="cancel"
                                    text="Cancelar"
                                    @click="router.back()"
                                />
                                <BaseButton
                                    v-if="can.includes('club-settings.update')"
                                    :icon-only="false"
                                    action="save"
                                    text="Guardar cambios"
                                    :loading="form.processing"
                                    @click="submit"
                                />
                            </div>

                        </v-form>

                    </v-container>
                </v-col>
            </v-row>
        </div>
    </AppLayout>
</template>
