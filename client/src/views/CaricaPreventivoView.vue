<script setup>
import { ref, computed, onUnmounted, watch } from 'vue'
import { Form, Field, ErrorMessage } from 'vee-validate'
import * as yup from 'yup'
import { useToast } from 'vue-toastification'
import { usePreventivoStore } from '@/stores/preventivoStore'
import { storeToRefs } from 'pinia'

const preventivoStore = usePreventivoStore()
const {
  statoElaborazione,
  vociPreventivo,
  messaggioErrore,
  isLoading,
  uploadProgress,
  isElaborazioneCompletata,
  isInAttesaDatiPaziente,
  isRicercaProposte,
  isPropostePronte,
  haErrore,
  isSenzaProposte
} = storeToRefs(preventivoStore)

const toast = useToast()

// --- STATO DEL COMPONENTE ---
const preventivoFile = ref(null)
const isDragging = ref(false)
const formUploadRef = ref(null)
const formDatiPazienteRef = ref(null)
const pollingInterval = ref(null)
const vociEditate = ref([])

// --- VALIDATION SCHEMAS ---
const uploadSchema = yup.object({
  preventivoFile: yup
    .mixed()
    .required('È necessario caricare un file.')
    .test('fileSize', 'Il file è troppo grande (max 10MB)', (value) =>
      !value || (value && value.size <= 10 * 1024 * 1024)
    )
    .test('fileType', 'Formato non supportato (accettati: PDF, JPG, PNG)', (value) =>
      !value || (value && ['application/pdf', 'image/jpeg', 'image/png'].includes(value.type))
    ),
})

const datiPazienteSchema = yup.object({
  email: yup.string().required("L'email è obbligatoria").email('Email non valida'),
  cellulare: yup.string().required('Il cellulare è obbligatorio').min(9, 'Numero non valido'),
  nome: yup.string().required('Il nome è obbligatorio'),
  cognome: yup.string().required('Il cognome è obbligatorio'),
  indirizzo: yup.string().required("L'indirizzo è obbligatorio"),
  citta: yup.string().required('La città è obbligatoria'),
  provincia: yup.string().required('La provincia è obbligatoria').length(2, 'La sigla deve essere di 2 lettere'),
  cap: yup.string().required('Il CAP è obbligatorio').length(5, 'Il CAP deve essere di 5 cifre'),
})

// --- GESTIONE FILE ---
const handleFileChange = (event) => {
  const file = event.target.files[0]
  if (file) {
    preventivoFile.value = file
    formUploadRef.value.setFieldValue('preventivoFile', file)
  }
}

const handleDrop = (event) => {
  isDragging.value = false
  const file = event.dataTransfer.files[0]
  if (file) {
    preventivoFile.value = file
    formUploadRef.value.setFieldValue('preventivoFile', file)
  }
}

const removeFile = () => {
  preventivoFile.value = null
  formUploadRef.value?.setFieldValue('preventivoFile', null)
}

// --- STEP 1: UPLOAD FILE ---
const handleUpload = async () => {
  if (!preventivoFile.value) {
    toast.error('Seleziona un file prima di procedere')
    return
  }

  const response = await preventivoStore.caricaPreventivo(preventivoFile.value)

  if (response.success) {
    toast.info('Il tuo preventivo è in fase di analisi. Attendi...')
    startPolling(preventivoStore.controllaStato)
  } else {
    toast.error(response.message)
  }
}

// --- POLLING ---
const startPolling = (action) => {
  stopPolling()
  action() // Esegui subito
  pollingInterval.value = setInterval(action, 5000) // Poi ogni 5 secondi
}

const stopPolling = () => {
  if (pollingInterval.value) {
    clearInterval(pollingInterval.value)
    pollingInterval.value = null
  }
}

// Watch per gestire i cambiamenti di stato
watch(statoElaborazione, (newStatus) => {
  if (newStatus === 'completato') {
    toast.success('Abbiamo analizzato il tuo preventivo! Controlla le voci qui sotto.')
    stopPolling()
    // Inizializza le voci editate
    if (vociPreventivo.value) {
      vociEditate.value = JSON.parse(JSON.stringify(vociPreventivo.value))
    }
  }

  if (newStatus === 'proposte_pronte') {
    toast.success('Abbiamo trovato delle proposte per te!')
    stopPolling()
  }

  if (newStatus === 'senza_proposte') {
    toast.warning('Non abbiamo trovato proposte nella tua zona al momento.')
    stopPolling()
  }

  if (newStatus === 'errore') {
    toast.error(messaggioErrore.value || 'Si è verificato un errore')
    stopPolling()
  }
})

// --- STEP 2: CONFERMA VOCI ---
const addVoce = () => {
  vociEditate.value.push({ prestazione: '', quantità: 1, prezzo: 0 })
}

const removeVoce = (index) => {
  vociEditate.value.splice(index, 1)
}

const formatCurrency = (value) => {
  if (isNaN(parseFloat(value))) return '0,00'
  return parseFloat(value).toLocaleString('it-IT', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}

const totalPreventivo = computed(() => {
  return vociEditate.value.reduce((acc, voce) => acc + parseFloat(voce.prezzo || 0), 0)
})

const handleConfermaVoci = async () => {
  const response = await preventivoStore.confermaVoci(vociEditate.value)

  if (response.success) {
    toast.success('Voci confermate! Ora inserisci i tuoi dati personali.')
  } else {
    toast.error(response.message)
  }
}

// --- STEP 3: SALVA DATI PAZIENTE ---
const handleSalvaDatiPaziente = async (values) => {
  const response = await preventivoStore.salvaDatiPaziente(values)

  if (response.success) {
    toast.info('Dati salvati! Stiamo cercando le migliori proposte per te.')
    startPolling(preventivoStore.controllaProposte)
  } else {
    toast.error(response.message)
  }
}

// --- RESET ---
const resetAll = () => {
  preventivoStore.reset()
  preventivoFile.value = null
  vociEditate.value = []
  stopPolling()
}

// --- CICLO DI VITA ---
onUnmounted(() => {
  stopPolling()
})
</script>

<template>
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-lg-10">
        <h1 class="display-5 fw-bold text-center mb-3">Carica il Tuo Preventivo</h1>
        <p class="lead text-muted text-center mb-5">
          Carica il tuo preventivo e ricevi proposte personalizzate dai migliori studi medici della tua zona
        </p>

        <div class="card border-0 shadow-sm">
          <div class="card-body p-4 p-md-5">

            <!-- STEP 1: UPLOAD FILE -->
            <div v-if="!statoElaborazione || statoElaborazione === 'caricato' || haErrore">
              <!-- Messaggio errore -->
              <div v-if="haErrore" class="alert alert-danger d-flex justify-content-between align-items-center mb-4">
                <div>
                  <p class="fw-bold mb-0">Si è verificato un errore</p>
                  <small>{{ messaggioErrore }}</small>
                </div>
                <button @click="resetAll" class="btn btn-sm btn-danger">Riprova</button>
              </div>

              <Form @submit="handleUpload" :validation-schema="uploadSchema" ref="formUploadRef">
                <div
                  class="upload-area text-center p-5 rounded-3 border-2"
                  :class="{ dragging: isDragging, 'has-file': preventivoFile }"
                  @dragover.prevent="isDragging = true"
                  @dragleave.prevent="isDragging = false"
                  @drop.prevent="handleDrop"
                >
                  <div v-if="!preventivoFile">
                    <i class="fa-solid fa-cloud-arrow-up fa-3x text-muted mb-3"></i>
                    <h5 class="fw-bold">Trascina il tuo file qui</h5>
                    <p class="text-muted">oppure</p>
                    <label for="file-input" class="btn btn-primary">Seleziona File</label>
                    <input
                      type="file"
                      id="file-input"
                      @change="handleFileChange"
                      accept=".pdf,.jpg,.jpeg,.png"
                      class="d-none"
                    />
                    <p class="text-muted small mt-3">Formati accettati: PDF, JPG, PNG (max 10MB)</p>
                  </div>
                  <div v-else class="file-preview">
                    <i class="fa-solid fa-file-invoice fa-3x text-primary mb-3"></i>
                    <p class="fw-bold mb-1">{{ preventivoFile.name }}</p>
                    <p class="text-muted small">{{ (preventivoFile.size / 1024).toFixed(2) }} KB</p>
                    <button type="button" @click="removeFile" class="btn btn-sm btn-danger mt-2">
                      Rimuovi
                    </button>
                  </div>
                </div>
                <ErrorMessage name="preventivoFile" class="text-danger small mt-2 d-block" />

                <!-- Progress bar -->
                <div v-if="uploadProgress > 0" class="mt-3">
                  <div class="progress">
                    <div
                      class="progress-bar"
                      role="progressbar"
                      :style="{ width: uploadProgress + '%' }"
                      :aria-valuenow="uploadProgress"
                      aria-valuemin="0"
                      aria-valuemax="100"
                    >
                      {{ uploadProgress }}%
                    </div>
                  </div>
                </div>

                <div class="text-end mt-4">
                  <button type="submit" class="btn btn-primary btn-lg" :disabled="isLoading || !preventivoFile">
                    <span v-if="isLoading" class="spinner-border spinner-border-sm me-2"></span>
                    {{ isLoading ? 'Caricamento...' : 'Carica Preventivo' }}
                  </button>
                </div>
              </Form>
            </div>

            <!-- LOADING: ELABORAZIONE IN CORSO -->
            <div v-else-if="statoElaborazione === 'in_elaborazione'" class="text-center p-5">
              <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem"></div>
              <h4 class="mt-4">Stiamo elaborando il tuo preventivo...</h4>
              <p class="text-muted">Questa operazione potrebbe richiedere fino a un minuto.</p>
            </div>

            <!-- STEP 2: CONFERMA VOCI -->
            <div v-else-if="isElaborazioneCompletata">
              <h3 class="mb-3">Verifica le voci del preventivo</h3>
              <p class="text-muted">
                Abbiamo estratto queste informazioni dal file che hai caricato. Controllale e, se necessario,
                modificane i valori prima di continuare.
              </p>

              <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                  <thead>
                    <tr>
                      <th scope="col">Prestazione</th>
                      <th scope="col" style="width: 120px">Quantità</th>
                      <th scope="col" style="width: 150px">Prezzo (€)</th>
                      <th scope="col" style="width: 80px"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(voce, index) in vociEditate" :key="index">
                      <td>
                        <input
                          type="text"
                          class="form-control"
                          v-model="voce.prestazione"
                          placeholder="Nome prestazione"
                        />
                      </td>
                      <td>
                        <input type="number" class="form-control" v-model.number="voce.quantità" min="1" />
                      </td>
                      <td>
                        <input
                          type="number"
                          class="form-control"
                          v-model.number="voce.prezzo"
                          step="0.01"
                          placeholder="0.00"
                        />
                      </td>
                      <td class="text-center">
                        <button @click="removeVoce(index)" class="btn btn-sm btn-outline-danger" title="Rimuovi riga">
                          <i class="fa-solid fa-trash-can"></i>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <button @click="addVoce" class="btn btn-sm btn-outline-secondary">
                <i class="fa-solid fa-plus me-1"></i> Aggiungi riga
              </button>

              <div class="text-end mt-4 fs-5 fw-bold">Totale Stimato: € {{ formatCurrency(totalPreventivo) }}</div>

              <hr class="my-4" />

              <div class="d-flex justify-content-between align-items-center">
                <button @click="resetAll" class="btn btn-secondary">Annulla e ricarica</button>
                <button @click="handleConfermaVoci" class="btn btn-primary btn-lg" :disabled="isLoading">
                  <span v-if="isLoading" class="spinner-border spinner-border-sm me-2"></span>
                  Conferma Voci
                </button>
              </div>
            </div>

            <!-- STEP 3: INSERIMENTO DATI PAZIENTE -->
            <div v-else-if="isInAttesaDatiPaziente">
              <h3 class="mb-3">I tuoi dati personali</h3>
              <p class="text-muted">
                Per completare la richiesta e ricevere le proposte, abbiamo bisogno di alcuni tuoi dati.
              </p>

              <Form @submit="handleSalvaDatiPaziente" :validation-schema="datiPazienteSchema" ref="formDatiPazienteRef">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Email *</label>
                    <Field name="email" type="email" class="form-control" />
                    <ErrorMessage name="email" class="text-danger small" />
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Cellulare *</label>
                    <Field name="cellulare" type="tel" class="form-control" />
                    <ErrorMessage name="cellulare" class="text-danger small" />
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Nome *</label>
                    <Field name="nome" type="text" class="form-control" />
                    <ErrorMessage name="nome" class="text-danger small" />
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Cognome *</label>
                    <Field name="cognome" type="text" class="form-control" />
                    <ErrorMessage name="cognome" class="text-danger small" />
                  </div>

                  <div class="col-12">
                    <label class="form-label">Indirizzo *</label>
                    <Field name="indirizzo" type="text" class="form-control" placeholder="Via/Piazza, numero civico" />
                    <ErrorMessage name="indirizzo" class="text-danger small" />
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Città *</label>
                    <Field name="citta" type="text" class="form-control" />
                    <ErrorMessage name="citta" class="text-danger small" />
                  </div>

                  <div class="col-md-3">
                    <label class="form-label">Provincia *</label>
                    <Field name="provincia" type="text" class="form-control" maxlength="2" placeholder="ES: MI" />
                    <ErrorMessage name="provincia" class="text-danger small" />
                  </div>

                  <div class="col-md-3">
                    <label class="form-label">CAP *</label>
                    <Field name="cap" type="text" class="form-control" maxlength="5" />
                    <ErrorMessage name="cap" class="text-danger small" />
                  </div>
                </div>

                <hr class="my-4" />

                <div class="d-flex justify-content-between align-items-center">
                  <button type="button" @click="resetAll" class="btn btn-secondary">Annulla</button>
                  <button type="submit" class="btn btn-primary btn-lg" :disabled="isLoading">
                    <span v-if="isLoading" class="spinner-border spinner-border-sm me-2"></span>
                    Invia e Cerca Proposte
                  </button>
                </div>
              </Form>
            </div>

            <!-- LOADING: RICERCA PROPOSTE -->
            <div v-else-if="isRicercaProposte" class="text-center p-5">
              <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem"></div>
              <h4 class="mt-4">Stiamo cercando le migliori proposte per te...</h4>
              <p class="text-muted">Ti contatteremo a breve con le offerte degli studi medici della tua zona.</p>
            </div>

            <!-- SUCCESS: PROPOSTE PRONTE -->
            <div v-else-if="isPropostePronte" class="text-center p-5">
              <i class="fa-solid fa-circle-check fa-4x text-success mb-4"></i>
              <h3 class="fw-bold">Proposte trovate!</h3>
              <p class="text-muted">
                Abbiamo trovato delle proposte per te. Ti contatteremo a breve via email con i dettagli.
              </p>
              <button @click="resetAll" class="btn btn-primary mt-3">Carica un altro preventivo</button>
            </div>

            <!-- NO PROPOSTE -->
            <div v-else-if="isSenzaProposte" class="text-center p-5">
              <i class="fa-solid fa-circle-exclamation fa-4x text-warning mb-4"></i>
              <h3 class="fw-bold">Nessuna proposta disponibile</h3>
              <p class="text-muted">
                Al momento non ci sono studi disponibili nella tua zona. Riprova più tardi o contattaci direttamente.
              </p>
              <button @click="resetAll" class="btn btn-primary mt-3">Carica un altro preventivo</button>
            </div>

          </div>
        </div>
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
  border-color: var(--bs-success);
  background-color: rgba(var(--bs-success-rgb), 0.1);
}
</style>
