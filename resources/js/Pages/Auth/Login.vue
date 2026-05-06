<script setup lang="ts">
import { ref } from "vue";
import { email as emailRule, required } from "@/constants/validationRules";
import AuthenticationCard from "@/Components/AuthenticationCard.vue";
import AuthenticationCardLogo from "@/Components/AuthenticationCardLogo.vue";
import Checkbox from "@/Components/Checkbox.vue";
import PasswordField from "@/Components/PasswordField.vue";
import InputError from "@/Components/InputError.vue";
import Loader from "@/Components/Loader.vue";
import { isLoading } from "@/loading";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { email } from "@/constants/validationRules";
const formLoginSendRef = ref();
defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: "",
    password: "",
    remember: false,
});

const submit = () => {
    formLoginSendRef.value?.validate().then(({ valid: isValid }) => {
        console.log(isValid);
        if (!isValid) {
            return;
        } else {
            form.transform((data) => ({
                ...data,
                remember: form.remember ? "on" : "",
            })).post(route("login"), {
                onFinish: () => form.reset("password"),
            });
        }
    });
};
</script>

<template>
    <Head title="Log in" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>

        <div
            v-if="status"
            class="mb-4 font-medium text-sm text-green-600 dark:text-green-400"
        >
            {{ status }}
        </div>

        <v-form
            @submit.prevent="submit"
            ref="formLoginSendRef"
            class="w-full flex flex-col"
        >
            <div>
                <!-- <InputLabel for="email" value="Email" /> -->
                <v-text-field
                    required
                    v-model="form.email"
                    prepend-inner-icon="mdi-email"
                    label="Correo electrónico"
                    variant="outlined"
                    type="email"
                    :rules="[required, emailRule]"
                ></v-text-field>

                <!-- <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 block w-full"
                    required
                    autofocus
                    autocomplete="username"
                /> -->
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <!--<v-text-field
                    required
                    v-model="form.password"
                    prepend-inner-icon="mdi-lock"
                    label="Contraseña"
                    type="password"
                    variant="outlined"
                ></v-text-field>-->
                <PasswordField v-model="form.password" label="Contraseña" />
                <!-- <InputLabel for="password" value="Password" />
                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="current-password"
                /> -->
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <v-checkbox
                v-model="form.remember"
                color="indigo"
                label="Recuérdame"
                hide-details
            ></v-checkbox>
            <!-- <Checkbox v-model:checked="form.remember" name="remember" />
                    <span class="ms-2 text-sm text-gray-600 dark:text-gray-400"
                        >Recuérdame</span
                    > -->
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="underline text-sm text-blue-600  hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800"
                >
                    ¿Olvidaste tu contraseña?
                </Link>

            <div class="flex flex-col items-center justify-end mt-4">

                <!-- <PrimaryButton
                    class="ms-4"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Log in
                </PrimaryButton> -->
                <v-btn color="primary" type="submit" prepend-icon="mdi-login"> Ingresar </v-btn>
                
            </div>
        </v-form>
        <Loader :overlay="isLoading" />
    </AuthenticationCard>
</template>
