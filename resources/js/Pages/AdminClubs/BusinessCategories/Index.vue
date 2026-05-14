<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import BaseButton from "@/Components/BaseButton.vue";
import { ref, watch, computed } from "vue";
import { debounce } from "lodash";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
const page = usePage();
const can = usePage().props.auth.permissions;

const props = defineProps<{ categories?: any }>();

const items = ref([]);
const total = ref(0);
const loading = ref(false);
const search = ref("");
const formErrors = computed(() => page.props.errors || {});
const options = ref({ page: 1, itemsPerPage: 10 });

const showModal = ref(false);
const imagePreview = ref<string | null>(null);
const hoverImage = ref<string | null>(null);

const form = ref<any>({ id: null, name: "", image: null, is_active: true });

const headers = [
  { title: "", key: "image", width: 80 },
  { title: "Categoría", key: "name" },
  { title: "Activo", key: "is_active", width: 120 },
  { title: "", key: "actions", sortable: false, width: 120 }
];

const fetchItems = () => {
  loading.value = true;
  router.get(route("business-categories.index"), {
    page: options.value.page,
    per_page: options.value.itemsPerPage,
    search: search.value || null
  }, {
    preserveState: true,
    replace: true,
    only: ["categories"]
  });
};
watch(() => props.categories, (val) => {
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
  form.value = { id: null, name: "", image: null, is_active: true };
  imagePreview.value = null;
  showModal.value = true;
};

const edit = (item:any) => {
  form.value = { id: item.id, name: item.name, image: null, is_active: item.is_active };
  imagePreview.value = item.image;
  showModal.value = true;
};

const removeImage = () => {
  form.value.image = null;
  form.value.remove_image = true;
  imagePreview.value = null;
};

const toggleStatus = (item:any, value:boolean) => {

  customConfirmSwal({
    title: value 
      ? "¿Activar categoría?" 
      : "¿Desactivar categoría?",
    text: "Confirma para continuar"
  }).then((result) => {

    if (!result.isConfirmed) return

    router.post(route("business-categories.update", item.id), {
      name: item.name,
      is_active: value
    }, {
      preserveState: true,
      onSuccess: () => {
        item.is_active = value;

        customToastSwal({
          title: value 
            ? "Categoría activada" 
            : "Categoría desactivada",
          icon: "success"
        });
      },
      onError: () => {
        customToastSwal({
          title: "Error al actualizar",
          icon: "error"
        });
      }
    });

  });
};

const savingCategory = ref(false)

const save = async () => {

  const result = await customConfirmSwal({
    title: form.value.id 
      ? "¿Actualizar categoría?" 
      : "¿Crear categoría?",
    text: "Confirma para continuar"
  })

  if (!result.isConfirmed) return

  if (savingCategory.value) return
  savingCategory.value = true

  const data = new FormData()
  data.append("name", form.value.name)
  data.append("is_active", form.value.is_active ? "1" : "0")

  if (form.value.remove_image) {
    data.append("remove_image", "1")
  }

  if (form.value.image) {
    const file = Array.isArray(form.value.image) 
      ? form.value.image[0] 
      : form.value.image

    data.append("image", file)
  }

  const request = form.value.id
    ? router.post(route("business-categories.update", form.value.id), data, {
        forceFormData: true,
        onSuccess: () => {
          customToastSwal({
            title: page.props.flash.success,
            icon: "success"
          })

          showModal.value = false
          fetchItems()

          savingCategory.value = false
        },
        onError: (errors) => {
          console.log(errors)

          customToastSwal({
            title: "Error al guardar",
            icon: "error"
          })

          savingCategory.value = false
        }
      })
    : router.post(route("business-categories.store"), data, {
        forceFormData: true,
        onSuccess: () => {
          customToastSwal({
            title: page.props.flash.success,
            icon: "success"
          })

          showModal.value = false
          fetchItems()

          savingCategory.value = false
        },
        onError: (errors) => {
          console.log(errors)

          customToastSwal({
            title: "Error al guardar",
            icon: "error"
          })

          savingCategory.value = false
        }
      })
};

const remove = (item:any) => {
  customConfirmSwal({ title: "Eliminar categoría", confirmText: "Eliminar" })
    .then(r => {
      if (r.isConfirmed) {
        router.delete(route("business-categories.destroy", item.id), {
          onSuccess: () => {
            items.value = items.value.filter(i => i.id !== item.id);
            customToastSwal({ title: page.props.flash.success, icon: "success" });
          },
          onError: (err) => {
            customToastSwal({
                title: err.messageError || "Error en el formulario",
                icon: "error"
            });
        },
      });
      }
    });
};

watch(() => form.value.image, (file) => {
  if (!file) return;

  const selected = Array.isArray(file) ? file[0] : file;

  // 2MB = 2048 KB = 2 * 1024 * 1024 bytes
  if (selected.size > 2 * 1024 * 1024) {
    customToastSwal({
      title: "La imagen no debe superar los 2MB",
      icon: "error"
    });

    form.value.image = null;
    imagePreview.value = null;
    return;
  }

  imagePreview.value = URL.createObjectURL(selected);
});
</script>

<template>
<Head title="Categorías" />

<AppLayout>

<div class="d-flex justify-space-between align-center mb-6">
  <div>
    <h2 class="text-h5 font-weight-bold">Categorías</h2>
    <span class="text-grey">Administra las categorías</span>
  </div>

  <BaseButton :text="'Nueva categoría'" action="add" @click="openCreate" :icon-only="false" variant="elevated" v-if="can.includes('business-categories.store')" />
</div>

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

      <!-- IMAGE + HOVER -->
      <template #item.image="{ item }">
        <div
            style="position: relative; display: inline-block;"
            @mouseenter="hoverImage = item.image"
            @mouseleave="hoverImage = null"
        >
            <v-avatar size="50">
            <v-img :src="item.image" cover />
            </v-avatar>

            <v-img
            v-show="hoverImage === item.image"
            :src="item.image"
            class="preview-hover"
            />
        </div>
        </template>

      <!-- NAME -->
      <template #item.name="{ item }">
        <div class="font-weight-medium">{{ item.name }}</div>
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
                <BaseButton v-if="can.includes('business-categories.update')" action="edit" @click="edit(item)" />
                <BaseButton v-if="can.includes('business-categories.destroy')" action="delete" @click="remove(item)" />
            </div>
       </template>

    </v-data-table-server>
  </v-card-text>
</v-card>
<v-dialog v-model="showModal" max-width="500">
  <v-card rounded="xl">

    <v-card-title>{{ form.id ? 'Editar' : 'Nueva' }} categoría</v-card-title>

    <v-card-text>
      <v-text-field v-model="form.name" label="Nombre" :error="!!formErrors.name" :error-messages="formErrors.name" />

      <v-file-input
        v-model="form.image"
        label="Imagen"
        accept="image/*"
        :error="!!formErrors.image"
        :error-messages="formErrors.image"
      />
        <div class="text-caption text-medium-emphasis">
            Máximo 2MB · Formatos JPG, PNG
        </div>
      <v-btn v-if="imagePreview" size="small" color="red" @click="removeImage">
        Eliminar imagen
      </v-btn>

      <v-img v-if="imagePreview" :src="imagePreview" max-height="150" class="mt-2" />
    </v-card-text>

    <v-card-actions>
        <v-spacer></v-spacer>
        <BaseButton :text="'Cancelar'" variant="tonal" :icon-only="false" action="cancel"
            @click="showModal = false" />
        <BaseButton :text="form.id ? 'Actualizar' : 'Guardar'"
          variant="flat"
          :icon-only="false"
          action="save"
          @click="save"
        />
    </v-card-actions>

  </v-card>
</v-dialog>

</AppLayout>
</template>

<style scoped>
.preview-hover {
  position: absolute;
  top: -20px;
  left: 60px;
  width: 420px;         
  max-height: 340px;
  object-fit: cover;    
  z-index: 999;
  border-radius: 16px;
  box-shadow: 0 8px 30px rgba(0,0,0,0.25); 

  pointer-events: none;
}
.preview-hover {
  opacity: 0;
  transform: scale(0.95) translateY(10px);
  transition: all 0.2s ease;
}

.preview-hover[v-cloak],
.preview-hover {
  opacity: 1;
  transform: scale(1) translateY(0);
}
.preview-hover {
  left: 60px;
}

@media (max-width: 768px) {
  .preview-hover {
    left: -260px; 
  }
}
</style>