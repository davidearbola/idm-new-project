<script setup>
import { ref, computed } from 'vue';
import { Form, Field, ErrorMessage } from 'vee-validate';
import * as yup from 'yup';
import { useToast } from 'vue-toastification';
import { useAuthStore } from '@/stores/authStore';
import { usePazienteStore } from '@/stores/pazienteStore';
import { storeToRefs } from 'pinia';

const authStore = useAuthStore();
const pazienteStore = usePazienteStore();
const { user } = storeToRefs(authStore);
const { isLoading } = storeToRefs(pazienteStore);
const toast = useToast();

const preventivoFile = ref(null);
const isDragging = ref(false);
const formRef = ref(null);

const showAnagraficaForm = computed(() => !user.value?.anagrafica_paziente);

const schema = computed(() => {
  let baseSchema = {
    preventivoFile: yup
      .mixed()
      .required('È necessario caricare un file.')
      .test(
        'fileSize',
        'Il file è troppo grande (max 10MB)',
        (value) => !value || (value && value.size <= 10 * 1024 * 1024)
      )
      .test(
        'fileType',
        'Formato non supportato (accettati: PDF, JPG, PNG)',
        (value) => !value || (value && ['application/pdf', 'image/jpeg', 'image/png'].includes(value.type))
      ),
  };

  if (showAnagraficaForm.value) {
    baseSchema = {
      ...baseSchema,
      cellulare: yup.string().required('Il cellulare è obbligatorio').min(9, 'Numero non valido'),
      indirizzo: yup.string().required("L'indirizzo è obbligatorio"),
      citta: yup.string().required('La città è obbligatoria'),
      cap: yup.string().required('Il CAP è obbligatorio').length(5, 'Il CAP deve essere di 5 cifre'),
      provincia: yup.string().required('La provincia è obbligatoria').length(2, 'La sigla deve essere di 2 lettere'),
    };
  }
  return yup.object(baseSchema);
});

const handleFileChange = (event) => {
  const file = event.target.files[0];
  if (file) {
    preventivoFile.value = file;
    formRef.value.setFieldValue('preventivoFile', file);
  }
};
const handleDrop = (event) => {
  isDragging.value = false;
  const file = event.dataTransfer.files[0];
  if (file) {
    preventivoFile.value = file;
    formRef.value.setFieldValue('preventivoFile', file);
  }
};
const removeFile = () => {
  preventivoFile.value = null;
  formRef.value.setFieldValue('preventivoFile', null);
};

const handleUpload = async (values) => {
  const dataToUpload = { ...values };
  
  const response = await pazienteStore.uploadQuote(dataToUpload);

  if (response.success) {
    toast.success(response.message);
    removeFile();
    if(formRef.value) {
      formRef.value.resetForm();
    }
  } else {
    toast.error(response.message);
  }
};
</script>

<template>
  <div>
    <h1 class="display-5 fw-bold">Carica il Tuo Preventivo</h1>
    <p class="lead text-muted">
      Trascina o seleziona un file (PDF, JPG, PNG) per ricevere le migliori proposte dai nostri studi medici.
    </p>
    <hr class="my-4" />

    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <Form @submit="handleUpload" :validation-schema="schema" ref="formRef" v-slot="{ errors }">
          <div
            class="upload-area text-center p-5 rounded-3 border-2"
            :class="{ 'dragging': isDragging, 'has-file': preventivoFile }"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="handleDrop"
          >
            <div v-if="!preventivoFile">
              <i class="fa-solid fa-cloud-arrow-up fa-3x text-muted mb-3"></i>
              <h5 class="fw-bold">Trascina il tuo file qui</h5>
              <p class="text-muted">o</p>
              <label for="file-input" class="btn btn-primary">Seleziona File</label>
              <input type="file" id="file-input" @change="handleFileChange" accept=".pdf,.jpg,.jpeg,.png" class="d-none" />
            </div>
            <div v-else class="file-preview">
              <i class="fa-solid fa-file-invoice fa-3x text-accent mb-3"></i>
              <p class="fw-bold mb-1">{{ preventivoFile.name }}</p>
              <p class="text-muted small">{{ (preventivoFile.size / 1024).toFixed(2) }} KB</p>
              <button type="button" @click="removeFile" class="btn btn-sm btn-danger mt-2">Rimuovi</button>
            </div>
          </div>
          <ErrorMessage name="preventivoFile" class="text-danger small mt-2 d-block" />

          <div v-if="showAnagraficaForm" class="mt-4">
            <h4 class="mb-3">Completa i tuoi dati</h4>
            <p class="text-muted small">Queste informazioni sono necessarie solo per il primo caricamento e ci aiuteranno a trovare gli studi più vicini a te.</p>
            <div class="row g-3 mt-2">
              <div class="col-md-6">
                <label class="form-label">Cellulare</label>
                <Field name="cellulare" type="tel" class="form-control" :class="{'is-invalid': errors.cellulare}" />
                <ErrorMessage name="cellulare" class="text-danger small" />
              </div>
              <div class="col-md-6">
                 <label class="form-label">Indirizzo (es. Via Roma, 1)</label>
                <Field name="indirizzo" type="text" class="form-control" :class="{'is-invalid': errors.indirizzo}" />
                <ErrorMessage name="indirizzo" class="text-danger small" />
              </div>
              <div class="col-md-5">
                 <label class="form-label">Città</label>
                <Field name="citta" type="text" class="form-control" :class="{'is-invalid': errors.citta}" />
                <ErrorMessage name="citta" class="text-danger small" />
              </div>
               <div class="col-md-3">
                 <label class="form-label">CAP</label>
                <Field name="cap" type="text" class="form-control" :class="{'is-invalid': errors.cap}" />
                <ErrorMessage name="cap" class="text-danger small" />
              </div>
              <div class="col-md-4">
                 <label class="form-label">Provincia (Sigla)</label>
                <Field name="provincia" type="text" class="form-control" :class="{'is-invalid': errors.provincia}" />
                <ErrorMessage name="provincia" class="text-danger small" />
              </div>
            </div>
          </div>
          
          <div class="text-end mt-4">
            <button type="submit" class="btn btn-accent btn-lg" :disabled="isLoading">
              <span v-if="isLoading" class="spinner-border spinner-border-sm me-2"></span>
              {{ isLoading ? 'Caricamento...' : 'Carica Preventivo' }}
            </button>
          </div>
        </Form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.upload-area {
  border: 2px dashed #dee2e6;
  transition: all 0.3s ease;
  background-color: #f8f9fa;
}
.upload-area.dragging {
  border-color: var(--bs-primary);
  background-color: rgba(var(--bs-primary-rgb), 0.1);
}
.upload-area.has-file {
    border-color: var(--bs-accent);
    background-color: rgba(var(--bs-accent-rgb), 0.1);
}
</style>