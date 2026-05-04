<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import BaseButton from "@/Components/BaseButton.vue";
import { ref, watch, computed } from "vue";
import { debounce } from "lodash";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";

const page = usePage();

const props = defineProps<{ surveys?: any }>();

const items = ref([]);
const total = ref(0);
const loading = ref(false);
const search = ref("");
const formErrors = computed(() => page.props.errors || {});
const options = ref({ page: 1, itemsPerPage: 10 });

const showModal = ref(false);

const form = ref<any>({
  id: null, 
  name: "",
  link: "",
  is_active: true
});

const headers = [
  { title: "Encuesta", key: "name" },
  { title: "Enlace", key: "link", sortable: false },
  { title: "Activo", key: "is_active", width: 120 },
  { title: "", key: "actions", sortable: false, width: 120 }
];

const fetchItems = () => {
  loading.value = true;
  router.get(route("surveys.index"), {
    page: options.value.page,
    per_page: options.value.itemsPerPage,
    search: search.value || null
  }, {
    preserveState: true,
    replace: true,
    only: ["surveys"]
  });
};

watch(() => props.surveys, (val) => {
  items.value = val?.data ?? [];
  total.value = val?.total ?? 0;
  loading.value = false;
}, { immediate: true });

watch(search, debounce(() => {
  options.value.page = 1;
  fetchItems();
}, 400));

watch(options, debounce(fetchItems, 400), { deep: true });

const openCreate = () => {
  form.value = { id: null, name: "", link: "", is_active: true };
  showModal.value = true;
};

const edit = (item:any) => {
  form.value = { ...item };
  showModal.value = true;
};

const toggleStatus = (item:any, value:boolean) => {
  router.post(route("surveys.update", item.id), {
    _method: 'PUT',
    ...item,
    is_active: value
  }, {
    preserveState: true,
    onSuccess: () => {
      item.is_active = value;
    }
  });
};

const save = () => {
  if (form.value.id) {
    router.put(route("surveys.update", form.value.id), form.value, {
      onSuccess: () => {
        customToastSwal({ title: page.props.flash.success, icon: "success"});
        showModal.value = false;
        fetchItems();
      },
      onError: () =>{
        customToastSwal({ title: page.props.flash.messageError, icon: "error" });
      }
    });
  } else {
    router.post(route("surveys.store"), form.value, {
      onSuccess: () => {
        customToastSwal({ title: page.props.flash.success, icon: "success" });
        showModal.value = false;
        fetchItems();
      },
      onError: () =>{
        customToastSwal({ title: page.props.flash.messageError, icon: "error" });
      }
    });
  }
};

const remove = (item:any) => {
  customConfirmSwal({ title: "Eliminar encuesta", confirmText: "Eliminar" })
    .then(r => {
      if (r.isConfirmed) {
        router.delete(route("surveys.destroy", item.id), {
          onSuccess: () => {
            items.value = items.value.filter(i => i.id !== item.id);
            customToastSwal({ title: page.props.flash.success, icon: "success" });
          },
          onError: () =>{
            customToastSwal({ title: page.props.flash.messageError, icon: "error" });
          }
        });
      }
    });
};
const openLink = (url: string) => {
  if (!url) return;

  // asegura que tenga protocolo
  const finalUrl = url.startsWith('http') ? url : `https://${url}`;
  window.open(finalUrl, '_blank');
};
</script>
<template>
<Head title="Encuestas" />

<AppLayout>

<!-- HEADER -->
<div class="d-flex justify-space-between align-center mb-6">
  <div>
    <h2 class="text-h5 font-weight-bold d-flex align-center gap-2">
      <v-icon>mdi-poll</v-icon>
      Encuestas
    </h2>
    <span class="text-grey">Administra las encuestas</span>
  </div>

  <BaseButton
    text="Nueva encuesta"
    action="add"
    @click="openCreate"
    :icon-only="false"
    variant="elevated"
  />
</div>

<!-- TABLE -->
<v-card rounded="xl" elevation="2">
  <v-card-text>

    <v-text-field
      v-model="search"
      placeholder="Buscar..."
      prepend-inner-icon="mdi-magnify"
      variant="outlined"
      hide-details
      style="max-width: 300px"
      class="mb-4"
    />

    <v-data-table-server
      :headers="headers"
      :items="items"
      :items-length="total"
      :loading="loading"
      v-model:options="options"
    >

      <!-- LINK -->
      <template #item.link="{ item }">
        <v-btn
          icon="mdi-open-in-new"
          variant="text"
          color="primary"
          @click="openLink(item.link)"
        />
      </template>

      <!-- TOGGLE -->
      <template #item.is_active="{ item }">
        <v-switch
          :model-value="item.is_active"
          color="green"
          hide-details
          @update:model-value="val => toggleStatus(item, val)"
        />
      </template>

      <!-- ACTIONS -->
      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <BaseButton action="edit" @click="edit(item)" />
          <BaseButton action="delete" @click="remove(item)" />
        </div>
      </template>

    </v-data-table-server>
  </v-card-text>
</v-card>

<!-- MODAL -->
<v-dialog v-model="showModal" max-width="500">
  <v-card rounded="xl">

    <v-card-title>
      {{ form.id ? 'Editar' : 'Nueva' }} encuesta
    </v-card-title>

    <v-card-text>
      <v-text-field
        v-model="form.name"
        label="Nombre"
        :error="!!formErrors.name"
        :error-messages="formErrors.name"
      />

      <v-text-field
        v-model="form.link"
        label="Enlace"
        :error="!!formErrors.link"
        :error-messages="formErrors.link"
      />

      <v-switch
        v-model="form.is_active"
        label="Activa"
        color="green"
        inset
      />
    </v-card-text>

    <v-card-actions>
      <v-spacer></v-spacer>

      <BaseButton
        text="Cancelar"
        action="cancel"
        @click="showModal = false"
        :icon-only="false"
      />

      <BaseButton
        :text="form.id ? 'Actualizar' : 'Guardar'"
        action="save"
        @click="save"
        :icon-only="false"
      />
    </v-card-actions>

  </v-card>
</v-dialog>

</AppLayout>
</template>